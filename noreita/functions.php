<?php
$functions_ver = 20250518;

//ページのコンテキストをセッションに保存
function set_page_context_to_session(): void {
	session_sta();
	// セッションに保存
	$_SESSION['current_page_context'] = [
		'page' => (int)filter_input_data('GET', 'page', FILTER_VALIDATE_INT),
		'resno' => filter_input_data('GET', 'resno', FILTER_VALIDATE_INT),//未設定時はnull。intでキャストしない事。
		'catalog' => (bool)(filter_input_data('GET', 'mode') === 'catalog'),
		'res_catalog' => (bool)filter_input_data('GET', 'res_catalog', FILTER_VALIDATE_BOOLEAN),
		'misskey_note' => (bool)filter_input_data('GET', 'misskey_note', FILTER_VALIDATE_BOOLEAN),
		'search' => (bool)(filter_input_data('GET', 'mode') === 'search'),
		'radio' => (int)filter_input_data('GET', 'radio', FILTER_VALIDATE_INT),
		'imgsearch' => (bool)filter_input_data('GET', 'imgsearch', FILTER_VALIDATE_BOOLEAN),
		'q' => (string)filter_input_data('GET', 'q'),
	];
	$_SESSION['current_id'] = null;
}

//管理者パスワードを確認
function is_admin_pass($pwd): bool {
	global $admin_pass,$second_pass;
	$pwd=(string)$pwd;
	return ($admin_pass && $pwd && $second_pass !== $admin_pass && $pwd === $admin_pass);
}

// 文字コード変換
function charconvert($str): string {
	mb_language(LANG);
	return mb_convert_encoding($str, "UTF-8", "auto");
}

/* NGワードがあれば拒絶 */
function Reject_if_NGword_exists_in_the_post($com, $name, $email, $url, $sub): void {
	global $badstring, $badname, $badstr_A, $badstr_B, $pwd, $admin_pass;
	//チェックする項目から改行・スペース・タブを消す
	$chk_com  = preg_replace("/\s/u", "", $com);
	$chk_name = preg_replace("/\s/u", "", $name);
	$chk_email = preg_replace("/\s/u", "", $email);
	$chk_sub = preg_replace("/\s/u", "", $sub);

	//本文に日本語がなければ拒絶
	if (USE_JAPANESEFILTER) {
		mb_regex_encoding("UTF-8");
		if (strlen($com) > 0 && !preg_match("/[ぁ-んァ-ヶー一-龠]+/u", $chk_com)) error(MSG035);
	}

	//本文へのURLの書き込みを禁止
	if (!($pwd === $admin_pass)) { //どちらも一致しなければ
		if (DENY_COMMENTS_URL && preg_match('/:\/\/|\.co|\.ly|\.gl|\.net|\.org|\.cc|\.ru|\.su|\.ua|\.gd/i', $com)) error(MSG036);
	}

	// 使えない文字チェック
	if (is_ngword($badstring, [$chk_com, $chk_sub, $chk_name, $chk_email])) {
		error(MSG032);
	}

	// 使えない名前チェック
	if (is_ngword($badname, $chk_name)) {
		error(MSG037);
	}

	//指定文字列が2つあると拒絶
	$bstr_A_find = is_ngword($badstr_A, [$chk_com, $chk_sub, $chk_name, $chk_email]);
	$bstr_B_find = is_ngword($badstr_B, [$chk_com, $chk_sub, $chk_name, $chk_email]);
	if ($bstr_A_find && $bstr_B_find) {
		error(MSG032);
	}
}

//念のため画像タイプチェック
function get_image_type($img_type, $dest = null): string {
	$img_type = mime_content_type($img_type);
	$map = [
		"image/gif" => ".gif",
		"image/jpeg" => ".jpg",
		"image/png" => ".png",
		"image/webp" => ".webp",
	];

	if (isset($map[$img_type])) {
		return $map[$img_type];
	}
	error(MSG004, $dest);
	return ''; // この行は実際には実行されないが、リンターを満足させるために必要
}

/**
 * NGワードチェック
 * @param $ngwords
 * @param string|array $strs
 * @return bool
 */
function is_ngword($ngwords, $strs): bool {
	if (empty($ngwords)) {
		return false;
	}
	if (!is_array($strs)) {
		$strs = [$strs];
	}
	foreach ($strs as $str) {
		foreach ($ngwords as $ngword) { //拒絶する文字列
			if ($ngword !== '' && preg_match("/{$ngword}/ui", $str)) {
				return true;
			}
		}
	}
	return false;
}

