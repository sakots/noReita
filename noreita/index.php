<?php
//--------------------------------------------------
//  おえかきけいじばん「noReita」
//  by sakots & OekakiBBS reDev.Team  https://oekakibbs.moe/
//--------------------------------------------------

// スクリプトのバージョン
const REITA_VER = 'v3.4.1 lot.260716.0';

// 言語判定
$lang = ($http_langs = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
  ? explode( ',', $http_langs )[0] : '';
$en = (stripos($lang,'ja')!== 0);

// phpのバージョンが古い場合動かさせない
if (version_compare($php_ver = phpversion(),'8.0.0', '<')) {
  die($en ? "PHP version 8.0 or higher is required for this program to work. <br>\n(Current PHP version:{$php_ver})" : "PHPバージョン8.0以上が必要です。 <br>\n(現在のPHPバージョン:{$php_ver})");
}

// functions.phpの存在とバージョンを確認
if (!is_file(__DIR__.'/functions.php')) {
  die(__DIR__.'/functions.php'.($en ? ' does not exist.':'がありません。'));
}
require_once(__DIR__.'/functions.php');
if(!defined('FUNCTIONS_VER') || FUNCTIONS_VER < 20260716) {
  die($en ? 'Please update functions.php to the latest version.' : 'functions.phpを最新版に更新してください。');
}

// コンフィグ
check_file(__DIR__.'/config.php');
require(__DIR__ . '/config.php');
//コンフィグのバージョンが古くて互換性がない場合動かさせない
if (!defined('CONF_VER') || CONF_VER < 20260405) {
  die($en ? 'The configuration file is incompatible. Please reconfigure it.' : 'コンフィグファイルに互換性がないようです。再設定をお願いします。');
}

// database.inc
check_file(__DIR__.'/database.inc.php');
require_once(__DIR__.'/database.inc.php');
if(!defined('DATABASE_INC_VER') || DATABASE_INC_VER < 20260716) {
  die($en ? 'Please update database.inc.php to the latest version.' : 'database.inc.phpを最新版に更新してください。');
}

// image.inc
check_file(__DIR__.'/image.inc.php');
require_once(__DIR__.'/image.inc.php');
if(!defined('IMAGE_INC_VER') || IMAGE_INC_VER < 20260716) {
  die($en ? 'Please update image.inc.php to the latest version.' : 'image.inc.phpを最新版に更新してください。');
}

// misskey_note.inc
check_file(__DIR__.'/misskey_note.inc.php');
require_once(__DIR__.'/misskey_note.inc.php');
if(!defined('MISSKEY_NOTE_VER') || MISSKEY_NOTE_VER < 20260716) {
  die($en ? 'Please update misskey_note.inc.php to the latest version.' : 'misskey_note.inc.phpを最新版に更新してください。');
}

// connect_misskey_api.php
check_file(__DIR__.'/connect_misskey_api.php');
require_once(__DIR__.'/connect_misskey_api.php');
if(!defined('CONNECT_MISSKEY_API_VER') || CONNECT_MISSKEY_API_VER < 20260716) {
  die($en ? 'Please update connect_misskey_api.php to the latest version.' : 'connect_misskey_api.phpを最新版に更新してください。');
}

// save.inc
check_file(__DIR__.'/save.inc.php');
require_once(__DIR__.'/save.inc.php');
if(!defined('SAVE_INC_VER') || SAVE_INC_VER < 20260716) {
  die($en ? 'Please update save.inc.php to the latest version.' : 'save.inc.phpを最新版に更新してください。');
}

// thumbnail.inc
check_file(__DIR__.'/thumbnail.inc.php');
require_once(__DIR__.'/thumbnail.inc.php');
if(!defined('THUMBNAIL_VER') || THUMBNAIL_VER < 20260716) {
  error($en ? 'Please update thumbnail.inc.php to the latest version.' : 'thumbnail.inc.phpを最新版に更新してください。');
}

// テーマ
require(__DIR__ . '/theme/' . THEME_DIR . '/theme_conf.php');

// タイムゾーン設定
date_default_timezone_set(DEFAULT_TIMEZONE);


// 管理パスが初期値(admin_pass)の場合は動作させない
if ($admin_pass === 'admin_pass') {
  die($en ? "The admin pass is still at its default value! This program can't run it until you fix it." : "管理パスが初期設定値のままです！危険なので動かせません。管理パスを変更してください。");
}

// BladeOne v4.19.19
include(__DIR__ . '/BladeOne/lib/BladeOne.php');
use eftec\bladeone\BladeOne;

$views = __DIR__ . '/theme/' . THEME_DIR; // テンプレートフォルダ
$cache = __DIR__ . '/cache'; // キャッシュフォルダ

// キャッシュフォルダがなかったら作成
if (!file_exists($cache)) {
  mkdir($cache, PERMISSION_FOR_DIR);
}

$blade = new BladeOne($views, $cache, BladeOne::MODE_AUTO); // MODE_DEBUGだと開発モード MODE_AUTOが速い。
$blade->pipeEnable = true; // パイプのフィルターを使えるようにする

$dat = array(); // bladeに格納する変数

// var_dump($_POST);

// 絶対パス取得
$path = realpath("./") . '/' . IMG_DIR;
$temp_path = realpath("./") . '/' . TEMP_DIR;

$message = "";
$self = PHP_SELF;

$dat['path'] = IMG_DIR;

$dat['neo_dir'] = NEO_DIR;
$dat['chicken_dir'] = CHICKEN_DIR;
$dat['klecks_dir'] = KLECKS_DIR;
$dat['tegaki_dir'] = TEGAKI_DIR;
$dat['axnos_dir'] = AXNOS_DIR;

$dat['ver'] = REITA_VER;
$dat['base'] = BASE;
$dat['board_title'] = TITLE;
$dat['home'] = HOME;
$dat['self'] = PHP_SELF;
$dat['message'] = $message;
$dat['pdef_w'] = PDEF_W;
$dat['pdef_h'] = PDEF_H;
$dat['pmax_w'] = PMAX_W;
$dat['pmax_h'] = PMAX_H;

$dat['max_name'] = MAX_NAME;
$dat['max_email'] = MAX_EMAIL;
$dat['max_sub'] = MAX_SUB;
$dat['max_url'] = MAX_URL;
$dat['max_com'] = MAX_COM;

$dat['theme_dir'] = THEME_DIR;
$dat['theme_name'] = THEME_NAME;
$dat['tver'] = THEME_VER;

$dat['switch_sns'] = SWITCH_SNS;

$dat['use_chicken'] = USE_CHICKENPAINT;
$dat['use_klecks'] = USE_KLECKS;
$dat['use_tegaki'] = USE_TEGAKI;
$dat['use_axnos'] = USE_AXNOS;

$dat['select_palettes'] = USE_SELECT_PALETTES;
$dat['pallets_dat'] = $pallets_dat;

$dat['display_id'] = DISP_ID;
$dat['updatemark'] = UPDATE_MARK;
$dat['use_resub'] = USE_RESUB;

$dat['useanime'] = USE_ANIME;
$dat['defanime'] = DEF_ANIME;
$dat['use_continue'] = USE_CONTINUE;
$dat['newpost_nopassword'] = !CONTINUE_PASS;

$dat['use_name'] = USE_NAME;
$dat['use_com'] = USE_COM;
$dat['use_sub'] = USE_SUB;

$dat['addinfo'] = $addinfo;

$dat['display_painttime'] = DSP_PAINTTIME;

$dat['share_button'] = SHARE_BUTTON;

$dat['use_hashtag'] = USE_HASHTAG;

defined('ADMIN_CAP') or define('ADMIN_CAP', '(ではない)');

$dat['sodane'] = SODANE;

$dat['use_oekaki_reply'] = USE_OEKAKI_REPLY;

$dat['theme_name'] = THEME_NAME;

//ペイント画面の$pwdの暗号化
const CRYPT_METHOD = 'aes-128-cbc';
define('CRYPT_IV', 'T3pkYxNyjN7Wz3pu'); //半角英数16文字

//テーマがXHTMLか設定されてないなら
defined('TH_XHTML') or define('TH_XHTML', 0);

//日付フォーマット
defined('DATE_FORMAT') or define('DATE_FORMAT', 'Y/m/d H:i:s');

//NSFW画像機能を使う
defined('USE_NSFW') or define('USE_NSFW', 1);
$dat['use_nsfw'] = USE_NSFW;

//データベース接続PDO
const DB_FILE = __DIR__ . '/' . DB_NAME . '.db';
const DB_PDO = 'sqlite:' . DB_FILE;

defined("SNS_WINDOW_WIDTH") or define("SNS_WINDOW_WIDTH","600");
defined("SNS_WINDOW_HEIGHT") or define("SNS_WINDOW_HEIGHT","600");

//misskey
$dat['use_misskey_note'] = USE_MISSKEY_NOTE;

//初期設定
init();

del_temp();

clean_old_thumbnails();

$message = "";

//var_dump($_COOKIE);

$pwd_cookie = filter_input(INPUT_COOKIE, 'pwd_cookie');
$dat['name_cookie'] = (string)t(filter_input_data('COOKIE', 'name_c'));
$dat['email_cookie'] = (string)t(filter_input_data('COOKIE', 'email_c'));
$dat['url_cookie'] = (string)t(filter_input_data('COOKIE', 'url_c'));
$dat['pwd_cookie'] = (string)t(filter_input_data('COOKIE', 'pwd_cookie'));
$dat['palette_cookie'] = (string)t(filter_input_data('COOKIE', 'palette_c'));
$usercode = filter_input(INPUT_COOKIE, 'usercode'); //nullならuser-codeを発行

//$_SERVERから変数を取得
//var_dump($_SERVER);

$req_method = isset($_SERVER["REQUEST_METHOD"]) ? $_SERVER["REQUEST_METHOD"] : "";
//INPUT_SERVER が動作しないサーバがあるので$_SERVERを使う。

//ユーザーip
function get_uip():  string {
  if ($userip = getenv("HTTP_CLIENT_IP")) {
    return $userip;
  } elseif ($userip = getenv("HTTP_X_FORWARDED_FOR")) {
    return $userip;
  } elseif ($userip = getenv("REMOTE_ADDR")) {
    return $userip;
  } else {
    return $userip;
  }
}

$https_only = (bool)($_SERVER['HTTPS'] ?? '');
//user-codeの発行
$usercode = t(filter_input_data('COOKIE', 'usercode')); //user-codeを取得

noreita_session_start();
$session_usercode = $_SESSION['usercode'] ?? "";
$session_usercode = t($session_usercode);

$usercode = $usercode ? $usercode : $session_usercode;
if(!$usercode){ //user-codeがなければ発行
  $userip = get_uip();
  $usercode = hash('sha256', $userip.random_bytes(16));
}
setcookie("usercode", $usercode, time()+(86400*365),"","",$https_only,true); //1年間
$_SESSION['usercode'] = $usercode;

//var_dump($_GET);

/*-----------mode-------------*/

$mode = (string)filter_input_data('POST','mode');
$mode = $mode ?: (string)filter_input_data('GET','mode');

// Ajaxリクエストかどうかをチェック
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// モード

switch ($mode) {
  case 'regist': // スレ立て
    return regist();
  case 'reply':
    return regist();
  case 'res':
    return res();
  case 'sodane': // そうだね
    return sodane();
  case 'paint':
    return paint_form("", filter_input_data('POST','modid',FILTER_VALIDATE_INT));
  case 'piccom':
    return paint_com("");
  case 'pictmp':
    return paint_com("tmp");
  case 'anime':
    return open_pch($sp ?? "");
  case 'continue':
    return in_continue();
  case 'contpaint':
    $type = filter_input(INPUT_POST, 'type');
    if (CONTINUE_PASS || $type === 'rep') usrchk();
    return paint_form($type, filter_input_data('POST','modid',FILTER_VALIDATE_INT));
  case 'picrep':
    return picreplace();
  case 'catalog': // カタログ表示
    return catalog();
  case 'search': // 検索
    return search();
  case 'edit':
    return editform();
  case 'editexec':
    return editexec();
  case 'del':
    return delmode();
  case 'saveimage': // 画像保存
    return save_image();
  case 'admin_in': // 管理モードin
    return admin_in();
  case 'admin': // 管理モード
    return admin();
  case 'set_share_server':
    return set_share_server();
  case 'post_share_server':
    return post_share_server();
  case 'before_misskey_note':
    return misskey_note::before_misskey_note();
  case 'misskey_note_edit_form':
    return misskey_note::misskey_note_edit_form();
  case 'create_misskey_note_sessiondata':
    return misskey_note::create_misskey_note_sessiondata();
  case 'create_misskey_authrequesturl':
    return misskey_note::create_misskey_authrequesturl();
  case 'misskey_success':
    return misskey_note::misskey_success();
  default: // 通常表示モード
    return def();
}

/*-----------Main-------------*/

function init(): void {
  global $en;
  // セキュリティヘッダーの設定
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: DENY');
  header('X-XSS-Protection: 1; mode=block');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
  try {
    $db = new PDO(DB_PDO);
    $migrator = new DatabaseMigrator($db, DB_FILE, __DIR__ . '/backup');
    $migrator->migrate();
    $db = null;
  } catch (Throwable $e) {
    error(($en ? 'Database migration failed. ' : 'データベースの移行に失敗しました。') . h($e->getMessage()));
  }
  $err = '';
  if (!is_writable(realpath("./"))) error($en ? "Current directory is not writable.<br>" : "カレントディレクトリに書けません<br>");

  check_dir(__DIR__.'/'.IMG_DIR);
  check_dir(__DIR__.'/'.TEMP_DIR);
  check_dir(__DIR__.'/'.THUMB_DIR);
  check_dir(__DIR__.'/thumbnail/');
  check_dir(__DIR__.'/session/');

  if ($err) error($err);
  if (is_file(DB_FILE)) {
    // データベースファイルのパーミッションを明示的に設定
    chmod(DB_FILE, 0600);
  }
}


// 投稿があればデータベースへ保存する
/* 記事書き込み スレ立てとリプライ */
function regist(): void {
  global $badip, $admin_pass, $admin_name, $en;
  global $req_method;
  global $dat;

  $dat['en'] = $en;

  // CSRFトークンをチェック
  if (CHECK_CSRF_TOKEN) {
    check_csrf_token();
  }

  $sub = (string)filter_input(INPUT_POST, 'sub');
  $name = (string)filter_input(INPUT_POST, 'name');
  $mail = (string)filter_input(INPUT_POST, 'mail');
  $url = (string)filter_input(INPUT_POST, 'url');
  $com = (string)filter_input(INPUT_POST, 'com');
  $picfile = filter_input(INPUT_POST, 'picfile');
  $invz = trim(filter_input(INPUT_POST, 'invz'));
  $img_w = trim(filter_input(INPUT_POST, 'img_w', FILTER_VALIDATE_INT));
  $img_h = trim(filter_input(INPUT_POST, 'img_h', FILTER_VALIDATE_INT));
  $pwd = (string)trim(filter_input(INPUT_POST, 'pwd'));
  $pwdh = password_hash($pwd, PASSWORD_DEFAULT);
  $sodane = trim(filter_input(INPUT_POST, 'sodane', FILTER_VALIDATE_INT));
  $pal = filter_input(INPUT_POST, 'palettes');
  $nsfw_flag = (string)filter_input(INPUT_POST, 'nsfw', FILTER_VALIDATE_INT);
  $rep = (string)filter_input(INPUT_POST, 'rep');

  $repcode = (string)filter_input(INPUT_POST, 'repcode');
  $id = (string)filter_input(INPUT_POST, 'id');
  $no = (string)filter_input(INPUT_POST, 'no');
  $enc_pwd = (string)filter_input(INPUT_POST, 'enc_pwd');
  $modid = (string)filter_input(INPUT_POST, 'modid');

  $resto = (string)filter_input(INPUT_POST, 'resto');

  // クッキー保存用
  $original_name = $name;

  if ($req_method !== "POST") {
    error($en ? "Invalid request method." : "不正なリクエスト方法です。");
  }

  // NGワードがあれば拒絶
  Reject_if_NGword_exists_in_the_post($com, $name, $mail, $url, $sub);

  // 名前がない場合は拒絶
  if (USE_NAME && !$name) {
    error($en ? "Name is required." : "名前は必須です。");
  }
  // 本文必須 リプライのときは必ず必要、スレ立てのときは設定次第
  if (($resto || USE_COM) && !$com) {
    error($en ? "Comment is required." : "本文は必須です。");
  }
  if (USE_SUB && !$sub) {
    error($en ? "Subject is required." : "タイトルは必須です。");
  }

  if (strlen($com) > MAX_COM) {
    error($en ? "Comment is too long." : "本文が長すぎます。");
  }
  if (strlen($name) > MAX_NAME) {
    error($en ? "Name is too long." : "名前が長すぎます。");
  }
  if (strlen($mail) > MAX_EMAIL) {
    error($en ? "Email is too long." : "メールアドレスが長すぎます。");
  }
  if (strlen($sub) > MAX_SUB) {
    error($en ? "Subject is too long." : "タイトルが長すぎます。");
  }
  if (strlen($url) > MAX_URL) {
    error($en ? "URL is too long." : "URLが長すぎます。");
  }

  //ホスト取得
  $host = gethostbyaddr(get_uip());

  foreach ($badip as $value) { //拒絶host
    if (preg_match("/$value$/i", $host)) {
      error($en ? "Your host is blocked." : "あなたのホストは拒絶されています。");
    }
  }
  //セキュリティ関連ここまで

  try {
    $repository = new BoardRepository();
    if (isset($_POST["send"])) {

      $strlen_com = strlen($com);

      // トリップ生成
      $name = generate_trip($name);

      if ($name   === "") $name = DEF_NAME;
      if ($com  === "") $com  = DEF_COM;
      if ($sub  === "") $sub  = DEF_SUB;

      // 二重投稿チェック
      //最新コメント取得
      $msg_wc = $repository->latestThread();
      if (!empty($msg_wc)) {
        $msg_sub = $msg_wc["sub"]; //最新タイトル
        $msg_com = $msg_wc["com"]; //最新コメント取得できた
        $msg_host = $msg_wc["host"]; //最新ホスト取得できた
        //どれも一致すれば二重投稿だと思う
        if ($strlen_com > 0 && $com == $msg_com && $host == $msg_host && $sub == $msg_sub) {
          $msg_w = null;
          $db = null; //db切断
          error($en ? 'Duplicate post?' : '二重投稿ですか ?');
        }
        //画像番号が一致の場合(投稿してブラウザバック、また投稿とか)
        //二重投稿と判別(画像がない場合は処理しない)
        if (!empty($_POST["modid"])) {
          if ($msg_wc["picfile"] !== "" && $picfile == $msg_wc["picfile"]) {
            $db = null; //db切断
            error($en ? 'Duplicate post?' : '二重投稿ですか ?');
          }
        }
      }
      //↑ 二重投稿チェックおわり

      //画像ファイルとか処理
      $thumbnail = '';
      $psec = 0;
      $utime = "";
      $used_tool = "";
      $nsfw = false;
      $ctype = null;
      if ($picfile) {
        // ctypeを取得して画像から続きを描いたかどうかを判定
        $ctype = filter_input(INPUT_POST, 'ctype');
        
        // usercodeからctypeを取得（POSTデータにない場合）
        if ($ctype === null) {
          $usercode = filter_input(INPUT_POST, 'usercode');
          if ($usercode) {
            parse_str($usercode, $usercode_params);
            if (isset($usercode_params['ctype'])) {
              $ctype = $usercode_params['ctype'];
            }
          }
        }
        
        // send_headerパラメータからusercodeを取得（POSTデータにない場合）
        if ($ctype === null) {
          $send_header = filter_input(INPUT_POST, 'send_header');
          if ($send_header) {
            parse_str($send_header, $header_params);
            if (isset($header_params['usercode'])) {
              $usercode = $header_params['usercode'];
              parse_str($usercode, $usercode_params);
              if (isset($usercode_params['ctype'])) {
                $ctype = $usercode_params['ctype'];
              }
            }
          }
        }
        
        // HTTPヘッダーからusercodeを取得（POSTデータにない場合）
        if ($ctype === null) {
          $http_usercode = filter_input(INPUT_SERVER, 'HTTP_X_USERCODE');
          if ($http_usercode) {
            parse_str($http_usercode, $usercode_params);
            if (isset($usercode_params['ctype'])) {
              $ctype = $usercode_params['ctype'];
            }
          }
        }
        
        // セッション変数からusercodeを取得（POSTデータにない場合）
        if ($ctype === null) {
          if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
          }
          if (isset($_SESSION['usercode'])) {
            $usercode = $_SESSION['usercode'];
            parse_str($usercode, $usercode_params);
            if (isset($usercode_params['ctype'])) {
              $ctype = $usercode_params['ctype'];
            }
          }
        }
        
        // ctypeがnullの場合は新規投稿として扱う（動画ファイルを処理する）
        if ($ctype === null) {
          $ctype = 'new';
        }
        
        $image_result = ImageService::finalizeNewPost(
          TEMP_DIR, IMG_DIR, (string)$picfile, $ctype, (bool)DSP_PAINTTIME, PDEF_W,
          USE_NSFW === 1 && $nsfw_flag === '1', PERMISSION_FOR_DEST
        );
        $img_w = $image_result['img_w'];
        $img_h = $image_result['img_h'];
        $pchfile = $image_result['pchfile'];
        $psec = $image_result['psec'];
        $utime = $image_result['utime'];
        $used_tool = $image_result['tool'];
        $thumbnail = $image_result['thumbnail'];
        $nsfw = $image_result['nsfw'];
      } else {
        $img_w = 0;
        $img_h = 0;
        $pchfile = "";
        $utime = "";
        $used_tool = "";
        $psec = 0;
        $utime = "";
        $thumbnail = "";
        $nsfw = false;
      }

      // 値を追加する

      //不要改行圧縮
      $com = preg_replace("/(\n|\r|\r\n){3,}/us", "\n\n", $com);

      //id生成
      $id = gen_id($host, $utime ?? time());

      //UUID生成
      $uuid = generate_uuid();

      //管理者名は管理パスじゃないと使えない
      if ($name === $admin_name && $pwd !== $admin_pass) {
        $name = $name . ADMIN_CAP;
      }

      //管理者名の投稿でパスワードが管理パスなら管理者バッジつける
      $admins = ($pwd === $admin_pass && $name === $admin_name) ? 1 : 0;

      // 'のエスケープ(入りうるところがありそうなとこだけにしといた)
      $name = str_replace("'", "''", $name);
      $sub = str_replace("'", "''", $sub);
      $com = str_replace("'", "''", $com);
      $mail = str_replace("'", "''", $mail);
      $url = str_replace("'", "''", $url);
      $host = str_replace("'", "''", $host);
      $id = str_replace("'", "''", $id);

      $tree = time() * 100000000;

      //スレ建てorお絵かきリプ
      if (!isset($resto) || $resto === "") {
        $thread = 1; //スレ建て
        $parent = NULL;
        $comid = NULL;
        
        $age = 0;
      } else {
        $thread = 0; //お絵かきリプ
        $parent = $resto;
        //レスの位置
        $tree = time() - $parent - (int)$msg_wc["tid"];
        $comid = $tree + time();

        //メール欄にsageが含まれるならageない
        $age = (int)$msg_wc["age"];
        if (strpos($mail, 'sage') !== false) {
          //sage
          $age = $age;
        } else {
          //age
          $age++;
          $age_tree = $age + (time() * 100000000);
          $repository->bumpThread((int)$parent, $age, $age_tree);
        }
      }
      $shd = 0;
      
      $repository->insertPost([
        'thread'=>$thread, 'parent'=>$parent, 'comid'=>$comid, 'tree'=>$tree, 'a_name'=>$name,
        'sub'=>$sub, 'com'=>$com, 'mail'=>$mail, 'a_url'=>$url, 'picfile'=>$picfile,
        'pchfile'=>$pchfile, 'img_w'=>$img_w, 'img_h'=>$img_h, 'psec'=>$psec, 'utime'=>$utime,
        'pwd'=>$pwdh, 'id'=>$id, 'sodane'=>$sodane, 'age'=>$age, 'invz'=>$invz, 'host'=>$host,
        'tool'=>$used_tool, 'admins'=>$admins, 'shd'=>$shd, 'nsfw'=>$nsfw, 'ctype'=>$ctype,
        'uuid'=>$uuid, 'thumbnail'=>$thumbnail,
      ]);

      $c_pass = $pwd;
      //-- クッキー保存 --
      //クッキー項目："クッキー名 クッキー値"
      $https_only = (bool)($_SERVER['HTTPS'] ?? '');

      $cookies = [["name_c",$original_name],["email_c",$mail] , ["url_c", $url], ["pwd_cookie", $c_pass] ,[ "palette_c" , $pal]];
      foreach ($cookies as $cookie) {
        list($c_name, $c_cookie) = $cookie;
        $c_name = (string)$c_name;
        $c_cookie = (string)$c_cookie;
        setcookie($c_name, $c_cookie, time() + (SAVE_COOKIE * 24 * 3600),"","",$https_only,true);
      }

      $dat['message'] = ($en ? 'Successfully posted.' : '書き込みに成功しました。');
    }
  } catch (Throwable $e) {
    error(($en ? 'Posting failed. ' : '投稿処理に失敗しました。') . h($e->getMessage()));
  }
  unset($name, $mail, $sub, $com, $url, $pwd, $pwdh, $resto, $pictmp, $picfile, $mode);
  //header('Location:'.PHP_SELF);
  //ログ行数オーバー処理
  //スレ数カウント
  $th_cnt = 0;
  try {
    $repository = new BoardRepository();
    $th_cnt = $repository->countThreads();
  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
    return;
  }
  if ($th_cnt > MAX_THREAD) {
    logdel();
  }

  //そろそろ消えるスレッドのフラグを設定
  $th_id = (int)round(MAX_THREAD * LOG_LIMIT / 100); //閾値 … 新しい方からこの件数以降がもうすぐ消える
  if ($th_cnt > $th_id) {
    // そろそろ消えるスレッドにshdフラグを設定
    try {
      (new BoardRepository())->markOldThreads($th_cnt - $th_id);
    } catch (PDOException $e) {
      echo "DB接続エラー:" . $e->getMessage();
    }
  }

  // そろそろ消えるスレッドの情報をテンプレートに渡す
  $dat['log_limit'] = LOG_LIMIT;
  $dat['MAX_THREAD'] = MAX_THREAD;
  $dat['th_cnt'] = $th_cnt;
  $dat['th_id'] = $th_id;
  $dat['will_delete_count'] = max(0, $th_cnt - $th_id);

  ok($en ? 'Successfully posted. Switching screen.' : '書き込みに成功しました。画面を切り替えます。');
}

