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
        <option value="plane/5u.min.css">plane</option>
      </select>
    </span>
  </p>
</form>
<script>
  colorIdx = GetCookie('_5u_colorIdx');
  document.getElementById("mystyle").selectedIndex = colorIdx;
</script>