/**
 * 描画時間を計算
 * @param $starttime
 * @return string
 */
function calcPtime($psec): string {

	$D = floor($psec / 86400);
	$H = floor($psec % 86400 / 3600);
	$M = floor($psec % 3600 / 60);
	$S = $psec % 60;

	return ($D ? $D . PTIME_D : '') . ($H ? $H . PTIME_H : '') . ($M ? $M . PTIME_M : '') . ($S ? $S . PTIME_S : '');
}
/**
 * ファイルがあれば削除
 * @param $path
 * @return bool
 */
function safe_unlink($path): bool {
	if ($path && is_file($path)) {
		return unlink($path);
	}
	return false;
}

/* オートリンク */
function auto_link($proto): string {
	if (!(stripos($proto, "script") !== false)) { //scriptがなければ続行
		$pattern = "{(https?|ftp)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)}";
		$replace = "<a href=\"\\1\\2\" target=\"_blank\" rel=\"nofollow noopener noreferrer\">\\1\\2</a>";
		$proto = preg_replace($pattern, $replace, $proto);
		return $proto;
	} else {
		return $proto;
	}
}

/* ハッシュタグリンク */
function hashtag_link($hashtag): string {
	$self = PHP_SELF;
	$pattern = "/(?:^|[^ｦ-ﾟー゛゜々ヾヽぁ-ヶ一-龠ａ-ｚＡ-Ｚ０-９a-zA-Z0-9&_\/]+)[#＃]([ｦ-ﾟー゛゜々ヾヽぁ-ヶ一-龠ａ-ｚＡ-Ｚ０-９a-zA-Z0-9_]*[ｦ-ﾟー゛゜々ヾヽぁ-ヶ一-龠ａ-ｚＡ-Ｚ０-９a-zA-Z]+[ｦ-ﾟー゛゜々ヾヽぁ-ヶ一-龠ａ-ｚＡ-Ｚ０-９a-zA-Z0-9_]*)/u";
	$replace = " <a href=\"{$self}?mode=search&amp;tag=tag&amp;search=\\1\">#\\1</a>";
	$hashtag = preg_replace($pattern, $replace, $hashtag);
	return $hashtag;
}

/* '>'色設定 */
function quote($quote): string {
	$quote = preg_replace("/(^|>)((&gt;|＞)[^<]*)/i", "\\1" . RE_START . "\\2" . RE_END, $quote);
	return $quote;
}

/* 改行を<br>に */
function tobr($com): string {
	if (TH_XHTML !== 1) {
		$com = nl2br($com, false);
	} else {
		$com = nl2br($com);
	}
	return $com;
}

/* ID生成 */
function gen_id($userip, $time): string {
	if (ID_CYCLE === '0') {
		return substr(crypt(md5($userip . ID_SEED), 'id'), -8);
	} elseif (ID_CYCLE === '1') {
		return substr(crypt(md5($userip . ID_SEED . date("Ymd", $time)), 'id'), -8);
	} elseif (ID_CYCLE === '2') {
		$week = ceil(date("d", $time) / 7);
		return substr(crypt(md5($userip . ID_SEED . date("Ym", $time) . $week), 'id'), -8);
	} elseif (ID_CYCLE === '3') {
		return substr(crypt(md5($userip . ID_SEED . date("Ym", $time)), 'id'), -8);
	} elseif (ID_CYCLE === '4') {
		return substr(crypt(md5($userip . ID_SEED . date("Y", $time)), 'id'), -8);
	} else {
		return substr(crypt(md5($userip . ID_SEED), 'id'), -8);
	}
}

//リダイレクト
function redirect($url): void {
	header("Location: {$url}");
	exit();
}

