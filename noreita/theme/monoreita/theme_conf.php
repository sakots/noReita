<?php
//--------------------------------------------------
// 「noReita」v1.3.0～用テーマ「monoreita」設定ファイル
//  by sakots https://oekakibbs.moe/
//--------------------------------------------------

//テーマ名
define('THEME_NAME', "monoreita");

//テーマのバージョン
define('THEME_VER', "1.8.7 lot.250612.0");

/* -------------------- */

//編集したときの目印
//※記事を編集したら日付の後ろに付きます
define('UPDATE_MARK', ' *');

//名前引用時の「さん」
define('A_NAME_SAN', 'さん');

//「そうだね」
define('SODANE', 'そうだね');

/* -------------------- */

//テーマがXHTMLか 1:XHTML 0:HTML
define('TH_XHTML', 0);

/* テンプレートファイル名に".blade.php"は不要 */

//メインのテンプレートファイル
define('MAINFILE', "monoreita_main");

//レスのテンプレートファイル
define('RESFILE', "monoreita_res");

//お絵かきのテンプレートファイル
define('PAINTFILE', "monoreita_paint");

//chickenPaintのテンプレートファイル
define('PAINTFILE_BE', "monoreita_be");

//動画再生のテンプレートファイル
define('ANIMEFILE', "monoreita_anime");

//投稿時のテンプレートファイル
define('PICFILE', "monoreita_picpost");

//カタログ、検索モードのテンプレートファイル
define('CATALOGFILE', "monoreita_catalog");

//管理モードのテンプレートファイル
define('ADMINFILE', "monoreita_admin");

//SNSシェア選択のテンプレートファイル
define('SET_SHARE_SERVER', "monoreita_sns_share");

//misskey関係のテンプレートファイル
define('MISSKEYFILE', "monoreita_misskey_note");

//その他のテンプレートファイル
define('OTHERFILE', "monoreita_other");

//描画時間の書式
//※日本語だと、"1日1時間1分1秒"
//※英語だと、"1day 1hr 1min 1sec"
define('PTIME_D', '日');
define('PTIME_H', '時間');
define('PTIME_M', '分');
define('PTIME_S', '秒');

//＞が付いた時の書式
//※RE_STARTとRE_ENDで囲むのでそれを考慮して
//ここは変更せずにcssで設定するの推奨
define('RE_START', '<span class="resma">');
define('RE_END', '</span>');

//エラーメッセージ
define('MSG001', "該当記事がみつかりません[Log is not found.]");
define('MSG002', "絵が選択されていません[Picture has not been selected.]");
define('MSG003', "アップロードに失敗しました[It failed in up-loading.]<br>サーバーがサポートしていない可能性があります[There is a possibility that the server doesn't support it.]");
define('MSG004', "アップロードに失敗しました[It failed in up-loading.]<br>画像ファイル以外は受け付けません[It is not accepted excluding the picture file.]");
define('MSG005', "アップロードに失敗しました[It failed in up-loading.]<br>同じ画像がありました[The same image existed.]");
define('MSG006', "不正な投稿です[Please do not do an illegal contribution.]<br>POST以外での投稿は受け付けません[The contribution excluding 'POST' is not accepted.]");
define('MSG007', "画像がありません[no image.]");
define('MSG008', "何か書いて下さい[write something.]");
define('MSG009', "名前がありません[no name.]");
define('MSG010', "題名がありません[no subject]");
define('MSG011', "本文が長すぎます[comment is too long.]");
define('MSG012', "名前が長すぎます[name is too long.]");
define('MSG013', "メールアドレスが長すぎます[email is too long.]");
define('MSG014', "題名が長すぎます[subject is too long.]");
define('MSG015', "異常です[Abnormality]");
define('MSG016', "拒絶されました[was rejected.]<br>そのHOSTからの投稿は受け付けません[Post from the 'HOST' is not accepted.]");
define('MSG017', "ＥＲＲＯＲ！[Error]　公開ＰＲＯＸＹ規制中！！[Open-PROXY is limited.](80)");
define('MSG018', "ＥＲＲＯＲ！[Error]　公開ＰＲＯＸＹ規制中！！[Open-PROXY is limited.](8080)");
define('MSG019', "ログの読み込みに失敗しました[It failed in reading the log.]");
define('MSG020', "連続投稿はもうしばらく時間を置いてからお願い致します[Please wait for a continuous post for a while.]");
define('MSG021', "画像連続投稿はもうしばらく時間を置いてからお願い致します[Please wait for a continuous post of the image for a while.]");
define('MSG022', "このコメントで一度投稿しています[Post once by this comment.]<br>別のコメントでお願い致します[Please put another comment.]");
define('MSG023', "ツリーの更新に失敗しました[It failed in the renewal of the tree.]");
define('MSG024', "ツリーの削除に失敗しました[It failed in the deletion of the tree.]");
define('MSG025', "スレッドがありません[no thread.]");
define('MSG026', "スレッドが最後の1つなので削除できません[thread is the last one, not delete.]");
define('MSG027', "削除に失敗しました(ユーザー)[failed in deletion.(User)]");
define('MSG028', "該当記事が見つからないかパスワードが間違っています[article is not found or password is wrong.]");
define('MSG029', "パスワードが違います[password is wrong.]");
define('MSG030', "削除に失敗しました(管理者権限)[failed in deletion.(Admin)]");
define('MSG031', "記事Noが未入力です[Please input No.]");
define('MSG032', "拒絶されました[was rejected.]<br>不正な文字列があります[illegal character string.]");
define('MSG033', "削除に失敗しました[failed in deletion.]<br>ユーザーに削除権限がありません[user doesn't have deletion authority.]");
define('MSG034', "アップロードに失敗しました[It failed in up-loading.]<br>規定の画像容量をオーバーしています[size over is picture file.]");
define('MSG035', "何か日本語で書いてください[Comment should have at least some Japanese characters.]");
define('MSG036', "本文にそのURLを書く事はできません。[This URL can not be used in text.]");
define('MSG037', "予備");
define('MSG038', "予備");
define('MSG039', "予備");
define('MSG040', "予備");
