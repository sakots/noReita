<section>
  <div class="thread">
    <h1 class="oekaki">投稿フォーム</h1>
    @foreach ($oya as $bbsline)
      <form class="ppost postform" action="{{$self}}?mode=editexec" method="post">
        <table>
          <tr>
            <td>name</td>
            <td><input type="text" name="name" size="28" autocomplete="section-edit username" value="{{$bbsline['a_name']}}" maxlength="{{$max_name}}"></td>
          </tr>
          <tr>
            <td>mail</td>
            <td><input type="text" name="mail" size="28" autocomplete="email" value="{{$bbsline['mail']}}" maxlength="{{$max_email}}"></td>
          </tr>
          <tr>
            <td>URL</td>
            <td><input type="text" name="url" size="28" autocomplete="url" value="{{$bbsline['a_url']}}" maxlength="{{$max_url}}"></td>
          </tr>
          <tr>
            <td>subject</td>
            <td>
            <input type="text" name="sub" size="35" autocomplete="section-sub" value="{{$bbsline['sub']}}" maxlength="{{$max_sub}}">
            <input type="hidden" name="invz" value="0">
            <input type="hidden" name="sodane" value="0">
            <input type="hidden" name="e_no" value="{{$bbsline['tid']}}">
            @if ($token != null)
            <input type="hidden" name="token" value="{{$token}}">
            @else
            <input type="hidden" name="token" value="">
            @endif
            </td>
          </tr>
          <tr>
            <td>comment</td>
            <td><textarea name="com" cols="48" rows="4" wrap="soft" onkeydown="if(event.ctrlKey&&event.keyCode==13){document.getElementById('send').click();return false};" maxlength="{{$max_com}}" required>{{$bbsline['com']}}</textarea></td>
          </tr>
          <tr>
            <td>pass</td>
            <td><input type="password" name="pwd" size="8" value="{{$pwd_cookie}}" autocomplete="section-edit current-password" onkeydown="if(event.ctrlKey&&event.keyCode==13){document.getElementById('send').click();return false};"></td>
          </tr>
          <tr>
            <td><input type="submit" name="send" id="send" value="書き込む"></td>
            <td>(PCならCtrl + Enterでも書き込めます)</td>
          </tr>
        </table>
      </form>
    @endforeach
  </div>
</section>