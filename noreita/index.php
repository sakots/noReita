<?php
//--------------------------------------------------
//  おえかきけいじばん「noReita」
//  by sakots & OekakiBBS reDev.Team  https://oekakibbs.moe/
//--------------------------------------------------

// スクリプトのバージョン
const REITA_VER = 'v4.2.0 lot.260818.1';

// 全エントリーポイント共通の設定・エラー処理を初期化する。
require_once __DIR__ . '/bootstrap.php';
try {
  ApplicationBootstrap::boot(__DIR__);
} catch (ConfigException $e) {
  http_response_code(500);
  die('Configuration error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
$en = ApplicationBootstrap::english();

// functions.phpのバージョンを確認
if(!defined('FUNCTIONS_VER') || FUNCTIONS_VER < 20260807) {
  die($en ? 'Please update functions.php to the latest version.' : 'functions.phpを最新版に更新してください。');
}

// 公開画面へのPHPエラー詳細表示を止め、非公開ログへ記録する。
if (!defined('ERROR_HANDLER_INC_VER') || ERROR_HANDLER_INC_VER < 20260817) {
  die($en ? 'Please update the error handler.' : 'エラーハンドラーを最新版に更新してください。');
}

// request_security.inc
check_file(__DIR__.'/request_security.inc.php');
require_once(__DIR__.'/request_security.inc.php');
if(!defined('REQUEST_SECURITY_INC_VER') || REQUEST_SECURITY_INC_VER < 20260726) {
  die($en ? 'Please update request_security.inc.php to the latest version.' : 'request_security.inc.phpを最新版に更新してください。');
}

// request_info.inc
check_file(__DIR__.'/request_info.inc.php');
require_once(__DIR__.'/request_info.inc.php');
if(!defined('REQUEST_INFO_INC_VER') || REQUEST_INFO_INC_VER < 20260816) {
  die($en ? 'Please update request_info.inc.php to the latest version.' : 'request_info.inc.phpを最新版に更新してください。');
}

// database.inc
check_file(__DIR__.'/database.inc.php');
require_once(__DIR__.'/database.inc.php');
if(!defined('DATABASE_INC_VER') || DATABASE_INC_VER < 20260817) {
  die($en ? 'Please update database.inc.php to the latest version.' : 'database.inc.phpを最新版に更新してください。');
}

// initialization.inc
check_file(__DIR__.'/initialization.inc.php');
require_once(__DIR__.'/initialization.inc.php');
if(!defined('INITIALIZATION_INC_VER') || INITIALIZATION_INC_VER < 20260817) {
  die($en ? 'Please update initialization.inc.php to the latest version.' : 'initialization.inc.phpを最新版に更新してください。');
}

// image.inc
check_file(__DIR__.'/image.inc.php');
require_once(__DIR__.'/image.inc.php');
if(!defined('IMAGE_INC_VER') || IMAGE_INC_VER < 20260818) {
  die($en ? 'Please update image.inc.php to the latest version.' : 'image.inc.phpを最新版に更新してください。');
}

// post.inc
check_file(__DIR__.'/post.inc.php');
require_once(__DIR__.'/post.inc.php');
if(!defined('POST_INC_VER') || POST_INC_VER < 20260807) {
  die($en ? 'Please update post.inc.php to the latest version.' : 'post.inc.phpを最新版に更新してください。');
}

// share.inc
check_file(__DIR__.'/share.inc.php');
require_once(__DIR__.'/share.inc.php');
if(!defined('SHARE_INC_VER') || SHARE_INC_VER < 20260725) {
  die($en ? 'Please update share.inc.php to the latest version.' : 'share.inc.phpを最新版に更新してください。');
}

// misskey_security.inc
check_file(__DIR__.'/misskey_security.inc.php');
require_once(__DIR__.'/misskey_security.inc.php');
if(!defined('MISSKEY_SECURITY_VER') || MISSKEY_SECURITY_VER < 20260816) {
  die($en ? 'Please update misskey_security.inc.php to the latest version.' : 'misskey_security.inc.phpを最新版に更新してください。');
}

// misskey_note.inc
check_file(__DIR__.'/misskey_note.inc.php');
require_once(__DIR__.'/misskey_note.inc.php');
if(!defined('MISSKEY_NOTE_VER') || MISSKEY_NOTE_VER < 20260817) {
  die($en ? 'Please update misskey_note.inc.php to the latest version.' : 'misskey_note.inc.phpを最新版に更新してください。');
}

// connect_misskey_api.php
check_file(__DIR__.'/connect_misskey_api.php');
require_once(__DIR__.'/connect_misskey_api.php');
if(!defined('CONNECT_MISSKEY_API_VER') || CONNECT_MISSKEY_API_VER < 20260817) {
  die($en ? 'Please update connect_misskey_api.php to the latest version.' : 'connect_misskey_api.phpを最新版に更新してください。');
}

// save.inc
check_file(__DIR__.'/save.inc.php');
require_once(__DIR__.'/save.inc.php');
if(!defined('SAVE_INC_VER') || SAVE_INC_VER < 20260817) {
  die($en ? 'Please update save.inc.php to the latest version.' : 'save.inc.phpを最新版に更新してください。');
}

// thumbnail.inc
check_file(__DIR__.'/thumbnail.inc.php');
require_once(__DIR__.'/thumbnail.inc.php');
if(!defined('THUMBNAIL_VER') || THUMBNAIL_VER < 20260820) {
  error($en ? 'Please update thumbnail.inc.php to the latest version.' : 'thumbnail.inc.phpを最新版に更新してください。', 500);
}

// external_image.inc
check_file(__DIR__.'/external_image.inc.php');
require_once(__DIR__.'/external_image.inc.php');
if(!defined('EXTERNAL_IMAGE_INC_VER') || EXTERNAL_IMAGE_INC_VER < 20260820) {
  error($en ? 'Please update external_image.inc.php to the latest version.' : 'external_image.inc.phpを最新版に更新してください。', 500);
}

// テーマ
require_once __DIR__ . '/theme_manifest.inc.php';
$theme_runtime = null;
try {
  $theme_runtime = ThemeRuntime::load(__DIR__ . '/theme', Config::string('paths.theme'));
} catch (Throwable $e) {
  error($en ? 'Theme configuration failed.' : 'テーマ設定の読み込みに失敗しました。', 500, $e);
}
if (!is_array($theme_runtime)) exit;
$theme_directory = $theme_runtime['active_directory'];

// タイムゾーン設定
date_default_timezone_set(Config::string('site.timezone'));


// 管理パスが初期値(admin_pass)の場合は動作させない
if (Config::string("admin.password") === 'admin_pass') {
  die($en ? "The admin pass is still at its default value! This program can't run it until you fix it." : "管理パスが初期設定値のままです！危険なので動かせません。管理パスを変更してください。");
}

// Composer dependencies (BladeOne / Twig)
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
  die($en
    ? 'Composer dependencies are missing. Run composer install in the noReita directory.'
    : 'Composer依存ライブラリがありません。noReitaディレクトリでcomposer installを実行してください。');
}
require_once $autoload;
require_once __DIR__ . '/template_engine.inc.php';

$views = $theme_runtime['view_directories']; // 子テーマから親テーマの順にテンプレートを探索
$cache = __DIR__ . '/cache'; // キャッシュフォルダ

// テンプレートキャッシュに必要な場所だけを書き込み可能にする。
if (!is_dir($cache) && !@mkdir($cache, Config::int('permissions.private_directory'), true) && !is_dir($cache)) {
  die($en ? 'Failed to create the template cache directory.' : 'テンプレートキャッシュディレクトリを作成できません。');
}
if (!is_readable($cache) || !is_writable($cache)) {
  die($en ? 'The template cache directory is not readable and writable.'
    : 'テンプレートキャッシュディレクトリを読み書きできません。');
}

$theme_template_engine = $theme_runtime['engine'];
if (!is_string($theme_template_engine) || !in_array($theme_template_engine, ['blade', 'twig'], true)) {
  die($en ? 'The theme template engine must be blade or twig.' : 'テーマのテンプレートエンジンはbladeまたはtwigを指定してください。');
}
$template_engine = TemplateEngineFactory::create($theme_template_engine, $views, $cache);

$dat = array(); // テンプレートに格納する変数

// var_dump($_POST);

// 絶対パス取得
$path = realpath("./") . '/' . Config::string('paths.images');
$temp_path = realpath("./") . '/' . Config::string('paths.temporary');

$message = "";
$self = Config::string('site.script_name');

$dat['path'] = Config::string('paths.images');

$dat['neo_dir'] = Config::string('paths.neo');
$dat['chicken_dir'] = Config::string('paths.chickenpaint');
$dat['klecks_dir'] = Config::string('paths.klecks');
$dat['tegaki_dir'] = Config::string('paths.tegaki');
$dat['axnos_dir'] = Config::string('paths.axnos');

$dat['ver'] = REITA_VER;
$dat['base'] = Config::string('site.base_url');
$dat['board_title'] = Config::string('site.title');
$dat['home'] = Config::string('site.home_url');
$dat['self'] = Config::string('site.script_name');
$dat['message'] = $message;
$dat['pdef_w'] = Config::int('limits.paint_default_width');
$dat['pdef_h'] = Config::int('limits.paint_default_height');
$dat['pmax_w'] = Config::int('limits.paint_max_width');
$dat['pmax_h'] = Config::int('limits.paint_max_height');

$dat['max_name'] = Config::int('limits.name_length');
$dat['max_email'] = Config::int('limits.email_length');
$dat['max_sub'] = Config::int('limits.subject_length');
$dat['max_url'] = Config::int('limits.url_length');
$dat['max_com'] = Config::int('limits.comment_length');

$dat['theme_dir'] = $theme_runtime['base_id'];
$dat['theme_active_dir'] = $theme_runtime['id'];
$dat['theme_name'] = $theme_runtime['name'];
$dat['tver'] = $theme_runtime['simple']
  ? (string)$theme_runtime['base_metadata']['version'] . '-' . $theme_runtime['version']
  : $theme_runtime['version'];
$dat['theme_custom_stylesheets'] = array_map(
  static fn(array $theme): string => 'theme/' . rawurlencode($theme['id']) . '/theme.css?v=' . rawurlencode($theme['version']),
  $theme_runtime['stylesheet_themes']
);

$dat['switch_sns'] = Config::bool('features.share_details');

$dat['use_chicken'] = Config::bool('features.chickenpaint');
$dat['use_klecks'] = Config::bool('features.klecks');
$dat['use_tegaki'] = Config::bool('features.tegaki');
$dat['use_axnos'] = Config::bool('features.axnos');

$dat['select_palettes'] = Config::bool('features.select_palettes');
$dat['pallets_dat'] = Config::array("drawing.palettes");

$dat['display_id'] = Config::bool('features.display_id');
$dat['updatemark'] = UPDATE_MARK;
$dat['use_resub'] = Config::bool('features.reply_subject');

$dat['useanime'] = Config::bool('features.animation');
$dat['defanime'] = Config::bool('features.animation_default');
$dat['use_continue'] = Config::bool('features.continue_drawing');
$dat['newpost_nopassword'] = !Config::bool('features.continue_password');

$dat['use_name'] = Config::bool('features.require_name');
$dat['use_com'] = Config::bool('features.require_comment');
$dat['use_sub'] = Config::bool('features.require_subject');

$dat['addinfo'] = Config::array("board.additional_info");

$dat['display_painttime'] = Config::bool('features.display_paint_time');
$dat['search_criteria'] = [
  'query' => '', 'target' => 'author', 'match' => 'partial', 'post_type' => 'all',
  'image' => 'any', 'nsfw' => 'any', 'sort' => 'newest',
];

$dat['share_button'] = Config::bool('features.share_button');

$dat['use_hashtag'] = Config::bool('features.hashtag');

$dat['sodane'] = SODANE;

$dat['use_oekaki_reply'] = Config::bool('features.oekaki_reply');
$dat['use_image_upload'] = Config::bool('features.image_upload');
$dat['use_animation_upload'] = Config::bool('features.image_upload') && Config::bool('features.animation');
$dat['animation_upload_accept'] = Config::bool('features.tegaki') ? '.pch,.tgkr' : '.pch';
$dat['animation_upload_format_label'] = Config::bool('features.tegaki') ? 'PCH / TGKR' : 'PCH';
$dat['animation_upload_max_kb'] = Config::int('limits.paint_work_kb');
$dat['animation_upload_max_bytes'] = Config::int('limits.paint_work_kb') * 1024;
$animation_upload_modified = @filemtime(__DIR__ . '/animation-upload.js');
$dat['animation_upload_version'] = $animation_upload_modified === false
  ? REITA_VER
  : (string)$animation_upload_modified;
$dat['diary_mode'] = Config::bool('features.diary_mode');
$dat['can_create_thread'] = diary_post_allowed(false);
$dat['can_post_reply'] = diary_post_allowed(true);
$dat['upload_max_kb'] = Config::int('limits.upload_kb');
$dat['upload_max_width'] = Config::int('limits.image_width');
$dat['upload_max_height'] = Config::int('limits.image_height');
$dat['upload_accept'] = ImageService::uploadAccept();
$dat['upload_format_label'] = ImageService::uploadFormatLabel();

$dat['theme_name'] = $theme_runtime['name'];

//ペイント画面の$pwdの暗号化
const CRYPT_METHOD = 'aes-128-cbc';
define('CRYPT_IV', 'T3pkYxNyjN7Wz3pu'); //半角英数16文字

//テーマがXHTMLか設定されてないなら
defined('TH_XHTML') or define('TH_XHTML', 0);

$dat['use_nsfw'] = Config::bool('features.nsfw');

//データベース接続PDO
define('DB_FILE', __DIR__ . '/' . Config::string('database.name') . '.db');
define('DB_PDO', 'sqlite:' . DB_FILE);

//misskey
$dat['use_misskey_note'] = Config::bool('features.misskey_note');

//初期設定
init();

$dat['theme_settings'] = [];
$dat['theme_settings_json'] = '{}';
try {
  $theme_settings = theme_settings_provider();
  if ($theme_settings !== null) {
    $template_data = $theme_settings->templateData();
    if (!is_array($template_data)) throw new RuntimeException('Theme settings template data is invalid.');
    $dat = array_merge($dat, $template_data);
  }
} catch (Throwable $e) {
  error($en ? 'Theme settings initialization failed.' : 'テーマ設定の初期化に失敗しました。', 500, $e);
}

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
$dat['admin_authenticated'] = AdminAuth::isAuthenticated(
  Config::string('admin.password'), Config::int('admin.session_lifetime')
);
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
    if (!diary_post_allowed((string)filter_input(INPUT_POST, 'resto') !== '')) {
      error($en ? 'Only an administrator can create this post.' : 'この投稿は管理者のみ作成できます。', 403);
    }
    return paint_form("", filter_input_data('POST','modid',FILTER_VALIDATE_INT));
  case 'piccom':
    return paint_com("");
  case 'pictmp':
    if (!diary_post_allowed(false)) {
      error($en ? 'Only an administrator can create a new post.' : '新規投稿は管理者のみ作成できます。', 403);
    }
    return paint_com("tmp");
  case 'anime':
    return open_pch();
  case 'continue':
    return in_continue();
  case 'contpaint':
    $type = filter_input(INPUT_POST, 'type');
    if (Config::bool('features.continue_password') || $type === 'rep') usrchk();
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
  case 'animation_upload': // 動画ファイルをPNGと組にして一時保存
    return animation_upload();
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
  case 'admin_theme_settings':
    return admin_theme_settings();
  case 'admin_errorlog':
    return admin_errorlog();
  case 'admin_auditlog':
    return admin_auditlog();
  case 'admin_temporary_images':
    return admin_temporary_images();
  case 'admin_temporary_images_manage':
    return admin_temporary_images_manage();
  case 'temporary_image':
    return temporary_image();
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
      __DIR__ . '/' . Config::string('paths.images') => Config::int('permissions.public_directory'),
      __DIR__ . '/' . Config::string('paths.temporary') => Config::int('permissions.public_directory'),
      __DIR__ . '/' . Config::string('paths.thumbnails') => Config::int('permissions.public_directory'),
      __DIR__ . '/thumbnail' => Config::int('permissions.public_directory'),
      __DIR__ . '/session' => Config::int('permissions.private_directory'),
      __DIR__ . '/cache' => Config::int('permissions.private_directory'),
      __DIR__ . '/backup' => Config::int('permissions.private_directory'),
      __DIR__ . '/errorlog' => Config::int('permissions.private_directory'),
      __DIR__ . '/auditlog' => Config::int('permissions.private_directory'),
    ],
    0600,
    __DIR__ . '/' . Config::string('paths.temporary'),
  );
  $initializer->sendSecurityHeaders();
  try {
    $initializer->prepareDirectories();
    $initializer->migrateDatabase();
    $initializer->secureDatabaseFile();
    $recovery = (new PostService(
      new BoardRepository(), __DIR__ . '/' . Config::string('paths.images'), Config::int('limits.paint_default_width'), Config::int('permissions.public_file'),
      __DIR__ . '/backup/delete-staging', __DIR__ . '/backup/delete-quarantine',
      Config::int('maintenance.delete_quarantine_days')
    ))->recoverInterruptedDeletions();
    if (array_sum($recovery) > 0) {
      ApplicationErrorHandler::reportMessage(
        'Deletion recovery result: ' . json_encode($recovery, JSON_UNESCAPED_SLASHES),
        'deletion-recovery'
      );
    }
  } catch (Throwable $e) {
    error($en ? 'Application initialization failed.' : 'アプリケーションの初期化に失敗しました。', 500, $e);
    return;
  }
}

