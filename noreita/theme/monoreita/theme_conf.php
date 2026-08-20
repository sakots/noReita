<?php
//--------------------------------------------------
// 「noReita」v3.0.0～用テーマ「monoreita」設定ファイル
//  by sakots https://oekakibbs.moe/
//--------------------------------------------------

//テーマ名
const THEME_NAME = "monoreita";

//テーマのバージョン
const THEME_VER = "lot.260820.0";

// テンプレートエンジン。Twigテーマでは'twig'に変更します。
const THEME_TEMPLATE_ENGINE = 'blade';

/* -------------------- */

//編集したときの目印
//※記事を編集したら日付の後ろに付きます
const UPDATE_MARK = ' *';

//名前引用時の「さん」
const A_NAME_SAN = 'さん';

//「そうだね」
const SODANE = 'そうだね';

/* -------------------- */

//テーマがXHTMLか 1:XHTML 0:HTML
const TH_XHTML = 0;

/* テンプレートファイル名に".blade.php"は不要 */

//メインのテンプレートファイル
const MAINFILE = "monoreita_main";

//レスのテンプレートファイル
const RESFILE = "monoreita_res";

//お絵かき(PaintBBS NEO/しぃペインター)のテンプレートファイル
const PAINTFILE = "monoreita_paint";

//お絵かき(chickenPaint/Klecks/Tegaki/Axnos)のテンプレートファイル
const PAINTFILE_BE = "monoreita_be";

//動画再生(PaintBBS NEO/しぃペインター)のテンプレートファイル
const ANIMEFILE = "monoreita_anime";

//動画再生(Tegaki)のテンプレートファイル
const ANIMEFILE_TEGAKI = "monoreita_tgkr_view";

//投稿時のテンプレートファイル
const PICFILE = "monoreita_picpost";

//カタログ、検索モードのテンプレートファイル
const CATALOGFILE = "monoreita_catalog";

//管理モードのテンプレートファイル
const ADMINFILE = "monoreita_admin";

//管理モードの記事詳細テンプレートファイル
const ADMINPOSTFILE = "monoreita_admin_post";

//管理モードのエラーログテンプレートファイル
const ADMINERRORLOGFILE = "monoreita_admin_errorlog";

//管理モードの一時画像管理テンプレートファイル
const ADMINTEMPORARYFILE = "monoreita_admin_temporary_images";

//SNSシェア選択のテンプレートファイル
const SET_SHARE_SERVER = "monoreita_sns_share";

//misskey関係のテンプレートファイル
const MISSKEYFILE = "monoreita_misskey_note";

//その他のテンプレートファイル
const OTHERFILE = "monoreita_other";

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
const RE_START = '<span class="resma">';
const RE_END = '</span>';