//通常表示モード
function def(): void {
  global $dat, $blade;
  $dsp_res = DSP_RES;
  $page_def = PAGE_DEF;

  $start = 0;

  //ログ行数オーバー処理
  //スレ数カウント
  $th_cnt = 0;
  try {
    $repository = new BoardRepository();
    $th_cnt = $repository->countThreads();
  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
    return;
  }
  if ($th_cnt > MAX_THREAD) {
    logdel();
  }

  //古いスレのレスボタンを表示しない
  $elapsed_time = ELAPSED_DAYS * 86400; //デフォルトの1年だと31536000
  $nowtime = time(); //いまのunixタイムスタンプを取得
  //あとはテーマ側で計算する
  $dat['nowtime'] = $nowtime;
  $dat['elapsed_time'] = $elapsed_time;

  //ページング
  try {
    $count = $repository->countThreads(true);
    if (isset($_GET['page']) && is_numeric($_GET['page'])) {
      $page = $_GET['page'];
      $page = max($page, 1);
    } else {
      $page = 1;
    }
    $start = $page_def * ($page - 1);

    //最大何ページあるのか
    $max_page = floor($count / $page_def) + 1;
    //最後にスレ数0のページができたら表示しない処理
    if (($count % $page_def) == 0) {
      $max_page = $max_page - 1;
      //ただしそれが1ページ目なら困るから表示
      $max_page = max($max_page, 1);
    }
    $dat['max_page'] = $max_page;

    //リンク作成用
    $dat['nowpage'] = $page;
    $p = 1;
    $pp = array();
    $paging = array();
    while ($p <= $max_page) {
      $paging[($p)] = compact('p');
      $pp[] = $paging;
      $p++;
    }
    $dat['paging'] = $paging;
    $dat['pp'] = $pp;

    $dat['back'] = ($page - 1);
    $dat['next'] = ($page + 1);

  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
  }

  //読み込み
  try {
    $posts = $repository->listThreads($start, $page_def);

    $i = 0;
    $j = 0;
    while ($i < PAGE_DEF) {
      $bbsline = $posts[$i] ?? false;
      if (empty($bbsline)) {
        break;
      } //スレがなくなったら抜ける
      $bbsline['thumb'] = $bbsline['thumbnail'] ?? '';
      $bbsline['thumb_avif'] = '';
      $oya_id = $bbsline["tid"]; //スレのid(親番号)を取得
      $posts_i = $repository->findReplies((int)$oya_id);
      $reply_index = 0;
      $j = 0;
      $flag = true;
      while ($flag == true) {
        $_pchext = pathinfo($bbsline['pchfile'], PATHINFO_EXTENSION);
        if ($_pchext === 'chi') {
          $bbsline['pchfile'] = ''; //litaChixは動画リンクを出さない
        }
        // 拡張子がない場合やctypeがimgの場合は動画リンクを出さない
        if ($_pchext === '' || $bbsline['pchfile'] === '' || (isset($bbsline['ctype']) && $bbsline['ctype'] === 'img')) {
          $bbsline['pchfile'] = '';
        }
        $res = $posts_i[$reply_index] ?? false;
        $reply_index++;
        if ($res) {
          $res['thumb'] = $res['thumbnail'] ?? '';
          $res['thumb_avif'] = '';
        }
        if (empty($res)) { //レスがなくなったら
          $bbsline['res_num'] = $j; //スレのレス数
          $bbsline['res_d_su'] = $j - DSP_RES; //スレのレス省略数
          if ($j > DSP_RES) { //スレのレス数が規定より多いと
            $bbsline['rflag'] = true; //省略フラグtrue
          } else {
            $bbsline['rflag'] = false; //省略フラグfalse
          }
          $flag = false;
          break;
        } //抜ける
        $res['resno'] = ($j + 1); //レス番号
        // http、https以外のURLの場合表示しない
        if (!filter_var($res['a_url'], FILTER_VALIDATE_URL) || !preg_match('|^https?://.*$|', $res['a_url'])) {
          $res['a_url'] = "";
        }
        $res['com'] = htmlspecialchars($res['com'], ENT_QUOTES | ENT_HTML5);

        //オートリンク
        if (AUTOLINK) $res['com'] = auto_link($res['com']);
        //画像URLにサムネイルを追加
        if (EXTERNAL_IMAGE_THUMB) {
          $res['com'] = image_thumbnail_link($res['com']);
        }
        //ハッシュタグ
        if (USE_HASHTAG) $res['com'] = hashtag_link($res['com']);
        //空行を縮める
        $res['com'] = preg_replace('/(\n|\r|\r\n|\n\r){3,}/us', "\n\n", $res['com']);
        //<br>に
        $res['com'] = tobr($res['com']);
        //引用の色
        $res['com'] = quote($res['com']);
        //日付をUNIX時間に変換して設定どおりにフォーマット
        $res['created'] = date(DATE_FORMAT, strtotime($res['created']));
        $res['modified'] = date(DATE_FORMAT, strtotime($res['modified']));
        $bbsline['res'][$j] = $res;
        $j++;
      }
      // http、https以外のURLの場合表示しない
      if (!filter_var($bbsline['a_url'], FILTER_VALIDATE_URL) || !preg_match('|^https?://.*$|', $bbsline['a_url'])) {
        $bbsline['a_url'] = "";
      }
      $bbsline['com'] = htmlspecialchars($bbsline['com'], ENT_QUOTES | ENT_HTML5);

      //オートリンク
      if (AUTOLINK) $bbsline['com'] = auto_link($bbsline['com']);
      //画像URLにサムネイルを追加
      if (EXTERNAL_IMAGE_THUMB) {
        $bbsline['com'] = image_thumbnail_link($bbsline['com']);
      }
      //ハッシュタグ
      if (USE_HASHTAG) $bbsline['com'] = hashtag_link($bbsline['com']);
      //空行を縮める
      $bbsline['com'] = preg_replace('/(\n|\r|\r\n){3,}/us', "\n\n", $bbsline['com']);
      //<br>に
      $bbsline['com'] = tobr($bbsline['com']);
      //引用の色
      $bbsline['com'] = quote($bbsline['com']);
      $bbsline['past'] = strtotime($bbsline['created']);
      $bbsline['created'] = date(DATE_FORMAT, strtotime($bbsline['created']));
      $bbsline['modified'] = date(DATE_FORMAT, strtotime($bbsline['modified']));

      $bbsline['encoded_t'] = urlencode('['.$bbsline['tid'].']'.$bbsline['sub'].($bbsline['a_name'] ? ' by '.$bbsline['a_name'] : '').' - '.TITLE);
      $bbsline['encoded_u'] = urlencode(BASE.'?resno='.$bbsline['tid']);

      // そろそろ消えるスレッドのフラグを設定
      $bbsline['will_delete'] = ($bbsline['shd'] === '1');

      $dat['oya'][$i] = $bbsline;
      $i++;
    }

    $dat['dsp_res'] = DSP_RES;
    $dat['path'] = IMG_DIR;

    echo $blade->run(MAINFILE, $dat);
  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
  }
}

