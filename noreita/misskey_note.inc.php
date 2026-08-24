<?php
//Petit Note 2021-2026 (c)satopian MIT LICENSE
//https://paintbbs.sakura.ne.jp/
//https://oekakibbs.moe/
//APIを使ってお絵かき掲示板からMisskeyにノート noReita版

const MISSKEY_NOTE_VER = 20260817; //misskey_note.inc.phpのバージョン

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
      'admins'   => $post['admins'],
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
      'pwd'      => $post['pwd'],
    ];
  } catch (PDOException $e) {
    error($en ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
  }
  return null;
}

// 投稿の存在確認
function check_post_exists(int $no, ApplicationContext $context): bool {
  $en = $context->english;
  try {
    $db = Database::connect();

    $sql = "SELECT COUNT(*) as count FROM board_log WHERE tid = :no";
    $stmt = $db->prepare($sql);
    $stmt->execute([':no' => $no]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result['count'] > 0;
  } catch (PDOException $e) {
    error($en ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
  }
  return false;
}

// 投稿のパスワード検証
function verify_post_password(int $no, string $id, string $pwd, ApplicationContext $context): bool {
  $en = $context->english;
  try {
    $db = Database::connect();

    $sql = "SELECT pwd FROM board_log WHERE tid = :no AND id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':no' => $no, ':id' => $id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
      return false;
    }

    return password_verify($pwd, $post['pwd']);
  } catch (PDOException $e) {
    error($en ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
  }
  return false;
}

// 投稿の編集権限チェック
function check_edit_permission(int $no, string $id, string $pwd, bool $admin, ApplicationContext $context): bool {
  $en = $context->english;
  try {
    $db = Database::connect();

    $sql = "SELECT created, admins FROM board_log WHERE tid = :no AND id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':no' => $no, ':id' => $id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
      return false;
    }

    if ($admin || $post['admins'] === 'admin_post') {
      return true;
    }

    if (!$pwd) {
      return false;
    }

    return verify_post_password($no, $id, $pwd, $context);
  } catch (PDOException $e) {
    error($en ? 'Database operation failed.' : 'データベース処理に失敗しました。', 500, $e);
  }
  return false;
}

class misskey_note {

  //投稿済みの記事をMisskeyにノートするための前処理
  public static function before_misskey_note(ApplicationContext $context): void {
    $en = $context->english;
    $template_engine = $context->templates;
    $dat =& $context->data;
    //管理者判定処理
    RequestSecurity::startSession();
    $admin_post = admin_post_valid();
    $admin_del = admin_del_valid();

    $dat['pwd_cookie'] = (string)filter_input_data('COOKIE', 'pwd_cookie');
    $dat['no'] = t(filter_input_data('POST', 'no', FILTER_VALIDATE_INT));
    $dat['no'] = $dat['no'] ? $dat['no'] : t(filter_input_data('GET', 'no', FILTER_VALIDATE_INT));

    if (!$dat['no']) {
      error($en ? 'Invalid post number.' : '投稿番号が無効です。');
    }

    if (!check_post_exists($dat['no'], $context)) {
      error($en ? 'The article does not exist.' : '記事がありません。', 404);
    }

    $post = get_post_from_db($dat['no'], $context);
    if (!$post) {
        error($en ? 'The article was not found.' : '記事が見つかりません。', 404);
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
    $en = $context->english;
    $template_engine = $context->templates;
    $dat =& $context->data;

    try {
      RequestSecurity::assertCurrentSameOriginRequest($en);
    } catch (RequestSecurityException $e) {
      error($e->getMessage(), $e->getCode() ?: 403);
    }

    $dat['token'] = RequestSecurity::csrfToken();

    $dat['admin_del'] = admin_del_valid();
    $dat['admin_post'] = admin_post_valid();
    $dat['admin'] = ($dat['admin_del'] || $dat['admin_post']);

    $pwd = (string)filter_input_data('POST', 'pwd');
    $pwd_cookie = (string)filter_input_data('COOKIE', 'pwd_cookie');
    $pwd = $pwd ? $pwd : $pwd_cookie;

    $id_and_no = (string)filter_input_data('POST', 'id_and_no');

    list($id, $no) = explode(",", trim($id_and_no));

    if (!$no) {
      error($en ? 'Invalid post number.' : '投稿番号が無効です。');
    }

    if (!check_post_exists($no, $context)) {
      error($en ? 'The article does not exist.' : '記事がありません。', 404);
    }

    if (!check_edit_permission($no, $id, $pwd, $dat['admin'], $context)) {
      error($en ? 'Password is incorrect.' : 'パスワードが違います。', 403);
    }

    check_AsyncRequest();

    $post = get_post_from_db($no, $context);
    if (!$post) {
      error($en ? 'The article was not found.' : '記事が見つかりません。', 404);
    }
    $dat['path'] = Config::string('paths.images');
    $dat['post'] = $post;

    // Misskeyサーバーリストをセット
    $dat['misskey_servers'] = Config::array('social.misskey_servers');

    $dat['nsfw_c'] = (bool)filter_input_data('COOKIE', 'nsfw_c', FILTER_VALIDATE_BOOLEAN);
    $dat['set_nsfw_show_hide'] = (bool)filter_input_data('COOKIE', 'p_n_set_nsfw_show_hide', FILTER_VALIDATE_BOOLEAN);

    $page = $_SESSION['current_page_context']["page"] ?? 0;
    $resno = $_SESSION['current_page_context']["resno"] ?? null; //下の行でnull判定
    $resno ?? $no;

    $user_del = false;
    $admin_del = false;

    $image_rep = false;

    $_SESSION['current_id'] = $id;

    $admin_pass = null;
    // HTML出力
    $dat['misskey_mode'] = 'note_edit_form';

    echo $template_engine->render(MISSKEYFILE, $dat);
    exit();
  }

  //Misskeyに投稿するSESSIONデータを作成
  public static function create_misskey_note_sessiondata(ApplicationContext $context): void {
    $en = $context->english;

    try {
      RequestSecurity::assertCurrentCsrfRequest($en);
    } catch (RequestSecurityException $e) {
      error($e->getMessage(), $e->getCode() ?: 403);
    }

    $userip = t(RequestInfo::clientIp());
    $no = t(filter_input_data('POST', 'no', FILTER_VALIDATE_INT));
    $src_image = t(filter_input_data('POST', 'src_image'));
    $com = t(filter_input_data('POST', 'com'));
    $abbr_toolname = t(filter_input_data('POST', 'abbr_toolname'));
    $paintsec = (int)filter_input_data('POST', 'paintsec', FILTER_VALIDATE_INT);
    $hide_thumbnail = (bool)filter_input_data('POST', 'hide_thumbnail', FILTER_VALIDATE_BOOLEAN);
    $show_painttime = (bool)filter_input_data('POST', 'show_painttime', FILTER_VALIDATE_BOOLEAN);
    $article_url_link = (bool)filter_input_data('POST', 'article_url_link', FILTER_VALIDATE_BOOLEAN);
    $hide_content = (bool)filter_input_data('POST', 'hide_content', FILTER_VALIDATE_BOOLEAN);
    $cw = t(filter_input_data('POST', 'cw'));

    if ($hide_content && !$cw) {
      error($en ? 'Content warning field is empty.' : '注釈がありません。', 400);
    }

    check_AsyncRequest();

    $cw = $hide_content ? $cw : null;
    $tool = switch_tool($abbr_toolname);

    $painttime = calcPtime($paintsec);
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
    $en = $context->english;

    try {
      RequestSecurity::assertCurrentSameOriginRequest($en);
    } catch (RequestSecurityException $e) {
      error($e->getMessage(), $e->getCode() ?: 403);
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
      error($en
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
    $Location = "{$baseUrl_to_set_in_session}/miauth/{$sns_api_session_id}?name=noReita&callback={$encoded_root_url}connect_misskey_api.php&permission=write:notes,write:drive";

    if (isset($_SESSION['accessToken'])) { //SESSIONのトークンが有効か確認
      // ダミーの投稿を試みる（textフィールドを空にする）
      $postUrl = "{$baseUrl_to_set_in_session}/api/notes/create";
      $postData = array(
        'i' => $_SESSION['accessToken'],
        'text' => '', // 投稿を成功させないようにするためtextフィールドを空にする
      );

      $postCurl = curl_init();
      $security_options = MisskeyServerSecurity::curlOptions($baseUrl_to_set_in_session);
      $safe_curl = $postCurl !== false && is_array($security_options)
        && curl_setopt_array($postCurl, $security_options);
      if (!$safe_curl) {
        unset($_SESSION['accessToken']);
      } else {
        curl_setopt($postCurl, CURLOPT_URL, $postUrl);
        curl_setopt($postCurl, CURLOPT_POST, true);
        curl_setopt($postCurl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($postCurl, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($postCurl, CURLOPT_RETURNTRANSFER, true);
      }
      $postResponse = $safe_curl ? curl_exec($postCurl) : false;
      $postStatusCode = $safe_curl ? (int)curl_getinfo($postCurl, CURLINFO_HTTP_CODE) : 0;
      if ($postCurl !== false) curl_close($postCurl);

      // HTTPステータスコードが403の時は、トークン不一致と判断しアプリを認証
      if ($postStatusCode === 403 || $postResponse === false) {
        unset($_SESSION['accessToken']); //トークンをクリア
      } elseif (in_array($postStatusCode, [200, 204], true)) {
        //アプリの認証をスキップするURL
        $Location = Config::string('site.base_url') . "connect_misskey_api.php?skip_auth_check=on&s_id={$sns_api_session_id}";
      }
    }

    redirect($Location);
  }

  // Misskeyへの投稿が成功した事を知らせる画面
  public static function misskey_success(ApplicationContext $context): void {
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
