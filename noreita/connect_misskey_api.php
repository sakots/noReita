<?php
//Petit Note 2021-2025 (c)satopian MIT License
//https://paintbbs.sakura.ne.jp/
//https://oekakibbs.moe/

//Misskey APIに接続
//noReita用に改造by sakots

require_once __DIR__ . '/bootstrap.php';
ApplicationBootstrap::boot(__DIR__);
require_once(__DIR__.'/request_security.inc.php');
require_once(__DIR__.'/misskey_security.inc.php');

// index.phpを経由しないMisskeyコールバックの直接実行時だけDB接続を初期化する。
// index.phpから読み込まれた場合は、すでに読み込み済みのDatabaseと後続のDB定数定義を使用する。
if (!class_exists('Database', false)) {
	defined('DB_FILE') or define('DB_FILE', __DIR__ . '/' . Config::string('database.name') . '.db');
	defined('DB_PDO') or define('DB_PDO', 'sqlite:' . DB_FILE);
	require_once(__DIR__.'/database.inc.php');
}

const CONNECT_MISSKEY_API_VER = 20260817;

final class MisskeyApiContext {
  public function __construct(
    public readonly bool $english,
    public readonly string $baseUrl,
  ) {}

  public static function englishFromRequest(): bool {
    $language = explode(',', (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''))[0];
    return stripos($language, 'ja') !== 0;
  }
}

function misskey_api_error(
	string $public_message,
	int $status,
	string $diagnostic,
	?Throwable $cause = null
): void {
	ApplicationErrorHandler::respondPlainError(
		$status,
		$public_message,
		MisskeyApiContext::englishFromRequest(),
		'Error: ',
		'Misskey API: ' . $diagnostic,
		$cause
	);
}

// 認証チェック
class connect_misskey_api{
	/** @param CurlHandle|resource|false $curl */
	private static function applySecurity($curl, string $base_url, int $timeout = 15): bool {
		if ($curl === false) return false;
		$options = MisskeyServerSecurity::curlOptions($base_url, $timeout);
		return is_array($options) && curl_setopt_array($curl, $options);
	}

	private static function responseErrorDetail(mixed $response): string {
		if (!is_array($response)) return 'Unknown API error';
		$message = $response['error']['message'] ?? null;
		if (!is_scalar($message)) return 'Unknown API error';
		$message = trim((string)$message);
		return $message !== '' ? mb_substr($message, 0, 1000) : 'Unknown API error';
	}

	private static function get_thread_no(int $no): int {
		try {
			$db = Database::connect();
			$stmt = $db->prepare("SELECT tid, parent, thread FROM board_log WHERE tid = :no LIMIT 1");
			$stmt->execute([':no' => $no]);
			$post = $stmt->fetch(PDO::FETCH_ASSOC);
			if (!$post) {
				return $no;
			}
			return ((int)$post['thread'] === 1) ? (int)$post['tid'] : (int)$post['parent'];
		} catch (PDOException $e) {
			ApplicationErrorHandler::reportHttpError(500, 'Misskey API: thread lookup failed.', $e);
			return $no;
		}
	}

