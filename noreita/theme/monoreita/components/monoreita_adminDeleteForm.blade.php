<form action="{{$self}}?mode=del" method="post">
  <p>
    No <input class="form" type="text" name="delno" value="" autocomplete="section-no">
    Pass <input class="form" type="password" name="pwd" value="" autocomplete="new-password">
    <input class="button" type="submit" value=" 削除 ">
    <input type="hidden" name="admindel" value="admindel">
  </p>
</form>