//カタログモード
function catalog(): void {
  global $blade, $dat;
  $page_def = CATALOG_N;

  $start = 0;

  //ページング
  try {
    $repository = new BoardRepository();
    if (isset($_GET['page']) && is_numeric($_GET['page'])) {
      $page = $_GET['page'];
      $page = max($page, 1);
    } else {
      $page = 1;
    }
    $start = $page_def * ($page - 1);

    //最大何ページあるのか
    $th_cnt = $repository->countVisibleImages();
    $max_page = floor($th_cnt / $page_def) + 1;
    //最後にスレ数0のページができたら表示しない処理
    if (($th_cnt % $page_def) == 0) {
      $max_page = $max_page - 1;
      //ただしそれが1ページ目なら困るから表示
      $max_page = max($max_page, 1);
    }
    $dat['max_page'] = $max_page;

    //リンク作成用
    $dat['nowpage'] = $page;
    $p = 1;
    $pp = array();
    $paging = array();
    while ($p <= $max_page) {
      $paging[($p)] = compact('p');
      $pp[] = $paging;
      $p++;
    }
    $dat['paging'] = $paging;
    $dat['pp'] = $pp;

    $dat['back'] = ($page - 1);

    $dat['next'] = ($page + 1);

  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
    return;
  }
  //読み込み

  try {
    $posts = $repository->listCatalog($start, $page_def);

    $oya = array();

    $i = 0;
    while ($i < CATALOG_N) {
      $bbsline = $posts[$i] ?? false;
      if (empty($bbsline)) {
        break;
      } //スレがなくなったら抜ける
      $bbsline['thumb'] = $bbsline['thumbnail'] ?? '';
      $bbsline['com'] = nl2br(htmlspecialchars($bbsline['com'], ENT_QUOTES | ENT_HTML5), false);
      $oya[] = $bbsline;
      $i++;
    }

    $dat['oya'] = $oya;
    $dat['path'] = IMG_DIR;

    //$smarty->debugging = true;
    $dat['catalogmode'] = 'catalog';
    echo $blade->run(CATALOGFILE, $dat);
  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
  }
}

