<?php
//Petit Note 2021-2025 (c)satopian MIT LICENSE
//https://paintbbs.sakura.ne.jp/
//https://oekakibbs.moe/
//APIを使ってお絵かき掲示板からMisskeyにノート noReita版
$misskey_note_ver = 20250521;

//グローバル変数の宣言
global $en, $home, $set_nsfw, $deny_all_posts, $autolink, $use_hashtag;

//設定読み込み
require_once __DIR__ . '/index.php';

// データベースから投稿を取得する
function get_post_from_db($no): ?array {
	global $en;
	try {
		$db = new PDO(DB_PDO);
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$sql = "SELECT * FROM tlog WHERE tid = :no";
		$stmt = $db->prepare($sql);
		$stmt->execute(['no' => $no]);
		$post = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$post) {
			return null;
		}

		return [
			'tid'      => $post['tid'],
			'sub'      => $post['sub'],
			'a_name'   => $post['a_name'],
			'admins'   => $post['admins'],
			'com'      => $post['com'],
			'mail'     => $post['mail'],
			'a_url'    => $post['a_url'],
			'id'       => $post['id'],
			'exid'     => $post['exid'],
			'picfile'  => $post['picfile'],
			'pchfile'  => $post['pchfile'],
			'img_w'    => $post['img_w'],
			'img_h'    => $post['img_h'],
			'tool'     => $post['tool'],
			'utime'    => $post['utime'],
			'created'  => $post['created'],
			'modified' => $post['modified'],
			'parent'   => $post['parent'],
			'pwd'      => $post['pwd'],
		];
	} catch (PDOException $e) {
		error($en ? "Database error: " . $e->getMessage() : "データベースエラー: " . $e->getMessage());
	}
	return null;
}

// 投稿の存在確認
function check_post_exists($no): bool {
	global $en;
	try {
		$db = new PDO(DB_PDO);
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$sql = "SELECT COUNT(*) as count FROM tlog WHERE tid = :no";
		$stmt = $db->prepare($sql);
		$stmt->execute([':no' => $no]);
		$result = $stmt->fetch(PDO::FETCH_ASSOC);

		return $result['count'] > 0;
	} catch (PDOException $e) {
		error($en ? "Database error: " . $e->getMessage() : "データベースエラー: " . $e->getMessage());
	}
	return false;
}

// 投稿のパスワード検証
function verify_post_password($no, $id, $pwd): bool {
	global $en;
	try {
		$db = new PDO(DB_PDO);
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$sql = "SELECT pwd FROM tlog WHERE tid = :no AND id = :id";
		$stmt = $db->prepare($sql);
		$stmt->execute([':no' => $no, ':id' => $id]);
		$post = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$post) {
			return false;
		}

		return password_verify($pwd, $post['pwd']);
	} catch (PDOException $e) {
		error($en ? "Database error: " . $e->getMessage() : "データベースエラー: " . $e->getMessage());
	}
	return false;
}

// 投稿の編集権限チェック
function check_edit_permission($no, $id, $pwd, $admin): bool {
	global $en;
	try {
		$db = new PDO(DB_PDO);
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$sql = "SELECT created, admins FROM tlog WHERE tid = :no AND id = :id";
		$stmt = $db->prepare($sql);
		$stmt->execute([':no' => $no, ':id' => $id]);
		$post = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$post) {
			return false;
		}

		if ($admin || $post['admins'] === 'admin_post') {
			return true;
		}

		if (!$pwd) {
			return false;
		}

		return verify_post_password($no, $id, $pwd);
	} catch (PDOException $e) {
		error($en ? "Database error: " . $e->getMessage() : "データベースエラー: " . $e->getMessage());
	}
	return false;
}

