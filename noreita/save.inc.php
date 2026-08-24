<?php
//Petit Note (c)さとぴあ @satopian 2021-2025 MIT License
//https://paintbbs.sakura.ne.jp/

const SAVE_INC_VER = 20260820; //save.inc.phpのバージョン

final class PaintSaveCapacityException extends RuntimeException {}

final class PaintSaveRequestGuard {
  private const FILE_FIELDS = [
    'neo' => ['picture', 'pch'],
    // LitaChix/ChickenPaintはPNG・.chiに加えて、編集済みパレットをswatchesとして送信する。
    'chi' => ['picture', 'chibifile', 'swatches'],
    'klecks' => ['picture', 'psd'],
    'tegaki' => ['picture', 'tgkr'],
    'axnos' => ['picture'],
    'animation_upload' => ['picture', 'animation'],
  ];

  /**
   * @param array<string,mixed> $server リクエストサーバー情報
   * @param array<string,mixed> $post POST値
   * @param array<string,mixed> $files アップロードファイル情報
   * @param string $tool 描画ツール識別子
   * @param int $image_max_bytes 画像ファイルの上限バイト数
   * @param int $work_max_bytes 動画・作業ファイルの上限バイト数
   * @param int $request_max_bytes リクエスト全体の上限バイト数
   * @throws PaintSaveCapacityException
   */
  public static function assertWithinLimits(
    array $server,
    array $post,
    array $files,
    string $tool,
    int $image_max_bytes,
    int $work_max_bytes,
    int $request_max_bytes
  ): void {
    if (!isset(self::FILE_FIELDS[$tool])) {
      throw new PaintSaveCapacityException('Invalid drawing tool.', 400);
    }
    $content_length = $server['CONTENT_LENGTH'] ?? null;
    if ($content_length !== null && $content_length !== '') {
      if (!is_scalar($content_length) || preg_match('/\A[0-9]+\z/D', (string)$content_length) !== 1) {
        throw new PaintSaveCapacityException('Invalid request size.', 400);
      }
      if ((int)$content_length > $request_max_bytes) {
        throw new PaintSaveCapacityException('The drawing upload is too large.', 413);
      }
    }

    $allowed = self::FILE_FIELDS[$tool];
    $uploaded_bytes = 0;
    $uploaded_files = 0;
    foreach ($files as $field => $file) {
      if (!is_string($field) || !in_array($field, $allowed, true) || !is_array($file)) {
        throw new PaintSaveCapacityException('Invalid drawing upload fields.', 400);
      }
      foreach (['error', 'size', 'tmp_name'] as $key) {
        if (!array_key_exists($key, $file) || is_array($file[$key])) {
          throw new PaintSaveCapacityException('Invalid drawing upload fields.', 400);
        }
      }
      $error = (int)$file['error'];
      if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new PaintSaveCapacityException('The drawing upload is too large.', 413);
      }
      if ($error !== UPLOAD_ERR_OK) continue;
      $uploaded_files++;
      if ($uploaded_files > count($allowed)) {
        throw new PaintSaveCapacityException('Too many drawing upload files.', 400);
      }
      $declared_size = max(0, (int)$file['size']);
      $actual_size = is_file((string)$file['tmp_name']) ? @filesize((string)$file['tmp_name']) : false;
      $file_size = max($declared_size, $actual_size === false ? 0 : (int)$actual_size);
      $file_limit = $field === 'picture' ? $image_max_bytes : $work_max_bytes;
      if ($file_size > $file_limit) {
        throw new PaintSaveCapacityException('The drawing upload is too large.', 413);
      }
      $uploaded_bytes += $file_size;
      if ($uploaded_bytes > $request_max_bytes) {
        throw new PaintSaveCapacityException('The drawing upload is too large.', 413);
      }
    }