//シェアするserverの選択画面
function set_share_server(): void {
	global $servers,$blade,$dat;

	//ShareするServerの一覧
	//｢"ラジオボタンに表示するServer名","snsのserverのurl"｣
	$servers = $servers ??
	[
		["X","https://x.com"],
		["Bluesky","https://bsky.app"],
		["Threads","https://www.threads.net"],
		["pawoo.net","https://pawoo.net"],
		["fedibird.com","https://fedibird.com"],
		["misskey.io","https://misskey.io"],
		["xissmie.xfolio.jp","https://xissmie.xfolio.jp"],
		["misskey.design","https://misskey.design"],
		["nijimiss.moe","https://nijimiss.moe"],
		["sushi.ski","https://sushi.ski"],
	];
	//設定項目ここまで
	$servers[]=["直接入力","direct"];//直接入力の箇所はそのまま。
	$dat['servers'] = $servers;

	$dat['encoded_t'] = filter_input_data('GET',"encoded_t");
	$dat['encoded_u'] = filter_input_data('GET',"encoded_u");
	$dat['sns_server_radio_cookie'] = (string)filter_input_data('COOKIE',"sns_server_radio_cookie");
	$dat['sns_server_direct_input_cookie'] = (string)filter_input_data('COOKIE',"sns_server_direct_input_cookie");

	$dat['admin_pass'] = null;
	$dat['token'] = get_csrf_token();
	//HTML出力
	echo $blade->run(SET_SHARE_SERVER, $dat);
}

//SNSへ共有リンクを送信
function post_share_server(): void {

	$sns_server_radio = (string)filter_input_data('POST',"sns_server_radio",FILTER_VALIDATE_URL);
	$sns_server_radio_for_cookie = (string)filter_input_data('POST',"sns_server_radio");//directを判定するためurlでバリデーションしていない
	$sns_server_radio_for_cookie = ($sns_server_radio_for_cookie === 'direct') ? 'direct' : $sns_server_radio;
	$sns_server_direct_input = (string)filter_input_data('POST',"sns_server_direct_input",FILTER_VALIDATE_URL);
	$encoded_t = (string)filter_input_data('POST',"encoded_t");
	$encoded_t = urlencode($encoded_t);
	$encoded_u = (string)filter_input_data('POST',"encoded_u");
	$encoded_u = urlencode($encoded_u);
	setcookie("sns_server_radio_cookie",$sns_server_radio_for_cookie, time() + (86400*30),"","",false,true);
	setcookie("sns_server_direct_input_cookie",$sns_server_direct_input, time() + (86400*30),"","",false,true);
	$share_url='';
	if($sns_server_radio) {
		$share_url = $sns_server_radio."/share?text=";
	} elseif($sns_server_direct_input) { //直接入力時
		$share_url = $sns_server_direct_input."/share?text=";
		if($sns_server_direct_input === "https://bsky.app") {
			$share_url = "https://bsky.app/intent/compose?text=";
		} elseif($sns_server_direct_input === "https://www.threads.net") {
			$share_url = "https://www.threads.net/intent/post?text=";
		}
	}
	if(in_array($sns_server_radio,["https://x.com","https://twitter.com"])) {
		// $share_url="https://x.com/intent/post?text=";
		$share_url = "https://twitter.com/intent/tweet?text=";
	} elseif($sns_server_radio === "https://bsky.app") {
		$share_url = "https://bsky.app/intent/compose?text=";
	}	elseif($sns_server_radio === "https://www.threads.net") {
		$share_url = "https://www.threads.net/intent/post?text=";
	}
	$share_url .= $encoded_t.'%20'.$encoded_u;
	$share_url = filter_var($share_url, FILTER_VALIDATE_URL) ? $share_url : '';
	if(!$share_url) {
		error("SNSの共有先を選択してください。");
	}
	redirect($share_url);
}

//filter_input のラッパー関数
function filter_input_data(string $input, string $key, int $filter=0): mixed {
	// $_GETまたは$_POSTからデータを取得
	$value = null;
	if ($input === 'GET') {
			$value = $_GET[$key] ?? null;
	} elseif ($input === 'POST') {
			$value = $_POST[$key] ?? null;
	} elseif ($input === 'COOKIE') {
			$value = $_COOKIE[$key] ?? null;
	}

	// データが存在しない場合はnullを返す
	if ($value === null) {
			return null;
	}

	// フィルタリング処理
	switch ($filter) {
		case FILTER_VALIDATE_BOOLEAN:
			return  filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		case FILTER_VALIDATE_INT:
			return filter_var($value, FILTER_VALIDATE_INT);
		case FILTER_VALIDATE_URL:
			return filter_var($value, FILTER_VALIDATE_URL);
		default:
			return $value;  // 他のフィルタはそのまま返す
	}
}

