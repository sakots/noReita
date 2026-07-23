<?php
//--------------------------------------------------
//  おえかきけいじばん「noReita」
//  by sakots & OekakiBBS reDev.Team  https://oekakibbs.moe/
//--------------------------------------------------

// スクリプトのバージョン
const REITA_VER = 'v3.6.1 lot.260722.0';

// 言語判定
$lang = ($http_langs = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
  ? explode( ',', $http_langs )[0] : '';
$en = (stripos($lang,'ja')!== 0);

// phpのバージョンが古い場合動かさせない
$php_ver = PHP_VERSION;
if (version_compare($php_ver, '8.0.0', '<')) {
  die($en ? "PHP version 8.0 or higher is required for this program to work. <br>\n(Current PHP version:{$php_ver})" : "PHPバージョン8.0以上が必要です。 <br>\n(現在のPHPバージョン:{$php_ver})");
}

// functions.phpの存在とバージョンを確認
if (!is_file(__DIR__.'/functions.php')) {
  die(__DIR__.'/functions.php'.($en ? ' does not exist.':'がありません。'));
}
require_once(__DIR__.'/functions.php');
if(!defined('FUNCTIONS_VER') || FUNCTIONS_VER < 20260722) {
  die($en ? 'Please update functions.php to the latest version.' : 'functions.phpを最新版に更新してください。');
}

// コンフィグ
check_file(__DIR__.'/config.php');
require(__DIR__ . '/config.php');
//コンフィグのバージョンが古くて互換性がない場合動かさせない
if (!defined('CONF_VER') || CONF_VER < 20260405) {
  die($en ? 'The configuration file is incompatible. Please reconfigure it.' : 'コンフィグファイルに互換性がないようです。再設定をお願いします。');
}

// request_security.inc
check_file(__DIR__.'/request_security.inc.php');
require_once(__DIR__.'/request_security.inc.php');
if(!defined('REQUEST_SECURITY_INC_VER') || REQUEST_SECURITY_INC_VER < 20260723) {
  die($en ? 'Please update request_security.inc.php to the latest version.' : 'request_security.inc.phpを最新版に更新してください。');
}

// request_info.inc
check_file(__DIR__.'/request_info.inc.php');
require_once(__DIR__.'/request_info.inc.php');
if(!defined('REQUEST_INFO_INC_VER') || REQUEST_INFO_INC_VER < 20260718) {
  die($en ? 'Please update request_info.inc.php to the latest version.' : 'request_info.inc.phpを最新版に更新してください。');
}

// database.inc
check_file(__DIR__.'/database.inc.php');
require_once(__DIR__.'/database.inc.php');
if(!defined('DATABASE_INC_VER') || DATABASE_INC_VER < 20260723) {
  die($en ? 'Please update database.inc.php to the latest version.' : 'database.inc.phpを最新版に更新してください。');
}

// initialization.inc
check_file(__DIR__.'/initialization.inc.php');
require_once(__DIR__.'/initialization.inc.php');
if(!defined('INITIALIZATION_INC_VER') || INITIALIZATION_INC_VER < 20260716) {
  die($en ? 'Please update initialization.inc.php to the latest version.' : 'initialization.inc.phpを最新版に更新してください。');
}

// image.inc
check_file(__DIR__.'/image.inc.php');
require_once(__DIR__.'/image.inc.php');
if(!defined('IMAGE_INC_VER') || IMAGE_INC_VER < 20260721) {
  die($en ? 'Please update image.inc.php to the latest version.' : 'image.inc.phpを最新版に更新してください。');
}

// post.inc
check_file(__DIR__.'/post.inc.php');
require_once(__DIR__.'/post.inc.php');
if(!defined('POST_INC_VER') || POST_INC_VER < 20260723) {
  die($en ? 'Please update post.inc.php to the latest version.' : 'post.inc.phpを最新版に更新してください。');
}

// share.inc
check_file(__DIR__.'/share.inc.php');
require_once(__DIR__.'/share.inc.php');
if(!defined('SHARE_INC_VER') || SHARE_INC_VER < 20260718) {
  die($en ? 'Please update share.inc.php to the latest version.' : 'share.inc.phpを最新版に更新してください。');
}

// misskey_note.inc
check_file(__DIR__.'/misskey_note.inc.php');
require_once(__DIR__.'/misskey_note.inc.php');
if(!defined('MISSKEY_NOTE_VER') || MISSKEY_NOTE_VER < 20260722) {
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
  error($en ? 'Please update thumbnail.inc.php to the latest version.' : 'thumbnail.inc.phpを最新版に更新してください。', 500);
}

// external_image.inc
check_file(__DIR__.'/external_image.inc.php');
require_once(__DIR__.'/external_image.inc.php');
if(!defined('EXTERNAL_IMAGE_INC_VER') || EXTERNAL_IMAGE_INC_VER < 20260718) {
  error($en ? 'Please update external_image.inc.php to the latest version.' : 'external_image.inc.phpを最新版に更新してください。', 500);
}

// テーマ
require(__DIR__ . '/theme/' . THEME_DIR . '/theme_conf.php');

// タイムゾーン設定
date_default_timezone_set(DEFAULT_TIMEZONE);


// 管理パスが初期値(admin_pass)の場合は動作させない
if ($admin_pass === 'admin_pass') {
  die($en ? "The admin pass is still at its default value! This program can't run it until you fix it." : "管理パスが初期設定値のままです！危険なので動かせません。管理パスを変更してください。");
}

// Composer dependencies (BladeOne v4.19.1)
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
  die($en
    ? 'Composer dependencies are missing. Run composer install in the noReita directory.'
    : 'Composer依存ライブラリがありません。noReitaディレクトリでcomposer installを実行してください。');
}
require_once $autoload;
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
defined('ADMIN_SESSION_LIFETIME') or define('ADMIN_SESSION_LIFETIME', 1800);
defined('ADMIN_THREADS_PER_PAGE') or define('ADMIN_THREADS_PER_PAGE', 50);


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

