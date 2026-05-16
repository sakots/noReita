<form action="{{$self}}?mode=del" method="post">
  <p>
    <select name="delt">
      <option value="1">レス</option>
      <option value="0">親</option>
    </select>
    No <input class="form" type="text" name="delno" value="" autocomplete="section-no">
    Pass <input class="form" type="password" name="pwd" value="" autocomplete="new-password">
    <input class="button" type="submit" value=" 削除 ">
    <input type="hidden" name="admindel" value="admindel">
  </p>
</form>