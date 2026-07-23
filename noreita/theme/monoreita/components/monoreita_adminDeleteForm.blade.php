<form action="{{$self}}?mode=del" method="post">
  <p>
    No <input class="form" type="number" min="1" name="delno" value="" autocomplete="off" required>
    <input class="button" type="submit" value=" 削除 ">
    <input type="hidden" name="admindel" value="admindel">
    <input type="hidden" name="token" value="{{$token}}">
  </p>
</form>