//検索モード 現在全件表示のみ対応
function search(): void {
  global $blade, $dat;

  $search_f = filter_input(INPUT_GET, 'search');
  $search = str_replace("'", "''", $search_f); //SQL
  //部分一致検索
  $similar =  filter_input(INPUT_GET, 'similar');
  //本文検索
  $tag = filter_input(INPUT_GET, 'tag');

  //読み込み
  try {
    $repository = new BoardRepository();
    //全スレッド取得
    //まずtagがあれば全文検索
    if ($tag == 'tag') {
      $posts = $repository->searchComments($search);
      $dat['catalogmode'] = 'hashsearch';
      $dat['tag'] = $search_f;
    } else {
      $posts = $repository->searchAuthors($search, $similar === 'similar');
      $dat['catalogmode'] = 'search';
      $dat['author'] = $search_f;
    }

    $oya = array();
    $ko = array();

    $i = 0;
    foreach ($posts as $bbsline) {
      $bbsline['thumb'] = $bbsline['thumbnail'] ?? '';
      $bbsline['com'] = nl2br(htmlspecialchars($bbsline['com'], ENT_QUOTES | ENT_HTML5), false);
      if ($bbsline['thread'] == 1) {
        $oya[] = $bbsline;
      } else {
        $ko[] = $bbsline;
      }
      $i++;
    }

    $dat['oya'] = $oya;
    $dat['ko'] = $ko;
    $dat['path'] = IMG_DIR;

    $dat['s_result'] = $i;
    echo $blade->run(CATALOGFILE, $dat);
  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
  }
}

//そうだね
function sodane(): void {
  $resto = filter_input(INPUT_GET, 'resto', FILTER_VALIDATE_INT);

  // Ajaxリクエストかどうかをチェック
  $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

  try {
    $new_sodane = (new BoardRepository())->incrementSodane((int)$resto);

    if ($is_ajax) {
      // Ajaxリクエストの場合はJSONレスポンス
      header('Content-Type: application/json');
      echo json_encode([
        'success' => true,
        'sodane' => $new_sodane,
        'message' => 'そうだねしました'
      ]);
      return;
    }

  } catch (PDOException $e) {
    if ($is_ajax) {
      header('Content-Type: application/json');
      echo json_encode([
        'success' => false,
        'error' => 'DB接続エラー:' . $e->getMessage()
      ]);
      return;
    } else {
      echo "DB接続エラー:" . $e->getMessage();
    }
  }

  // 通常のリクエストの場合は従来通りリダイレクト
  header('Location:' . PHP_SELF);
  def();
}

