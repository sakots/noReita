<form action="{{$self}}?mode=reply" method="post" class="postform" enctype="multipart/form-data">
  <table>
    <tr>
      <td>name @if ($use_name) * @endif</td>
      <td><input type="text" name="name" size="18" value="{{$name_cookie}}" autocomplete="section-reply username" @if ($use_name) required @endif maxlength="{{$max_name}}"></td>
    </tr>
    <tr>
      <td>mail</td>
      <td><input type="text" name="mail" size="18" value="{{$email_cookie}}" autocomplete="email" maxlength="{{$max_email}}"></td>
    </tr>
    <tr>
      <td>URL</td>
      <td><input type="text" name="url" size="18" value="{{$url_cookie}}" autocomplete="url" maxlength="{{$max_url}}"></td>
    </tr>
    <tr>
      <td>subject @if ($use_sub) * @endif</td>
      <td>
        @if ($use_resub)
          <input type="text" name="sub" size="18" value="Re:{{$bbsline['sub']}}" autocomplete="section-sub" @if ($use_sub) required @endif maxlength="{{$max_sub}}">
        @else
          <input type="text" name="sub" size="18" value="" autocomplete="section-sub" @if ($use_sub) required @endif maxlength="{{$max_sub}}">
        @endif
        <input type="hidden" name="picfile" value="">
        <input type="hidden" name="parent" value="{{$resno}}">
        <input type="hidden" name="invz" value="0">
        <input type="hidden" name="img_w" value="0">
        <input type="hidden" name="img_h" value="0">
        <input type="hidden" name="time" value="0">
        <input type="hidden" name="sodane" value="0">
        <input type="hidden" name="modid" value="{{$resno}}">
        <input type="hidden" name="resto" value="{{$resno}}">
        @if ($token != null)
          <input type="hidden" name="token" value="{{$token}}">
        @else
          <input type="hidden" name="token" value="">
        @endif
      </td>
    </tr>
    <tr>
      <td>comment * </td>
      <td>
        <textarea name="com" rows="5" cols="48" id="p_input_com" onkeydown="if(event.ctrlKey&&event.keyCode==13){document.getElementById('submit').click();return false};"></textarea required maxlength="{{$max_com}}">
      </td>
    </tr>
    <tr>
      <td>pass</td>
      <td>
        <input type="password" name="pwd" size="8" value="{{$pwd_cookie}}" autocomplete="section-reply current-password" onkeydown="if(event.ctrlKey&&event.keyCode==13){document.getElementById('submit').click();return false};">
        (記事の編集削除用。英数字で)
      </td>
    </tr>
    <tr>
      <td><input type="submit" id="submit" name="send" value="書き込む"></td>
      <td>
        (PCならCtrl + Enterでも書き込めます)
      </td>
    </tr>
  </table>
</form>