$https_only = (bool)($_SERVER['HTTPS'] ?? '');
//user-codeの発行
$usercode = t(filter_input_data('COOKIE', 'usercode')); //user-codeを取得

RequestSecurity::startSession();
$session_usercode = $_SESSION['usercode'] ?? "";
$session_usercode = t($session_usercode);

$usercode = $usercode ? $usercode : $session_usercode;
if(!$usercode){ //user-codeがなければ発行
  $userip = RequestInfo::clientIp();
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
  case 'admin_login':
    return admin_login();
  case 'admin_logout':
    return admin_logout();
  case 'admin_delete':
    return admin_delete();
  case 'admin_manage':
    return admin_manage();
  case 'admin_post':
    return admin_post();
  case 'admin_edit':
    return admin_edit();
  case 'admin': // 管理モード
    return admin();
  case 'set_share_server':
    return show_share_server_form();
  case 'post_share_server':
    return submit_share_server();
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
  $initializer = new ApplicationInitializer(
    DB_PDO, DB_FILE, __DIR__ . '/backup', __DIR__,
    [
      __DIR__ . '/' . IMG_DIR, __DIR__ . '/' . TEMP_DIR, __DIR__ . '/' . THUMB_DIR,
      __DIR__ . '/thumbnail', __DIR__ . '/session',
    ],
    PERMISSION_FOR_DIR
  );
  $initializer->sendSecurityHeaders();
  try {
    $initializer->prepareDirectories();
    $initializer->migrateDatabase();
    $initializer->secureDatabaseFile();
  } catch (Throwable $e) {
    error(($en ? 'Application initialization failed. ' : 'アプリケーションの初期化に失敗しました。') . h($e->getMessage()), 500);
    return;
  }
}

function show_share_server_form(): void {
  global $servers, $blade, $dat;

  $configured_servers = isset($servers) && is_array($servers) ? $servers : null;
  $dat['servers'] = ShareService::servers($configured_servers);
  $dat['encoded_t'] = (string)filter_input_data('GET', 'encoded_t');
  $dat['encoded_u'] = (string)filter_input_data('GET', 'encoded_u');
  $dat['sns_server_radio_cookie'] = (string)filter_input_data('COOKIE', 'sns_server_radio_cookie');
  $dat['sns_server_direct_input_cookie'] = (string)filter_input_data('COOKIE', 'sns_server_direct_input_cookie');
  $dat['admin_pass'] = null;
  $dat['token'] = RequestSecurity::csrfToken();
  echo $blade->run(SET_SHARE_SERVER, $dat);
}