//レス画面
function res(): void {
  global $blade, $dat;
  $resno = filter_input(INPUT_GET, 'res',FILTER_VALIDATE_INT);
  $uuid = trim((string)filter_input(INPUT_GET, 'uuid'));

  //csrfトークンをセット
  $dat['token'] = '';
  if (CHECK_CSRF_TOKEN) {
    $token = get_csrf_token();
    $_SESSION['token'] = $token;
    $dat['token'] = $token;
  }

  //古いスレのレスフォームを表示しない
  $elapsed_time = ELAPSED_DAYS * 86400; //デフォルトの1年だと31536000
  $nowtime = time(); //いまのunixタイムスタンプを取得
  //あとはテーマ側で計算する
  $dat['elapsed_time'] = $elapsed_time;
  $dat['nowtime'] = $nowtime;

  try {
    $repository = new BoardRepository();

    if ($uuid !== '') {
      $resno = $repository->findThreadIdByUuid($uuid) ?? $resno;
    }
    $dat['resno'] = $resno;

    $thread = $repository->findPost((int)$resno);
    $posts = $thread ? [$thread] : [];

    $oya = array();
    $ko = array();
    foreach ($posts as $bbsline) {
      $bbsline['thumb'] = $bbsline['thumbnail'] ?? '';
      $bbsline['thumb_avif'] = '';
      //スレッドの記事を取得
      $posts_i = $repository->findReplies((int)$resno);
      $r_res_name = array();
      foreach ($posts_i as $res) {
        $res['thumb'] = $res['thumbnail'] ?? '';
        $res['thumb_avif'] = '';
        $res['com'] = htmlspecialchars($res['com'], ENT_QUOTES | ENT_HTML5);

        if (AUTOLINK) {
          $res['com'] = auto_link($res['com']);
        }
        //ハッシュタグ
        if (USE_HASHTAG) {
          $res['com'] = hashtag_link($res['com']);
        }
        //空行を縮める
        $res['com'] = preg_replace('/(\n|\r|\r\n){3,}/us', "\n\n", $res['com']);
        //<br>に
        $res['com'] = tobr($res['com']);
        //引用の色
        $res['com'] = quote($res['com']);
        //日付をUNIX時間に
        $bbsline['past'] = strtotime($bbsline['created']); // このスレは古いので用
        $res['created'] = date(DATE_FORMAT, strtotime($res['created']));
        $res['modified'] = date(DATE_FORMAT, strtotime($res['modified']));
        $ko[] = $res;
        //投稿者名取得
        if (!in_array($res['a_name'], $r_res_name)) { //重複除外
          $r_res_name[] = $res['a_name']; //投稿者名を配列に入れる
        }
        // http、https以外のURLの場合表示しない
        if (!filter_var($res['a_url'], FILTER_VALIDATE_URL) || !preg_match('|^https?://.*$|', $res['a_url'])) {
          $res['a_url'] = "";
        }
      }
      $bbsline['com'] = htmlspecialchars($bbsline['com'], ENT_QUOTES | ENT_HTML5);

      if (AUTOLINK) {
        $bbsline['com'] = auto_link($bbsline['com']);
      }
      //画像URLにサムネイルを追加
      $bbsline['com'] = image_thumbnail_link($bbsline['com']);
      //ハッシュタグ
      if (USE_HASHTAG) {
        $bbsline['com'] = hashtag_link($bbsline['com']);
      }
      //空行を縮める
      $bbsline['com'] = preg_replace('/(\n|\r|\r\n){3,}/us', "\n", $bbsline['com']);
      //<br>に
      $bbsline['com'] = tobr($bbsline['com']);
      //引用の色
      $bbsline['com'] = quote($bbsline['com']);
      //日付をUNIX時間に
      $bbsline['past'] = strtotime($bbsline['created']); //古いので用
      $bbsline['created'] = date(DATE_FORMAT, strtotime($bbsline['created']));
      $bbsline['modified'] = date(DATE_FORMAT, strtotime($bbsline['modified']));
      if (!in_array($bbsline['a_name'], $r_res_name)) {
        $r_res_name[] = $bbsline['a_name'];
      }
      // http、https以外のURLの場合表示しない
      if (!filter_var($bbsline['a_url'], FILTER_VALIDATE_URL) || !preg_match('|^https?://.*$|', $bbsline['a_url'])) {
        $bbsline['a_url'] = "";
      }
      //名前付きレス用
      $resname = implode(A_NAME_SAN . ' ', $r_res_name);
      $dat['resname'] = $resname;

      $bbsline['encoded_t'] = urlencode('['.$bbsline['tid'].']'.$bbsline['sub'].($bbsline['a_name'] ? ' by '.$bbsline['a_name'] : '').' - '.TITLE);
      $bbsline['encoded_u'] = urlencode(BASE.'?resno='.$bbsline['tid']);

      $oya[] = $bbsline;

      $dat['oya'] = $oya;
      $dat['ko'] = $ko;
    }
    $db = null;
  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
  }

  $dat['path'] = IMG_DIR;

  echo $blade->run(RESFILE, $dat);
}

//お絵描き画面
function paint_form(string $rep, int|null $reply_to): void {
  global $message, $usercode, $quality, $qualitys, $no;
  global $mode, $ctype, $pch, $type;
  global $blade, $dat;
  global $pallets_dat;

  $pwd = (string)filter_input(INPUT_POST, 'pwd');
  $imgfile = filter_input(INPUT_POST, 'img');

  //ツール
  if (isset($_POST["tools"])) {
    $tool = filter_input(INPUT_POST, 'tools');
  } else {
    $tool = "neo";
  }
  $dat['tool'] = $tool;

  $dat['message'] = $message;

  $picw = filter_input(INPUT_POST, 'picw', FILTER_VALIDATE_INT);
  $pich = filter_input(INPUT_POST, 'pich', FILTER_VALIDATE_INT);

  if ($mode === "contpaint" && (!$picw || !$pich)) {
    $imgfile = filter_input(INPUT_POST, 'img'); // 先にimgfileを取得
    if ($imgfile && is_file(IMG_DIR . $imgfile)) {
      list($picw, $pich) = getimagesize(IMG_DIR . $imgfile); //キャンバスサイズ
    }
  }

  $anime = isset($_POST["anime"]) ? true : false;
  $dat['anime'] = $anime;

  if ($picw < 300) $picw = 300;
  if ($pich < 300) $pich = 300;
  if ($picw > PMAX_W) $picw = PMAX_W;
  if ($pich > PMAX_H) $pich = PMAX_H;

  $dat['picw'] = $picw;
  $dat['pich'] = $pich;

  //NEOのときの幅と高さ
  $ww = $picw + 150;
  $hh = $pich + 172;

  if ($hh < 560) {
    $hh = 560;
  } //共通の最低高
  $dat['w'] = $ww;
  $dat['h'] = $hh;

  $dat['undo'] = UNDO;
  $dat['undo_in_mg'] = UNDO_IN_MG;

  $dat['useanime'] = USE_ANIME;

  $dat['path'] = IMG_DIR;

  $dat['stime'] = time();

  $userip = get_uip();

  //続きから
  if ($rep !== "") {
    $ctype = filter_input(INPUT_POST, 'ctype');
    $type = $rep;
    $pwd_f = filter_input(INPUT_POST, 'pwd');

    // 動画ファイルの存在をチェックしてctypeを自動設定
    if ($ctype === null || $ctype === '') {
      $pch = filter_input(INPUT_POST, 'pch');
      if ($pch) {
        $pch_filename = pathinfo($pch, PATHINFO_FILENAME);
        if (is_file(IMG_DIR . $pch_filename . '.pch') || is_file(IMG_DIR . $pch_filename . '.spch') || is_file(IMG_DIR . $pch_filename . '.chi')) {
          $ctype = 'pch'; // 動画ファイルが存在する場合
        } else {
          $ctype = 'img'; // 動画ファイルが存在しない場合
        }
      } else {
        $ctype = 'img'; // pchが指定されていない場合
      }
    }

    noreita_session_start();

    // 続きから描く場合は一時画像を除外するフラグを設定
    $dat['exclude_temp_images'] = true;

    $dat['no'] = $no;
    $dat['pwd'] = $pwd_f;
    $dat['ctype'] = $ctype;
    if (is_file(IMG_DIR . $pch . '.pch')) {
      $dat['useneo'] = true;
    } elseif (is_file(IMG_DIR . $pch . '.spch')) {
      $dat['useneo'] = false;
      $dat['use_shi_painter'] = true;
    }
    if ((C_SECURITY_CLICK || C_SECURITY_TIMER) && SECURITY_URL) {
      $dat['security'] = true;
      $dat['security_click'] = C_SECURITY_CLICK;
      $dat['security_timer'] = C_SECURITY_TIMER;
    }
  } else {
    if ((SECURITY_CLICK || SECURITY_TIMER) && SECURITY_URL) {
      $dat['security'] = true;
      $dat['security_click'] = SECURITY_CLICK;
      $dat['security_timer'] = SECURITY_TIMER;
    }
    $dat['newpaint'] = true;
  }
  $dat['security_url'] = SECURITY_URL;

  //パレット設定
  //初期パレット
  $lines = array();
  $initial_palette = 'Palettes[0] = "#000000\n#FFFFFF\n#B47575\n#888888\n#FA9696\n#C096C0\n#FFB6FF\n#8080FF\n#25C7C9\n#E7E58D\n#E7962D\n#99CB7B\n#FCECE2\n#F9DDCF";';
  foreach ($pallets_dat as $p_value) {
    if ($p_value[1] == filter_input(INPUT_POST, 'palettes')) { // キーと入力された値が同じなら
      $set_palettec = $p_value[1];
      setcookie("palettec", $set_palettec, time() + (86400 * SAVE_COOKIE)); // Cookie保存
      if (is_array($p_value)) {
        $lines = file($p_value[1]);
      } else {
        $lines = file($p_value);
      }
      break;
    }
  }

  //お絵かきリプ
  $dat['resto'] = $reply_to;

  $datmode = NULL;

  $pal = array();
  $DynP = array();
  $p_cnt = 0;
  $arr_pal=[];
  foreach ($lines as $i => $line) {
    $line = charconvert(str_replace(["\r", "\n", "\t"], "", $line));
    list($pid, $pname, $pal[0], $pal[2], $pal[4], $pal[6], $pal[8], $pal[10], $pal[1], $pal[3], $pal[5], $pal[7], $pal[9], $pal[11], $pal[12], $pal[13]) = explode(",", $line);
    $DynP[] = $pname;
    $p_cnt = $i + 1;
    $palettes = 'Palettes[' . $p_cnt . '] = "#';
    ksort($pal);
    $palettes .= implode('\n#', $pal);
    $palettes .= '";';
    $arr_pal[$i] = $palettes;
  }
  $user_palette_i = $initial_palette . implode('', $arr_pal);
  $dat['palettes'] = $user_palette_i;

  $count_dyn_p = count($DynP) + 1;

  $dat['palsize'] = $count_dyn_p;

  //パスワード暗号化
  $pwd_f = openssl_encrypt($pwd, CRYPT_METHOD, CRYPT_PASS, true, CRYPT_IV); //暗号化
  $pwd_f = bin2hex($pwd_f); //16進数に
  $arr_dyn_p=[];
  foreach ($DynP as $p) {
    $arr_dyn_p[] = '<option>' . $p . '</option>';
  }
  $dat['dynp'] = implode('', $arr_dyn_p);

  if ($ctype == 'pch' || $ctype == 'spch') {
    $pchfile = filter_input(INPUT_POST, 'pch');
    $dat['pchfile'] = IMG_DIR . $pchfile;
  } elseif ($ctype == 'img') {
    $dat['animeform'] = false;
    $dat['anime'] = false;
    $dat['useanime'] = false; // 動画機能を無効化
    $imgfile = filter_input(INPUT_POST, 'img');
    $dat['imgfile'] = IMG_DIR . $imgfile;
    // 画像から続きを描く場合はpchfileを設定しない
    $dat['pchfile'] = null;
  } else {
    // 新規投稿の場合はpchfileを設定しない（動画ファイルは後で生成される）
    $dat['pchfile'] = null;
  }
  $usercode .= '&tool=' . $tool . '&stime=' . time(); //拡張ヘッダにツールと描画開始時間をセット
  
  // ctypeが設定されている場合はusercodeに含める
  if ($ctype !== null) {
    $usercode .= '&ctype=' . $ctype;
  }

  //差し換え時の認識コード追加
  if ($type === 'rep') {
    $no = filter_input(INPUT_POST, 'no', FILTER_VALIDATE_INT);
    $userip = get_uip();

    noreita_session_start();
    $time = time();
    $repcode = substr(crypt(md5($no . $userip . $pwd_f . date("Ymd", $time)), $time), -8);
    //念の為にエスケープ文字があればアルファベットに変換
    $repcode = strtr($repcode, "!\"#$%&'()+,/:;<=>?@[\\]^`/{|}~", "ABCDEFGHIJKLMNOabcdefghijklmn");
    $datmode = 'picrep&no=' . $no . '&pwd=' . $pwd_f . '&repcode=' . $repcode;
    $usercode .= '&repcode=' . $repcode;
    $dat['rep'] = true;
    $dat['repcode'] = $repcode;
    $dat['enc_pwd'] = $pwd_f;
    $dat['pwd'] = $pwd_f;
  }
  $dat['usercode'] = $usercode; //usercodeにいろいろくっついたものをまとめて出力

  // デバッグ用：usercodeの内容を確認
  // error_log("paintform関数 - usercode: " . $usercode);

  // usercodeをセッション変数に保存
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
  $_SESSION['usercode'] = $usercode;

  //出口
  if ($type === 'rep') {
    //差し替え
    $dat['mode'] = $datmode;
  } else {
    //新規投稿
    $dat['mode'] = 'piccom';
  }
  //出力
  if ($tool === 'chicken' || $tool === 'klecks' || $tool === 'tegaki' || $tool === 'axnos') {
    echo $blade->run(PAINTFILE_BE, $dat);
  } elseif ($tool === 'shi' || $tool === 'neo') {
    echo $blade->run(PAINTFILE, $dat);
  } else {
    echo $blade->run(PAINTFILE, $dat);
  }
}

