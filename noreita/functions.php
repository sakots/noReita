<?php
const FUNCTIONS_VER = 20260718;

//ページのコンテキストをセッションに保存
function set_page_context_to_session(): void {
  RequestSecurity::startSession();
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
function is_admin_pass(string $pwd): bool {
  global $admin_pass,$second_pass;
  $pwd=(string)$pwd;
  return ($admin_pass && $pwd && $second_pass !== $admin_pass && $pwd === $admin_pass);
}

// 文字コード変換
function charconvert(string $str): string {
  mb_language(LANG);
  return mb_convert_encoding($str, "UTF-8", "auto");
}

//念のため画像タイプチェック
function get_image_type(string $img_type, ?string $dest = null): string {
  global $en;
  // 既にMIMEタイプが渡されている場合はそのまま使用
  if (strpos($img_type, 'image/') === 0) {
    $mime_type = $img_type;
  } else {
    // ファイルパスが渡されている場合はMIMEタイプを取得
    $mime_type = mime_content_type($img_type);
  }
  
  $map = [
    "image/gif" => ".gif",
    "image/jpeg" => ".jpg",
    "image/png" => ".png",
    "image/webp" => ".webp",
    "image/avif" => ".avif",
  ];

  if (isset($map[$mime_type])) {
    return $map[$mime_type];
  }
  error($en ? "Invalid image type." : "無効な画像タイプです。");
  return ''; // この行は実際には実行されないが、リンターを満足させるために必要
}

/**
 * NGワードチェック
 * @param $ngwords
 * @param string|array $strs
 * @return bool
 */
function is_ngword(array $ngwords, string|array $strs): bool {
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

// 描画時間を計算
function calcPtime(int $psec): string {

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
function safe_unlink(string $path): bool {
  if ($path && is_file($path)) {
    try {
      return @unlink($path);
    } catch (Exception $e) {
      // エラーをログに記録するか、静かに失敗する
      error_log("Failed to delete file: {$path} - " . $e->getMessage());
      return false;
    }
  }
  return false;
}

/* オートリンク */
function auto_link(string $proto): string {
  if (!(stripos($proto, "script") !== false)) { //scriptがなければ続行
    // 画像URLを一時的にプレースホルダーに置き換え
    $image_urls = [];
    preg_match_all('/https?:\/\/[^\s<>"\'{}|\\^`[\]]+\.(jpg|jpeg|png|gif|webp|avif)(\?.*)?/i', $proto, $matches);
    foreach ($matches[0] as $img_url) {
      $placeholder = '<!--IMGURL' . md5($img_url) . '-->';
      $proto = str_replace($img_url, $placeholder, $proto);
      $image_urls[$placeholder] = $img_url;
    }
    $pattern = "{(https?|ftp)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)}";
    $replace = "<a href=\"\\1\\2\" target=\"_blank\" rel=\"nofollow noopener noreferrer\">\\1\\2</a>";
    $proto = preg_replace($pattern, $replace, $proto);
    // プレースホルダーを元の画像URLに戻す
    foreach ($image_urls as $placeholder => $img_url) {
      $proto = str_replace($placeholder, $img_url, $proto);
    }
    return $proto;
  } else {
    return $proto;
  }
}

/* ハッシュタグリンク */
function hashtag_link(string $hashtag): string {
  $self = PHP_SELF;
  $pattern = "/(?:^|[^ｦ-ﾟー゛゜々ヾヽぁ-ヶ一-龠ａ-ｚＡ-Ｚ０-９a-zA-Z0-9&_\/]+)[#＃]([ｦ-ﾟー゛゜々ヾヽぁ-ヶ一-龠ａ-ｚＡ-Ｚ０-９a-zA-Z0-9_]*[ｦ-ﾟー゛゜々ヾヽぁ-ヶ一-龠ａ-ｚＡ-Ｚ０-９a-zA-Z]+[ｦ-ﾟー゛゜々ヾヽぁ-ヶ一-龠ａ-ｚＡ-Ｚ０-９a-zA-Z0-9_]*)/u";
  $replace = " <a href=\"{$self}?mode=search&amp;tag=tag&amp;search=\\1\">#\\1</a>";
  $hashtag = preg_replace($pattern, $replace, $hashtag);
  return $hashtag;
}

/* '>'色設定 */
function quote(string $quote): string {
  $quote = preg_replace("/(^|>)((&gt;|＞)[^<]*)/i", "\\1" . RE_START . "\\2" . RE_END, $quote);
  return $quote;
}

/* 改行を<br>に */
function tobr(string $com): string {
  if (TH_XHTML !== 1) {
    $com = nl2br($com, false);
  } else {
    $com = nl2br($com);
  }
  return $com;
}

/* ID生成 */
function gen_id(string $userip, string $time): string {
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
function redirect(string $url): void {
  header("Location: {$url}");
  exit();
}

//filter_input のラッパー関数
function filter_input_data(string $input, string $key, int|string $filter=0): mixed {
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

//エスケープ
function h(string|null $str): string {
  if(zero_check($str)){
    return '0';
  }
  if(!$str){
    return '';
  }
  return htmlspecialchars($str,ENT_QUOTES,"utf-8",false);
}
//タブ除去
function t(string|null $str): string {
  if(zero_check($str)){
    return '0';
  }
  if(!$str){
    return '';
  }
  return str_replace("\t","",(string)$str);
}
//タグ除去
function s(string|null $str): string {
  if(zero_check($str)){
    return '0';
  }
  if(!$str){
    return '';
  }
  return strip_tags((string)$str);
}

// 0 または "0" かどうか
function zero_check(string|int|null $str): bool {
  return($str === 0 || $str === '0');
}

// ファイル存在チェック
function check_file(string $path): void {
  $msg = initial_error_message();

  if (!is_file($path)){
    die(h($path) . $msg['001']);
  }
  if (!is_readable($path)){
    die(h($path) . $msg['002']);
  }
}

//PaintBBS NEOのpchかどうか調べる
function is_neo(string $src): bool {
  $fp = fopen("$src", "rb");
  $is_neo=(fread($fp,3) === "NEO");
  fclose($fp);
  return $is_neo;
}
//pchデータから幅と高さを取得
function get_pch_size(string $src): ?array {
  if(!$src){
    return null;
  }
  $fp = fopen("$src", "rb");
  $is_neo=(fread($fp,3) === "NEO");//ファイルポインタが3byte移動
  $pch_data=(string)bin2hex(fread($fp,5));
  fclose($fp);
  if(!$is_neo || !$pch_data){
    return null;
  }
  $width = null;
  $height = null;
  $w0 = hexdec(substr($pch_data,2,2));
  $w1 = hexdec(substr($pch_data,4,2));
  $h0 = hexdec(substr($pch_data,6,2));
  $h1 = hexdec(substr($pch_data,8,2));
  if( !is_numeric($w0) || !is_numeric($w1) || !is_numeric($h0) || !is_numeric($h1)){
    return null;
  }
  $width = (int)$w0 + ((int)$w1 * 256);
  $height = (int)$h0 + ((int)$h1 * 256);
  if( !$width || !$height) {
    return null;
  }
  return[(int)$width,(int)$height];
}

function initial_error_message(): array {
  global $en;
  $msg['001'] = $en ? ' does not exist.':'がありません。';
  $msg['002'] = $en ? ' is not readable.':'を読めません。';
  $msg['003'] = $en ? ' is not writable.':'を書けません。';
return $msg;
}

function switch_tool(string $tool): string {
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
  RequestSecurity::startSession();
  return isset($_SESSION['admin_post']) && ($second_pass && $_SESSION['admin_post'] === $second_pass);
}
function admin_del_valid(): bool {
  global $second_pass;
  RequestSecurity::startSession();
  return isset($_SESSION['admin_del']) && ($second_pass && $_SESSION['admin_del'] === $second_pass);
}
function user_del_valid(): bool {
  RequestSecurity::startSession();
  return isset($_SESSION['user_del']) && ($_SESSION['user_del'] === 'user_del_mode');
}

// トリップ生成
function generate_trip(string $name): string {
  if ( ( $index = strpos($name, '#') ) === false)
    return str_replace( '◆', '◇', $name );
  $original_name = $name;
  $name = str_replace( '◆', '◇', substr($name, 0, $index) );
  $trip_key = mb_convert_encoding(substr($original_name, $index + 1), 'SJIS', 'UTF-8');
  if ( strlen($trip_key) >= 12 ) {
    if ( $trip_key[0] === '#' ) { // 10 digits new protocol
      if ( preg_match( '|^#([0-9a-fA-F]{16})([./0-9a-zA-Z]{0,2})$|', $trip_key, $matches ) ) {
        $key = pack('H*', $matches[1]);
        if ( ( $index = strpos($key, chr(128)) ) !== false )
          $key = substr($key, 0, $index);
        $trip = substr(crypt($key, substr($matches[2].'..', 0, 2)), -10);
      } else {
        $trip = '???';
      }
    } elseif ( $trip_key[0] === '$' ) { // reserved
      $trip = '???';
    } else { // 12 digits
      $trip = str_replace('+', '.', substr(base64_encode(sha1($trip_key, true)), 0, 12));
    }
  } else { // 10 digits
    $key = htmlspecialchars($trip_key, ENT_QUOTES, 'SJIS');
    $salt = preg_replace( '/[^.-z]/', '.', substr($key.'H.', 1, 2) );
    $map = array(':'=>'A', ';'=>'B', '<'=>'C', '='=>'D', '>'=>'E', '?'=>'F', '@'=>'G', '['=>'a', '\\'=>'b', ']'=>'c', '^'=>'d', '_'=>'e', '`'=>'f');
    $trip = substr(crypt($key, strtr($salt, $map)), -10);
  }
  return $name.' ◆'.$trip;
}

// UUIDv7生成
// UUIDv7 see https://www.rfc-editor.org/rfc/rfc9562#name-uuid-version-7
function generate_uuid(): string {
  // current timestamp in ms
  $timestamp = intval(microtime(true) * 1000);

  return sprintf(
    '%02x%02x%02x%02x-%02x%02x-%04x-%04x-%012x',
    // first 48 bits are timestamp based
    ($timestamp >> 40) & 0xFF,
    ($timestamp >> 32) & 0xFF,
    ($timestamp >> 24) & 0xFF,
    ($timestamp >> 16) & 0xFF,
    ($timestamp >> 8) & 0xFF,
    $timestamp & 0xFF,

    // 16 bits: 4 bits for version (7) and 12 bits for rand_a
    random_int(0, 0x0FFF) | 0x7000,

    // 16 bits: 4 bits for variant where 2 bits are fixed 10 and next 2 are random to get (8-9, a-b)
    // next 12 are random
    random_int(0, 0x3FFF) | 0x8000,

    // random 48 bits
    random_int(0, 0xFFFFFFFFFFFF),
  );
}
