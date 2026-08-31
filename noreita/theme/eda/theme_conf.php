<?php
//--------------------------------------------------
// 「noReita」v4.0.0～用テーマ「eda」設定ファイル
//  by sakots https://oekakibbs.moe/
//--------------------------------------------------

//テーマ名
const THEME_NAME = "eda";

//テーマのバージョン
const THEME_VER = "lot.260901.0";

// テンプレートエンジン。Twigテーマでは'twig'に変更します。
const THEME_TEMPLATE_ENGINE = 'twig';

// テーマ固有設定の保存処理。テーマを差し替えてもコアへ実装を残しません。
require_once __DIR__ . '/theme_settings.php';
const THEME_SETTINGS_CLASS = 'EdaThemeSettings';

/* -------------------- */

//編集したときの目印
//※記事を編集したら日付の後ろに付きます
const UPDATE_MARK = '*';

//名前引用時の「さん」
const A_NAME_SAN = 'さん';

//「そうだね」
const SODANE = 'そうだね';

/* -------------------- */

//テーマがXHTMLか 1:XHTML 0:HTML
const TH_XHTML = 0;

/* テンプレートファイル名に".blade.php"は不要 */

//メインのテンプレートファイル
const MAINFILE = "eda_main";

//レスのテンプレートファイル
const RESFILE = "eda_res";

//お絵かき(PaintBBS NEO/しぃペインター)のテンプレートファイル
const PAINTFILE = "eda_paint";

//お絵かき(chickenPaint/Klecks/Tegaki/Axnos)のテンプレートファイル
const PAINTFILE_BE = "eda_be";

//動画再生(PaintBBS NEO/しぃペインター)のテンプレートファイル
const ANIMEFILE = "eda_anime";

//動画再生(Tegaki)のテンプレートファイル
const ANIMEFILE_TEGAKI = "eda_tgkr_view";

//投稿時のテンプレートファイル
const PICFILE = "eda_picpost";

//カタログ、検索モードのテンプレートファイル
const CATALOGFILE = "eda_catalog";

//管理モードのテンプレートファイル
const ADMINFILE = "eda_admin";

//管理モードの記事詳細テンプレートファイル
const ADMINPOSTFILE = "eda_admin_post";

//管理モードのエラーログテンプレートファイル
const ADMINERRORLOGFILE = "eda_admin_errorlog";

//管理モードの一時画像管理テンプレートファイル
const ADMINTEMPORARYFILE = "eda_admin_temporary_images";

//SNSシェア選択のテンプレートファイル
const SET_SHARE_SERVER = "eda_sns_share";

//misskey関係のテンプレートファイル
const MISSKEYFILE = "eda_misskey_note";

//その他のテンプレートファイル
const OTHERFILE = "eda_other";

//描画時間の書式
//※日本語だと、"1日1時間1分1秒"
//※英語だと、"1day 1hr 1min 1sec"
defined('PTIME_D') or define('PTIME_D', '日');
defined('PTIME_H') or define('PTIME_H', '時間');
defined('PTIME_M') or define('PTIME_M', '分');
defined('PTIME_S') or define('PTIME_S', '秒');

//＞が付いた時の書式
//※RE_STARTとRE_ENDで囲むのでそれを考慮して
//ここは変更せずにcssで設定するの推奨
const RE_START = '<q class="re">';
const RE_END = '</q>';