    $parsed_bytes = self::valueBytes($post, $request_max_bytes + 1);
    if ($uploaded_bytes + $parsed_bytes > $request_max_bytes) {
      throw new PaintSaveCapacityException('The drawing upload is too large.', 413);
    }
    if ((int)$content_length > 0 && $post === [] && $files === []) {
      // post_max_size超過でPHPがPOSTデータを破棄した場合を容量超過として扱う。
      throw new PaintSaveCapacityException('The drawing upload is too large.', 413);
    }
  }

  /**
   * @param int $width 画像幅
   * @param int $height 画像高
   * @param int $max_width 最大画像幅
   * @param int $max_height 最大画像高
   * @param int $max_pixels 最大ピクセル数
   * @throws PaintSaveCapacityException
   */
  public static function assertImageDimensions(
    int $width,
    int $height,
    int $max_width,
    int $max_height,
    int $max_pixels = 20000000
  ): void {
    if ($width < 1 || $height < 1
      || $width > $max_width || $height > $max_height
      || $width * $height > $max_pixels) {
      throw new PaintSaveCapacityException('The image dimensions are too large.', 413);
    }
  }

  /**
   * @param array<string,mixed> $values
   * @param int $stop_after この値以上は打ち切るバイト数
   */
  private static function valueBytes(array $values, int $stop_after): int {
    $bytes = 0;
    $pending = [$values];
    $visited = 0;
    while ($pending !== []) {
      $value = array_pop($pending);
      if (++$visited > 2000) return $stop_after;
      foreach ($value as $key => $item) {
        $bytes += strlen((string)$key);
        if (is_array($item)) $pending[] = $item;
        elseif (is_scalar($item) || $item === null) $bytes += strlen((string)$item);
        else return $stop_after;
        if ($bytes >= $stop_after) return $bytes;
      }
    }
    return $bytes;
  }
}

class image_save{

  /** @var int|string */
  private $security_timer,$imgfile,$en,$count,$errtext,$session_usercode;
  /** @var int|string */
  private $tool,$repcode,$stime,$resto,$timer,$error_type,$hide_animation,$pmax_w,$pmax_h,$usercode_header;
  
