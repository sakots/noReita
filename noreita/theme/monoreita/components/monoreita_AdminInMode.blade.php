<section>
  <div class="thread">
    <h1 class="oekaki">管理モードin</h1>
    <hr>
    <form action="{{$self}}?mode=admin_login" method="post">
      <input type="hidden" name="token" value="{{$token}}">
      <label>ADMIN PASS <input class="form" type="password" name="adminpass" size="8" autocomplete="current-password" required></label>
      <input class="button" type="submit" value="SUBMIT">
    </form>
    <hr>
    <p>
      <img alt="GitHub release (latest by date)" src="https://img.shields.io/github/v/release/sakots/noReita?label=Latest%20release">
    </p>
    <p>
      <a href="#" onclick="javascript:window.history.back(-1);return false;">[もどる]</a>
    </p>
  </div>
</section>