	public static function mi_auth_check(MisskeyApiContext $context): void {
		$en = $context->english;
		$baseUrl = $context->baseUrl;
		$sns_api_session_id = $_SESSION['sns_api_session_id'];
		$checkUrl = $baseUrl . "/api/miauth/{$sns_api_session_id}/check";

		$checkCurl = curl_init();
		if (!self::applySecurity($checkCurl, $baseUrl)) {
			misskey_api_error(
				$en ? 'Invalid Misskey server.' : 'Misskeyサーバーが無効です。',
				400,
				'Miauth check rejected an invalid or unsafe server.'
			);
		}
		curl_setopt($checkCurl, CURLOPT_URL, $checkUrl);
		curl_setopt($checkCurl, CURLOPT_POST, true);
		curl_setopt($checkCurl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($checkCurl, CURLOPT_POSTFIELDS, json_encode([]));//空のData
		curl_setopt($checkCurl, CURLOPT_RETURNTRANSFER, true);

		$checkResponse = curl_exec($checkCurl);
		$checkStatusCode = (int)curl_getinfo($checkCurl, CURLINFO_HTTP_CODE);
		$checkCurlError = curl_error($checkCurl);
		curl_close($checkCurl);

		if ($checkResponse === false) {
			misskey_api_error(
				$en ? 'Authentication failed.' : '認証に失敗しました。',
				502,
				'Miauth check transport failed: ' . $checkCurlError
			);
		}
		if (!in_array($checkStatusCode, [200, 204], true)) {
			misskey_api_error(
				$en ? 'Authentication failed.' : '認証に失敗しました。',
				502,
				'Miauth check returned HTTP ' . $checkStatusCode . '.'
			);
		}

		$responseData = json_decode($checkResponse, true);
		if(!is_array($responseData) || !isset($responseData['token']) || !is_string($responseData['token'])){
			misskey_api_error(
				$en ? 'Authentication failed.' : '認証に失敗しました。',
				502,
				'Miauth response did not contain a valid token.'
			);
		}
		$accessToken = $responseData['token'];
		$_SESSION['accessToken'] = $accessToken;
		self::create_misskey_note($context);
	}

	public static function create_misskey_note(MisskeyApiContext $context): void {
		$en = $context->english;
		$baseUrl = $context->baseUrl;

		$accessToken = $_SESSION['accessToken'] ?? '';
		if(!is_string($accessToken) || $accessToken === ''){
			misskey_api_error(
				$en ? 'Authentication failed.' : '認証に失敗しました。',
				401,
				'Misskey access token was missing from the session.'
			);
		}

		$sns_api_values = $_SESSION['sns_api_val'] ?? null;
		if (!is_array($sns_api_values) || !array_is_list($sns_api_values) || count($sns_api_values) !== 8) {
			misskey_api_error(
				$en ? 'Invalid posting session.' : '投稿セッションが不正です。',
				400,
				'Misskey posting session data had an invalid structure.'
			);
		}
		list($com,$src_image,$tool,$painttime,$hide_thumbnail,$no,$article_url_link,$cw) = $sns_api_values;
		foreach ([$com, $src_image, $tool, $painttime, $hide_thumbnail, $no, $article_url_link, $cw] as $value) {
			if (!is_scalar($value) && $value !== null) {
				misskey_api_error(
					$en ? 'Invalid posting session.' : '投稿セッションが不正です。',
					400,
					'Misskey posting session data contained a non-scalar value.'
				);
			}
		}

		$src_image=basename($src_image);

		// 画像のアップロード
		$imagePath = __DIR__.'/'.Config::string('paths.images').$src_image;

		if(!is_file($imagePath)){
			misskey_api_error(
				$en ? 'Image does not exist.' : '画像がありません。',
				404,
				'Misskey upload source image was missing.'
			);
		};

		$uploadUrl = $baseUrl . "/api/drive/files/create";
		$uploadFields = array(
			'i' => $accessToken,
			'file' => new CURLFile($imagePath),
		);
		$uploadCurl = curl_init();
		if (!self::applySecurity($uploadCurl, $baseUrl, 30)) {
			misskey_api_error(
				$en ? 'Invalid Misskey server.' : 'Misskeyサーバーが無効です。',
				400,
				'Misskey drive upload rejected an invalid or unsafe server.'
			);
		}
		curl_setopt($uploadCurl, CURLOPT_URL, $uploadUrl);
		curl_setopt($uploadCurl, CURLOPT_POST, true);
		curl_setopt($uploadCurl, CURLOPT_POSTFIELDS, $uploadFields);
		curl_setopt($uploadCurl, CURLOPT_RETURNTRANSFER, true);

		$uploadResponse = curl_exec($uploadCurl);
		$uploadStatusCode = curl_getinfo($uploadCurl, CURLINFO_HTTP_CODE);
		$curlError = curl_error($uploadCurl);
		curl_close($uploadCurl);

		if ($uploadResponse === false) {
			misskey_api_error(
				$en ? 'Failed to upload the image.' : '画像のアップロードに失敗しました。',
				502,
				'Misskey drive upload transport failed: ' . $curlError
			);
		}

		$responseData = json_decode($uploadResponse, true);

		if ($uploadStatusCode !== 200 && $uploadStatusCode !== 204) {
			misskey_api_error(
				$en ? 'Failed to upload the image.' : '画像のアップロードに失敗しました。',
				502,
				'Misskey drive upload returned HTTP ' . $uploadStatusCode . ': ' . self::responseErrorDetail($responseData)
			);
		}

		$fileId = is_array($responseData) && is_string($responseData['id'] ?? null)
			? $responseData['id'] : '';

		if(!$fileId){
			misskey_api_error(
				$en ? 'Failed to upload the image.' : '画像のアップロードに失敗しました。',
				502,
				'Misskey drive upload response did not contain a file ID.'
			);
		}

		$updateUrl = $baseUrl . "/api/drive/files/update";
		$updateHeaders = array(
			'Content-Type: application/json'
		);
		$updateData = array(
			'i' => $accessToken,
			'fileId' => $fileId,
			'isSensitive' => (bool)($hide_thumbnail),
		);

		$updateCurl = curl_init();
		if (!self::applySecurity($updateCurl, $baseUrl)) {
			misskey_api_error(
				$en ? 'Invalid Misskey server.' : 'Misskeyサーバーが無効です。',
				400,
				'Misskey drive update rejected an invalid or unsafe server.'
			);
		}
		curl_setopt($updateCurl, CURLOPT_URL, $updateUrl);
		curl_setopt($updateCurl, CURLOPT_POST, true);
		curl_setopt($updateCurl, CURLOPT_HTTPHEADER, $updateHeaders);
		curl_setopt($updateCurl, CURLOPT_POSTFIELDS, json_encode($updateData));
		curl_setopt($updateCurl, CURLOPT_RETURNTRANSFER, true);
		$updateResponse = curl_exec($updateCurl);
		$updateStatusCode = curl_getinfo($updateCurl, CURLINFO_HTTP_CODE);
		$updateCurlError = curl_error($updateCurl);
		curl_close($updateCurl);

		if ($updateResponse === false) {
			misskey_api_error(
				$en ? 'Failed to update the uploaded image.' : 'ファイルの更新に失敗しました。',
				502,
				'Misskey drive update transport failed: ' . $updateCurlError
			);
		}
		if ($updateStatusCode !== 200 && $updateStatusCode !== 204) {
			$updateResponseData = json_decode($updateResponse, true);
			misskey_api_error(
				$en ? 'Failed to update the uploaded image.' : 'ファイルの更新に失敗しました。',
				502,
				'Misskey drive update returned HTTP ' . $updateStatusCode . ': ' . self::responseErrorDetail($updateResponseData)
			);
		}

		sleep(10);

		$tool= $tool ? 'Tool:'.$tool."\n" :'';
		$painttime= $painttime ? 'Paint time:'.$painttime."\n" :'';

		$src_image_filename = pathinfo($src_image, PATHINFO_FILENAME );//拡張子除去

		$thread_no = self::get_thread_no((int)$no);
		$fixed_link = Config::string('site.base_url').'?mode=res&res='.$thread_no.'#'.$src_image_filename;
		$fixed_link = filter_var($fixed_link,FILTER_VALIDATE_URL) ? $fixed_link : '';
		$article_url_link = $article_url_link ? $fixed_link : '';
		$com=str_replace(["\r\n","\r"],"\n",$com);
		$com=$com ? $com."\n" :'';
		$com = preg_replace("/(\s*\n){2,}/u","\n",$com); //不要改行カット

		$status = $tool.$painttime.$com.$article_url_link;

		$postUrl = $baseUrl . "/api/notes/create";
		$postHeaders = array(
			'Content-Type: application/json'
		);
		$postData = array(
			'i' => $accessToken,
			'cw' => $cw,
			'text' => $status,
			'fileIds' => array($fileId),
		);

		$postCurl = curl_init();
		if (!self::applySecurity($postCurl, $baseUrl)) {
			misskey_api_error(
				$en ? 'Invalid Misskey server.' : 'Misskeyサーバーが無効です。',
				400,
				'Misskey note creation rejected an invalid or unsafe server.'
			);
		}
		curl_setopt($postCurl, CURLOPT_URL, $postUrl);
		curl_setopt($postCurl, CURLOPT_POST, true);
		curl_setopt($postCurl, CURLOPT_HTTPHEADER, $postHeaders);
		curl_setopt($postCurl, CURLOPT_POSTFIELDS, json_encode($postData));
		curl_setopt($postCurl, CURLOPT_RETURNTRANSFER, true);
		$postResponse = curl_exec($postCurl);
		$postStatusCode = curl_getinfo($postCurl, CURLINFO_HTTP_CODE);
		$postCurlError = curl_error($postCurl);
		curl_close($postCurl);

		if ($postResponse === false) {
			misskey_api_error(
				$en ? 'Failed to post the content.' : 'Misskeyへの投稿に失敗しました。',
				502,
				'Misskey note creation transport failed: ' . $postCurlError
			);
		}

		if ($postStatusCode !== 200 && $postStatusCode !== 204) {
			$postResponseData = json_decode($postResponse, true);
			misskey_api_error(
				$en ? 'Failed to post the content.' : 'Misskeyへの投稿に失敗しました。',
				502,
				'Misskey note creation returned HTTP ' . $postStatusCode . ': ' . self::responseErrorDetail($postResponseData)
			);
		}

		$postResult = json_decode($postResponse, true);
		if (!empty($postResult['createdNote']["fileIds"])) {

			unset($_SESSION['sns_api_session_id']);
			unset($_SESSION['sns_api_val']);
			unset($_SESSION['userdel']);

			redirect(Config::string('site.base_url').'?mode=misskey_success&no='.$thread_no);
		}
		else {
			misskey_api_error(
				$en ? 'Failed to post the content.' : '投稿に失敗しました。',
				502,
				'Misskey note creation response did not contain createdNote file IDs.'
			);
		}
	}
}

function connect_misskey_api_dispatch(): void {
	RequestSecurity::startSession();
	$context = new MisskeyApiContext(MisskeyApiContext::englishFromRequest(), '');
	$en = $context->english;

	if((!isset($_SESSION['sns_api_session_id'])) || (!isset($_SESSION['sns_api_val']))) {
		misskey_api_error(
			$en ? 'The Misskey posting session is missing.' : 'セッションがありません。Misskey投稿フローが正しく動作していません。',
			400,
			'Misskey callback session was missing.'
		);
	};

	$baseUrl = MisskeyServerSecurity::normalizeBaseUrl(
		(string)($_SESSION['misskey_server_radio'] ?? '')
	);
	if($baseUrl === false){
		misskey_api_error(
			$en ? 'Invalid Misskey server URL.' : 'サーバのURLが無効です。',
			400,
			'Misskey callback session contained an invalid server URL.'
		);
	}
	$_SESSION['misskey_server_radio'] = $baseUrl;
	$context = new MisskeyApiContext($context->english, $baseUrl);

	$skip_auth_check = (bool)filter_input_data('GET','skip_auth_check',FILTER_VALIDATE_BOOLEAN);
	if($skip_auth_check){
		if((string)filter_input_data('GET','s_id') !== $_SESSION['sns_api_session_id']){
			misskey_api_error(
				$en ? 'Operation failed.' : '失敗しました。',
				403,
				'Misskey callback state did not match the session.'
			);
		}
		connect_misskey_api::create_misskey_note($context);
		return;
	}

	connect_misskey_api::mi_auth_check($context);
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
	connect_misskey_api_dispatch();
}