  function __construct(int $security_timer = 0, int $pmax_w = 0, int $pmax_h = 0){
  $this->security_timer = $security_timer;
  $this->pmax_w = $pmax_w;
  $this->pmax_h = $pmax_h;
  if(($_SERVER["REQUEST_METHOD"]) !== "POST"){
    redirect("./");
  }

  $lang = ($http_langs = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
  ? explode( ',', $http_langs )[0] : '';
  $this->en= (stripos($lang,'ja')!==0);

  $request_tool = (string)filter_input_data('GET', 'tool');
  $this->error_type = $request_tool === 'neo' ? 'neo' : ($request_tool === 'chi' ? 'chi' : $request_tool);
  try {
    PaintSaveRequestGuard::assertWithinLimits(
      $_SERVER,
      $_POST,
      $_FILES,
      $request_tool,
      Config::int('limits.paint_image_kb') * 1024,
      Config::int('limits.paint_work_kb') * 1024,
      Config::int('limits.paint_request_kb') * 1024
    );
  } catch (PaintSaveCapacityException $e) {
    $this->error_msg(
      $this->en ? $e->getMessage() : ($e->getCode() === 413 ? 'お絵描き保存データの容量が大きすぎます。' : 'お絵描き保存リクエストが不正です。'),
      $e->getCode() ?: 400
    );
  }

  $this->imgfile = time().substr(microtime(),2,6);  //画像ファイル名
  $this->imgfile = is_file(Config::string('paths.temporary').$this->imgfile.'.png') ? ((time()+1).substr(microtime(),2,6)) : $this->imgfile;
  
  $this->pmax_w= $pmax_w ?? '';
  $this->pmax_h= $pmax_h ?? '';
  
  }

  public function save_klecks(): void {

    $this->error_type="klecks";

    $this->tool = t(filter_input_data('POST', 'tool'));
    $this->repcode = t(filter_input_data('POST', 'repcode'));
    $this->resto = t(filter_input_data('POST', 'resto',FILTER_VALIDATE_INT));
    $this->stime = t(filter_input_data('POST', 'stime',FILTER_VALIDATE_INT));
    $this->hide_animation = t(filter_input_data('POST', 'hide_animation'));

    $this->check_security();
    $this->move_uploaded_image();
    $this->move_uploaded_psd();
    $this->put_user_dat();

    echo "ok";
    exit;

  }
  public function save_neo(): void {

    $this->error_type="neo";

    $sendheader = (string)filter_input_data('POST','header');

    $sendheader = str_replace("&amp;", "&", $sendheader);
    $this->tool = 'neo';
    
    //拡張ヘッダから情報を取得    
    /** @var array<string,string> $u */
    $u = [];
    parse_str($sendheader, $u);
    $this->repcode = isset($u['repcode']) ? t($u['repcode']) : '';
    $this->resto = isset($u['resto']) ? t($u['resto']) : '';
    $this->stime = isset($u['stime']) ? t($u['stime']) : '';
    $this->hide_animation = isset($u['hide_animation']) ? t($u['hide_animation']) : '';
    $this->usercode_header = isset($u['usercode']) ? t($u['usercode']) : '';

    $this->count = isset($u['count']) ? t($u['count']) : 0;

    $this->check_security();
    $this->move_uploaded_image();
    $this->move_uploaded_pch();
    $this->put_user_dat();

    echo "ok";
    exit;
  }

  public function save_chickenpaint(): void {

    $this->error_type="chi";
    $this->tool = 'chi';
    $this->repcode = t(filter_input_data('GET', 'repcode'));
    $this->resto = t(filter_input_data('GET', 'resto',FILTER_VALIDATE_INT));
    $this->stime = t(filter_input_data('GET', 'stime',FILTER_VALIDATE_INT));

    $this->check_security();
    $this->move_uploaded_image();
    $this->move_uploaded_chi();
    $this->put_user_dat();

    echo "CHIBIOK\n";
    exit;
  }

  private function check_async_request(): void {
    if(isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
      return;
    }
    if(isset($_SERVER['HTTP_ORIGIN']) || isset($_SERVER['HTTP_REFERER'])) {
      return;
    }
    $this->error_msg($this->en ? "The post has been rejected." : "拒絶されました。", 403);
  }

  private function check_security(): void {

    $this->check_async_request();

    RequestSecurity::startSession();
    $this->session_usercode = $_SESSION['usercode'] ?? "";
    $cookie_usercode = t(filter_input_data('COOKIE', 'usercode'));
    if ($this->session_usercode && $cookie_usercode) {
      if ($this->session_usercode !== $cookie_usercode) {
        $this->error_msg($this->en ? "User code has been reissued.\nPlease try again." : "ユーザーコードを再発行しました。\n再度投稿してみてください。", 403);
      }
    } elseif ($cookie_usercode) {
      $this->session_usercode = $cookie_usercode;
    } elseif (!empty($this->usercode_header)) {
      $this->session_usercode = $this->usercode_header;
    } else {
      $this->error_msg($this->en ? "User code has been reissued.\nPlease try again." : "ユーザーコードを再発行しました。\n再度投稿してみてください。", 403);
    }

    $sec_fetch_site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    $same_origin = ($sec_fetch_site === 'same-origin');
    
    if(!isset($_SERVER['HTTP_ORIGIN']) || !isset($_SERVER['HTTP_HOST'])){
      $this->error_msg($this->en ? "Your browser is not supported." : "お使いのブラウザはサポートされていません。", 400);
    }
    if(!$same_origin && (parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) !== $_SERVER['HTTP_HOST'])){
      $this->error_msg($this->en ? "The post has been rejected." : "拒絶されました。", 403);
    }

    $this->timer=time()-(int)$this->stime;

    if((bool)$this->security_timer && !$this->repcode && ((int)$this->timer<(int)$this->security_timer)){

      $psec=(int)$this->security_timer-(int)$this->timer;
      $waiting_time=calcPtime ($psec);
      if($this->en){
        $this->error_msg("Please draw for another {$waiting_time}.", 429);
      }else{
        $this->error_msg("描画時間が短すぎます。あと{$waiting_time}。", 429);
      }
    }
  }