function submit_share_server(): void {
  global $en;

  if (CHECK_CSRF_TOKEN) {
    try {
      RequestSecurity::assertCurrentCsrfRequest($en);
    } catch (RequestSecurityException $e) {
      error($e->getMessage(), $e->getCode() ?: 403);
    }
  }
  $selected_server = (string)filter_input_data('POST', 'sns_server_radio');
  $direct_server = (string)filter_input_data('POST', 'sns_server_direct_input');
  try {
    $share_url = ShareService::buildShareUrl(
      $selected_server,
      $direct_server,
      (string)filter_input_data('POST', 'encoded_t'),
      (string)filter_input_data('POST', 'encoded_u')
    );
  } catch (InvalidArgumentException $e) {
    error($en ? 'Please select a sharing destination for SNS.' : 'SNSの共有先を選択してください。');
    return;
  }

  $https_only = (bool)($_SERVER['HTTPS'] ?? '');
  $server_cookie = $selected_server === 'direct' ? 'direct' : rtrim($selected_server, '/');
  $direct_cookie = filter_var($direct_server, FILTER_VALIDATE_URL) ? rtrim($direct_server, '/') : '';
  setcookie('sns_server_radio_cookie', $server_cookie, time() + 86400 * 30, '', '', $https_only, true);
  setcookie('sns_server_direct_input_cookie', $direct_cookie, time() + 86400 * 30, '', '', $https_only, true);
  redirect($share_url);
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
    try {
      RequestSecurity::assertCurrentCsrfRequest($en);
    } catch (RequestSecurityException $e) {
      error($e->getMessage(), $e->getCode() ?: 403);
    }
  }

  $input = PostValidator::inputFromHttp();
  $sub = $input['sub'];
  $name = $input['name'];
  $mail = $input['mail'];
  $url = $input['url'];
  $com = $input['com'];
  $picfile = $input['picfile'];
  $pwd = $input['pwd'];
  $pal = $input['pal'];
  $nsfw_flag = $input['nsfw_flag'];

  // クッキー保存用
  $original_name = $name;

  //ホスト取得
  $host = gethostbyaddr(RequestInfo::clientIp());
  try {
    $rules = PostValidator::configuredRules($en, $req_method, $host, $badip, $admin_pass, (bool)USE_COM);
    PostValidator::validate($input, $rules);
  } catch (PostValidationException $e) {
    error($e->getMessage(), $e->getCode() ?: 400);
    return;
  }
  //セキュリティ関連ここまで

  try {
    $repository = new BoardRepository();
    if (isset($_POST["send"])) {
      $service = new PostService($repository, $admin_pass, IMG_DIR);
      try {
        $prepared_post = $service->prepareNewPost($input, $host, [
          'default_name' => DEF_NAME, 'default_comment' => DEF_COM, 'default_subject' => DEF_SUB,
          'admin_name' => $admin_name, 'admin_cap' => ADMIN_CAP,
        ]);
      } catch (DuplicatePostException $e) {
        error($en ? 'Duplicate post?' : '二重投稿ですか ?', 409);
        return;
      }

      $image_result = [
        'img_w' => 0, 'img_h' => 0, 'pchfile' => '', 'psec' => 0, 'utime' => '',
        'tool' => '', 'thumbnail' => '', 'nsfw' => false, 'ctype' => null,
      ];
      if ($picfile) {
        $ctype = PostInput::ctypeFromHttp();
        $image_result = ImageService::finalizeNewPost(
          TEMP_DIR, IMG_DIR, (string)$picfile, $ctype, (bool)DSP_PAINTTIME, PDEF_W,
          USE_NSFW === 1 && $nsfw_flag === '1', PERMISSION_FOR_DEST
        );
        $image_result['ctype'] = $ctype;
      }
      $service->createPreparedPost($prepared_post, $image_result);

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
    error(($en ? 'Posting failed. ' : '投稿処理に失敗しました。') . h($e->getMessage()), 500);
  }
  unset($name, $mail, $sub, $com, $url, $pwd, $pictmp, $picfile, $mode);
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
          $res['com'] = external_image_service()->addThumbnailLinks($res['com']);
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
        $bbsline['com'] = external_image_service()->addThumbnailLinks($bbsline['com']);
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

  $search_f = (string)filter_input(INPUT_GET, 'search');
  $search = $search_f;
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
    $token = RequestSecurity::csrfToken();
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
      $bbsline['com'] = external_image_service()->addThumbnailLinks($bbsline['com']);
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

  $userip = RequestInfo::clientIp();

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

    RequestSecurity::startSession();

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
    $userip = RequestInfo::clientIp();

    RequestSecurity::startSession();
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
  RequestSecurity::startSession();
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

  $pch = (string)filter_input(INPUT_GET, 'pch');
  try {
    $playback = ImageService::animationPlaybackData(IMG_DIR, $pch, (int)($sp ?: PCH_SPEED));
  } catch (Throwable $e) {
    error((LANG === 'English' ? 'Failed to open animation. ' : '動画を開けませんでした。') . h($e->getMessage()), 404);
    return;
  }
  $template = $playback['template_type'] === 'tegaki' ? ANIMEFILE_TEGAKI : ANIMEFILE;
  unset($playback['template_type']);
  $dat = array_merge($dat, $playback);

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
    $token = RequestSecurity::csrfToken();
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
  $userip = RequestInfo::clientIp();
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
  global $en;

  $no = trim((string)filter_input(INPUT_GET, 'no')); // 画像ファイル名なので文字列として取得
  if (!ImageService::isSafePostedImageFilename($no)) {
    error($en ? 'The image does not exist.' : '画像が存在しません。', 404);
    return;
  }

  $oya = [];
  try {
    $repository = new BoardRepository();
    $oya = $repository->findPostsByImage($no);
  } catch (Throwable $e) {
    error($en ? 'Failed to find the image.' : '画像の検索に失敗しました。', 500);
    return;
  }
  if (empty($oya) || !is_file(IMG_DIR . $no) || !is_readable(IMG_DIR . $no)) {
    error($en ? 'The image does not exist.' : '画像が存在しません。', 404);
    return;
  }

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
    $continue_posts = [];
    foreach ($oya as $bbsline) {
      $bbsline['com'] = nl2br(htmlentities($bbsline['com'], ENT_QUOTES | ENT_HTML5), false);
      $continue_posts[] = $bbsline;
    }
    $dat['oya'] = $continue_posts;
    $hist_ope = pathinfo($no, PATHINFO_FILENAME); //拡張子除去
    $hist_filename = IMG_DIR . $hist_ope;
    
    // データベースからctypeを取得
    $db_ctype = $continue_posts[0]['ctype'] ?? null;
    
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
  } catch (Throwable $e) {
    error($en ? 'Failed to prepare the continuation screen.' : '続きを描く画面の準備に失敗しました。', 500);
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
  $admin_delete = isset($_POST['admindel']);
  if ($admin_delete) {
    try {
      RequestSecurity::assertCurrentCsrfRequest($en);
    } catch (RequestSecurityException $e) {
      error($e->getMessage(), $e->getCode() ?: 403);
    }
    if (!AdminAuth::isAuthenticated($admin_pass, ADMIN_SESSION_LIFETIME)) {
      error($en ? 'Administrator login is required.' : '管理者ログインが必要です。', 403);
    }
    $p_pwd = $admin_pass;
  }

  try {
    $service = new PostService(new BoardRepository(), $admin_pass, IMG_DIR, PDEF_W, PERMISSION_FOR_DEST);
    $result = $service->delete((int)$delno, (string)$p_pwd, $admin_delete);
    $dat['message'] = $result === 'hidden'
      ? ($en ? 'Post hidden.' : '非表示にしました。')
      : ($en ? 'Successfully deleted.' : '削除しました。');
  } catch (PostNotFoundException $e) {
    error($en ? 'That post does not exist.' : 'そんな記事ない気がします。', 404);
    return;
  } catch (PostAuthorizationException $e) {
    error($en ? 'Invalid password or post number.' : 'パスワードまたは記事番号が違います。', 403);
    return;
  } catch (Throwable $e) {
    error(($en ? 'Deletion failed. ' : '削除に失敗しました。') . h($e->getMessage()), 500);
    return;
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
  $host = gethostbyaddr(RequestInfo::clientIp());

  foreach ($badip as $value) { //拒絶host
    if (preg_match("/$value$/i", $host)) error($en ? 'Your host is blocked.' : 'あなたのホストは拒絶されています。', 403);
  }

  $temporary_image = ImageService::findTemporaryImageByReplacementCode(TEMP_DIR, (string)$repcode);
  if ($temporary_image === null) {
    error($en ? 'No temporary file found.' : 'テンポラリファイルが見つかりませんでした。', 404);
  }
  $filename = $temporary_image['base_name'];
  $imgext = $temporary_image['image_extension'];
  $starttime = $temporary_image['start_time'];
  $postedtime = $temporary_image['posted_time'];

  $replacement = null;
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
      $host = gethostbyaddr(RequestInfo::clientIp());

      //id生成
      $id = gen_id($host, $utime ?? time());

      //nsfw
      if (USE_NSFW == 1 && $nsfw_flag == 1) {
        $nsfw = true;
      } else {
        $nsfw = false;
      }

      // 続き描きでは新しい画像から必ずサムネイルを作り直す。
      $thumbnail = ImageService::refreshNsfwThumbnail(
        IMG_DIR, $new_picfile, (string)($msg_d['thumbnail'] ?? ''), $nsfw,
        PDEF_W, PERMISSION_FOR_DEST, true, false
      );
      if ($thumbnail !== '') {
        $replacement['created_files'][] = rtrim(IMG_DIR, '/\\') . DIRECTORY_SEPARATOR . $thumbnail;
      }
      $old_thumbnail = basename((string)($msg_d['thumbnail'] ?? ''));
      if ($old_thumbnail !== '' && $old_thumbnail !== $thumbnail) {
        $old_thumbnail_path = rtrim(IMG_DIR, '/\\') . DIRECTORY_SEPARATOR . $old_thumbnail;
        if (is_file($old_thumbnail_path)) $replacement['old_files'][] = $old_thumbnail_path;
      }

      $repository->updateImage((int)$no, [
        'host' => $host, 'picfile' => $new_picfile, 'pchfile' => $new_pchfile, 'author_id' => $id,
        'psec' => $psec, 'utime' => $utime, 'nsfw' => $nsfw, 'thumbnail' => $thumbnail,
        'expected_picfile' => (string)$msg_d['picfile'],
      ]);
      ImageService::completePostedReplacement($replacement);
    } else {
      error($en ? 'Invalid password or post number.' : 'パスワードまたは記事番号が違います。', 403);
    }
  } catch (Throwable $e) {
    if (is_array($replacement)) ImageService::rollbackPostedReplacement($replacement);
    error(($en ? 'Image replacement failed. ' : '画像差し替えに失敗しました。') . h($e->getMessage()), 500);
  }
  editform((int)$no, (string)$pwd_f);
}

