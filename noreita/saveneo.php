<?php
if(($_SERVER["REQUEST_METHOD"]) !== "POST"){
	return header( "Location: ./ ") ;
}

//設定
include(__DIR__.'/config.php');
defined('SECURITY_TIMER') or define('SECURITY_TIMER', 0); //config.phpで未定義なら0
$lang = ($http_langs = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '')
  ? explode( ',', $http_langs )[0] : '';
$en = (stripos($lang,'ja') !== 0);

if($en){//ブラウザの言語が日本語以外の時
	$errormsg_1 = "Your picture upload failed! Please try again!";
	$errormsg_2 = "Your browser is not supported.";
	$errormsg_3 = "The post has been rejected.";
	$errormsg_4 = "User code mismatch.";
	$errormsg_5 = "The size of the picture is too big. ";
	$errormsg_6 = "Illegal image detected.\nImages are not saved.";
}else{//日本語
	$errormsg_1 = "投稿に失敗。時間をおいて再度投稿してみてください。";
	$errormsg_2 = "お使いのブラウザはサポートされていません。";
	$errormsg_3 = "拒絶されました。";
	$errormsg_4 = "ユーザーコードが一致しません。";
	$errormsg_5 = "ファイルサイズが大きすぎます。";
	$errormsg_6 = "不正な画像を検出しました。\n画像は保存されません。";
}

//容量違反チェックをする する:1 しない:0
define('SIZE_CHECK', '1');
//PNG画像データ投稿容量制限KB(chiは含まない)
define('PICTURE_MAX_KB', '8192');//8MBまで
define('PCH_MAX_KB', '40960');//40MBまで。ただしサーバのPHPの設定によって2MB以下に制限される可能性があります。
defined('PERMISSION_FOR_LOG') or define('PERMISSION_FOR_LOG', 0600); //config.phpで未定義なら0600
defined('PERMISSION_FOR_DEST') or define('PERMISSION_FOR_DEST', 0606); //config.phpで未定義なら0606

$time = time();
$imgfile = $time.substr(microtime(),2,6);	//画像ファイル名
$imgfile = is_file(TEMP_DIR.$imgfile.'.png') ? ((time()+1).substr(microtime(),2,6)) : $imgfile;

header('Content-type: text/plain');
//Sec-Fetch-SiteがSafariに実装されていないので、Orijinと、hostをそれぞれ取得して比較。
//Orijinがhostと異なっていたら投稿を拒絶。
if(!isset($_SERVER['HTTP_ORIGIN']) || !isset($_SERVER['HTTP_HOST'])){
	die("error\n{$errormsg_2}");
}
$url_scheme=parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_SCHEME).'://';
if(str_replace($url_scheme,'',$_SERVER['HTTP_ORIGIN']) !== $_SERVER['HTTP_HOST']){
	die("error\n{$errormsg_3}");
}
if(!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
	die("error\n{$errormsg_3}");
}

$u_ip = get_uip();
$u_host = $u_ip ? gethostbyaddr($u_ip) : '';
$u_agent = $_SERVER["HTTP_USER_AGENT"];
$u_agent = str_replace("\t", "", $u_agent);
$imgext='.png';
// 拡張ヘッダーを取り出す
$sendheader = (string)filter_input(INPUT_POST,'header');
/* ---------- 投稿者情報記録 ---------- */
$userdata = "$u_ip\t$u_host\t$u_agent\t$imgext";
$usercode='';
if($sendheader){
	$sendheader = str_replace("&amp;", "&", $sendheader);
	parse_str($sendheader, $u);
	$tool = 'neo';
	$usercode = isset($u['usercode']) ? $u['usercode'] : '';
	$resto = isset($u['resto']) ? $u['resto'] : '';
	$repcode = isset($u['repcode']) ? $u['repcode'] : '';
	$stime = isset($u['stime']) ? $u['stime'] : '';
	$count = isset($u['count']) ? $u['count'] : 0;
	$timer = isset($u['timer']) ? ($u['timer']/1000) : 0;
	//usercode 差し換え認識コード 描画開始 完了時間 レス先 を追加
	$userdata .= "\t$usercode\t$repcode\t$stime\t$time\t$resto\t$tool";
}
$userdata .= "\n";

//csrf
if($usercode !== (string)filter_input(INPUT_COOKIE, 'usercode')){
	die("error\n{$errormsg_4}");
}