  private function put_user_dat(): void {

    $time=time();
    $u_ip = RequestInfo::clientIp();
    $u_host = $u_ip ? gethostbyaddr($u_ip) : '';
    $u_agent = trim($_SERVER["HTTP_USER_AGENT"]);
    $u_agent = t($u_agent);
    $imgext='.png';
    $this->session_usercode = trim($this->session_usercode);
    $this->repcode = trim($this->repcode);
    $this->stime = trim($this->stime);
    $this->resto = trim($this->resto);
    $this->tool = trim($this->tool);
    $this->hide_animation = isset($this->hide_animation) ? trim($this->hide_animation) : ''; 
    $this->hide_animation = trim($this->hide_animation);
    /* ---------- 投稿者情報記録 ---------- */
    $userdata = "$u_ip\t$u_host\t$u_agent\t$imgext";
    //usercode 差し換え認識コード 描画開始 完了時間 レス先 を追加
    $userdata .= "\t$this->session_usercode\t$this->repcode\t$this->stime\t$time\t$this->resto\t$this->tool\t$this->hide_animation";
    $userdata .= "\n";
    
    // 情報データをファイルに書き込む
    file_put_contents(Config::string('paths.temporary').$this->imgfile.".dat",$userdata,LOCK_EX);
      
    if(!is_file(Config::string('paths.temporary').$this->imgfile.'.dat')){
      $this->error_msg($this->en ? "Your picture upload failed!\nPlease try again!" : "投稿に失敗。\n時間を置いて再度投稿してみてください。", 500);
    }
    chmod(Config::string('paths.temporary').$this->imgfile.'.dat',Config::int('permissions.private_file'));
    // 描画直後のコメント入力では、この画像を投稿対象として固定する。
    $_SESSION['pending_picfile'] = $this->imgfile . '.png';

  }
  
  private function move_uploaded_image(): void {

    if(!isset ($_FILES["picture"]) || $_FILES['picture']['error'] != UPLOAD_ERR_OK) {
      $this->error_msg($this->en ? "Your picture upload failed!\nPlease try again!" : "投稿に失敗。\n時間を置いて再度投稿してみてください。", 400);
    }
    
    if((int)$_FILES['picture']['size'] > (Config::int('limits.paint_image_kb') * 1024)){
      $this->error_msg($this->en ? "The size of the picture is too big. " : "ファイルサイズが大きすぎます。", 413);
    }

    $image_info = @getimagesize($_FILES['picture']['tmp_name']);
    if(!is_array($image_info) || ($image_info['mime'] ?? '') !== 'image/png'){
      $this->error_msg($this->en ? "Your picture upload failed!\nPlease try again!" : "投稿に失敗。\n時間を置いて再度投稿してみてください。", 415);
    }

    try {
      PaintSaveRequestGuard::assertImageDimensions(
        (int)$image_info[0],
        (int)$image_info[1],
        Config::int('limits.paint_max_width'),
        Config::int('limits.paint_max_height')
      );
    } catch (PaintSaveCapacityException $e) {
      $this->error_msg($this->en ? $e->getMessage() : '画像のサイズが大きすぎます。', 413);
    }

    if(function_exists('imagecreatefrompng')){//PNG画像が壊れていたらエラー
      $im_in = @imagecreatefrompng($_FILES['picture']['tmp_name']);
      if(!$im_in){
        $this->error_msg($this->en ? "The image appears to be corrupted.\nPlease consider saving a screenshot to preserve your work." : "破損した画像が検出されました。\nスクリーンショットを撮り作品を保存する事を強くおすすめします。", 422);
      }
      // PHP 8以降のGD画像はオブジェクトなので、参照を外して自動解放に任せる。
      unset($im_in);
    }

    // list($w,$h)=getimagesize($_FILES['picture']['tmp_name']);

    // if($w > $this->pmax_w || $h > $this->pmax_h){//幅と高さ
    //   //規定サイズ違反を検出しました。画像は保存されません。
    //   $this->error_msg($this->en ? "The image dimensions are too large." : "画像のサイズが大きすぎます。");
    // }

    $success = move_uploaded_file($_FILES['picture']['tmp_name'], Config::string('paths.temporary').$this->imgfile.'.png');
    
    if(!$success||!is_file(Config::string('paths.temporary').$this->imgfile.'.png')) {
      $this->error_msg($this->en ? "Your picture upload failed!\nPlease try again!" : "投稿に失敗。\n時間を置いて再度投稿してみてください。", 500);
    }
    chmod(Config::string('paths.temporary').$this->imgfile.'.png',Config::int('permissions.public_file'));
  }