//アニメ再生

function open_pch(string $sp = ""): void {
  global $blade, $dat;
  $message = "";

  $pch = (string)filter_input(INPUT_GET, 'pch');
  if (!ImageService::isSafeAnimationFilename($pch)) {
    error(LANG === 'English' ? 'Invalid animation filename.' : '動画ファイル名が不正です。');
  }
  $pch_h = pathinfo($pch, PATHINFO_FILENAME);
  $extension = strtolower(pathinfo($pch, PATHINFO_EXTENSION));

  $picfile = IMG_DIR . $pch_h . ".png";

  if ($extension == 'spch') {
    $pchfile = IMG_DIR . $pch;
    $dat['tool'] = 'shi'; //拡張子がspchのときはしぃペインター
    $template = ANIMEFILE;
  } elseif ($extension == 'pch') {
    $pchfile = IMG_DIR . $pch;
    $dat['tool'] = 'neo'; //拡張子がpchのときはNEO
    $template = ANIMEFILE;
  } elseif ($extension=='tgkr'){
    $pchfile = IMG_DIR . $pch;
    $dat['tool'] = 'tegaki'; //拡張子がtgkrのときはTegaki
    $template = ANIMEFILE_TEGAKI;
  } else {
    $w = $h = $picw = $pich = $datasize = ""; //動画が無い時は処理しない
    $dat['tool'] = 'neo';
    $pchfile = null; // pchfileを明示的にnullに設定
    $template = ANIMEFILE; // 念のため
  }
  
  // pchfileが定義されている場合のみfilesizeを実行
  if ($pchfile !== null && is_file($pchfile)) {
    $datasize = filesize($pchfile);
  } else {
    $datasize = 0;
  }

  $size = getimagesize($picfile);
  if (!$sp) $sp = PCH_SPEED;
  $picw = $size[0];
  $pich = $size[1];
  $w = $picw;
  $h = $pich + 26;
  if ($w < 300) {
    $w = 300;
  }
  if ($h < 326) {
    $h = 326;
  }

  $dat['picw'] = $picw;
  $dat['pich'] = $pich;
  $dat['w'] = $w;
  $dat['h'] = $h;
  $dat['pchfile'] = './' . $pch;
  $dat['datasize'] = $datasize;

  $dat['speed'] = PCH_SPEED;

  $dat['path'] = IMG_DIR;
  $dat['a_stime'] = time();

  echo $blade->run($template, $dat);
}

//お絵かき投稿
function paint_com(string $tmpmode): void {
  global $usercode, $ptime;
  global $blade, $dat;

  $stime = filter_input(INPUT_GET, 'stime', FILTER_VALIDATE_INT);
  $resto = filter_input(INPUT_POST, 'resto', FILTER_VALIDATE_INT);
  if (!isset($resto) || $resto === null) {
    $resto = filter_input(INPUT_GET, 'resto', FILTER_VALIDATE_INT);
  }

  $dat['parent'] = $_SERVER['REQUEST_TIME'];
  $dat['usercode'] = $usercode;
  $dat['resto'] = $resto;

  //----------

  //csrfトークンをセット
  $dat['token'] = '';
  if (CHECK_CSRF_TOKEN) {
    $token = get_csrf_token();
    $_SESSION['token'] = $token;
    $dat['token'] = $token;
  }

  //投稿途中一覧 or 画像新規投稿 or 画像差し替え
  if ($tmpmode == "tmp") {
    $dat['picmode'] = 'is_temp';
  } elseif ($tmpmode == "rep") {
    $dat['picmode'] = 'pict_rep';
  } else {
    $dat['picmode'] = 'pict_up';
  }

  //----------

  //var_dump($_POST);
  $userip = get_uip();
  $tmp = [];
  foreach (ImageService::listTemporaryImages(TEMP_DIR) as $temporary_image) {
    if ($temporary_image['user_code'] === $usercode || $temporary_image['ip'] === $userip) {
      if (!empty($dat['exclude_temp_images'])) continue;
      $tmp[] = $temporary_image;
    }
  }

  $post_mode = true;
  $regist = true;
  $ipcheck = true;
  if (count($tmp) == 0) {
    $no_tmp = true;
    $pictmp = 1;
  } else {
    $pictmp = 2;
    $temp = array();
    foreach ($tmp as $temporary_image) {
      $src = TEMP_DIR . $temporary_image['filename'];
      $src_name = $temporary_image['filename'];
      $date = gmdate("Y/m/d H:i", filemtime($src) + 9 * 60 * 60);
      $tool = $temporary_image['tool'];
      $utime = $temporary_image['paint_time'];
      $psec = $temporary_image['paint_seconds'];
      $temp[] = compact('src', 'src_name', 'date', 'tool', 'utime', 'psec');
    }
    $dat['temp'] = $temp;
  }

  $tmp2 = array();
  $dat['tmp'] = $tmp2;

  echo $blade->run(PICFILE, $dat);
}