if(((bool)SECURITY_TIMER && !$repcode && (bool)$timer) && ((int)$timer<(int)SECURITY_TIMER)){

	$psec=(int)SECURITY_TIMER-(int)$timer;
	$waiting_time=calcPtime ($psec);
	if($en){
		die("error\nPlease draw for another {$waiting_time}.");
	}else{
		die("error\n描画時間が短すぎます。あと{$waiting_time}。");
	}

}
if(((int)SECURITY_CLICK && !$repcode && $count) && ($count<(int)SECURITY_CLICK)){
	$nokori=(int)SECURITY_CLICK-$count;

	if($en){
		die("error\nPlease draw more. Further {$nokori} steps.");
	}else{
		die("error\n工程数が少なすぎます。あと{$nokori}工程。");
	}

}

if(!isset ($_FILES["picture"]) || $_FILES['picture']['error'] != UPLOAD_ERR_OK){
	die("error\n{$errormsg_1}");
}

if(SIZE_CHECK && ($_FILES['picture']['size'] > (PICTURE_MAX_KB * 1024))){
	die("error\n{$errormsg_5}");
}

$mime_type = mime_content_type($_FILES['picture']['tmp_name']);
$allowed_types = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
if(!in_array($mime_type, $allowed_types)){
	die("error\n{$errormsg_1}");
}

$chk = md5_file($_FILES['picture']['tmp_name']);
if(isset($badfile)&&is_array($badfile)){
	foreach($badfile as $value){
		if(preg_match("/\A$value/",$chk)){
			unlink($_FILES['picture']['tmp_name']);
			// 不正な画像を検出しました。画像は保存されません。
			die("error\n{$errormsg_6}");
		}
	}
}
$success = move_uploaded_file($_FILES['picture']['tmp_name'], TEMP_DIR.$imgfile.'.png');

if(!$success||!is_file(TEMP_DIR.$imgfile.'.png')) {
    die("error\n{$errormsg_1}");
}
chmod(TEMP_DIR.$imgfile.'.png',PERMISSION_FOR_DEST);
if(isset($_FILES['pch']) && ($_FILES['pch']['error'] == UPLOAD_ERR_OK)){
	$pch_mime_type = mime_content_type($_FILES['pch']['tmp_name']);
	$allowed_pch_types = ['application/octet-stream', 'application/x-binary', 'binary/octet-stream'];
	if(in_array($pch_mime_type, $allowed_pch_types) || strpos($pch_mime_type, 'application/') === 0){
		if(!SIZE_CHECK || ($_FILES['pch']['size'] < (PCH_MAX_KB * 1024))){
			//PSDファイルのアップロードができなかった場合はエラーメッセージはださず、画像のみ投稿する。 
			move_uploaded_file($_FILES['pch']['tmp_name'], TEMP_DIR.$imgfile.'.pch');
			if(is_file(TEMP_DIR.$imgfile.'.pch')){
				chmod(TEMP_DIR.$imgfile.'.pch',PERMISSION_FOR_DEST);
			}
		}
	}
}
// 情報データをファイルに書き込む
file_put_contents(TEMP_DIR.$imgfile.".dat",$userdata,LOCK_EX);
if(!is_file(TEMP_DIR.$imgfile.'.dat')){
	die("error\n{$errormsg_1}");
}
chmod(TEMP_DIR.$imgfile.'.dat',PERMISSION_FOR_LOG);

die("ok");
/**
 * 描画時間を計算
 * @param $starttime
 * @return string
 */
function calcPtime ($psec) {
	global $en;

	$D = floor($psec / 86400);
	$H = floor($psec % 86400 / 3600);
	$M = floor($psec % 3600 / 60);
	$S = $psec % 60;

	if($en){
		return
			($D ? $D.'day '  : '')
			. ($H ? $H.'hr ' : '')
			. ($M ? $M.'min ' : '')
			. ($S ? $S.'sec' : '')
			. ((!$D&&!$H&&!$M&&!$S) ? '0sec':'');

	}
		return
			($D ? $D.'日'  : '')
			. ($H ? $H.'時間' : '')
			. ($M ? $M.'分' : '')
			. ($S ? $S.'秒' : '')
			. ((!$D&&!$H&&!$M&&!$S) ? '0秒':'');

}
//ユーザーip
function get_uip(){
	$ip = isset($_SERVER["HTTP_CLIENT_IP"]) ? $_SERVER["HTTP_CLIENT_IP"] :'';
	$ip = $ip ? $ip : (isset($_SERVER["HTTP_INCAP_CLIENT_IP"]) ? $_SERVER["HTTP_INCAP_CLIENT_IP"] : '');
	$ip = $ip ? $ip : (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? $_SERVER["HTTP_X_FORWARDED_FOR"] : '');
	$ip = $ip ? $ip : (isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : '');
	if(strstr($ip, ', ')) {
		$ips = explode(', ', $ip);
		$ip = $ips[0];
	}
	return $ip;
}

// by satopian