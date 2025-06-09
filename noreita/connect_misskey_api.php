<?php
//Petit Note 2021-2025 (c)satopian MIT License
//https://paintbbs.sakura.ne.jp/
//https://oekakibbs.moe/

//Misskey APIに接続
//noReita用に改造by sakots

require_once(__DIR__.'/config.php');
require_once(__DIR__.'/functions.php');

$lang = ($http_langs = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
? explode( ',', $http_langs )[0] : '';
$en= (stripos($lang,'ja')!==0);

session_sta();

if((!isset($_SESSION['sns_api_session_id'])) || (!isset($_SESSION['sns_api_val']))) {
	die("Error: セッションがありません。Misskey投稿フローが正しく動作していません。");
};

$baseUrl = $_SESSION['misskey_server_radio'] ?? "";
if(!filter_var($baseUrl,FILTER_VALIDATE_URL)){
	die("Error: サーバのURLが無効です。: " . $baseUrl);
}

$skip_auth_check = (bool)filter_input_data('GET','skip_auth_check',FILTER_VALIDATE_BOOLEAN);
if($skip_auth_check){
	if((string)filter_input_data('GET','s_id') !== $_SESSION['sns_api_session_id']){
		die("Error: " . ($en ? "Operation failed." :"失敗しました。"));
	}
	return connect_misskey_api::create_misskey_note();
}

connect_misskey_api::mi_auth_check();

// 認証チェック
class connect_misskey_api{

	public static function mi_auth_check(): void {
		global $en,$baseUrl;
		$sns_api_session_id = $_SESSION['sns_api_session_id'];
		$checkUrl = $baseUrl . "/api/miauth/{$sns_api_session_id}/check";

		$checkCurl = curl_init();
		curl_setopt($checkCurl, CURLOPT_URL, $checkUrl);
		curl_setopt($checkCurl, CURLOPT_POST, true);
		curl_setopt($checkCurl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($checkCurl, CURLOPT_POSTFIELDS, json_encode([]));//空のData
		curl_setopt($checkCurl, CURLOPT_RETURNTRANSFER, true);

		$checkResponse = curl_exec($checkCurl);
		curl_close($checkCurl);

		if (!$checkResponse) {
			die("Error: " . ($en ? "Authentication failed." :"認証に失敗しました。") . " (Curl error)");
		}

		$responseData = json_decode($checkResponse, true);
		if(!isset($responseData['token'])){
			die("Error: " . ($en ? "Authentication failed." :"認証に失敗しました。") . " (No token in response)");
		}
		$accessToken = $responseData['token'];
		$_SESSION['accessToken'] = $accessToken;
		$user = $responseData['user'];
		self::create_misskey_note();
	}

	public static function create_misskey_note(): void {

		global $en,$baseUrl,$root_url;

		$accessToken = $_SESSION['accessToken'] ?? "";
		if(!$accessToken){
			die("Error: " . ($en ? "Authentication failed." :"認証に失敗しました。") . " (No access token)");
		}

		list($com,$src_image,$tool,$painttime,$hide_thumbnail,$no,$article_url_link,$cw) = $_SESSION['sns_api_val'];

		$src_image=basename($src_image);

		// 画像のアップロード
		$imagePath = __DIR__.'/'.IMG_DIR.$src_image;

		if(!is_file($imagePath)){
			die("Error: " . ($en ? "Image does not exist." : "画像がありません。") . ": " . $imagePath);
		};

		$uploadUrl = $baseUrl . "/api/drive/files/create";
		$uploadHeaders = array(
			'Content-Type: multipart/form-data'
		);
		$uploadFields = array(
			'i' => $accessToken,
			'file' => new CURLFile($imagePath),
		);
		$uploadCurl = curl_init();
		curl_setopt($uploadCurl, CURLOPT_URL, $uploadUrl);
		curl_setopt($uploadCurl, CURLOPT_POST, true);
		curl_setopt($uploadCurl, CURLOPT_POSTFIELDS, $uploadFields);
		curl_setopt($uploadCurl, CURLOPT_RETURNTRANSFER, true);

		$uploadResponse = curl_exec($uploadCurl);
		$uploadStatusCode = curl_getinfo($uploadCurl, CURLINFO_HTTP_CODE);
		$curlError = curl_error($uploadCurl);
		curl_close($uploadCurl);

		if ($uploadResponse === false) {
			die("Error: 画像のアップロードに失敗しました (cURL Error: " . $curlError . ")");
		}

		$responseData = json_decode($uploadResponse, true);

		if ($uploadStatusCode !== 200 && $uploadStatusCode !== 204) {
			$errorDetails = isset($responseData['error']['message']) ? $responseData['error']['message'] : 'Unknown API Error';
			die("Error: 画像のアップロードに失敗しました (API Status: " . $uploadStatusCode . ", Details: " . $errorDetails . ")");
		}

		$fileId = $responseData['id'] ?? '';

		if(!$fileId){
			die("Error: " . ($en ? "Failed to upload the image." : "画像のアップロードに失敗しました。") . " (No file ID in response)");
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
			die("Error: ファイルの更新に失敗しました (cURL Error: " . $updateCurlError . ")");
		}
		if ($updateStatusCode !== 200 && $updateStatusCode !== 204) {
			$updateResponseData = json_decode($updateResponse, true);
			$errorDetails = isset($updateResponseData['error']['message']) ? $updateResponseData['error']['message'] : 'Unknown API Error';
			die("Error: ファイルの更新に失敗しました (API Status: " . $updateStatusCode . ", Details: " . $errorDetails . ")");
		}

		$uploadResult = json_decode($uploadResponse, true);

		if (!$fileId) {
			die("Error: " . ($en ? "Failed to post the content." : "投稿に失敗しました。") . " (No file ID in response)");
		}

		sleep(10);

		$tool= $tool ? 'Tool:'.$tool."\n" :'';
		$painttime= $painttime ? 'Paint time:'.$painttime."\n" :'';

		$src_image_filename = pathinfo($src_image, PATHINFO_FILENAME );//拡張子除去

		$fixed_link = BASE.'?mode=res&res='.$no.'#'.$src_image_filename;
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
			die("Error: Misskeyへの投稿に失敗しました (cURL Error: " . $postCurlError . ")");
		}

		if ($postStatusCode !== 200 && $postStatusCode !== 204) {
			$postResponseData = json_decode($postResponse, true);
			$errorDetails = isset($postResponseData['error']['message']) ? $postResponseData['error']['message'] : 'Unknown API Error';
			die("Error: Misskeyへの投稿に失敗しました (API Status: " . $postStatusCode . ", Details: " . $errorDetails . ")");
		}

		$postResult = json_decode($postResponse, true);
		if (!empty($postResult['createdNote']["fileIds"])) {

			unset($_SESSION['sns_api_session_id']);
			unset($_SESSION['sns_api_val']);
			unset($_SESSION['userdel']);

			redirect(BASE.'?mode=misskey_success&no='.$no);
		}
		else {
			die("Error: " . ($en ? "Failed to post the content." : "投稿に失敗しました。") . " (API response missing createdNote)");
		}
	}
}