//コンティニュー画面in
function in_continue(): void {
  global $blade, $dat;

  $no = filter_input(INPUT_GET, 'no'); // 画像ファイル名なので文字列として取得
  $dat['othermode'] = 'incontinue';
  $dat['continue_mode'] = true;

  if (isset($_POST["tools"])) {
    $tool = filter_input(INPUT_POST, 'tools');
  } else {
    $tool = "neo";
  }
  $dat['tool'] = $tool;

  //コンティニュー時は削除キーを常に表示
  $dat['passflag'] = true;
  //新規投稿で削除キー不要の時 true
  if (!CONTINUE_PASS) $dat['newpost_nopassword'] = true;

  try {
    $repository = new BoardRepository();
    $oya = array();
    foreach ($repository->findPostsByImage((string)$no) as $bbsline) {
      $bbsline['com'] = nl2br(htmlentities($bbsline['com'], ENT_QUOTES | ENT_HTML5), false);
      $oya[] = $bbsline;
      $dat['oya'] = $oya; //配列に格納
    }
    $hist_ope = pathinfo($no, PATHINFO_FILENAME); //拡張子除去
    $hist_filename = IMG_DIR . $hist_ope;
    
    // データベースからctypeを取得
    $db_ctype = $oya[0]['ctype'] ?? null;
    
    if (is_file($hist_filename . '.pch')) {
      //$pchfile = IMG_DIR.$pch;
      $dat['tool'] = 'neo'; //拡張子がpchのときはNEO
      $dat['useshi'] = false;
      $dat['useneo'] = true;
      $dat['ctype_pch'] = true;
      $dat['ctype_img'] = false;
    } elseif (is_file($hist_filename . '.spch')) {
      $dat['tool'] = 'shi'; //拡張子がspchのときはしぃぺ
      $dat['useshi'] = true;
      $dat['useneo'] = false;
      $dat['ctype_pch'] = true;
      $dat['ctype_img'] = false;
    } elseif (is_file($hist_filename . '.chi')) {
      $dat['tool'] = 'chicken'; //拡張子がchiのときはlitaChix
      $dat['useshi'] = false;
      $dat['useneo'] = false;
      $dat['ctype_pch'] = true;
      $dat['ctype_img'] = false;
    } else { // どれでもない＝動画が無い時
      //$w=$h=$picw=$pich=$datasize="";
      $dat['useneo'] = true;
      $dat['useshi'] = true;
      $dat['ctype_pch'] = false;
      $dat['ctype_img'] = true;
    }
    // useshi, useneoは互換のためにいちおう残してある
    
    // データベースのctypeを優先する
    if ($db_ctype === 'img') {
      $dat['ctype_img'] = true;
      $dat['ctype_pch'] = false;
    } elseif ($db_ctype === 'pch' || $db_ctype === 'spch') {
      $dat['ctype_img'] = false;
      $dat['ctype_pch'] = true;
    }

    $db = null; //db切断
  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
  }

  echo $blade->run(OTHERFILE, $dat);
}

//削除くん

function delmode(): void {
  global $admin_pass;
  global $dat;
  global $en;

  $delno = filter_input(INPUT_POST, 'delno',FILTER_VALIDATE_INT);

  $p_pwd = filter_input(INPUT_POST, 'pwd');

  //記事呼び出し
  try {
    $repository = new BoardRepository();
    $msg = $repository->findPost((int)$delno);
    if (empty($msg)) {
      error($en ? 'That post does not exist.' : 'そんな記事ない気がします。');
    }
    $msg_pic = (string)$msg['picfile'];

    if (isset($_POST["admindel"])) {
      $admindel_mode = 1;
    } else {
      $admindel_mode = 0;
    }

    if (password_verify($p_pwd, $msg['pwd'])) {
      ImageService::deleteRelatedFiles(IMG_DIR, $msg_pic);
      $repository->deletePost((int)$delno);
      $dat['message'] = $en ? 'Successfully deleted.' : '削除しました。';
    } elseif ($admin_pass == $p_pwd && $admindel_mode == 1) {
      ImageService::deleteRelatedFiles(IMG_DIR, $msg_pic);
      $repository->deletePost((int)$delno, true);
      $dat['message'] = $en ? 'Successfully deleted.' : '削除しました。';
    } elseif ($admin_pass == $p_pwd && $admindel_mode != 1) {
      $repository->hidePost((int)$delno);
      $dat['message'] = $en ? 'Post hidden.' : '非表示にしました。';
    } else {
      error($en ? 'Invalid password or post number.' : 'パスワードまたは記事番号が違います。');
    }
    $msg = null;
  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
  }
  //変数クリア
  unset($delno, $delt);
  //header('Location:'.PHP_SELF);
  ok($en ? 'Successfully deleted. Switching screen.' : '削除しました。画面を切り替えます。');
}

//画像差し替え
function picreplace(): void {
  global $type;
  global $path, $badip;
  global $en;

  $stime = filter_input(INPUT_GET, 'stime', FILTER_VALIDATE_INT);
  $stime = $stime ?: ($_SERVER['REQUEST_TIME'] ?? time());
  $no = filter_input(INPUT_GET, 'no', FILTER_VALIDATE_INT);
  $no = $no ?: filter_input(INPUT_POST, 'no', FILTER_VALIDATE_INT);
  $repcode = filter_input(INPUT_GET, 'repcode');
  $repcode = $repcode ?: filter_input(INPUT_POST, 'repcode');
  $pwd = filter_input(INPUT_GET, 'pwd');
  $pwd = $pwd ?: filter_input(INPUT_POST, 'enc_pwd');
  if (!$no || !$repcode || !$pwd || !ctype_xdigit($pwd) || strlen($pwd) % 2 !== 0) {
    error($en ? 'Invalid replacement request.' : '画像差し替えのリクエストが不正です。');
  }
  $pwd_bin = hex2bin($pwd); //バイナリに
  $pwd_f = $pwd_bin === false ? false : openssl_decrypt($pwd_bin, CRYPT_METHOD, CRYPT_PASS, true, CRYPT_IV); //復号化
  if ($pwd_f === false) {
    error($en ? 'Invalid replacement request.' : '画像差し替えのリクエストが不正です。');
  }
  $nsfw_flag = filter_input(INPUT_POST, 'nsfw');

  //ホスト取得
  $host = gethostbyaddr(get_uip());

  foreach ($badip as $value) { //拒絶host
    if (preg_match("/$value$/i", $host)) error($en ? 'Your host is blocked.' : 'あなたのホストは拒絶されています。');
  }

  $temporary_image = ImageService::findTemporaryImageByReplacementCode(TEMP_DIR, (string)$repcode);
  if ($temporary_image === null) {
    error($en ? 'No temporary file found.' : 'テンポラリファイルが見つかりませんでした。');
  }
  $filename = $temporary_image['base_name'];
  $imgext = $temporary_image['image_extension'];
  $starttime = $temporary_image['start_time'];
  $postedtime = $temporary_image['posted_time'];

  // ログ読み込み
  try {
    $repository = new BoardRepository();
    $msg_d = $repository->findPost((int)$no);
    //パスワード照合
    // $flag = false;
    if (password_verify($pwd_f, $msg_d["pwd"])) {
      $replacement = ImageService::replacePostedFiles(
        TEMP_DIR, IMG_DIR, $filename, $imgext, (int)$stime,
        (string)$msg_d['picfile'], (string)$msg_d['pchfile'], PERMISSION_FOR_DEST
      );
      $new_picfile = $replacement['picfile'];
      $new_pchfile = $replacement['pchfile'];

      //描画時間を$userdataをもとに計算
      $psec = (int)$msg_d['psec'] + ((int)$postedtime - (int)$starttime);
      $utime = calcPtime($psec);

      //ホスト名取得
      $host = gethostbyaddr(get_uip());

      //id生成
      $id = gen_id($host, $utime ?? time());

      // 念のため'のエスケープ
      $host = str_replace("'", "''", $host);

      //nsfw
      if (USE_NSFW == 1 && $nsfw_flag == 1) {
        $nsfw = true;
      } else {
        $nsfw = false;
      }

      $repository->updateImage((int)$no, [
        'host' => $host, 'picfile' => $new_picfile, 'pchfile' => $new_pchfile, 'author_id' => $id,
        'psec' => $psec, 'utime' => $utime, 'nsfw' => $nsfw,
      ]);
    } else {
      error($en ? 'Invalid password or post number.' : 'パスワードまたは記事番号が違います。');
    }
  } catch (Throwable $e) {
    error(($en ? 'Image replacement failed. ' : '画像差し替えに失敗しました。') . h($e->getMessage()));
  }
  ok($en ? 'Successfully edited. Switching screen.' : '編集に成功しました。画面を切り替えます。');
}

//編集モードくん入口
function editform(): void {
  global $admin_pass;
  global $blade, $dat;
  global $en;

  //csrfトークンをセット
  $dat['token'] = '';
  if (CHECK_CSRF_TOKEN) {
    $token = get_csrf_token();
    $_SESSION['token'] = $token;
    $dat['token'] = $token;
  }

  //入力されたパスワード
  $post_pwd = filter_input(INPUT_POST, 'pwd');

  $edit_no = filter_input(INPUT_POST, 'delno',FILTER_VALIDATE_INT);
  if ($edit_no == "") {
    error($en ? 'Please enter the post number.' : '記事番号を入力してください');
  }

  //記事呼び出し
  try {
    $msg = (new BoardRepository())->findPost((int)$edit_no);
    if (empty($msg)) {
      error($en ? 'That post does not exist.' : 'そんな記事ないです。');
    }
    if (password_verify($post_pwd, $msg['pwd'])) {
      $dat['message'] = $en ? 'Editing mode...' : '編集モード...';
    } elseif ($admin_pass == $post_pwd) {
      $dat['message'] = $en ? 'Administrator editing mode...' : '管理者編集モード...';
    } else {
      error($en ? 'Invalid password or post number.' : 'パスワードまたは記事番号が違います。');
    }
    $msg['com'] = nl2br(htmlentities($msg['com'], ENT_QUOTES | ENT_HTML5), false);
    $dat['oya'] = [$msg];

    $dat['othermode'] = 'edit'; //編集モード
    echo $blade->run(OTHERFILE, $dat);
  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
  }
}