//編集モードくん入口
function editform(?int $authorized_post_id = null, ?string $authorized_password = null): void {
  global $admin_pass;
  global $blade, $dat;
  global $en;

  //csrfトークンをセット
  $dat['token'] = '';
  if (CHECK_CSRF_TOKEN) {
    $token = RequestSecurity::csrfToken();
    $_SESSION['token'] = $token;
    $dat['token'] = $token;
  }

  //入力されたパスワード
  $post_pwd = $authorized_password ?? filter_input(INPUT_POST, 'pwd');

  $edit_no = $authorized_post_id ?? filter_input(INPUT_POST, 'delno',FILTER_VALIDATE_INT);
  if ($edit_no == "") {
    error($en ? 'Please enter the post number.' : '記事番号を入力してください');
  }

  //記事呼び出し
  try {
    $service = new PostService(new BoardRepository(), $admin_pass, IMG_DIR);
    $authorization = $service->authorize((int)$edit_no, (string)$post_pwd);
    $msg = $authorization['post'];
    if ($authorization['role'] === 'owner') {
      $dat['message'] = $en ? 'Editing mode...' : '編集モード...';
    } else {
      $dat['message'] = $en ? 'Administrator editing mode...' : '管理者編集モード...';
    }
    $dat['oya'] = [$msg];

    $dat['othermode'] = 'edit'; //編集モード
    echo $blade->run(OTHERFILE, $dat);
  } catch (PostNotFoundException $e) {
    error($en ? 'That post does not exist.' : 'そんな記事ないです。', 404);
  } catch (PostAuthorizationException $e) {
    error($en ? 'Invalid password or post number.' : 'パスワードまたは記事番号が違います。', 403);
  } catch (Throwable $e) {
    error(($en ? 'Failed to open edit mode. ' : '編集画面を開けませんでした。') . h($e->getMessage()), 500);
  }
}

