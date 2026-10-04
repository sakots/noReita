<?php
//Petit Note 2021-2026 (c)satopian MIT LICENSE
//https://paintbbs.sakura.ne.jp/
//https://oekakibbs.moe/
//APIを使ってお絵かき掲示板からMisskeyにノート noReita版

const MISSKEY_NOTE_VER = 20261004; //misskey_note.inc.phpのバージョン

//設定読み込み
require_once __DIR__ . '/index.php';

// データベースから投稿を取得する
function get_post_from_db(int $no, ApplicationContext $context): ?array {
  $en = $context->english;
  try {
    $db = Database::connect();

    $sql = "SELECT * FROM board_log WHERE tid = :no";
    $stmt = $db->prepare($sql);
    $stmt->execute(['no' => $no]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
      return null;
    }

    return [
      'tid'      => $post['tid'],
      'sub'      => $post['sub'],
      'a_name'   => $post['a_name'],
      'com'      => $post['com'],
      'mail'     => $post['mail'],
      'a_url'    => $post['a_url'],
      'id'       => $post['id'],
      'sodane'   => $post['sodane'],
      'picfile'  => $post['picfile'],
      'pchfile'  => $post['pchfile'],
      'img_w'    => $post['img_w'],
      'img_h'    => $post['img_h'],
      'tool'     => $post['tool'],
      'utime'    => $post['utime'],
      'created'  => $post['created'],
      'modified' => $post['modified'],
      'parent'   => $post['parent'],
      'psec'     => $post['psec'],
      'invz'     => $post['invz'],
      'nsfw'     => $post['nsfw'],
      'image_alt'=> $post['image_alt'],
    ];
  } catch (PDOException $e) {
    render_error($context, $en ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
  }
  return null;
}

require_once __DIR__ . '/misskey_post_authorization.inc.php';

class misskey_note {

  //投稿済みの記事をMisskeyにノートするための前処理
  public static function before_misskey_note(ApplicationContext $context): void {
    MisskeyPostAuthorization::assertFeatureEnabled($context);
    $en = $context->english;
    $template_engine = $context->templates;
    $dat =& $context->data;

    $dat['pwd_cookie'] = (string)filter_input_data('COOKIE', 'pwd_cookie');
    $dat['no'] = t(filter_input_data('POST', 'no', FILTER_VALIDATE_INT));
    $dat['no'] = $dat['no'] ? $dat['no'] : t(filter_input_data('GET', 'no', FILTER_VALIDATE_INT));

    if (!$dat['no']) {
      render_error($context, $en ? 'Invalid post number.' : '投稿番号が無効です。');
    }

    $post = get_post_from_db($dat['no'], $context);
    if (!$post) {
        render_error($context, $en ? 'The article was not found.' : '記事が見つかりません。', 404);
    }

    // 非表示投稿は、管理者または投稿パスワードを知る本人にだけ内容を表示する。
    if ((int)$post['invz'] !== 0 && !MisskeyPostAuthorization::isAdministrator()
      && MisskeyPostAuthorization::authorize((int)$post['tid'], $dat['pwd_cookie']) === null) {
      $dat['token'] = RequestSecurity::csrfToken();
      $dat['misskey_mode'] = 'authorize';
      echo $template_engine->render(MISSKEYFILE, $dat);
      exit();
    }
    $dat['post'] = $post;

    $dat['path'] = Config::string('paths.images');
    $dat['token'] = RequestSecurity::csrfToken();

    // nsfw
    $dat['nsfw_c'] = (bool)filter_input_data('COOKIE', 'nsfw_c', FILTER_VALIDATE_BOOLEAN);
    $dat['set_nsfw_show_hide'] = (bool)filter_input_data('COOKIE', 'p_n_set_nsfw_show_hide', FILTER_VALIDATE_BOOLEAN);

    $dat['count_r_arr'] = count($dat['post']);
    $dat['edit_mode'] = 'edit_mode';

    $admin_pass = null;

    $dat['misskey_mode'] = 'before';
    echo $template_engine->render(MISSKEYFILE, $dat);
    exit();
  }

  //投稿済みの画像をMisskeyにNoteするための投稿フォーム
  public static function misskey_note_edit_form(ApplicationContext $context): void {
    MisskeyPostAuthorization::assertFeatureEnabled($context);
    $en = $context->english;
    $template_engine = $context->templates;
    $dat =& $context->data;

    try {
      RequestSecurity::assertCurrentCsrfRequest($context->usercode, $en);
    } catch (RequestSecurityException $e) {
      render_error($context, $e->getMessage(), $e->getCode() ?: 403);
    }

    $dat['token'] = RequestSecurity::csrfToken();

    $pwd = (string)filter_input_data('POST', 'pwd');
    $pwd_cookie = (string)filter_input_data('COOKIE', 'pwd_cookie');
    $pwd = $pwd ? $pwd : $pwd_cookie;

    $no = filter_input_data('POST', 'no', FILTER_VALIDATE_INT);
    if (!$no) {
      $id_and_no = (string)filter_input_data('POST', 'id_and_no');
      $id_and_no_parts = explode(',', trim($id_and_no), 2);
      $no = filter_var($id_and_no_parts[1] ?? '', FILTER_VALIDATE_INT);
    }

    if (!$no) {
      render_error($context, $en ? 'Invalid post number.' : '投稿番号が無効です。');
    }

    $authorization = MisskeyPostAuthorization::authorize((int)$no, $pwd);
    if ($authorization === null) {
      render_error($context, $en ? 'Password is incorrect.' : 'パスワードが違います。', 403);
    }
    MisskeyPostAuthorization::remember($authorization, $context->usercode);

    check_AsyncRequest();

    $post = get_post_from_db((int)$no, $context);
    $dat['path'] = Config::string('paths.images');
    $dat['post'] = $post;

    // Misskeyサーバーリストをセット
    $dat['misskey_sensitive_checked'] = (bool)$post['nsfw'] ? 'checked' : '';
    $dat['misskey_sensitive_disabled'] = (bool)$post['nsfw'] ? 'disabled' : '';
    $dat['misskey_servers'] = Config::array('social.misskey_servers');

    $dat['nsfw_c'] = (bool)filter_input_data('COOKIE', 'nsfw_c', FILTER_VALIDATE_BOOLEAN);
    $dat['set_nsfw_show_hide'] = (bool)filter_input_data('COOKIE', 'p_n_set_nsfw_show_hide', FILTER_VALIDATE_BOOLEAN);

    $page = $_SESSION['current_page_context']["page"] ?? 0;
    $resno = $_SESSION['current_page_context']["resno"] ?? null; //下の行でnull判定
    $resno ?? $no;

    $user_del = false;
    $admin_del = false;

    $image_rep = false;

    // HTML出力
    $dat['misskey_mode'] = 'note_edit_form';

    echo $template_engine->render(MISSKEYFILE, $dat);
    exit();
  }

  //Misskeyに投稿するSESSIONデータを作成
  public static function create_misskey_note_sessiondata(ApplicationContext $context): void {
    MisskeyPostAuthorization::assertFeatureEnabled($context);
    $en = $context->english;

    try {
      RequestSecurity::assertCurrentCsrfRequest($context->usercode, $en);
    } catch (RequestSecurityException $e) {
      render_error($context, $e->getMessage(), $e->getCode() ?: 403);
    }

    $no = filter_input_data('POST', 'no', FILTER_VALIDATE_INT);
    $com = t(filter_input_data('POST', 'com'));
    $hide_thumbnail = (bool)filter_input_data('POST', 'hide_thumbnail', FILTER_VALIDATE_BOOLEAN);
    $show_painttime = (bool)filter_input_data('POST', 'show_painttime', FILTER_VALIDATE_BOOLEAN);
    $article_url_link = (bool)filter_input_data('POST', 'article_url_link', FILTER_VALIDATE_BOOLEAN);
    $cw_input = filter_input_data('POST', 'cw');
    if ($cw_input !== null && (!is_string($cw_input) || !mb_check_encoding($cw_input, 'UTF-8'))) {
      render_error($context, $en ? 'Invalid content warning.' : '注釈の入力が不正です。', 400);
    }
    $cw = trim(t($cw_input));
    if (mb_strlen($cw, 'UTF-8') > 100) {
      render_error($context, $en ? 'Content warning must be 100 characters or fewer.' : '注釈は100文字以内で入力してください。', 400);
    }
    // 両テーマにある注釈欄の内容で判断する。空欄だけを注釈なしとし、「0」は残す。
    $cw = $cw !== '' ? $cw : null;

    $post = $no ? MisskeyPostAuthorization::authorizedPost((int)$no, $context->usercode) : null;
    if ($post === null) {
      render_error($context, $en ? 'Post authorization is required.' : '投稿者認証が必要です。', 403);
      return;
    }

    // フォーム値が省略・改変されても、掲示板でNSFWの画像を通常画像として共有しない。
    $hide_thumbnail = (bool)$post['nsfw'] || $hide_thumbnail;

    // hidden inputの投稿番号・画像名・描画情報は信用せず、認可直後に再取得したDB値を使う。
    $no = (int)$post['tid'];
    $src_image = (string)$post['picfile'];
    if ($src_image === '') {
      render_error($context, $en ? 'The post does not contain an image.' : '投稿画像がありません。', 400);
    }

    check_AsyncRequest();

    $tool = switch_tool((string)$post['tool']);

    $painttime = calcPtime((int)$post['psec']);
    $painttime_str = '';
    if (is_array($painttime)) {
      $painttime_str = $en ? ($painttime['en'] ?? '') : ($painttime['ja'] ?? '');
    } else {
      $painttime_str = (string)$painttime;
    }
    $painttime_to_session = $show_painttime ? $painttime_str : '';

    RequestSecurity::startSession();

    // 投稿データをセッションに保存
    $_SESSION['misskey_note_data'] = [
      'no' => $no,
      'src_image' => $src_image,
      'com' => $com,
      'tool' => $tool,
      'painttime' => $painttime_to_session,
      'hide_thumbnail' => $hide_thumbnail,
      'article_url_link' => $article_url_link,
      'cw' => $cw
    ];

    // sns_api_valを設定
    $_SESSION['sns_api_val'] = [
      $com,
      $src_image,
      $tool,
      $painttime_to_session,
      $hide_thumbnail,
      $no,
      $article_url_link,
      $cw
    ];

    // Misskeyサーバー認証URLを生成する処理を直接呼び出す
    self::create_misskey_authrequesturl($context);
  }

  // Misskeyサーバー認証URLを生成
  public static function create_misskey_authrequesturl(ApplicationContext $context): void {
    MisskeyPostAuthorization::assertFeatureEnabled($context);
    $en = $context->english;

    try {
      RequestSecurity::assertCurrentSameOriginRequest($context->usercode, $en);
    } catch (RequestSecurityException $e) {
      render_error($context, $e->getMessage(), $e->getCode() ?: 403);
    }

    // ラジオボタンの値
    $misskey_server_radio_value = filter_input_data('POST', "misskey_server_radio"); // フィルタリングしない生の値を取得

    // 直接入力欄の値
    $misskey_server_direct_input_value = filter_input_data('POST', "misskey_server_direct_input"); // フィルタリングしない生の値を取得

    // セッションにセットする最終的なURLを決定する。
    // 設定済み一覧も直接入力も、同じSSRF境界で検証する。
    $baseUrl_to_set_in_session = false;

    if ($misskey_server_radio_value && $misskey_server_radio_value !== 'direct') {
      $baseUrl_to_set_in_session = MisskeyServerSecurity::normalizeBaseUrl(
        (string)$misskey_server_radio_value
      );
    } elseif ($misskey_server_radio_value === 'direct' && $misskey_server_direct_input_value) {
      $baseUrl_to_set_in_session = MisskeyServerSecurity::normalizeBaseUrl(
        (string)$misskey_server_direct_input_value
      );
    }

    // どちらにも有効なURLがない場合エラー
    if (!$baseUrl_to_set_in_session) {
      render_error($context, $en
        ? 'Please select a public HTTPS Misskey server.'
        : '公開HTTPSのMisskeyサーバーを指定してください。', 400);
    }

    // Cookie セット (misskey_server_radio_cookie は "direct" または URLを保存)
    $misskey_server_radio_for_cookie = ($misskey_server_radio_value === 'direct') ? 'direct' : $baseUrl_to_set_in_session;
    setcookie("misskey_server_radio_cookie", $misskey_server_radio_for_cookie, time() + (86400 * 30), "", "", false, true);
    setcookie(
      "misskey_server_direct_input_cookie",
      $misskey_server_radio_value === 'direct' ? $baseUrl_to_set_in_session : '',
      time() + (86400 * 30), "", "", false, true
    );

    RequestSecurity::startSession();
    // セッションIDとユニークIDを結合
    $sns_api_session_id = session_id() . random_bytes(16);

    // SHA256ハッシュ化
    $sns_api_session_id = hash('sha256', $sns_api_session_id);

    $_SESSION['sns_api_session_id'] = $sns_api_session_id;

    $encoded_root_url = urlencode(Config::string('site.base_url'));

    //別のサーバを選択した時はトークンをクリア
    if (!isset($_SESSION['misskey_server_radio']) ||
      $_SESSION['misskey_server_radio'] !== $baseUrl_to_set_in_session) {
      unset($_SESSION['accessToken']); //トークンをクリア
    }
    // 投稿完了画面に表示するサーバのURl としてセッションにセット
    $_SESSION['misskey_server_radio'] = $baseUrl_to_set_in_session;

    //アプリを認証するためのURL
    $Location = "{$baseUrl_to_set_in_session}/miauth/{$sns_api_session_id}?name=noReita&callback={$encoded_root_url}connect_misskey_api.php&permission=read:account,write:notes,write:drive";

    if (isset($_SESSION['accessToken'])) {
      // アカウント取得に成功したトークンだけ再利用する。権限不足・失効・通信失敗時は再認証する。
      if (is_string($_SESSION['accessToken'])
        && MisskeyTokenVerifier::isValid($baseUrl_to_set_in_session, $_SESSION['accessToken'])) {
        $Location = Config::string('site.base_url') . "connect_misskey_api.php?skip_auth_check=on&s_id={$sns_api_session_id}";
      } else {
        unset($_SESSION['accessToken']);
      }
    }

    redirect($Location);
  }

  // Misskeyへの投稿が成功した事を知らせる画面
  public static function misskey_success(ApplicationContext $context): void {
    MisskeyPostAuthorization::assertFeatureEnabled($context);
    $template_engine = $context->templates;
    $dat =& $context->data;
    $no = (string)filter_input_data('GET', 'no', FILTER_VALIDATE_INT);

    RequestSecurity::startSession();

    $misskey_server_url = $_SESSION['misskey_server_radio'] ?? "";
    if (!$misskey_server_url || !filter_var($misskey_server_url, FILTER_VALIDATE_URL) || !$no) {
      redirect('./');
    }
    $admin_pass = null;
    $dat['misskey_mode'] = 'success';
    $dat['no'] = $no;
    echo $template_engine->render(MISSKEYFILE, $dat);
    exit();
  }
}