function show_share_server_form(): void {
  global $template_engine, $dat;

  $dat['servers'] = ShareService::servers(Config::array('social.servers'));
  $dat['encoded_t'] = (string)filter_input_data('GET', 'encoded_t');
  $dat['encoded_u'] = (string)filter_input_data('GET', 'encoded_u');
  $dat['sns_server_radio_cookie'] = (string)filter_input_data('COOKIE', 'sns_server_radio_cookie');
  $dat['sns_server_direct_input_cookie'] = (string)filter_input_data('COOKIE', 'sns_server_direct_input_cookie');
  $dat['admin_pass'] = null;
  $dat['token'] = RequestSecurity::csrfToken();
  echo $template_engine->render(SET_SHARE_SERVER, $dat);
}

function submit_share_server(): void {
  global $en;

  if (Config::bool('features.csrf')) {
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
  global $en;
  global $req_method;
  global $dat;

  $dat['en'] = $en;

  // CSRFトークンをチェック
  if (Config::bool('features.csrf')) {
    try {
      RequestSecurity::assertCurrentCsrfRequest($en);
    } catch (RequestSecurityException $e) {
      error($e->getMessage(), $e->getCode() ?: 403);
    }
  }

  $input = PostValidator::inputFromHttp();
  if (!diary_post_allowed($input['resto'] !== '')) {
    error($en ? 'Only an administrator can create this post.' : 'この投稿は管理者のみ作成できます。', 403);
    return;
  }
  $sub = $input['sub'];
  $name = $input['name'];
  $mail = $input['mail'];
  $url = $input['url'];
  $com = $input['com'];
  $picfile = $input['picfile'];
  $pwd = $input['pwd'];
  $pal = $input['pal'];
  $nsfw_flag = $input['nsfw_flag'];
  $uploaded_file = $_FILES['image_upload'] ?? null;
  $has_uploaded_file = $uploaded_file !== null
    && (!is_array($uploaded_file) || ($uploaded_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
  $raw_animation_file = $_FILES['animation_upload'] ?? null;
  $has_unconverted_animation = $raw_animation_file !== null
    && (!is_array($raw_animation_file)
      || ($raw_animation_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
  if ($has_unconverted_animation) {
    error(
      $en
        ? 'The animation could not be checked. Enable JavaScript, reload the page, and try again.'
        : '動画を確認できませんでした。JavaScriptを有効にしてページを再読み込みし、もう一度お試しください。',
      422
    );
    return;
  }
  $pending_picfile = $_SESSION['pending_picfile'] ?? '';
  if (is_string($pending_picfile) && $pending_picfile !== '') {
    $pending_metadata = ImageService::parseTemporaryMetadata(
      rtrim(Config::string('paths.temporary'), '/\\') . DIRECTORY_SEPARATOR
      . pathinfo($pending_picfile, PATHINFO_FILENAME) . '.dat'
    );
    $pending_image_exists = ImageService::isSafePostedImageFilename($pending_picfile)
      && is_file(Config::string('paths.temporary') . $pending_picfile)
      && is_array($pending_metadata)
      && hash_equals((string)$pending_metadata['filename'], $pending_picfile);
    if (!$pending_image_exists) {
      unset($_SESSION['pending_picfile']);
      ApplicationErrorHandler::reportMessage('Discarded an unavailable pending drawing image.', 'pending-image-reset');
    } else {
      if (!hash_equals($pending_picfile, (string)$picfile)) {
        ApplicationErrorHandler::reportMessage('Normalized a pending drawing image selection.', 'pending-image-selection');
      }
      // 画面の古いキャッシュやPOST値の改変に影響されず、直前に描いた画像を投稿する。
      $input['picfile'] = $pending_picfile;
      $picfile = $pending_picfile;
    }
  }

  // クッキー保存用
  $original_name = $name;

  //ホスト取得
  $host = gethostbyaddr(RequestInfo::clientIp());
  try {
    $is_administrator = AdminAuth::isAuthenticated(
      Config::string('admin.password'), Config::int('admin.session_lifetime')
    );
    $rules = PostValidator::configuredRules(
      $en, $req_method, $host, Config::array("spam.bad_hosts"), $is_administrator,
      (bool)Config::bool('features.require_comment')
    );
    PostValidator::validate($input, $rules);
  } catch (PostValidationException $e) {
    error($e->getMessage(), $e->getCode() ?: 400);
    return;
  }
  if ($has_uploaded_file && !Config::bool('features.image_upload')) {
    error($en ? 'Image uploads are disabled.' : '画像アップロードは無効です。', 403);
    return;
  }
  if ($has_uploaded_file && $picfile) {
    error($en ? 'Choose either a drawing image or an uploaded image.' : 'お絵かき画像とアップロード画像を同時に投稿することはできません。', 400);
    return;
  }
  //セキュリティ関連ここまで

  $uploaded_image = null;
  try {
    $repository = new BoardRepository();
    if (isset($_POST["send"])) {
      $service = new PostService($repository, Config::string('paths.images'));
      if ($has_uploaded_file) {
        if (!is_array($uploaded_file)) {
          throw new ImageUploadException('Invalid uploaded file.', 400);
        }
        $uploaded_image = ImageService::storeUploadedImage(
          $uploaded_file, Config::string('paths.images'), Config::int('limits.upload_kb'),
          Config::int('limits.image_width'), Config::int('limits.image_height'),
          Config::int('limits.paint_default_width'), Config::bool('features.nsfw') && $nsfw_flag === '1',
          Config::int('permissions.public_file')
        );
        $input['picfile'] = $uploaded_image['picfile'];
        $picfile = $uploaded_image['picfile'];
      }
      try {
        $prepared_post = $service->prepareNewPost($input, $host, [
          'default_name' => Config::string('board.default_name'), 'default_comment' => Config::string('board.default_comment'), 'default_subject' => Config::string('board.default_subject'),
          'admin_name' => Config::string("admin.name"), 'admin_cap' => Config::string('admin.cap'),
          'is_admin' => $is_administrator,
        ]);
      } catch (DuplicatePostException $e) {
        if (is_array($uploaded_image)) ImageService::deleteRelatedFiles(Config::string('paths.images'), $uploaded_image['picfile']);
        error($en ? 'Duplicate post?' : '二重投稿ですか ?', 409);
        return;
      }

      $image_result = [
        'img_w' => 0, 'img_h' => 0, 'pchfile' => '', 'psec' => 0, 'utime' => '',
        'tool' => '', 'thumbnail' => '', 'nsfw' => false, 'ctype' => null,
      ];
      if (is_array($uploaded_image)) {
        $image_result = $uploaded_image;
      } elseif ($picfile) {
        $ctype = PostInput::ctypeFromHttp();
        $image_result = ImageService::finalizeNewPost(
          Config::string('paths.temporary'), Config::string('paths.images'), (string)$picfile, $ctype, (bool)Config::bool('features.display_paint_time'), Config::int('limits.paint_default_width'),
          Config::bool('features.nsfw') && $nsfw_flag === '1', Config::int('permissions.public_file')
        );
        $image_result['ctype'] = $ctype;
      }
      $service->createPreparedPost($prepared_post, $image_result);
      unset($_SESSION['pending_picfile']);

      $c_pass = $pwd;
      //-- クッキー保存 --
      //クッキー項目："クッキー名 クッキー値"
      $https_only = (bool)($_SERVER['HTTPS'] ?? '');

      $cookies = [["name_c",$original_name],["email_c",$mail] , ["url_c", $url], ["pwd_cookie", $c_pass] ,[ "palette_c" , $pal]];
      foreach ($cookies as $cookie) {
        list($c_name, $c_cookie) = $cookie;
        $c_name = (string)$c_name;
        $c_cookie = (string)$c_cookie;
        setcookie($c_name, $c_cookie, time() + (Config::int('board.cookie_days') * 24 * 3600),"","",$https_only,true);
      }

      $dat['message'] = ($en ? 'Successfully posted.' : '書き込みに成功しました。');
    }
  } catch (ImageUploadException $e) {
    if (is_array($uploaded_image)) ImageService::deleteRelatedFiles(Config::string('paths.images'), $uploaded_image['picfile']);
    $upload_error = $en
      ? $e->getMessage()
      : ($e->getCode() === 415
        ? 'このサーバーでは、この画像形式を処理できません。'
        : '画像ファイルを受け付けられませんでした。');
    error($upload_error, $e->getCode() ?: 400, $e);
  } catch (Throwable $e) {
    if (is_array($uploaded_image)) ImageService::deleteRelatedFiles(Config::string('paths.images'), $uploaded_image['picfile']);
    error($en ? 'Posting failed.' : '投稿処理に失敗しました。', 500, $e);
  }
  unset($name, $mail, $sub, $com, $url, $pwd, $pictmp, $picfile, $mode);
  //header('Location:'.Config::string('site.script_name'));
  //ログ行数オーバー処理
  //スレ数カウント
  $th_cnt = 0;
  try {
    $repository = new BoardRepository();
    $th_cnt = $repository->countThreads();
  } catch (PDOException $e) {
    error($en ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
    return;
  }
  if ($th_cnt > Config::int('board.max_threads')) {
    logdel();
  }

  //そろそろ消えるスレッドのフラグを設定
  $th_id = (int)round(Config::int('board.max_threads') * Config::int('board.log_warning_percent') / 100); //閾値 … 新しい方からこの件数以降がもうすぐ消える
  if ($th_cnt > $th_id) {
    // そろそろ消えるスレッドにshdフラグを設定
    try {
      (new BoardRepository())->markOldThreads($th_cnt - $th_id);
    } catch (PDOException $e) {
      error($en ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
    }
  }

  // そろそろ消えるスレッドの情報をテンプレートに渡す
  $dat['log_limit'] = Config::int('board.log_warning_percent');
  $dat['th_cnt'] = $th_cnt;
  $dat['th_id'] = $th_id;
  $dat['will_delete_count'] = max(0, $th_cnt - $th_id);

  ok($en ? 'Successfully posted. Switching screen.' : '書き込みに成功しました。画面を切り替えます。');
}

//通常表示モード
function def(): void {
  global $dat, $template_engine;
  global $en;
  $dsp_res = Config::int('board.replies_shown');
  $page_def = Config::int('board.page_size');

  $start = 0;

  //ログ行数オーバー処理
  //スレ数カウント
  $th_cnt = 0;
  try {
    $repository = new BoardRepository();
    $th_cnt = $repository->countThreads();
  } catch (PDOException $e) {
    error($en ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
    return;
  }
  if ($th_cnt > Config::int('board.max_threads')) {
    logdel();
  }

  //古いスレのレスボタンを表示しない
  $elapsed_time = Config::int('board.elapsed_reply_days') * 86400; //デフォルトの1年だと31536000
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
    error($en ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
  }

  //読み込み
  try {
    $posts = $repository->listThreads($start, $page_def);

    $i = 0;
    $j = 0;
    while ($i < Config::int('board.page_size')) {
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
          $bbsline['res_d_su'] = $j - Config::int('board.replies_shown'); //スレのレス省略数
          if ($j > Config::int('board.replies_shown')) { //スレのレス数が規定より多いと
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
        if (Config::bool('features.autolink')) $res['com'] = auto_link($res['com']);
        //画像URLにサムネイルを追加
        if (Config::bool('features.external_image_thumbnail')) {
          $res['com'] = external_image_service()->addThumbnailLinks($res['com']);
        }
        //ハッシュタグ
        if (Config::bool('features.hashtag')) $res['com'] = hashtag_link($res['com']);
        //空行を縮める
        $res['com'] = preg_replace('/(\n|\r|\r\n|\n\r){3,}/us', "\n\n", $res['com']);
        //<br>に
        $res['com'] = tobr($res['com']);
        //引用の色
        $res['com'] = quote($res['com']);
        //日付をUNIX時間に変換して設定どおりにフォーマット
        $res['created'] = date(Config::string('board.date_format'), strtotime($res['created']));
        $res['modified'] = date(Config::string('board.date_format'), strtotime($res['modified']));
        $bbsline['res'][$j] = $res;
        $j++;
      }
      // http、https以外のURLの場合表示しない
      if (!filter_var($bbsline['a_url'], FILTER_VALIDATE_URL) || !preg_match('|^https?://.*$|', $bbsline['a_url'])) {
        $bbsline['a_url'] = "";
      }
      $bbsline['com'] = htmlspecialchars($bbsline['com'], ENT_QUOTES | ENT_HTML5);

      //オートリンク
      if (Config::bool('features.autolink')) $bbsline['com'] = auto_link($bbsline['com']);
      //画像URLにサムネイルを追加
      if (Config::bool('features.external_image_thumbnail')) {
        $bbsline['com'] = external_image_service()->addThumbnailLinks($bbsline['com']);
      }
      //ハッシュタグ
      if (Config::bool('features.hashtag')) $bbsline['com'] = hashtag_link($bbsline['com']);
      //空行を縮める
      $bbsline['com'] = preg_replace('/(\n|\r|\r\n){3,}/us', "\n\n", $bbsline['com']);
      //<br>に
      $bbsline['com'] = tobr($bbsline['com']);
      //引用の色
      $bbsline['com'] = quote($bbsline['com']);
      $bbsline['past'] = strtotime($bbsline['created']);
      $bbsline['created'] = date(Config::string('board.date_format'), strtotime($bbsline['created']));
      $bbsline['modified'] = date(Config::string('board.date_format'), strtotime($bbsline['modified']));

      $bbsline['encoded_t'] = urlencode('['.$bbsline['tid'].']'.$bbsline['sub'].($bbsline['a_name'] ? ' by '.$bbsline['a_name'] : '').' - '.Config::string('site.title'));
      $bbsline['encoded_u'] = urlencode(Config::string('site.base_url').'?resno='.$bbsline['tid']);

      // そろそろ消えるスレッドのフラグを設定
      $bbsline['will_delete'] = ($bbsline['shd'] === '1');

      $dat['oya'][$i] = $bbsline;
      $i++;
    }

    $dat['dsp_res'] = Config::int('board.replies_shown');
    $dat['path'] = Config::string('paths.images');

    echo $template_engine->render(MAINFILE, $dat);
  } catch (PDOException $e) {
    error($en ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
  }
}

//カタログモード
function catalog(): void {
  global $template_engine, $dat;
  $page_def = Config::int('board.catalog_size');

  $start = 0;

  //ページング
  try {
    $repository = new BoardRepository();
    $th_cnt = $repository->countVisibleImages();
    $page_value = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $pagination = catalog_paging($th_cnt, $page_def, $page_value === false || $page_value === null ? 1 : $page_value);
    $start = $pagination['start'];
    $dat['max_page'] = $pagination['max_page'];
    $dat['nowpage'] = $pagination['page'];
    $dat['paging'] = $pagination['paging'];
    $dat['pp'] = $pagination['pp'];
    $dat['back'] = $pagination['back'];
    $dat['next'] = $pagination['next'];
    $dat['catalog_paging_query'] = 'mode=catalog';
    $dat['catalog_paging_enabled'] = true;

  } catch (PDOException $e) {
    error(Config::string('site.language') === 'English' ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
    return;
  }
  //読み込み

  try {
    $posts = $repository->listCatalog($start, $page_def);

    $oya = array();

    $i = 0;
    while ($i < Config::int('board.catalog_size')) {
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
    $dat['path'] = Config::string('paths.images');

    //$smarty->debugging = true;
    $dat['catalogmode'] = 'catalog';
    echo $template_engine->render(CATALOGFILE, $dat);
  } catch (PDOException $e) {
    error(Config::string('site.language') === 'English' ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
  }
}

/** @return array{page:int,max_page:int,start:int,paging:array<int,array{p:int}>,pp:array<int,array<int,array{p:int}>>,back:int,next:int} */
function catalog_paging(int $total, int $per_page, int $requested_page): array {
  if ($per_page < 1) throw new InvalidArgumentException('Invalid catalog page size.');
  $max_page = max(1, (int)ceil($total / $per_page));
  $page = min(max($requested_page, 1), $max_page);
  $paging = [];
  $pp = [];
  for ($p = 1; $p <= $max_page; $p++) {
    $paging[$p] = compact('p');
    $pp[] = $paging;
  }
  return [
    'page' => $page, 'max_page' => $max_page, 'start' => $per_page * ($page - 1),
    'paging' => $paging, 'pp' => $pp, 'back' => $page - 1, 'next' => $page + 1,
  ];
}

/** @return array<string,string> */
function public_search_criteria(): array {
  $query = (string)filter_input(INPUT_GET, 'search');
  $target = filter_input(INPUT_GET, 'target');
  $legacy_tag = filter_input(INPUT_GET, 'tag');
  $legacy_similar = filter_input(INPUT_GET, 'similar');
  if (!is_string($target) || $target === '') {
    return PublicPostSearch::normalize([
      'query' => $query,
      'target' => $legacy_tag === 'tag' ? 'comment' : 'author',
      'match' => $legacy_tag === 'tag' || $legacy_similar === 'similar' ? 'partial' : 'exact',
      // Legacy author search showed image posts only.
      'image' => $legacy_tag === 'tag' ? 'any' : 'with',
    ]);
  }
  return PublicPostSearch::normalize([
    'query' => $query, 'target' => $target, 'match' => filter_input(INPUT_GET, 'match'),
    'post_type' => filter_input(INPUT_GET, 'post_type'), 'image' => filter_input(INPUT_GET, 'image'),
    'nsfw' => filter_input(INPUT_GET, 'nsfw'), 'sort' => filter_input(INPUT_GET, 'sort'),
  ]);
}

//検索モード
function search(): void {
  global $template_engine, $dat;

  try {
    $criteria = public_search_criteria();
    $repository = new BoardRepository();
    $page_value = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $pagination = catalog_paging(
      $repository->countPublicSearch($criteria), Config::int('board.catalog_size'), $page_value === false || $page_value === null ? 1 : $page_value
    );
    $posts = $repository->searchVisiblePosts($criteria, $pagination['start'], Config::int('board.catalog_size'));

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
    $dat['path'] = Config::string('paths.images');
    $dat['catalogmode'] = 'search';
    $dat['search_term'] = $criteria['query'];
    $dat['search_label'] = PublicPostSearch::label($criteria);
    $dat['s_result'] = $repository->countPublicSearch($criteria);
    $dat['search_criteria'] = $criteria;
    $dat['catalog_paging_query'] = 'mode=search&' . PublicPostSearch::queryString($criteria);
    $dat['catalog_paging_enabled'] = true;
    $dat['max_page'] = $pagination['max_page'];
    $dat['nowpage'] = $pagination['page'];
    $dat['paging'] = $pagination['paging'];
    $dat['pp'] = $pagination['pp'];
    $dat['back'] = $pagination['back'];
    $dat['next'] = $pagination['next'];
    echo $template_engine->render(CATALOGFILE, $dat);
  } catch (InvalidArgumentException $e) {
    error(Config::string('site.language') === 'English' ? 'Invalid search criteria.' : '検索条件が不正です。', 400, $e);
  } catch (PDOException $e) {
    error(Config::string('site.language') === 'English' ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
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
      $error_id = ApplicationErrorHandler::reportThrowable($e);
      http_response_code(500);
      header('Content-Type: application/json');
      echo json_encode([
        'success' => false,
        'error' => ApplicationErrorHandler::publicMessage($error_id, Config::string('site.language') === 'English')
      ]);
      return;
    } else {
      error(Config::string('site.language') === 'English' ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
    }
  }

  // 通常のリクエストの場合は従来通りリダイレクト
  header('Location:' . Config::string('site.script_name'));
  def();
}

//レス画面
function res(): void {
  global $template_engine, $dat;
  global $en;
  $resno = filter_input(INPUT_GET, 'res',FILTER_VALIDATE_INT);
  $uuid = trim((string)filter_input(INPUT_GET, 'uuid'));

  //csrfトークンをセット
  $dat['token'] = '';
  if (Config::bool('features.csrf')) {
    $token = RequestSecurity::csrfToken();
    $_SESSION['token'] = $token;
    $dat['token'] = $token;
  }

  //古いスレのレスフォームを表示しない
  $elapsed_time = Config::int('board.elapsed_reply_days') * 86400; //デフォルトの1年だと31536000
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

        if (Config::bool('features.autolink')) {
          $res['com'] = auto_link($res['com']);
        }
        //ハッシュタグ
        if (Config::bool('features.hashtag')) {
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
        $res['created'] = date(Config::string('board.date_format'), strtotime($res['created']));
        $res['modified'] = date(Config::string('board.date_format'), strtotime($res['modified']));
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

      if (Config::bool('features.autolink')) {
        $bbsline['com'] = auto_link($bbsline['com']);
      }
      //画像URLにサムネイルを追加
      if (Config::bool('features.external_image_thumbnail')) {
        $bbsline['com'] = external_image_service()->addThumbnailLinks($bbsline['com']);
      }
      //ハッシュタグ
      if (Config::bool('features.hashtag')) {
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
      $bbsline['created'] = date(Config::string('board.date_format'), strtotime($bbsline['created']));
      $bbsline['modified'] = date(Config::string('board.date_format'), strtotime($bbsline['modified']));
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

      $bbsline['encoded_t'] = urlencode('['.$bbsline['tid'].']'.$bbsline['sub'].($bbsline['a_name'] ? ' by '.$bbsline['a_name'] : '').' - '.Config::string('site.title'));
      $bbsline['encoded_u'] = urlencode(Config::string('site.base_url').'?resno='.$bbsline['tid']);

      $oya[] = $bbsline;

      $dat['oya'] = $oya;
      $dat['ko'] = $ko;
    }
    $db = null;
  } catch (PDOException $e) {
    error($en ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
  }

  $dat['path'] = Config::string('paths.images');

  echo $template_engine->render(RESFILE, $dat);
}

//お絵描き画面
function paint_form(string $rep, ?int $reply_to): void {
  global $message, $usercode, $quality, $qualitys, $no;
  global $mode, $ctype, $pch, $type;
  global $template_engine, $dat;

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
    if ($imgfile && is_file(Config::string('paths.images') . $imgfile)) {
      list($picw, $pich) = getimagesize(Config::string('paths.images') . $imgfile); //キャンバスサイズ
    }
  }

  $anime = isset($_POST["anime"]) ? true : false;
  $dat['anime'] = $anime;

  if ($picw < 300) $picw = 300;
  if ($pich < 300) $pich = 300;
  if ($picw > Config::int('limits.paint_max_width')) $picw = Config::int('limits.paint_max_width');
  if ($pich > Config::int('limits.paint_max_height')) $pich = Config::int('limits.paint_max_height');

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

  $dat['undo'] = Config::int('limits.undo');
  $dat['undo_in_mg'] = Config::int('limits.undo_group');

  $dat['useanime'] = Config::bool('features.animation');

  $dat['path'] = Config::string('paths.images');

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
        if (is_file(Config::string('paths.images') . $pch_filename . '.pch') || is_file(Config::string('paths.images') . $pch_filename . '.spch') || is_file(Config::string('paths.images') . $pch_filename . '.chi')) {
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
    if (is_file(Config::string('paths.images') . $pch . '.pch')) {
      $dat['useneo'] = true;
    } elseif (is_file(Config::string('paths.images') . $pch . '.spch')) {
      $dat['useneo'] = false;
      $dat['use_shi_painter'] = true;
    }
    if ((Config::string('security.continue_click_count') || Config::string('security.continue_timer')) && Config::string('security.failure_url')) {
      $dat['security'] = true;
      $dat['security_click'] = Config::string('security.continue_click_count');
      $dat['security_timer'] = Config::string('security.continue_timer');
    }
  } else {
    if ((Config::string('security.click_count') || Config::string('security.timer')) && Config::string('security.failure_url')) {
      $dat['security'] = true;
      $dat['security_click'] = Config::string('security.click_count');
      $dat['security_timer'] = Config::string('security.timer');
    }
    $dat['newpaint'] = true;
  }
  $dat['security_url'] = Config::string('security.failure_url');

  //パレット設定
  //初期パレット
  $lines = array();
  $initial_palette = 'Palettes[0] = "#000000\n#FFFFFF\n#B47575\n#888888\n#FA9696\n#C096C0\n#FFB6FF\n#8080FF\n#25C7C9\n#E7E58D\n#E7962D\n#99CB7B\n#FCECE2\n#F9DDCF";';
  foreach (Config::array("drawing.palettes") as $p_value) {
    if ($p_value[1] == filter_input(INPUT_POST, 'palettes')) { // キーと入力された値が同じなら
      $set_palettec = $p_value[1];
      setcookie("palettec", $set_palettec, time() + (86400 * Config::int('board.cookie_days'))); // Cookie保存
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
  $pwd_f = openssl_encrypt($pwd, CRYPT_METHOD, Config::string('security.paint_password'), true, CRYPT_IV); //暗号化
  $pwd_f = bin2hex($pwd_f); //16進数に
  $arr_dyn_p=[];
  foreach ($DynP as $p) {
    $arr_dyn_p[] = '<option>' . $p . '</option>';
  }
  $dat['dynp'] = implode('', $arr_dyn_p);

  if ($ctype == 'pch' || $ctype == 'spch') {
    $pchfile = filter_input(INPUT_POST, 'pch');
    $dat['pchfile'] = Config::string('paths.images') . $pchfile;
  } elseif ($ctype == 'img') {
    $dat['animeform'] = false;
    $dat['anime'] = false;
    $dat['useanime'] = false; // 動画機能を無効化
    $imgfile = filter_input(INPUT_POST, 'img');
    $dat['imgfile'] = Config::string('paths.images') . $imgfile;
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
    echo $template_engine->render(PAINTFILE_BE, $dat);
  } elseif ($tool === 'shi' || $tool === 'neo') {
    echo $template_engine->render(PAINTFILE, $dat);
  } else {
    echo $template_engine->render(PAINTFILE, $dat);
  }
}

//アニメ再生

function open_pch(string $sp = ""): void {
  global $template_engine, $dat;

  $pch = (string)filter_input(INPUT_GET, 'pch');
  try {
    $playback = ImageService::animationPlaybackData(Config::string('paths.images'), $pch, (int)($sp ?: Config::int('drawing.animation_speed')));
  } catch (Throwable $e) {
    ApplicationErrorHandler::reportThrowable($e, 'animation-open-error');
    error(Config::string('site.language') === 'English' ? 'Failed to open animation.' : '動画を開けませんでした。', 404);
    return;
  }
  $template = $playback['template_type'] === 'tegaki' ? ANIMEFILE_TEGAKI : ANIMEFILE;
  unset($playback['template_type']);
  $dat = array_merge($dat, $playback);

  echo $template_engine->render($template, $dat);
}

//お絵かき投稿
function paint_com(string $tmpmode): void {
  global $usercode, $ptime;
  global $template_engine, $dat;

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
  if (Config::bool('features.csrf')) {
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
  foreach (ImageService::listTemporaryImages(Config::string('paths.temporary')) as $temporary_image) {
    if (hash_equals((string)$temporary_image['user_code'], (string)$usercode)) {
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
      $image_path = Config::string('paths.temporary') . $temporary_image['filename'];
      $src = temporary_image_url((string)$temporary_image['filename']);
      $src_name = $temporary_image['filename'];
      $modified = filemtime($image_path);
      $date = gmdate("Y/m/d H:i", ($modified === false ? time() : $modified) + 9 * 60 * 60);
      $tool = $temporary_image['tool'];
      $utime = $temporary_image['paint_time'];
      $psec = $temporary_image['paint_seconds'];
      $temp[] = compact('src', 'src_name', 'date', 'tool', 'utime', 'psec');
    }
    $dat['temp'] = $temp;
    $pending_picfile = $_SESSION['pending_picfile'] ?? '';
    if (is_string($pending_picfile) && $pending_picfile !== '') {
      $pending_image_found = false;
      foreach ($temp as $temporary_image) {
        if (hash_equals((string)$temporary_image['src_name'], $pending_picfile)) {
          $dat['selected_picfile'] = $pending_picfile;
          $pending_image_found = true;
          break;
        }
      }
      if (!$pending_image_found) {
        unset($_SESSION['pending_picfile']);
        ApplicationErrorHandler::reportMessage('Discarded an unavailable pending drawing image.', 'pending-image-reset');
      }
    }
  }

  $tmp2 = array();
  $dat['tmp'] = $tmp2;

  echo $template_engine->render(PICFILE, $dat);
}

function temporary_image_url(string $filename): string {
  return Config::string('site.script_name') . '?mode=temporary_image&file=' . rawurlencode($filename);
}

/** Accept a browser-rendered PNG together with its original NEO/Tegaki replay. */
function animation_upload(): void {
  global $en, $usercode;

  header('Content-Type: text/plain; charset=UTF-8');
  if (!Config::bool('features.image_upload') || !Config::bool('features.animation')) {
    error($en ? 'Animation uploads are disabled.' : '動画ファイルのアップロードは無効です。', 403);
    return;
  }
  try {
    if (Config::bool('features.csrf')) {
      RequestSecurity::assertCurrentCsrfRequest($en);
    } else {
      RequestSecurity::assertCurrentSameOriginRequest($en);
    }
    PaintSaveRequestGuard::assertWithinLimits(
      $_SERVER,
      $_POST,
      $_FILES,
      'animation_upload',
      Config::int('limits.paint_image_kb') * 1024,
      Config::int('limits.paint_work_kb') * 1024,
      Config::int('limits.paint_request_kb') * 1024
    );
    $resto_value = filter_input_data('POST', 'resto', FILTER_VALIDATE_INT);
    $resto = is_int($resto_value) && $resto_value > 0 ? $resto_value : 0;
    if (!diary_post_allowed($resto > 0)) {
      throw new ImageUploadException(
        $en ? 'Only an administrator can create this post.' : 'この投稿は管理者のみ作成できます。',
        403
      );
    }
    $picture = $_FILES['picture'] ?? null;
    $animation = $_FILES['animation'] ?? null;
    if (!is_array($picture) || !is_array($animation)) {
      throw new ImageUploadException('Invalid animation upload.', 400);
    }
    $extension = strtolower(pathinfo((string)($animation['name'] ?? ''), PATHINFO_EXTENSION));
    if ($extension === 'tgkr' && !Config::bool('features.tegaki')) {
      throw new ImageUploadException('Tegaki uploads are disabled.', 403);
    }
    $result = ImageService::storeUploadedAnimation(
      $picture,
      $animation,
      Config::string('paths.temporary'),
      (string)$usercode,
      $resto,
      Config::int('limits.paint_image_kb') * 1024,
      Config::int('limits.paint_work_kb') * 1024,
      Config::int('limits.paint_max_width'),
      Config::int('limits.paint_max_height'),
      Config::int('permissions.public_file'),
      Config::int('permissions.private_file')
    );
    $_SESSION['pending_picfile'] = $result['picfile'];
    echo "ok\n" . $result['picfile'] . "\n" . temporary_image_url($result['preview']);
  } catch (RequestSecurityException|PaintSaveCapacityException|ImageUploadException $e) {
    error($en ? $e->getMessage() : animation_upload_error_message($e), $e->getCode() ?: 400, $e);
  } catch (Throwable $e) {
    error($en ? 'Animation upload failed.' : '動画ファイルの保存に失敗しました。', 500, $e);
  }
}

function animation_upload_error_message(Throwable $error): string {
  return match ($error->getCode()) {
    403 => '動画ファイルをアップロードできません。設定と投稿権限を確認してください。',
    413 => '動画または生成画像の容量・寸法が上限を超えています。',
    415 => '対応していない動画形式です。',
    422 => '動画ファイルまたは生成画像が不正です。',
    default => '動画ファイルを受け付けられませんでした。',
  };
}

/** Serve the PNG/JPEG/GIF/WebP/AVIF preview of a pending drawing after authorization. */
function temporary_image(): void {
  global $usercode;

  $filename = (string)filter_input_data('GET', 'file');
  $is_administrator = AdminAuth::isAuthenticated(
    Config::string('admin.password'), Config::int('admin.session_lifetime')
  );
  $image = ImageService::authorizedTemporaryImage(
    Config::string('paths.temporary'), $filename, (string)$usercode, $is_administrator
  );
  if ($image === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store, private');
    exit('Not Found');
  }

  $size = @filesize($image['path']);
  header('Content-Type: ' . $image['mime_type']);
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store, private');
  header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"');
  if ($size !== false) header('Content-Length: ' . $size);
  readfile($image['path']);
  exit;
}

//コンティニュー画面in
function in_continue(): void {
  global $template_engine, $dat;
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
    error($en ? 'Failed to find the image.' : '画像の検索に失敗しました。', 500, $e);
    return;
  }
  if (empty($oya) || !is_file(Config::string('paths.images') . $no) || !is_readable(Config::string('paths.images') . $no)) {
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
  if (!Config::bool('features.continue_password')) $dat['newpost_nopassword'] = true;

  try {
    $continue_posts = [];
    foreach ($oya as $bbsline) {
      $bbsline['com'] = nl2br(htmlentities($bbsline['com'], ENT_QUOTES | ENT_HTML5), false);
      $continue_posts[] = $bbsline;
    }
    $dat['oya'] = $continue_posts;
    $hist_ope = pathinfo($no, PATHINFO_FILENAME); //拡張子除去
    $hist_filename = Config::string('paths.images') . $hist_ope;

    // データベースからctypeを取得
    $db_ctype = $continue_posts[0]['ctype'] ?? null;

    if (is_file($hist_filename . '.pch')) {
      //$pchfile = Config::string('paths.images').$pch;
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
    error($en ? 'Failed to prepare the continuation screen.' : '続きを描く画面の準備に失敗しました。', 500, $e);
  }

  echo $template_engine->render(OTHERFILE, $dat);
}

//削除くん

function delmode(): void {
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
    if (!AdminAuth::isAuthenticated(Config::string("admin.password"), Config::int('admin.session_lifetime'))) {
      error($en ? 'Administrator login is required.' : '管理者ログインが必要です。', 403);
    }
    $p_pwd = '';
  }

  try {
    $service = new PostService(new BoardRepository(), Config::string('paths.images'), Config::int('limits.paint_default_width'), Config::int('permissions.public_file'));
    $service->delete((int)$delno, (string)$p_pwd, $admin_delete);
    $dat['message'] = $en ? 'Successfully deleted.' : '削除しました。';
  } catch (PostNotFoundException $e) {
    error($en ? 'That post does not exist.' : 'そんな記事ない気がします。', 404);
    return;
  } catch (PostAuthorizationException $e) {
    error($en ? 'Invalid password or post number.' : 'パスワードまたは記事番号が違います。', 403);
    return;
  } catch (Throwable $e) {
    error($en ? 'Deletion failed.' : '削除に失敗しました。', 500, $e);
    return;
  }
  //変数クリア
  unset($delno, $delt);
  //header('Location:'.Config::string('site.script_name'));
  ok($en ? 'Successfully deleted. Switching screen.' : '削除しました。画面を切り替えます。');
}

//画像差し替え
function picreplace(): void {
  global $type;
  global $path;
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
  $pwd_f = $pwd_bin === false ? false : openssl_decrypt($pwd_bin, CRYPT_METHOD, Config::string('security.paint_password'), true, CRYPT_IV); //復号化
  if ($pwd_f === false) {
    error($en ? 'Invalid replacement request.' : '画像差し替えのリクエストが不正です。');
  }
  $nsfw_flag = filter_input(INPUT_POST, 'nsfw');

  //ホスト取得
  $host = gethostbyaddr(RequestInfo::clientIp());

  foreach (Config::array("spam.bad_hosts") as $value) { //拒絶host
    if (preg_match("/$value$/i", $host)) error($en ? 'Your host is blocked.' : 'あなたのホストは拒絶されています。', 403);
  }

  $temporary_image = ImageService::findTemporaryImageByReplacementCode(Config::string('paths.temporary'), (string)$repcode);
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
        Config::string('paths.temporary'), Config::string('paths.images'), $filename, $imgext, (int)$stime,
        (string)$msg_d['picfile'], (string)$msg_d['pchfile'], Config::int('permissions.public_file')
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
      if (Config::bool('features.nsfw') && $nsfw_flag == 1) {
        $nsfw = true;
      } else {
        $nsfw = false;
      }

      // 続き描きでは新しい画像から必ずサムネイルを作り直す。
      $thumbnail = ImageService::refreshNsfwThumbnail(
        Config::string('paths.images'), $new_picfile, (string)($msg_d['thumbnail'] ?? ''), $nsfw,
        Config::int('limits.paint_default_width'), Config::int('permissions.public_file'), true, false
      );
      if ($thumbnail !== '') {
        $replacement['created_files'][] = rtrim(Config::string('paths.images'), '/\\') . DIRECTORY_SEPARATOR . $thumbnail;
      }
      $old_thumbnail = basename((string)($msg_d['thumbnail'] ?? ''));
      if ($old_thumbnail !== '' && $old_thumbnail !== $thumbnail) {
        $old_thumbnail_path = rtrim(Config::string('paths.images'), '/\\') . DIRECTORY_SEPARATOR . $old_thumbnail;
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
    error($en ? 'Image replacement failed.' : '画像差し替えに失敗しました。', 500, $e);
  }
  editform((int)$no, (string)$pwd_f);
}

//編集モードくん入口
function editform(?int $authorized_post_id = null, ?string $authorized_password = null, bool $authorized_as_admin = false): void {
  global $template_engine, $dat;
  global $en;

  //csrfトークンをセット
  $dat['token'] = '';
  if (Config::bool('features.csrf')) {
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
    if ($authorized_as_admin
      && !AdminAuth::isAuthenticated(Config::string('admin.password'), Config::int('admin.session_lifetime'))) {
      error($en ? 'Administrator login is required.' : '管理者ログインが必要です。', 403);
    }
    $service = new PostService(new BoardRepository(), Config::string('paths.images'));
    $authorization = $service->authorize((int)$edit_no, (string)$post_pwd, $authorized_as_admin);
    $msg = $authorization['post'];
    if ($authorization['role'] === 'owner') {
      $dat['message'] = $en ? 'Editing mode...' : '編集モード...';
    } else {
      $dat['message'] = $en ? 'Administrator editing mode...' : '管理者編集モード...';
    }
    $msg['input_name'] = PostService::nameForEdit(
      (string)$msg['a_name'], (string)($dat['name_cookie'] ?? ''), $authorization['role'] === 'owner'
    );
    // 続き描きや所有者編集で認証済みの投稿パスワードを使う。
    // 別投稿で保存されたCookieのパスワードでは、画像だけ更新され本文編集が失敗し得る。
    $msg['input_password'] = $authorization['role'] === 'owner'
      ? (string)$post_pwd
      : '';
    $msg['admin_edit'] = $authorization['role'] === 'admin';
    $dat['oya'] = [$msg];

    $dat['othermode'] = 'edit'; //編集モード
    echo $template_engine->render(OTHERFILE, $dat);
  } catch (PostNotFoundException $e) {
    error($en ? 'That post does not exist.' : 'そんな記事ないです。', 404);
  } catch (PostAuthorizationException $e) {
    error($en ? 'Invalid password or post number.' : 'パスワードまたは記事番号が違います。', 403);
  } catch (Throwable $e) {
    error($en ? 'Failed to open edit mode.' : '編集画面を開けませんでした。', 500, $e);
  }
}

//編集モードくん本体
function editexec(): void {
  global $req_method;
  global $dat;
  global $en;

  //CSRFトークンをチェック
  if (Config::bool('features.csrf')) {
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
  $edit_nsfw = Config::bool('features.nsfw') && $input['nsfw_flag'] === '1';
  $edit_as_admin = filter_input_data('POST', 'admin_edit') === '1';
  if ($edit_as_admin
    && !AdminAuth::isAuthenticated(Config::string('admin.password'), Config::int('admin.session_lifetime'))) {
    error($en ? 'Administrator login is required.' : '管理者ログインが必要です。', 403);
  }

  //ホスト取得
  $host = gethostbyaddr(RequestInfo::clientIp());
  try {
    $rules = PostValidator::configuredRules(
      $en, $req_method, $host, Config::array("spam.bad_hosts"), $edit_as_admin, true
    );
    PostValidator::validate($input, $rules);
  } catch (PostValidationException $e) {
    error($e->getMessage(), $e->getCode() ?: 400);
    return;
  }
  //↑セキュリティ関連ここまで

  try {
    $service = new PostService(new BoardRepository(), Config::string('paths.images'), Config::int('limits.paint_default_width'), Config::int('permissions.public_file'));
    $edit_role = $service->edit((int)$e_no, $pwd, [
      'name' => $name, 'mail' => $mail, 'sub' => $sub, 'com' => $com, 'url' => $url,
      'host' => $host, 'sodane' => $sodane, 'edit_nsfw' => $edit_nsfw,
    ], $edit_as_admin);
    if ($edit_role === 'admin') {
      ApplicationErrorHandler::reportAdminAudit('post-edit', ['posts' => 1]);
    }
    if ($edit_role === 'owner') {
      $https_only = (bool)($_SERVER['HTTPS'] ?? '');
      setcookie(
        'name_c', $name, time() + (Config::int('board.cookie_days') * 24 * 3600),
        '', '', $https_only, true
      );
    }
    $dat['message'] = $en ? 'Editing completed successfully.' : '編集完了しました。';
  } catch (PostNotFoundException $e) {
    error($en ? 'That post does not exist.' : 'そんな記事ないです。', 404);
    return;
  } catch (PostAuthorizationException $e) {
    error($en ? 'Invalid password or post number.' : 'パスワードまたは記事番号が違います。', 403);
    return;
  } catch (Throwable $e) {
    error($en ? 'Editing failed.' : '編集に失敗しました。', 500, $e);
    return;
  }
  unset($name, $mail, $sub, $com, $url, $pwd, $resto, $pictmp, $picfile, $mode);
  //header('Location:'.Config::string('site.script_name'));
  ok($en ? 'Successfully edited. Switching screen.' : '編集に成功しました。画面を切り替えます。');
}

//管理モードin
function admin_in(): void {
  global $template_engine, $dat;
  admin_no_store();
  if (AdminAuth::isAuthenticated(Config::string("admin.password"), Config::int('admin.session_lifetime'))) {
    redirect(Config::string('site.script_name') . '?mode=admin');
  }
  $dat['othermode'] = 'admin_in';
  $dat['token'] = RequestSecurity::csrfToken();

  echo $template_engine->render(OTHERFILE, $dat);
}

function admin_login(): void {
  global $en;
  admin_no_store();
  try {
    RequestSecurity::assertCurrentCsrfRequest($en);
  } catch (RequestSecurityException $e) {
    error($e->getMessage(), $e->getCode() ?: 403);
  }
  $client_ip = RequestInfo::clientIp();
  $client_ip = $client_ip !== '' ? $client_ip : 'unknown';
  $limiter = new AdminLoginRateLimiter(
    __DIR__ . '/session',
    Config::string("admin.password"),
    Config::int('admin.login.max_failures'),
    Config::int('admin.login.window'),
    Config::int('admin.login.lockout'),
    Config::int('permissions.private_file')
  );
  $retry_after = 0;
  try {
    // 成否にかかわらず、ログイン試行時に期限切れ記録を少しずつ掃除する。
    if (random_int(1, 100) === 1) $limiter->cleanupExpired();
    $retry_after = $limiter->retryAfter($client_ip);
  } catch (Throwable $e) {
    error($en ? 'Administrator login protection failed.' : '管理者ログイン保護の処理に失敗しました。', 500, $e);
  }
  if ($retry_after > 0) {
    header('Retry-After: ' . $retry_after);
    error($en ? 'Too many administrator login attempts. Please try again later.'
      : '管理者ログインの試行回数が多すぎます。時間をおいて再試行してください。', 429);
  }
  $password = (string)filter_input_data('POST', 'adminpass');
  if (!AdminAuth::login($password, Config::string("admin.password"))) {
    $retry_after = 0;
    try {
      $retry_after = $limiter->recordFailure($client_ip);
    } catch (Throwable $e) {
      error($en ? 'Administrator login protection failed.' : '管理者ログイン保護の処理に失敗しました。', 500, $e);
    }
    if ($retry_after > 0) {
      header('Retry-After: ' . $retry_after);
      error($en ? 'Too many administrator login attempts. Please try again later.'
        : '管理者ログインの試行回数が多すぎます。時間をおいて再試行してください。', 429);
    }
    error($en ? 'Administrator password is incorrect.' : '管理パスが違います。', 403);
  }
  try {
    $limiter->clear($client_ip);
  } catch (Throwable $e) {
    error($en ? 'Administrator login protection failed.' : '管理者ログイン保護の処理に失敗しました。', 500, $e);
  }
  ApplicationErrorHandler::reportAdminAudit('login');
  redirect(Config::string('site.script_name') . '?mode=admin');
}

function admin_logout(): void {
  global $en;
  admin_no_store();
  try {
    RequestSecurity::assertCurrentCsrfRequest($en);
  } catch (RequestSecurityException $e) {
    error($e->getMessage(), $e->getCode() ?: 403);
  }
  ApplicationErrorHandler::reportAdminAudit('logout');
  AdminAuth::logout();
  redirect(Config::string('site.script_name') . '?mode=admin_in');
}

function admin_delete(): void {
  admin_manage('delete');
}

function admin_manage(?string $forced_operation = null): void {
  global $en;
  admin_no_store();
  try {
    RequestSecurity::assertCurrentCsrfRequest($en);
  } catch (RequestSecurityException $e) {
    error($e->getMessage(), $e->getCode() ?: 403);
  }
  if (!AdminAuth::isAuthenticated(Config::string("admin.password"), Config::int('admin.session_lifetime'))) {
    error($en ? 'Administrator login is required.' : '管理者ログインが必要です。', 403);
  }

  $selected = filter_input_data('POST', 'delno');
  if (!is_array($selected)) $selected = [];
  $operation = $forced_operation ?? (string)filter_input_data('POST', 'operation');
  if (!in_array($operation, ['hide', 'show', 'delete'], true)) {
    error($en ? 'Invalid administration operation.' : '管理操作が不正です。', 400);
  }
  try {
    /** @var AdminPostManagementService $service */
    $service = new PostService(
      new BoardRepository(), Config::string('paths.images'), Config::int('limits.paint_default_width'), Config::int('permissions.public_file')
    );
    if ($operation === 'delete') {
      $count = $service->deleteManyAsAdmin($selected);
      unset($_SESSION['admin_image_usage']);
      ApplicationErrorHandler::reportAdminAudit('post-delete', ['posts' => $count]);
      $_SESSION['admin_message'] = $en
        ? "{$count} selected post(s) were deleted."
        : "選択した{$count}件の記事を完全削除しました。";
    } else {
      $hidden = $operation === 'hide';
      $count = $service->setVisibilityManyAsAdmin($selected, $hidden);
      ApplicationErrorHandler::reportAdminAudit($hidden ? 'post-hide' : 'post-show', ['posts' => $count]);
      $_SESSION['admin_message'] = $en
        ? "{$count} selected post(s) were " . ($hidden ? 'hidden.' : 'made visible.')
        : "選択した{$count}件の記事を" . ($hidden ? '非表示にしました。' : '再表示しました。');
    }
  } catch (InvalidArgumentException $e) {
    error($en ? 'Please select at least one post.' : '操作する記事を選択してください。', 400);
  } catch (PostNotFoundException $e) {
    error($en ? 'The selected posts do not exist.' : '選択した記事が見つかりません。', 404);
  } catch (Throwable $e) {
    error($en ? 'Failed to update the selected posts.' : '選択した記事の更新に失敗しました。', 500, $e);
  }
  redirect(Config::string('site.script_name') . '?mode=admin');
}

/** @return object|null Theme providers implement templateData(), save(array), and reset(). */
function theme_settings_provider(): ?object {
  global $theme_directory;

  if (!defined('THEME_SETTINGS_CLASS')) return null;
  $class = constant('THEME_SETTINGS_CLASS');
  if (!is_string($class) || $class === '' || !class_exists($class)) {
    throw new RuntimeException('Theme settings provider is unavailable.');
  }
  $provider = new $class(
    $theme_directory, Config::int('database.busy_timeout'), Config::int('permissions.private_file')
  );
  foreach (['templateData', 'save', 'reset'] as $method) {
    if (!method_exists($provider, $method)) {
      throw new RuntimeException('Theme settings provider has an invalid interface.');
    }
  }
  return $provider;
}

function admin_theme_settings(): void {
  global $en;

  admin_no_store();
  $settings = null;
  try {
    RequestSecurity::assertCurrentCsrfRequest($en);
  } catch (RequestSecurityException $e) {
    error($e->getMessage(), $e->getCode() ?: 403);
  }
  require_admin_session();
  try {
    $settings = theme_settings_provider();
  } catch (Throwable $e) {
    error($en ? 'Theme settings are unavailable.' : 'テーマ設定を利用できません。', 500, $e);
    return;
  }
  if ($settings === null) {
    error($en ? 'Theme color settings are unavailable for this theme.' : 'このテーマでは配色設定を利用できません。', 404);
    return;
  }

  $operation = (string)filter_input_data('POST', 'operation');
  if (!in_array($operation, ['save', 'reset'], true)) {
    error($en ? 'Invalid theme settings operation.' : 'テーマ設定の操作が不正です。', 400);
  }
  try {
    if ($operation === 'reset') {
      $settings->reset();
      ApplicationErrorHandler::reportAdminAudit('theme-settings-reset');
      $_SESSION['theme_settings_message'] = $en ? 'Theme settings were reset.' : 'テーマ標準の設定に戻しました。';
    } else {
      $values = $_POST['theme_settings'] ?? null;
      if (!is_array($values)) throw new InvalidArgumentException('Invalid theme settings.');
      $settings->save($values);
      ApplicationErrorHandler::reportAdminAudit('theme-settings-save');
      $_SESSION['theme_settings_message'] = $en ? 'Theme settings were saved.' : 'テーマ設定をサイト全体に保存しました。';
    }
  } catch (InvalidArgumentException $e) {
    error($en ? 'Invalid theme settings values.' : 'テーマ設定の値が不正です。', 400);
  } catch (Throwable $e) {
    error($en ? 'Failed to save theme settings.' : 'テーマ設定を保存できませんでした。', 500, $e);
  }
  redirect(Config::string('site.script_name') . '?mode=admin');
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
  global $en;

  admin_no_store();
  if (!AdminAuth::isAuthenticated(Config::string("admin.password"), Config::int('admin.session_lifetime'))) {
    error($en ? 'Administrator login is required.' : '管理者ログインが必要です。', 403);
  }
}

// 管理者向けエラーログ閲覧
function admin_errorlog(): void {
  global $template_engine, $dat;
  global $en;

  require_admin_session();
  $dates = ErrorLogReader::availableDates(__DIR__ . '/errorlog');
  $date_input = (string)(filter_input_data('GET', 'log_date') ?? '');
  if ($date_input !== '' && !in_array($date_input, $dates, true)) {
    error($en ? 'The requested error log date does not exist.' : '指定されたエラーログの日付は存在しません。', 404);
  }
  $date = $date_input !== '' ? $date_input : ($dates[0] ?? '');
  $type = (string)(filter_input_data('GET', 'log_type') ?? 'all');
  $status_group = (string)(filter_input_data('GET', 'log_status') ?? 'all');
  if (!in_array($status_group, ['all', '4xx', '5xx'], true)
    || ($type !== 'all' && preg_match('/\A[a-z][a-z0-9-]{0,63}\z/D', $type) !== 1)) {
    error($en ? 'Invalid error log filter.' : 'エラーログの絞り込み条件が不正です。', 400);
  }

  try {
    $result = $date === ''
      ? ['records' => [], 'total' => 0, 'types' => []]
      : ErrorLogReader::read(__DIR__ . '/errorlog', $date, $type, $status_group);
    $dat['admin_errorlog_dates'] = $dates;
    $dat['admin_errorlog_date'] = $date;
    $dat['admin_errorlog_type'] = $type;
    $dat['admin_errorlog_status'] = $status_group;
    $dat['admin_errorlog_types'] = $result['types'];
    $dat['admin_errorlog_records'] = $result['records'];
    $dat['admin_errorlog_total'] = $result['total'];
    $dat['admin_errorlog_limit'] = 100;
    $dat['admin_log_is_audit'] = false;
    $dat['admin_log_mode'] = 'admin_errorlog';
    $dat['token'] = RequestSecurity::csrfToken();
    echo $template_engine->render(ADMINERRORLOGFILE, $dat);
  } catch (Throwable $e) {
    error($en ? 'Failed to load the error log.' : 'エラーログの読み込みに失敗しました。', 500, $e);
  }
}

// 管理者向け監査ログ閲覧
function admin_auditlog(): void {
  global $template_engine, $dat;
  global $en;

  require_admin_session();
  $dates = AuditLogReader::availableDates(__DIR__ . '/auditlog');
  $date_input = (string)(filter_input_data('GET', 'log_date') ?? '');
  if ($date_input !== '' && !in_array($date_input, $dates, true)) {
    error($en ? 'The requested audit log date does not exist.' : '指定された監査ログの日付は存在しません。', 404);
  }
  $date = $date_input !== '' ? $date_input : ($dates[0] ?? '');

  try {
    $result = $date === ''
      ? ['records' => [], 'total' => 0, 'types' => []]
      : AuditLogReader::read(__DIR__ . '/auditlog', $date);
    $dat['admin_errorlog_dates'] = $dates;
    $dat['admin_errorlog_date'] = $date;
    $dat['admin_errorlog_type'] = 'all';
    $dat['admin_errorlog_status'] = 'all';
    $dat['admin_errorlog_types'] = $result['types'];
    $dat['admin_errorlog_records'] = $result['records'];
    $dat['admin_errorlog_total'] = $result['total'];
    $dat['admin_errorlog_limit'] = 100;
    $dat['admin_log_is_audit'] = true;
    $dat['admin_log_mode'] = 'admin_auditlog';
    $dat['token'] = RequestSecurity::csrfToken();
    echo $template_engine->render(ADMINERRORLOGFILE, $dat);
  } catch (Throwable $e) {
    error($en ? 'Failed to load the audit log.' : '監査ログの読み込みに失敗しました。', 500, $e);
  }
}

// 管理者向け一時画像管理
function admin_temporary_images(): void {
  global $template_engine, $dat;
  global $en;

  require_admin_session();
  $page_input = filter_input_data('GET', 'page');
  $page = 1;
  if ($page_input !== null && $page_input !== '') {
    $validated_page = filter_var($page_input, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($validated_page === false) {
      error($en ? 'Invalid temporary image page number.' : '一時画像一覧のページ番号が不正です。', 400);
    }
    $page = (int)$validated_page;
  }
  try {
    $temporary_images = ImageService::temporaryImageEntries(
      Config::string('paths.temporary'), Config::int('limits.temporary_days')
    );
    $total = count($temporary_images);
    $per_page = Config::int('admin.temporary_images_per_page');
    $total_pages = max(1, (int)ceil($total / $per_page));
    if ($page > $total_pages) {
      error($en ? 'The temporary image page does not exist.' : '指定された一時画像一覧のページはありません。', 404);
    }
    $offset = ($page - 1) * $per_page;
    $temporary_images = array_slice($temporary_images, $offset, $per_page);
    foreach ($temporary_images as &$temporary_image) {
      $temporary_image['url'] = temporary_image_url((string)$temporary_image['filename']);
      $temporary_image['modified'] = date(Config::string('board.date_format'), (int)$temporary_image['modified_at']);
      $temporary_image['size'] = ImageService::formatBytes((int)$temporary_image['related_bytes']);
    }
    unset($temporary_image);
    $dat['admin_temporary_images'] = $temporary_images;
    $dat['admin_temporary_images_total'] = $total;
    $dat['admin_temporary_images_per_page'] = $per_page;
    $dat['admin_temporary_images_page'] = $page;
    $dat['admin_temporary_images_total_pages'] = $total_pages;
    $dat['admin_temporary_images_range_start'] = $total === 0 ? 0 : $offset + 1;
    $dat['admin_temporary_images_range_end'] = $offset + count($temporary_images);
    $dat['admin_temporary_images_message'] = isset($_SESSION['admin_temporary_images_message'])
      ? (string)$_SESSION['admin_temporary_images_message'] : '';
    unset($_SESSION['admin_temporary_images_message']);
    $dat['temporary_days'] = Config::int('limits.temporary_days');
    $dat['token'] = RequestSecurity::csrfToken();
    echo $template_engine->render(ADMINTEMPORARYFILE, $dat);
  } catch (Throwable $e) {
    error($en ? 'Failed to load temporary images.' : '一時画像の読み込みに失敗しました。', 500, $e);
  }
}

function admin_temporary_images_manage(): void {
  global $en;

  admin_no_store();
  try {
    RequestSecurity::assertCurrentCsrfRequest($en);
  } catch (RequestSecurityException $e) {
    error($e->getMessage(), $e->getCode() ?: 403);
  }
  require_admin_session();
  $operation = (string)filter_input_data('POST', 'operation');
  try {
    if ($operation === 'delete_selected') {
      $selected = filter_input_data('POST', 'temporary_image');
      if (!is_array($selected) || $selected === []) {
        throw new InvalidArgumentException('No temporary image was selected.');
      }
      $result = ImageService::deleteTemporaryImages(Config::string('paths.temporary'), $selected);
      ApplicationErrorHandler::reportAdminAudit('temporary-images-delete', [
        'files' => $result['files'], 'images' => $result['images'], 'skipped' => $result['skipped'],
      ]);
      $_SESSION['admin_temporary_images_message'] = $en
        ? "Deleted {$result['images']} pending image(s) and {$result['files']} file(s)."
        : "一時画像{$result['images']}件と関連ファイル{$result['files']}件を削除しました。";
    } elseif ($operation === 'cleanup_expired') {
      $files = ImageService::cleanupTemporaryFiles(
        Config::string('paths.temporary'), Config::int('limits.temporary_days')
      );
      ApplicationErrorHandler::reportAdminAudit('temporary-images-cleanup', ['files' => $files]);
      $_SESSION['admin_temporary_images_message'] = $en
        ? "Deleted {$files} expired temporary file(s)."
        : "期限切れの一時ファイル{$files}件を削除しました。";
    } else {
      throw new InvalidArgumentException('Invalid temporary image operation.');
    }
  } catch (InvalidArgumentException $e) {
    error($en ? 'Invalid temporary image operation.' : '一時画像の管理操作が不正です。', 400);
  } catch (Throwable $e) {
    error($en ? 'Failed to manage temporary images.' : '一時画像の管理に失敗しました。', 500, $e);
  }
  redirect(Config::string('site.script_name') . '?mode=admin_temporary_images');
}

function diary_post_allowed(bool $is_reply): bool {
  return DiaryPostPolicy::allows(
    Config::bool('features.diary_mode'),
    Config::bool('features.diary_allow_public_replies'),
    AdminAuth::isAuthenticated(Config::string('admin.password'), Config::int('admin.session_lifetime')),
    $is_reply
  );
}

function admin_post(): void {
  global $template_engine, $dat;
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
      && is_file(Config::string('paths.images') . $picfile) ? Config::string('paths.images') . $picfile : '';
    $dat['admin_thumbnail_url'] = $thumbnail !== '' && $thumbnail === (string)$post['thumbnail']
      && is_file(Config::string('paths.images') . $thumbnail) ? Config::string('paths.images') . $thumbnail : '';
    $dat['admin_pch_playback_url'] = $pchfile !== '' && $pchfile === (string)$post['pchfile']
      && is_file(Config::string('paths.images') . $pchfile)
      ? Config::string('site.script_name') . '?mode=anime&pch=' . rawurlencode($pchfile)
      : '';
    $dat['token'] = RequestSecurity::csrfToken();
    echo $template_engine->render(ADMINPOSTFILE, $dat);
  } catch (Throwable $e) {
    error($en ? 'Failed to load the post details.' : '投稿詳細の読み込みに失敗しました。', 500, $e);
  }
}

function admin_edit(): void {
  require_admin_session();
  editform(admin_post_id(), '', true);
}

//管理モード
function admin(): void {
  global $template_engine, $dat;
  global $en;

  admin_no_store();
  if (!AdminAuth::isAuthenticated(Config::string("admin.password"), Config::int('admin.session_lifetime'))) {
    error($en ? 'Administrator login is required.' : '管理者ログインが必要です。', 403);
  }
  $dat['path'] = Config::string('paths.images');
  $dat['token'] = RequestSecurity::csrfToken();
  $dat['message'] = isset($_SESSION['admin_message']) ? (string)$_SESSION['admin_message'] : '';
  unset($_SESSION['admin_message']);
  $dat['theme_settings_message'] = isset($_SESSION['theme_settings_message']) ? (string)$_SESSION['theme_settings_message'] : '';
  unset($_SESSION['theme_settings_message']);

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
  $per_page = max(1, min(100, (int)Config::int('admin.threads_per_page')));

  try {
    $repository = new BoardRepository();
    $dashboard_stats = $repository->adminDashboardStats();
    $cached_usage = $_SESSION['admin_image_usage'] ?? null;
    if (!is_array($cached_usage) || !isset($cached_usage['measured_at'], $cached_usage['files'], $cached_usage['bytes'])
      || (int)$cached_usage['measured_at'] < time() - 300) {
      $usage = ImageService::directoryUsage(Config::string('paths.images'));
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
    echo $template_engine->render(ADMINFILE, $dat);
  } catch (Throwable $e) {
    error($en ? 'Failed to load the administration screen.' : '管理画面の読み込みに失敗しました。', 500, $e);
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
    error($en ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
  }
  if (!$flag) {
    error($en ? "The specified post could not be found or the password is incorrect." : "該当記事が見つからないかパスワードが間違っています", 403);
  }
}

//OK画面
function ok(string $mes): void {
  global $template_engine, $dat;
  $dat['okmes'] = $mes;
  $dat['othermode'] = 'ok';
  $async_flag = (bool)filter_input(INPUT_POST,'asyncflag',FILTER_VALIDATE_BOOLEAN);
  $http_x_requested_with = (bool)(isset($_SERVER['HTTP_X_REQUESTED_WITH']));
  if($http_x_requested_with || $async_flag){
    die("OK!\n$mes");
  }
  echo $template_engine->render(OTHERFILE, $dat);
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
  ImageService::cleanupTemporaryFiles(Config::string('paths.temporary'), Config::int('limits.temporary_days'));
}

//古い外部画像サムネイルの削除
function clean_old_thumbnails(): void {
  $thumbnail_dir = __DIR__ . '/thumbnail/';
  ExternalImageService::cleanupLegacyThumbnails($thumbnail_dir);
  if (Config::int('limits.external_thumbnail_days') <= 0) {
    return;
  }
  if (!is_dir($thumbnail_dir)) {
    return;
  }
  $handle = opendir($thumbnail_dir);
  while ($file = readdir($handle)) {
    $file_path = $thumbnail_dir . $file;
    if (!is_dir($file_path) && preg_match('/_thumb\.(jpg|png|gif|webp|avif)$/', $file)) {
      $lapse = time() - filemtime($file_path);
      if ($lapse > (Config::int('limits.external_thumbnail_days') * 24 * 3600)) {
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
  global $en;
  //オーバーした行の画像とスレ番号を取得
  try {
    $repository = new BoardRepository();
    $msg = $repository->oldestPost();
    if (!$msg) return;

    $del_id = (int)$msg["tid"]; //消す行のスレ番号
    $msg_pic = $msg["picfile"]; //画像の名前取得できた
    ImageService::deleteRelatedFiles(Config::string('paths.images'), (string)$msg_pic);

    $repository->deletePost($del_id, true);
  } catch (PDOException $e) {
    error($en ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
  }
}

//エラー画面
function error(string $mes, int $status = 400, ?Throwable $cause = null): void {
  global $db;
  global $template_engine, $dat;
  global $en;
  if ($status < 400 || $status > 599) $status = 500;
  // 4xxも含め、利用者へエラー画面を返すすべての異常を記録する。
  $error_id = ApplicationErrorHandler::reportHttpError($status, strip_tags($mes), $cause);
  if ($status >= 500) {
    $mes = h(ApplicationErrorHandler::publicMessage($error_id, $en));
  }
  http_response_code($status);
  $db = null; //db切断
  $dat['errmes'] = $mes;
  $dat['othermode'] = 'err';
  $async_flag = (bool)filter_input(INPUT_POST,'asyncflag',FILTER_VALIDATE_BOOLEAN);
  $http_x_requested_with = (bool)(isset($_SERVER['HTTP_X_REQUESTED_WITH']));
  if($http_x_requested_with || $async_flag){
    die("error\n$mes");
  }
  if (!isset($template_engine)) die($mes);
  echo $template_engine->render(OTHERFILE, $dat);
  exit;
}

//画像差し替え失敗
function error2(): void {
  global $db;
  global $template_engine, $dat;
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
  echo $template_engine->render(OTHERFILE, $dat);
  exit;
}