  private function move_uploaded_chi(): void {
    if(isset($_FILES['chibifile']) && ($_FILES['chibifile']['error'] == UPLOAD_ERR_OK)){
      if(mime_content_type($_FILES['chibifile']['tmp_name'])==="application/octet-stream"){
        if($_FILES['chibifile']['size'] <= (Config::int('limits.paint_work_kb') * 1024)){
          //chiファイルのアップロードができなかった場合はエラーメッセージはださず、画像のみ投稿する。 
          move_uploaded_file($_FILES['chibifile']['tmp_name'], Config::string('paths.temporary').$this->imgfile.'.chi');
          if(is_file(Config::string('paths.temporary').$this->imgfile.'.chi')){
            chmod(Config::string('paths.temporary').$this->imgfile.'.chi',Config::int('permissions.public_file'));
          }
        }
      }
    }
  }
  private function move_uploaded_psd(): void {
    if(isset($_FILES['psd']) && ($_FILES['psd']['error'] == UPLOAD_ERR_OK)){
      if(mime_content_type($_FILES['psd']['tmp_name'])==="image/vnd.adobe.photoshop"){
        if($_FILES['psd']['size'] <= (Config::int('limits.paint_work_kb') * 1024)){
          //PSDファイルのアップロードができなかった場合はエラーメッセージはださず、画像のみ投稿する。 
          move_uploaded_file($_FILES['psd']['tmp_name'], Config::string('paths.temporary').$this->imgfile.'.psd');
          if(is_file(Config::string('paths.temporary').$this->imgfile.'.psd')){
            chmod(Config::string('paths.temporary').$this->imgfile.'.psd',Config::int('permissions.public_file'));
          }
        }
      }
    }
    if(isset($_FILES['tgkr']) && ($_FILES['tgkr']['error'] == UPLOAD_ERR_OK)){
      if(mime_content_type($_FILES['tgkr']['tmp_name'])==="application/octet-stream"){
        if($_FILES['tgkr']['size'] <= (Config::int('limits.paint_work_kb') * 1024)){
          //PSDファイルのアップロードができなかった場合はエラーメッセージはださず、画像のみ投稿する。 
          move_uploaded_file($_FILES['tgkr']['tmp_name'], Config::string('paths.temporary').$this->imgfile.'.tgkr');
          if(is_file(Config::string('paths.temporary').$this->imgfile.'.tgkr')){
            chmod(Config::string('paths.temporary').$this->imgfile.'.tgkr',Config::int('permissions.public_file'));
          }
        }
      }
    }
  }
  private function move_uploaded_pch(): void {
    if(isset($_FILES['pch']) && ($_FILES['pch']['error'] == UPLOAD_ERR_OK)){
      if(mime_content_type($_FILES['pch']['tmp_name'])==="application/octet-stream"){
        if($_FILES['pch']['size'] <= (Config::int('limits.paint_work_kb') * 1024)){
          //PSDファイルのアップロードができなかった場合はエラーメッセージはださず、画像のみ投稿する。 
          move_uploaded_file($_FILES['pch']['tmp_name'], Config::string('paths.temporary').$this->imgfile.'.pch');
          if(is_file(Config::string('paths.temporary').$this->imgfile.'.pch')){
            chmod(Config::string('paths.temporary').$this->imgfile.'.pch',Config::int('permissions.public_file'));
          }
        }
      }
    }
  }

  private function error_msg(string $message, int $http_status = 400): void {
    switch ($this->error_type){
      case "neo":
        $errtext="error\n";
      break;
      case "chi":
        $errtext="CHIBIERROR ";
      break;
      case "klecks":
        $errtext="";
      break;
      default:
      $errtext="";
    }

    // LitaChixは非2xx応答の本文を読まず、HTTPステータスだけを一般的なエラー文に置き換える。
    // CHIBIERROR本文を表示させるため、実際の失敗コードはヘッダーとログに残しつつ200で返す。
    $is_litachix = $this->error_type === 'chi';
    ApplicationErrorHandler::respondPlainError(
      $http_status,
      $message,
      (bool)$this->en,
      $errtext,
      'Drawing save API: ' . str_replace(["\r", "\n"], ' ', $message),
      null,
      $is_litachix,
      $is_litachix ? 200 : null
    );
  }
}