// 投稿データを整形して表示用の配列を作成
function create_res($post): array {
	global $en, $autolink, $use_hashtag;

	try {
		// 投稿データの整形
		$res = [
			'tid' => $post['tid'],
			'sub' => $post['sub'],
			'a_name' => $post['a_name'],
			'admins' => $post['admins'],
			'com' => $post['com'],
			'mail' => $post['mail'],
			'a_url' => $post['a_url'],
			'id' => $post['id'],
			'exid' => $post['exid'],
			'picfile' => $post['picfile'],
			'pchfile' => $post['pchfile'],
			'img_w' => $post['img_w'],
			'img_h' => $post['img_h'],
			'tool' => $post['tool'],
			'utime' => $post['utime'],
			'created' => (!empty($post['created']) && strtotime($post['created'])) ? date(DATE_FORMAT, strtotime($post['created'])) : '',
			'modified' => (!empty($post['modified']) && strtotime($post['modified'])) ? date(DATE_FORMAT, strtotime($post['modified'])) : '',
			'past' => (!empty($post['created']) && strtotime($post['created'])) ? strtotime($post['created']) : 0,
			'parent' => $post['parent'],
			'pwd' => $post['pwd'],
		];

		// コメントの整形
		if ($autolink) {
			$res['com'] = auto_link($res['com']);
		}
		if ($use_hashtag) {
			$res['com'] = hashtag_link($res['com']);
		}
		// 空行を縮める
		$res['com'] = preg_replace('/(\n|\r|\r\n){3,}/us', "\n", $res['com']);
		// <br>に変換
		$res['com'] = tobr($res['com']);
		// 引用の色付け
		$res['com'] = quote($res['com']);

		// URLの検証
		if (!filter_var($res['a_url'], FILTER_VALIDATE_URL) || !preg_match('|^https?://.*$|', $res['a_url'])) {
			$res['a_url'] = "";
		}

		// 共有用のエンコード
		$res['encoded_t'] = urlencode('[' . $res['tid'] . ']' . $res['sub'] . ($res['a_name'] ? ' by ' . $res['a_name'] : '') . ' - ' . TITLE);
		$res['encoded_u'] = urlencode(BASE . '?resno=' . $res['tid']);

		return $res;
	} catch (Exception $e) {
		error($en ? "Error processing post data: " . $e->getMessage() : "投稿データの処理中にエラーが発生しました: " . $e->getMessage());
	}
	return [];
}

class misskey_note {

	//投稿済みの記事をMisskeyにノートするための前処理
	public static function before_misskey_note(): void {
		global $home, $set_nsfw, $en, $deny_all_posts;
		global $blade, $dat;
		//管理者判定処理
		session_sta();
		$admin_post = admin_post_valid();
		$admin_del = admin_del_valid();

		$dat['pwdc'] = (string)filter_input_data('COOKIE', 'pwdc');
		$dat['no'] = t(filter_input_data('POST', 'no', FILTER_VALIDATE_INT));
		$dat['no'] = $dat['no'] ? $dat['no'] : t(filter_input_data('GET', 'no', FILTER_VALIDATE_INT));

		if (!$dat['no']) {
			error($en ? 'Invalid post number.' : '投稿番号が無効です。');
		}

		if (!check_post_exists($dat['no'])) {
			error($en ? 'The article does not exist.' : '記事がありません。');
		}

		$post = get_post_from_db($dat['no']);
    if (!$post) {
        error($en ? 'The article was not found.' : '記事が見つかりません。');
    }
    $dat['post'] = $post;

		$dat['path'] = IMG_DIR;
		$dat['token'] = get_csrf_token();

		// nsfw
		$dat['nsfwc'] = (bool)filter_input_data('COOKIE', 'nsfwc', FILTER_VALIDATE_BOOLEAN);
		$dat['set_nsfw_show_hide'] = (bool)filter_input_data('COOKIE', 'p_n_set_nsfw_show_hide', FILTER_VALIDATE_BOOLEAN);

		$dat['count_r_arr'] = count($dat['post']);
		$dat['edit_mode'] = 'editmode';

		$admin_pass = null;

		$dat['misskey_mode'] = 'before';
		echo $blade->run(MISSKEYFILE, $dat);
		exit();
	}