//編集モードくん本体
function editexec(): void {
  global $badip;
  global $admin_pass;
  global $req_method;
  global $dat;
  global $en;

  //CSRFトークンをチェック
  if (CHECK_CSRF_TOKEN) {
    try {
      RequestSecurity::assertCurrentCsrfRequest($en);
    } catch (RequestSecurityException $e) {
      error($e->getMessage(), $e->getCode() ?: 403);
    }
  }

  $input = PostValidator::inputFromHttp();
  $resedit = $input['resedit'];
  $e_no = $input['e_no'];
  $sub = $input['sub'];
  $name = $input['name'];
  $mail = $input['mail'];
  $url = $input['url'];
  $com = $input['com'];
  $picfile = (string)$input['picfile'];
  $pwd = $input['pwd'];
  $sodane = $input['sodane'];
  $edit_nsfw = USE_NSFW === 1 && $input['nsfw_flag'] === '1';

  //ホスト取得
  $host = gethostbyaddr(RequestInfo::clientIp());
  try {
    $rules = PostValidator::configuredRules($en, $req_method, $host, $badip, $admin_pass, true);
    PostValidator::validate($input, $rules);
  } catch (PostValidationException $e) {
    error($e->getMessage(), $e->getCode() ?: 400);
    return;
  }
  //↑セキュリティ関連ここまで

  try {
    $service = new PostService(new BoardRepository(), $admin_pass, IMG_DIR, PDEF_W, PERMISSION_FOR_DEST);
    $service->edit((int)$e_no, $pwd, [
      'name' => $name, 'mail' => $mail, 'sub' => $sub, 'com' => $com, 'url' => $url,
      'host' => $host, 'sodane' => $sodane, 'edit_nsfw' => $edit_nsfw,
    ]);
    $dat['message'] = $en ? 'Editing completed successfully.' : '編集完了しました。';
  } catch (PostNotFoundException $e) {
    error($en ? 'That post does not exist.' : 'そんな記事ないです。', 404);
    return;
  } catch (PostAuthorizationException $e) {
    error($en ? 'Invalid password or post number.' : 'パスワードまたは記事番号が違います。', 403);
    return;
  } catch (Throwable $e) {
    error(($en ? 'Editing failed. ' : '編集に失敗しました。') . h($e->getMessage()), 500);
    return;
  }
  unset($name, $mail, $sub, $com, $url, $pwd, $resto, $pictmp, $picfile, $mode);
  //header('Location:'.PHP_SELF);
  ok($en ? 'Successfully edited. Switching screen.' : '編集に成功しました。画面を切り替えます。');
}

