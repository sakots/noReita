<?php
//Petit Note 2021-2025 (c)satopian MIT LICENSE
//https://paintbbs.sakura.ne.jp/
//APIを使ってお絵かき掲示板からMisskeyにノート
$misskey_note_ver = 20250326;

// グローバル変数の宣言
global $en, $home, $petit_ver, $petit_lot, $set_nsfw, $deny_all_posts, $autolink, $use_hashtag, $date_format;

//データベース接続PDO
define('DB_PDO', 'sqlite:' . DB_NAME . '.db');

// データベースから投稿を取得する関数
function get_post_from_db($no, $id): array|null {
	global $en;
	try {
		$db = new PDO(DB_PDO);
		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$sql = "SELECT * FROM tlog WHERE tid = :no AND id = :id";
		$stmt = $db->prepare($sql);
		$stmt->execute([':no' => $no, ':id' => $id]);
		$post = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$post) {
			return null;
		}

		return [
			'no' => $post['tid'],
			'sub' => $post['sub'],
			'name' => $post['a_name'],
			'verified' => $post['admins'],
			'com' => $post['com'],
			'url' => $post['a_url'],
			'imgfile' => $post['picfile'],
			'w' => $post['img_w'],
			'h' => $post['img_h'],
			'thumbnail' => $post['picfile'],
			'painttime' => $post['utime'],
			'log_hash_img' => '',
			'tool' => $post['tool'],
			'pchext' => pathinfo($post['pchfile'], PATHINFO_EXTENSION),
			'time' => $post['id'],
			'first_posted_time' => $post['created'],
			'host' => $post['host'],
			'userid' => '',
			'hash' => $post['pwd'],
			'oya' => $post['parent']
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
	global $en, $autolink, $use_hashtag, $date_format;

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
			'created' => date($date_format, strtotime($post['created'])),
			'modified' => date($date_format, strtotime($post['modified'])),
			'past' => strtotime($post['created']),
			'parent' => $post['parent']
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
	public static function before_misskey_note (): void {

		global $home,$set_nsfw,$en,$deny_all_posts,$blade;
		//管理者判定処理
		session_sta();
		$admin_post=admin_post_valid();
		$admin_del=admin_del_valid();

		$pwdc=(string)filter_input_data('COOKIE','pwdc');
		$id = t(filter_input_data('POST','id'));//intの範囲外
		$id = $id ? $id : t(filter_input_data('GET','id'));//intの範囲外
		$no = t(filter_input_data('POST','no',FILTER_VALIDATE_INT));
		$no = $no ? $no : t(filter_input_data('GET','no',FILTER_VALIDATE_INT));
		$user_del=isset($_SESSION['user_del'])&&($_SESSION['user_del']==='user_del_mode');
		$resmode = false;//使っていない
		$page= $_SESSION['current_page_context']["page"] ?? 0;
		$resno= $_SESSION['current_page_context']["resno"] ?? null;//下の行でnull判定
		$resno ?? $no;

		if (!$no) {
			error($en ? 'Invalid post number.' : '投稿番号が無効です。');
		}

		if (!check_post_exists($no)) {
			error($en ? 'The article does not exist.' : '記事がありません。');
		}

		$post = get_post_from_db($no, $id);
		if (!$post) {
			error($en ? 'The article was not found.' : '記事が見つかりません。');
		}

		$dat[0][] = $post;
		$token=get_csrf_token();

		// nsfw
		$nsfwc=(bool)filter_input_data('COOKIE','nsfwc',FILTER_VALIDATE_BOOLEAN);
		$set_nsfw_show_hide=(bool)filter_input_data('COOKIE','p_n_set_nsfw_show_hide',FILTER_VALIDATE_BOOLEAN);

		$count_r_arr = count($post);
		$edit_mode = 'editmode';

		$_SESSION['current_id']	= $id;

		$admin_pass= null;

		$dat['misskey_mode'] = 'before';
		echo $blade->run(MISSKEYFILE, $dat);
		exit();
	}
	//投稿済みの画像をMisskeyにNoteするための投稿フォーム
	public static function misskey_note_edit_form(): void {

		global $home,$set_nsfw,$en,$max_kb,$use_upload;
		global $blade;

		check_same_origin();

		$token = get_csrf_token();

		$admin_del = admin_del_valid();
		$admin_post = admin_post_valid();
		$admin = ($admin_del||$admin_post);

		$pwd=(string)filter_input_data('POST','pwd');
		$pwdc=(string)filter_input_data('COOKIE','pwdc');
		$pwd = $pwd ? $pwd : $pwdc;

		$id_and_no=(string)filter_input_data('POST','id_and_no');

		list($id,$no)=explode(",",trim($id_and_no));

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

		$dat[0][] = create_res($post);//$postから、情報を取り出す;


		$nsfwc=(bool)filter_input_data('COOKIE','nsfwc',FILTER_VALIDATE_BOOLEAN);
		$set_nsfw_show_hide=(bool)filter_input_data('COOKIE','p_n_set_nsfw_show_hide',FILTER_VALIDATE_BOOLEAN);

		$page= $_SESSION['current_page_context']["page"] ?? 0;
		$resno= $_SESSION['current_page_context']["resno"] ?? null;//下の行でnull判定
		$resno ?? $no;

		$user_del = false;
		$admin_del = false;

		$image_rep=false;

		$_SESSION['current_id']	= $id;

		$admin_pass= null;
		// HTML出力
		$dat['misskey_mode'] = 'note_edit_form';
		echo $blade->run(MISSKEYFILE, $dat);
		exit();
	}

	//Misskeyに投稿するSESSIONデータを作成
	public static function create_misskey_note_sessiondata(): void {
		global $en,$usercode,$root_url,$skindir,$petit_lot,$misskey_servers,$boardname;

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
			error($en ? "Content warning field is empty." : "注釈がありません。");
		}

		check_AsyncRequest();

		$cw = $hide_content ? $cw : null;
		$tool = switch_tool($abbr_toolname);

		$painttime = calcPtime($paintsec);
		$painttime_en = $painttime ? $painttime['en'] : '';
		$painttime_ja = $painttime ? $painttime['ja'] : '';
		$painttime = $en ? $painttime_en : $painttime_ja;
		$painttime = $show_painttime ? $painttime : '';

		session_sta();

		$src_image = basename($src_image);
		$_SESSION['sns_api_val'] = [$com, $src_image, $tool, $painttime, $hide_thumbnail, $no, $article_url_link, $cw];

		$misskey_servers = $misskey_servers ?? [
			["misskey.io", "https://misskey.io"],
			["xissmie.xfolio.jp", "https://xissmie.xfolio.jp"],
			["misskey.design", "https://misskey.design"],
			["nijimiss.moe", "https://nijimiss.moe"],
			["misskey.art", "https://misskey.art"],
			["oekakiskey.com", "https://oekakiskey.com"],
			["misskey.gamelore.fun", "https://misskey.gamelore.fun"],
			["novelskey.tarbin.net", "https://novelskey.tarbin.net"],
			["tyazzkey.work", "https://tyazzkey.work"],
			["sushi.ski", "https://sushi.ski"],
			["misskey.delmulin.com", "https://misskey.delmulin.com"],
			["side.misskey.productions", "https://side.misskey.productions"],
			["mk.shrimpia.network", "https://mk.shrimpia.network"],
		];
		$misskey_servers[] = [($en ? "Direct input" : "直接入力"), "direct"];

		$misskey_server_radio_cookie = (string)filter_input_data('COOKIE', "misskey_server_radio_cookie");
		$misskey_server_direct_input_cookie = (string)filter_input_data('COOKIE', "misskey_server_direct_input_cookie");

		$admin_pass = null;
		$templete = 'misskey_server_selection.html';
		include __DIR__ . '/' . $skindir . $templete;
		exit();
	}

	public static function create_misskey_authrequesturl(): void {
		global $root_url;
		global $en;

		check_same_origin();

		$misskey_server_radio = (string)filter_input_data('POST',"misskey_server_radio",FILTER_VALIDATE_URL);
		$misskey_server_radio_for_cookie = (string)filter_input_data('POST',"misskey_server_radio");//directを判定するためurlでバリデーションしていない
		$misskey_server_radio_for_cookie = ($misskey_server_radio_for_cookie === 'direct') ? 'direct' : $misskey_server_radio;
		$misskey_server_direct_input = (string)filter_input_data('POST',"misskey_server_direct_input",FILTER_VALIDATE_URL);
		setcookie("misskey_server_radio_cookie",$misskey_server_radio_for_cookie, time()+(86400*30),"","",false,true);
		setcookie("misskey_server_direct_input_cookie",$misskey_server_direct_input, time()+(86400*30),"","",false,true);

		if(!$misskey_server_radio && !$misskey_server_direct_input){
			error($en ? "Please select an misskey server.":"Misskeyサーバを選択してください。");
		}

		if(!$misskey_server_radio && $misskey_server_direct_input){
			$misskey_server_radio = $misskey_server_direct_input;
		}

		session_sta();
		// セッションIDとユニークIDを結合
		$sns_api_session_id = session_id() . random_bytes(16);

		// SHA256ハッシュ化
		$sns_api_session_id=hash('sha256', $sns_api_session_id);

		$_SESSION['sns_api_session_id'] = $sns_api_session_id;

		$encoded_root_url = urlencode($root_url);

		//別のサーバを選択した時はトークンをクリア
		if(!isset($_SESSION['misskey_server_radio']) ||
		$_SESSION['misskey_server_radio'] !== $misskey_server_radio){
			unset($_SESSION['accessToken']);//トークンをクリア
		}
		//投稿完了画面に表示するサーバのURl
		$_SESSION['misskey_server_radio'] = $misskey_server_radio;

		//アプリを認証するためのURL
		$Location = "{$misskey_server_radio}/miauth/{$sns_api_session_id}?name=Petit%20Note&callback={$encoded_root_url}connect_misskey_api.php&permission=write:notes,write:drive";

		if(isset($_SESSION['accessToken'])){//SESSIONのトークンが有効か確認

			// ダミーの投稿を試みる（textフィールドを空にする）
			$postUrl = "{$misskey_server_radio}/api/notes/create";
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
			$postStatusCode = curl_getinfo($postCurl, CURLINFO_HTTP_CODE); // HTTPステータスコードを取得
			curl_close($postCurl);

			// HTTPステータスコードが403の時は、トークン不一致と判断しアプリを認証
			if ($postStatusCode === 403) {
				unset($_SESSION['accessToken']);//トークンをクリア
			} else {
				//アプリの認証をスキップするURL
				$Location = "{$root_url}connect_misskey_api.php?skip_auth_check=on&s_id={$sns_api_session_id}";
			}
		}

		redirect($Location);

	}
	// Misskeyへの投稿が成功した事を知らせる画面
	public static function misskey_success(): void {
		global $en,$skindir,$boardname,$petit_lot;
		$no = (string)filter_input_data('GET', 'no',FILTER_VALIDATE_INT);

		session_sta();

		$misskey_server_url = $_SESSION['misskey_server_radio'] ?? "";
		if(!$misskey_server_url || !filter_var($misskey_server_url,FILTER_VALIDATE_URL) || !$no){
			redirect('./');
		}
		$admin_pass= null;
		$templete='misskey_success.html';
		include __DIR__.'/'.$skindir.$templete;
		exit();
	}
}

