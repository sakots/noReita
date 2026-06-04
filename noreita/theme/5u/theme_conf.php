<?php
//--------------------------------------------------
// 「noReita」v3.0.0～用テーマ「5u」設定ファイル
//  by sakots https://oekakibbs.moe/
//--------------------------------------------------

//テーマ名
const THEME_NAME = "5u";

//テーマのバージョン
const THEME_VER = "0.0.0 lot.260604.0";

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
const MAINFILE = "5u_main";

//レスのテンプレートファイル
const RESFILE = "5u_res";

//お絵かき(PaintBBS NEO/しぃペインター)のテンプレートファイル
const PAINTFILE = "5u_paint";

//お絵かき(chickenPaint/Klecks/Tegaki/Axnos)のテンプレートファイル
const PAINTFILE_BE = "5u_be";

//動画再生(PaintBBS NEO/しぃペインター)のテンプレートファイル
const ANIMEFILE = "5u_anime";

//動画再生(Tegaki)のテンプレートファイル
const ANIMEFILE_TEGAKI = "5u_tgkr_view";

//投稿時のテンプレートファイル
const PICFILE = "5u_picpost";

//カタログ、検索モードのテンプレートファイル
const CATALOGFILE = "5u_catalog";

//管理モードのテンプレートファイル
const ADMINFILE = "5u_admin";

//SNSシェア選択のテンプレートファイル
const SET_SHARE_SERVER = "5u_sns_share";

//misskey関係のテンプレートファイル
const MISSKEYFILE = "5u_misskey_note";

//その他のテンプレートファイル
const OTHERFILE = "5u_other";

//描画時間の書式
//※日本語だと、"1日1時間1分1秒"
//※英語だと、"1day 1hr 1min 1sec"
const PTIME_D = '日';
const PTIME_H = '時間';
const PTIME_M = '分';
const PTIME_S = '秒';

//＞が付いた時の書式
//※RE_STARTとRE_ENDで囲むのでそれを考慮して
//ここは変更せずにcssで設定するの推奨
const RE_START = '<span class="resma">';
const RE_END = '</span>';
