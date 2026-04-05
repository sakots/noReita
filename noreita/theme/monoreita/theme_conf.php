<?php
//--------------------------------------------------
// 「noReita」v3.0.0～用テーマ「monoreita」設定ファイル
//  by sakots https://oekakibbs.moe/
//--------------------------------------------------

//テーマ名
const THEME_NAME = "monoreita";

//テーマのバージョン
const THEME_VER = "3.0.0 lot.260405.0";

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

//動画再生のテンプレートファイル
const ANIMEFILE = "monoreita_anime";

//投稿時のテンプレートファイル
const PICFILE = "monoreita_picpost";

//カタログ、検索モードのテンプレートファイル
const CATALOGFILE = "monoreita_catalog";

//管理モードのテンプレートファイル
const ADMINFILE = "monoreita_admin";

//SNSシェア選択のテンプレートファイル
const SET_SHARE_SERVER = "monoreita_sns_share";

//misskey関係のテンプレートファイル
const MISSKEYFILE = "monoreita_misskey_note";

//その他のテンプレートファイル
const OTHERFILE = "monoreita_other";

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