	//投稿済みの画像をMisskeyにNoteするための投稿フォーム
	public static function misskey_note_edit_form(): void {
		global $home, $set_nsfw, $en, $max_kb, $use_upload, $admin, $misskey_servers;
		global $blade, $dat;

		check_same_origin();

		$dat['token'] = get_csrf_token();

		$dat['admin_del'] = admin_del_valid();
		$dat['admin_post'] = admin_post_valid();
		$dat['admin'] = ($dat['admin_del'] || $dat['admin_post']);

		$pwd = (string)filter_input_data('POST', 'pwd');
		$pwdc = (string)filter_input_data('COOKIE', 'pwdc');
		$pwd = $pwd ? $pwd : $pwdc;

		$id_and_no = (string)filter_input_data('POST', 'id_and_no');

		list($id, $no) = explode(",", trim($id_and_no));

		if (!$no) {
			error($en ? 'Invalid post number.' : '投稿番号が無効です。');
		}

		if (!check_post_exists($no)) {
			error($en ? 'The article does not exist.' : '記事がありません。');
		}

		if (!check_edit_permission($no, $id, $pwd, $admin)) {
			error($en ? 'Password is incorrect.' : 'パスワードが違います。');
		}

		check_AsyncRequest();

		$post = get_post_from_db($no, $id);
		if (!$post) {
			error($en ? 'The article was not found.' : '記事が見つかりません。');
		}
		$dat['path'] = IMG_DIR;
		$dat['post'] = $post;

		// Misskeyサーバーリストをセット
		$dat['misskey_servers'] = $misskey_servers;

		$dat['nsfwc'] = (bool)filter_input_data('COOKIE', 'nsfwc', FILTER_VALIDATE_BOOLEAN);
		$dat['set_nsfw_show_hide'] = (bool)filter_input_data('COOKIE', 'p_n_set_nsfw_show_hide', FILTER_VALIDATE_BOOLEAN);

		$page = $_SESSION['current_page_context']["page"] ?? 0;
		$resno = $_SESSION['current_page_context']["resno"] ?? null; //下の行でnull判定
		$resno ?? $no;

		$user_del = false;
		$admin_del = false;

		$image_rep = false;

		$_SESSION['current_id'] = $id;

		$admin_pass = null;
		// HTML出力
		$dat['misskey_mode'] = 'note_edit_form';

		echo $blade->run(MISSKEYFILE, $dat);
		exit();
	}

	//Misskeyに投稿するSESSIONデータを作成
	public static function create_misskey_note_sessiondata(): void {
		global $en, $usercode, $misskey_servers;

		check_csrf_token();

		$userip = t(get_uip());
		$no = t(filter_input_data('POST', 'no', FILTER_VALIDATE_INT));
		$src_image = t(filter_input_data('POST', 'src_image'));
		$com = t(filter_input_data('POST', 'com'));
		$abbr_toolname = t(filter_input_data('POST', 'abbr_toolname'));
		$paintsec = (int)filter_input_data('POST', 'paintsec', FILTER_VALIDATE_INT);
		$hide_thumbnail = (bool)filter_input_data('POST', 'hide_thumbnail', FILTER_VALIDATE_BOOLEAN);
		$show_painttime = (bool)filter_input_data('POST', 'show_painttime', FILTER_VALIDATE_BOOLEAN);
		$article_url_link = (bool)filter_input_data('POST', 'article_url_link', FILTER_VALIDATE_BOOLEAN);
		$hide_content = (bool)filter_input_data('POST', 'hide_content', FILTER_VALIDATE_BOOLEAN);
		$cw = t(filter_input_data('POST', 'cw'));

		if ($hide_content && !$cw) {
			die("Error: " . ($en ? "Content warning field is empty." : "注釈がありません。"));
		}

		check_AsyncRequest();

		$cw = $hide_content ? $cw : null;
		$tool = switch_tool($abbr_toolname);

		$painttime = calcPtime($paintsec);
		$painttime_str = '';
		if (is_array($painttime)) {
			$painttime_str = $en ? ($painttime['en'] ?? '') : ($painttime['ja'] ?? '');
		} else {
			$painttime_str = (string)$painttime;
		}
		$painttime_to_session = $show_painttime ? $painttime_str : '';

		session_sta();

		// 投稿データをセッションに保存
		$_SESSION['misskey_note_data'] = [
			'no' => $no,
			'src_image' => $src_image,
			'com' => $com,
			'tool' => $tool,
			'painttime' => $painttime_to_session,
			'hide_thumbnail' => $hide_thumbnail,
			'article_url_link' => $article_url_link,
			'cw' => $cw
		];

		// sns_api_valを設定
		$_SESSION['sns_api_val'] = [
			$com,
			$src_image,
			$tool,
			$painttime_to_session,
			$hide_thumbnail,
			$no,
			$article_url_link,
			$cw
		];

		// Misskeyサーバー認証URLを生成する処理を直接呼び出す
		self::create_misskey_authrequesturl();
	}