//csrfトークンを作成
function get_csrf_token(): string {
	if (!isset($_SESSION)) {
		session_sta();
	}
	header('Expires:');
	header('Cache-Control:');
	header('Pragma:');
	if (!isset($_SESSION['token'])) {
		$_SESSION['token'] = hash('sha256', session_id(), false);
	}
	return $_SESSION['token'];
}
//csrfトークンをチェック
function check_csrf_token(): void {
	global $en;
	if(($_SERVER["REQUEST_METHOD"]) !== "POST"){
		error($en ? "This operation has failed." : "この操作は失敗しました。");
	}
	check_same_origin();

	session_sta();
	$token = (string)filter_input_data('POST','token');
	$session_token = isset($_SESSION['token']) ? (string)$_SESSION['token'] : '';
	if(!$token || !$session_token || !hash_equals($session_token,$token)) {
		error($en ? "CSRF token mismatch.\nPlease reload." : "CSRFトークンが一致しません。\nリロードしてください。");
	}
}

//session開始

function session_sta(): void {
	global $session_name;
	if (session_status() === PHP_SESSION_NONE) {
		$session_name = SESSION_NAME ?? 'noreita_session';
		session_name($session_name);
		session_save_path(__DIR__ . '/session/');
		$https_only = (bool)($_SERVER['HTTPS'] ?? '');
		ini_set('session.use_strict_mode', 1);
		session_set_cookie_params(
			0,"","",$https_only,true
		);
		session_start();
		header('Expires:');
		header('Cache-Control:');
		header('Pragma:');
	}
}

//エスケープ
function h($str): string {
	if(zero_check($str)){
		return '0';
	}
	if(!$str){
		return '';
	}
	return htmlspecialchars($str,ENT_QUOTES,"utf-8",false);
}
//タブ除去
function t($str): string {
	if(zero_check($str)){
		return '0';
	}
	if(!$str){
		return '';
	}
	return str_replace("\t","",(string)$str);
}
//タグ除去
function s($str): string {
	if(zero_check($str)){
		return '0';
	}
	if(!$str){
		return '';
	}
	return strip_tags((string)$str);
}

// 0 または "0" かどうか
function zero_check($str): bool {
	return($str === 0 || $str === '0');
}

// ファイル存在チェック
function check_file ($path): void {
	$msg = initial_error_message();

	if (!is_file($path)){
		die(h($path) . $msg['001']);
	}
	if (!is_readable($path)){
		die(h($path) . $msg['002']);
	}
}

function initial_error_message(): array {
	global $en;
	$msg['001'] = $en ? ' does not exist.':'がありません。';
	$msg['002'] = $en ? ' is not readable.':'を読めません。';
	$msg['003'] = $en ? ' is not writable.':'を書けません。';
return $msg;
}

function check_same_origin(): void {
	global $en,$usercode;

	session_sta();
	$c_usercode = t(filter_input_data('COOKIE', 'usercode'));//user-codeを取得
	$session_usercode = isset($_SESSION['usercode']) ? t($_SESSION['usercode']) : "";
	if(!$c_usercode){
		error( $en ? 'Cookie check failed.':'Cookieが確認できません。');
	}
	if(!$usercode || ($usercode !== $c_usercode) && ($usercode !== $session_usercode)){
		error( $en ? "User code mismatch.":"ユーザーコードが一致しません。");
	}
	// POSTリクエストの場合のみHTTP_ORIGINをチェックする
	if(($_SERVER["REQUEST_METHOD"]) === "POST"){
		if(!isset($_SERVER['HTTP_ORIGIN']) || !isset($_SERVER['HTTP_HOST'])){
				error( $en ? 'Your browser is not supported. ':'お使いのブラウザはサポートされていません。');
		}
		if(parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) !== $_SERVER['HTTP_HOST']){
				error( $en ? "The post has been rejected.":'拒絶されました。');
		}
}
}

function switch_tool($tool): string {
	switch($tool){
	case 'neo':
		$tool='PaintBBS NEO';
		break;
	case 'PaintBBS':
		$tool='PaintBBS';
		break;
	case 'shi-Painter':
		$tool='Shi-Painter';
		break;
	case 'chi':
		$tool='ChickenPaint';
		break;
	default:
		$tool='';
		break;
	}
	return $tool;
}

//sessionの確認
function admin_post_valid(): bool {
	global $second_pass;
	session_sta();
	return isset($_SESSION['admin_post']) && ($second_pass && $_SESSION['admin_post'] === $second_pass);
}
function admin_del_valid(): bool {
	global $second_pass;
	session_sta();
	return isset($_SESSION['admin_del']) && ($second_pass && $_SESSION['admin_del'] === $second_pass);
}
function user_del_valid(): bool {
	session_sta();
	return isset($_SESSION['user_del']) && ($_SESSION['user_del'] === 'user_del_mode');
}