//編集モードくん本体
function editexec(): void {
  global $badip;
  global $req_method;
  global $dat;
  global $en;

  //CSRFトークンをチェック
  if (CHECK_CSRF_TOKEN) {
    check_csrf_token();
  }

  $resedit = trim((string)filter_input(INPUT_POST, 'resedit'));
  $e_no = trim((string)filter_input(INPUT_POST, 'e_no'));

  if ($req_method !== "POST") {
    error($en ? "Request denied." : "拒絶されました");
  }

  $sub = (string)filter_input(INPUT_POST, 'sub');
  $name = (string)filter_input(INPUT_POST, 'name');
  $mail = (string)filter_input(INPUT_POST, 'mail');
  $url = (string)filter_input(INPUT_POST, 'url');
  $com = (string)filter_input(INPUT_POST, 'com');
  $picfile = trim((string)filter_input(INPUT_POST, 'picfile'));
  $pwd = (string)trim(filter_input(INPUT_POST, 'pwd'));
  $pwdh = password_hash($pwd, PASSWORD_DEFAULT);
  $sodane = trim((string)filter_input(INPUT_POST, 'sodane', FILTER_VALIDATE_INT));

  //NGワードがあれば拒絶
  Reject_if_NGword_exists_in_the_post($com, $name, $mail, $url, $sub);

  if (USE_NAME && !$name) {
    error($en ? "Name is required." : "名前は必須です。");
  }
  //本文必須でいいだろ
  if (!$com) {
    error($en ? "Comment is required." : "本文は必須です。");
  }
  if (USE_SUB && !$sub) {
    error($en ? "Subject is required." : "タイトルは必須です。");
  }

  if (strlen($com) > MAX_COM) {
    error($en ? "Comment is too long." : "本文が長すぎます。");
  }
  if (strlen($name) > MAX_NAME) {
    error($en ? "Name is too long." : "名前が長すぎます。");
  }
  if (strlen($mail) > MAX_EMAIL) {
    error($en ? "Email is too long." : "メールアドレスが長すぎます。");
  }
  if (strlen($sub) > MAX_SUB) {
    error($en ? "Subject is too long." : "タイトルが長すぎます。");
  }
  if (strlen($url) > MAX_URL) {
    error($en ? "URL is too long." : "URLが長すぎます。");
  }

  //ホスト取得
  $host = gethostbyaddr(get_uip());

  foreach ($badip as $value) { //拒絶host
    if (preg_match("/$value$/i", $host)) {
      error($en ? "Your host is blocked." : "あなたのホストは拒絶されています。");
    }
  }
  //↑セキュリティ関連ここまで

  // 'のエスケープ(入りうるところがありそうなとこだけにしといた)
  $name = str_replace("'", "''", $name);
  $sub = str_replace("'", "''", $sub);
  $com = str_replace("'", "''", $com);
  $mail = str_replace("'", "''", $mail);
  $url = str_replace("'", "''", $url);
  $host = str_replace("'", "''", $host);

  try {
    (new BoardRepository())->updateContent((int)$e_no, [
      'name' => $name, 'mail' => $mail, 'sub' => $sub, 'com' => $com, 'url' => $url,
      'host' => $host, 'sodane' => $sodane, 'pwdh' => $pwdh,
    ]);
    $dat['message'] = $en ? 'Editing completed successfully.' : '編集完了しました。';
  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
  }
  unset($name, $mail, $sub, $com, $url, $pwd, $pwdh, $resto, $pictmp, $picfile, $mode);
  //header('Location:'.PHP_SELF);
  ok($en ? 'Successfully edited. Switching screen.' : '編集に成功しました。画面を切り替えます。');
}

//管理モードin
function admin_in(): void {
  global $blade, $dat;
  $dat['othermode'] = 'admin_in';

  echo $blade->run(OTHERFILE, $dat);
}

//管理モード
function admin(): void {
  global $admin_pass;
  global $blade, $dat;
  global $en;

  $dat['path'] = IMG_DIR;

  //最大何ページあるのか
  //記事呼び出しから
  try {
    $repository = new BoardRepository();
    //読み込み
    $adminpass = filter_input(INPUT_POST, 'adminpass');
    if ($adminpass === $admin_pass) {
      $oya = array();
      foreach ($repository->listForAdmin(true) as $bbsline) {
        if (empty($bbsline)) {
          break;
        } //スレがなくなったら抜ける
        //$oya_id = $bbsline["tid"]; //スレのtid(親番号)を取得
        $bbsline['com'] = htmlentities($bbsline['com'], ENT_QUOTES | ENT_HTML5);
        $oya[] = $bbsline;
      }
      $dat['oya'] = $oya;

      //スレッドの記事を取得
      $ko = array();
      foreach ($repository->listForAdmin(false) as $res) {
        $res['com'] = htmlentities($res['com'], ENT_QUOTES | ENT_HTML5);
        $ko[] = $res;
      }
      $dat['ko'] = $ko;
      echo $blade->run(ADMINFILE, $dat);
    } else {
      error($en ? 'Please enter the admin password.' : '管理パスを入力してください');
    }
  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
  }
}

// コンティニュー認証 (画像)
function usrchk(): void {
  global $en;

  $no = filter_input(INPUT_POST, 'no', FILTER_VALIDATE_INT);
  $pwd_f = filter_input(INPUT_POST, 'pwd');
  $flag = FALSE;
  try {
    $msg = (new BoardRepository())->findPost((int)$no);
    if (password_verify($pwd_f, $msg['pwd'])) {
      $flag = true;
    } else {
      $flag = false;
    }
  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
  }
  if (!$flag) {
    error($en ? "The specified post could not be found or the password is incorrect." : "該当記事が見つからないかパスワードが間違っています");
  }
}

//OK画面
function ok(string $mes): void {
  global $blade, $dat;
  $dat['okmes'] = $mes;
  $dat['othermode'] = 'ok';
  $async_flag = (bool)filter_input(INPUT_POST,'asyncflag',FILTER_VALIDATE_BOOLEAN);
  $http_x_requested_with = (bool)(isset($_SERVER['HTTP_X_REQUESTED_WITH']));
  if($http_x_requested_with || $async_flag){
    die("OK!\n$mes");
  }
  echo $blade->run(OTHERFILE, $dat);
}

//Asyncリクエストの時は処理を中断
function check_AsyncRequest($picfile=''): void {
  //ヘッダーが確認できなかった時の保険
  $asyncflag = (bool)filter_input(INPUT_POST,'asyncflag',FILTER_VALIDATE_BOOLEAN);
  $http_x_requested_with = (bool)(isset($_SERVER['HTTP_X_REQUESTED_WITH']));
  if($http_x_requested_with || $asyncflag){
    safe_unlink($picfile);
    exit;
  }
}

/* テンポラリ内のゴミ除去 */
function del_temp(): void {
  ImageService::cleanupTemporaryFiles(TEMP_DIR, TEMP_LIMIT);
}

//古い外部画像サムネイルの削除
function clean_old_thumbnails(): void {
  if (!defined('EXTERNAL_IMAGE_THUMB_DAYS') || EXTERNAL_IMAGE_THUMB_DAYS <= 0) {
    return;
  }
  $thumbnail_dir = __DIR__ . '/thumbnail/';
  if (!is_dir($thumbnail_dir)) {
    return;
  }
  $handle = opendir($thumbnail_dir);
  while ($file = readdir($handle)) {
    $file_path = $thumbnail_dir . $file;
    if (!is_dir($file_path) && preg_match('/_thumb\.(jpg|png|gif|webp|avif)$/', $file)) {
      $lapse = time() - filemtime($file_path);
      if ($lapse > (EXTERNAL_IMAGE_THUMB_DAYS * 24 * 3600)) {
        safe_unlink($file_path);
      }
    }
  }
  closedir($handle);
}

//画像保存
function save_image(): void {
  $tool = filter_input(INPUT_GET,"tool");
  $image_save = new image_save;
  header('Content-type: text/plain');
  switch($tool){
    case "neo":
      $image_save->save_neo();
      break;
    case "chi":
      $image_save->save_chickenpaint();
      break;
    case "klecks":
      $image_save->save_klecks();
      break;
    case "tegaki":
      $image_save->save_klecks();
      break;
    case "axnos":
      $image_save->save_klecks();
      break;
  }
}

//ログの行数が最大値を超えていたら削除
function logdel(): void {
  //オーバーした行の画像とスレ番号を取得
  try {
    $repository = new BoardRepository();
    $msg = $repository->oldestPost();
    if (!$msg) return;

    $del_id = (int)$msg["tid"]; //消す行のスレ番号
    $msg_pic = $msg["picfile"]; //画像の名前取得できた
    ImageService::deleteRelatedFiles(IMG_DIR, (string)$msg_pic);

    $repository->deletePost($del_id, true);
  } catch (PDOException $e) {
    echo "DB接続エラー:" . $e->getMessage();
  }
}

//エラー画面
function error(string $mes): void {
  global $db;
  global $blade, $dat;
  $db = null; //db切断
  $dat['errmes'] = $mes;
  $dat['othermode'] = 'err';
  $async_flag = (bool)filter_input(INPUT_POST,'asyncflag',FILTER_VALIDATE_BOOLEAN);
  $http_x_requested_with = (bool)(isset($_SERVER['HTTP_X_REQUESTED_WITH']));
  if($http_x_requested_with || $async_flag){
    die("error\n$mes");
  }
  echo $blade->run(OTHERFILE, $dat);
  exit;
}

//画像差し替え失敗
function error2(): void {
  global $db;
  global $blade, $dat;
  global $self;
  global $en;

  $db = null; //db切断
  $dat['othermode'] = 'err2';
  $async_flag = (bool)filter_input(INPUT_POST,'asyncflag',FILTER_VALIDATE_BOOLEAN);
  $http_x_requested_with = (bool)(isset($_SERVER['HTTP_X_REQUESTED_WITH']));
  if($http_x_requested_with || $async_flag){
    die($en ? "error?\nImage not found. There might be a failure in the posting.<a href=\"{{$self}}?mode=piccom\">Uploaded images</a> might still be available." : "error?\n画像が見当たりません。投稿に失敗している可能性があります。<a href=\"{{$self}}?mode=piccom\">アップロード途中の画像</a>に残っているかもしれません。");
  }
  echo $blade->run(OTHERFILE, $dat);
  exit;
}