	// Misskeyサーバー認証URLを生成
	public static function create_misskey_authrequesturl(): void {
		global $en;

		check_same_origin();

		// ラジオボタンの値
		$misskey_server_radio_value = filter_input_data('POST', "misskey_server_radio"); // フィルタリングしない生の値を取得

		// 直接入力欄の値
		$misskey_server_direct_input_value = filter_input_data('POST', "misskey_server_direct_input"); // フィルタリングしない生の値を取得

		// セッションにセットする最終的なURLを決定
		$baseUrl_to_set_in_session = null;

		if ($misskey_server_radio_value && $misskey_server_radio_value !== 'direct') {
			// ラジオボタンが選択されており、かつ"direct"でない場合
			// この値が有効なURLか検証して使用
			$validated_url = filter_var($misskey_server_radio_value, FILTER_VALIDATE_URL);
			if ($validated_url) {
				$baseUrl_to_set_in_session = $validated_url;
			}
		} elseif ($misskey_server_radio_value === 'direct' && $misskey_server_direct_input_value) {
			// ラジオボタンが"direct"で、直接入力に値がある場合
			// 直接入力の値を有効なURLか検証して使用
			$validated_url = filter_var($misskey_server_direct_input_value, FILTER_VALIDATE_URL);
			if ($validated_url) {
				$baseUrl_to_set_in_session = $validated_url;
			}
		}

		// どちらにも有効なURLがない場合エラー
		if (!$baseUrl_to_set_in_session) {
			die("Error: " . ($en ? "Please select a valid Misskey server or enter a valid URL." : "有効なMisskeyサーバーを選択するか、有効なURLを直接入力してください。"));
		}

		// Cookie セット (misskey_server_radio_cookie は "direct" または URLを保存)
		$misskey_server_radio_for_cookie = ($misskey_server_radio_value === 'direct') ? 'direct' : $baseUrl_to_set_in_session;
		setcookie("misskey_server_radio_cookie", $misskey_server_radio_for_cookie, time() + (86400 * 30), "", "", false, true);
		setcookie("misskey_server_direct_input_cookie", $misskey_server_direct_input_value, time() + (86400 * 30), "", "", false, true);

		session_sta();
		// セッションIDとユニークIDを結合
		$sns_api_session_id = session_id() . random_bytes(16);

		// SHA256ハッシュ化
		$sns_api_session_id = hash('sha256', $sns_api_session_id);

		$_SESSION['sns_api_session_id'] = $sns_api_session_id;

		$encoded_root_url = urlencode(BASE);

		//別のサーバを選択した時はトークンをクリア
		if (!isset($_SESSION['misskey_server_radio']) ||
			$_SESSION['misskey_server_radio'] !== $baseUrl_to_set_in_session) {
			unset($_SESSION['accessToken']); //トークンをクリア
		}
		// 投稿完了画面に表示するサーバのURl としてセッションにセット
		$_SESSION['misskey_server_radio'] = $baseUrl_to_set_in_session;

		//アプリを認証するためのURL
		$Location = "{$baseUrl_to_set_in_session}/miauth/{$sns_api_session_id}?name=noReita&callback={$encoded_root_url}connect_misskey_api.php&permission=write:notes,write:drive";

		if (isset($_SESSION['accessToken'])) { //SESSIONのトークンが有効か確認
			// ダミーの投稿を試みる（textフィールドを空にする）
			$postUrl = "{$baseUrl_to_set_in_session}/api/notes/create";
			$postData = array(
				'i' => $_SESSION['accessToken'],
				'text' => '', // 投稿を成功させないようにするためtextフィールドを空にする
			);

			$postCurl = curl_init();
			curl_setopt($postCurl, CURLOPT_URL, $postUrl);
			curl_setopt($postCurl, CURLOPT_POST, true);
			curl_setopt($postCurl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
			curl_setopt($postCurl, CURLOPT_POSTFIELDS, json_encode($postData));
			curl_setopt($postCurl, CURLOPT_RETURNTRANSFER, true);
			$postResponse = curl_exec($postCurl);
			$postStatusCode = curl_getinfo($postCurl, CURLINFO_HTTP_CODE);
			curl_close($postCurl);

			// HTTPステータスコードが403の時は、トークン不一致と判断しアプリを認証
			if ($postStatusCode === 403) {
				unset($_SESSION['accessToken']); //トークンをクリア
			} else {
				//アプリの認証をスキップするURL
				$Location = BASE . "connect_misskey_api.php?skip_auth_check=on&s_id={$sns_api_session_id}";
			}
		}

		redirect($Location);
	}

	// Misskeyへの投稿が成功した事を知らせる画面
	public static function misskey_success(): void {
		global $en, $blade, $dat;
		$no = (string)filter_input_data('GET', 'no', FILTER_VALIDATE_INT);

		session_sta();

		$misskey_server_url = $_SESSION['misskey_server_radio'] ?? "";
		if (!$misskey_server_url || !filter_var($misskey_server_url, FILTER_VALIDATE_URL) || !$no) {
			redirect('./');
		}
		$admin_pass = null;
		$dat['misskey_mode'] = 'success';
		echo $blade->run(MISSKEYFILE, $dat);
		exit();
	}
}