//管理モードin
function admin_in(): void {
  global $admin_pass, $blade, $dat;
  admin_no_store();
  if (AdminAuth::isAuthenticated($admin_pass, ADMIN_SESSION_LIFETIME)) {
    redirect(PHP_SELF . '?mode=admin');
  }
  $dat['othermode'] = 'admin_in';
  $dat['token'] = RequestSecurity::csrfToken();

  echo $blade->run(OTHERFILE, $dat);
}

function admin_login(): void {
  global $admin_pass, $en;
  admin_no_store();
  try {
    RequestSecurity::assertCurrentCsrfRequest($en);
  } catch (RequestSecurityException $e) {
    error($e->getMessage(), $e->getCode() ?: 403);
  }
  $password = (string)filter_input_data('POST', 'adminpass');
  if (!AdminAuth::login($password, $admin_pass)) {
    error($en ? 'Administrator password is incorrect.' : '管理パスが違います。', 403);
  }
  redirect(PHP_SELF . '?mode=admin');
}

function admin_logout(): void {
  global $en;
  admin_no_store();
  try {
    RequestSecurity::assertCurrentCsrfRequest($en);
  } catch (RequestSecurityException $e) {
    error($e->getMessage(), $e->getCode() ?: 403);
  }
  AdminAuth::logout();
  redirect(PHP_SELF . '?mode=admin_in');
}

function admin_delete(): void {
  admin_manage('delete');
}

function admin_manage(?string $forced_operation = null): void {
  global $admin_pass, $en;
  admin_no_store();
  try {
    RequestSecurity::assertCurrentCsrfRequest($en);
  } catch (RequestSecurityException $e) {
    error($e->getMessage(), $e->getCode() ?: 403);
  }
  if (!AdminAuth::isAuthenticated($admin_pass, ADMIN_SESSION_LIFETIME)) {
    error($en ? 'Administrator login is required.' : '管理者ログインが必要です。', 403);
  }

  $selected = filter_input_data('POST', 'delno');
  if (!is_array($selected)) $selected = [];
  $operation = $forced_operation ?? (string)filter_input_data('POST', 'operation');
  if (!in_array($operation, ['hide', 'show', 'delete'], true)) {
    error($en ? 'Invalid administration operation.' : '管理操作が不正です。', 400);
  }
  try {
    $service = new PostService(
      new BoardRepository(), $admin_pass, IMG_DIR, PDEF_W, PERMISSION_FOR_DEST
    );
    if ($operation === 'delete') {
      $count = $service->deleteManyAsAdmin($selected);
      unset($_SESSION['admin_image_usage']);
      $_SESSION['admin_message'] = $en
        ? "{$count} selected post(s) were deleted."
        : "選択した{$count}件の記事を完全削除しました。";
    } else {
      $hidden = $operation === 'hide';
      $count = $service->setVisibilityManyAsAdmin($selected, $hidden);
      $_SESSION['admin_message'] = $en
        ? "{$count} selected post(s) were " . ($hidden ? 'hidden.' : 'made visible.')
        : "選択した{$count}件の記事を" . ($hidden ? '非表示にしました。' : '再表示しました。');
    }
  } catch (InvalidArgumentException $e) {
    error($en ? 'Please select at least one post.' : '操作する記事を選択してください。', 400);
  } catch (PostNotFoundException $e) {
    error($en ? 'The selected posts do not exist.' : '選択した記事が見つかりません。', 404);
  } catch (Throwable $e) {
    error($en ? 'Failed to update the selected posts.' : '選択した記事の更新に失敗しました。', 500);
  }
  redirect(PHP_SELF . '?mode=admin');
}

