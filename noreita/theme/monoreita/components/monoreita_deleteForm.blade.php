<form class="delf" action="{{$self}}" method="post">
  <p>
    No <input class="form" type="number" min="1" name="delno" value="" autocomplete="off" required>
    Pass <input class="form" type="password" name="pwd" value="" autocomplete="current-password">
    <select class="form" name="mode">
      <option value="edit">編集</option>
      <option value="del">削除</option>
    </select>
    <input class="button" type="submit" value=" OK ">
    <label for="mystyle">Color</label>
    <span class="stylechanger">
      <select class="form" name="select" id="mystyle" onchange="SetCss(this);">
        <option value="reita/mono.min.css">REITA</option>
        <option value="red/mono.min.css">RED</option>
        <option value="main/mono.min.css">MONO</option>
        <option value="dark/mono.min.css">dark</option>
        <option value="deep/mono.min.css">deep</option>
        <option value="mayo/mono.min.css">MAYO</option>
        <option value="dev/mono.min.css">DEV</option>
        <option value="sql/mono.min.css">SQL</option>
        <option value="pop/mono.min.css">POP</option>
      </select>
    </span>
  </p>
</form>
<script>
  colorIdx = GetCookie('_monoreita_colorIdx');
  document.getElementById("mystyle").selectedIndex = colorIdx;
</script>