function admin_no_store(): void {
  if (!headers_sent()) {
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
  }
}

function admin_post_id(): int {
  global $en;

  $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
  if ($id === false || $id === null) {
    error($en ? 'Invalid post number.' : '記事番号が不正です。', 400);
  }
  return (int)$id;
}

function require_admin_session(): void {
  global $admin_pass;
  global $en;

  admin_no_store();
  if (!AdminAuth::isAuthenticated($admin_pass, ADMIN_SESSION_LIFETIME)) {
    error($en ? 'Administrator login is required.' : '管理者ログインが必要です。', 403);
  }
}

function admin_post(): void {
  global $blade, $dat;
  global $en;

  require_admin_session();
  $id = admin_post_id();

  try {
    $repository = new BoardRepository();
    $post = $repository->findPost($id);
    if (!$post) {
      error($en ? 'That post does not exist.' : 'そんな記事ないです。', 404);
    }

    $parent = false;
    $replies = [];
    if ((int)$post['thread'] === 1) {
      $replies = $repository->findRepliesForAdmin($id);
    } elseif ((int)$post['parent'] > 0) {
      $parent = $repository->findPost((int)$post['parent']);
    }

    $picfile = basename((string)$post['picfile']);
    $thumbnail = basename((string)$post['thumbnail']);
    $pchfile = basename((string)$post['pchfile']);
    $post['com_html'] = nl2br(h((string)$post['com']), false);
    $dat['admin_post'] = $post;
    $dat['admin_parent'] = $parent;
    $dat['admin_replies'] = $replies;
    $dat['admin_pic_url'] = $picfile !== '' && $picfile === (string)$post['picfile']
      && is_file(IMG_DIR . $picfile) ? IMG_DIR . $picfile : '';
    $dat['admin_thumbnail_url'] = $thumbnail !== '' && $thumbnail === (string)$post['thumbnail']
      && is_file(IMG_DIR . $thumbnail) ? IMG_DIR . $thumbnail : '';
    $dat['admin_pch_playback_url'] = $pchfile !== '' && $pchfile === (string)$post['pchfile']
      && is_file(IMG_DIR . $pchfile)
      ? PHP_SELF . '?mode=anime&pch=' . rawurlencode($pchfile)
      : '';
    $dat['token'] = RequestSecurity::csrfToken();
    echo $blade->run(ADMINPOSTFILE, $dat);
  } catch (Throwable $e) {
    error($en ? 'Failed to load the post details.' : '投稿詳細の読み込みに失敗しました。', 500);
  }
}

function admin_edit(): void {
  global $admin_pass;

  require_admin_session();
  editform(admin_post_id(), $admin_pass);
}

//管理モード
function admin(): void {
  global $admin_pass;
  global $blade, $dat;
  global $en;

  admin_no_store();
  if (!AdminAuth::isAuthenticated($admin_pass, ADMIN_SESSION_LIFETIME)) {
    error($en ? 'Administrator login is required.' : '管理者ログインが必要です。', 403);
  }
  $dat['path'] = IMG_DIR;
  $dat['token'] = RequestSecurity::csrfToken();
  $dat['message'] = isset($_SESSION['admin_message']) ? (string)$_SESSION['admin_message'] : '';
  unset($_SESSION['admin_message']);

  $filters = [];
  try {
    $filters = AdminPostFilter::normalize([
      'id' => filter_input_data('GET', 'id'),
      'q' => filter_input_data('GET', 'q'),
      'name' => filter_input_data('GET', 'name'),
      'host' => filter_input_data('GET', 'host'),
      'date_from' => filter_input_data('GET', 'date_from'),
      'date_to' => filter_input_data('GET', 'date_to'),
      'type' => filter_input_data('GET', 'type') ?: 'all',
      'image' => filter_input_data('GET', 'image') ?: 'all',
      'nsfw' => filter_input_data('GET', 'nsfw') ?: 'all',
      'visibility' => filter_input_data('GET', 'visibility') ?: 'all',
      'isAdministrator' => filter_input_data('GET', 'isAdministrator') ?: 'all',
    ]);
  } catch (InvalidArgumentException $e) {
    error($en ? 'Invalid administration search criteria.' : '管理画面の検索条件が不正です。', 400);
  }
  $dat['admin_filters'] = $filters;
  $filter_query = AdminPostFilter::query($filters);
  $dat['admin_filter_query'] = $filter_query === '' ? '' : '&' . $filter_query;
  $dat['admin_filter_active'] = AdminPostFilter::isActive($filters);

  $page_input = filter_input_data('GET', 'page');
  $page = 1;
  if ($page_input !== null && $page_input !== '') {
    $validated_page = filter_var($page_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($validated_page === false) {
      error($en ? 'Invalid administration page number.' : '管理画面のページ番号が不正です。', 400);
    }
    $page = (int)$validated_page;
  }
  $per_page = max(1, min(100, (int)ADMIN_THREADS_PER_PAGE));

  try {
    $repository = new BoardRepository();
    $dashboard_stats = $repository->adminDashboardStats();
    $cached_usage = $_SESSION['admin_image_usage'] ?? null;
    if (!is_array($cached_usage) || !isset($cached_usage['measured_at'], $cached_usage['files'], $cached_usage['bytes'])
      || (int)$cached_usage['measured_at'] < time() - 300) {
      $usage = ImageService::directoryUsage(IMG_DIR);
      $cached_usage = $usage + ['measured_at' => time()];
      $_SESSION['admin_image_usage'] = $cached_usage;
    }
    $dashboard_stats['image_files'] = max(0, (int)$cached_usage['files']);
    $dashboard_stats['image_bytes'] = max(0, (int)$cached_usage['bytes']);
    $dashboard_stats['image_size'] = ImageService::formatBytes($dashboard_stats['image_bytes']);
    $dat['admin_stats'] = $dashboard_stats;
    $total_posts = $repository->countAdminPosts($filters);
    $total_threads = $repository->countAdminThreads($filters);
    $total_pages = max(1, (int)ceil($total_threads / $per_page));
    if ($page > $total_pages) {
      error($en ? 'The administration page does not exist.' : '指定された管理画面のページはありません。', 404);
    }
    $offset = ($page - 1) * $per_page;

    $oya = array();
    foreach ($repository->listAdminThreads($offset, $per_page, $filters) as $bbsline) {
      if (empty($bbsline)) break;
      $bbsline['_admin_matched'] = AdminPostFilter::matches($bbsline, $filters);
      $bbsline['com'] = htmlentities($bbsline['com'], ENT_QUOTES | ENT_HTML5);
      $oya[] = $bbsline;
    }
    $dat['oya'] = $oya;

    $ko = array();
    $parent_ids = array_column($oya, 'tid');
    foreach ($repository->listAdminReplies($parent_ids) as $res) {
      $res['_admin_matched'] = AdminPostFilter::matches($res, $filters);
      $res['com'] = htmlentities($res['com'], ENT_QUOTES | ENT_HTML5);
      $ko[(int)$res['parent']][] = $res;
    }
    $dat['ko'] = $ko;
    $dat['admin_page'] = $page;
    $dat['admin_total_pages'] = $total_pages;
    $dat['admin_total_posts'] = $total_posts;
    $dat['admin_total_threads'] = $total_threads;
    $dat['admin_range_start'] = $total_threads === 0 ? 0 : $offset + 1;
    $dat['admin_range_end'] = $offset + count($oya);
    $dat['admin_page_posts'] = count($oya) + array_sum(array_map('count', $ko));
    echo $blade->run(ADMINFILE, $dat);
  } catch (Throwable $e) {
    error($en ? 'Failed to load the administration screen.' : '管理画面の読み込みに失敗しました。', 500);
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
    error($en ? "The specified post could not be found or the password is incorrect." : "該当記事が見つからないかパスワードが間違っています", 403);
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
function error(string $mes, int $status = 400): void {
  global $db;
  global $blade, $dat;
  if ($status < 400 || $status > 599) $status = 500;
  http_response_code($status);
  $db = null; //db切断
  $dat['errmes'] = $mes;
  $dat['othermode'] = 'err';
  $async_flag = (bool)filter_input(INPUT_POST,'asyncflag',FILTER_VALIDATE_BOOLEAN);
  $http_x_requested_with = (bool)(isset($_SERVER['HTTP_X_REQUESTED_WITH']));
  if($http_x_requested_with || $async_flag){
    die("error\n$mes");
  }
  if (!isset($blade)) die($mes);
  echo $blade->run(OTHERFILE, $dat);
  exit;
}

//画像差し替え失敗
function error2(): void {
  global $db;
  global $blade, $dat;
  global $self;
  global $en;
  http_response_code(500);

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
