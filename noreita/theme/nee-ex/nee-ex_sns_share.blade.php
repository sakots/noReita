<!DOCTYPE html>
<html lang="ja">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	@include('nee-ex_headcss')
  <style>
    form.form_radio_sns_server {
      margin: 1em 0 0;
    }
    input[type=submit]{
      font-size: 18px;
    }
    .form_radio_sns_server label {
      display: block;
      margin: 0 0 5px;
      padding: 2px;
      border-radius: 5px;
    }
    .form_radio_sns_server input[type="text"] {
      margin: 3px 0;
    }
    input.post_share_button {
      width: 100%;
      margin: 8px 0 0;
    }
    :not(input){
      -moz-user-select: none;
      -webkit-user-select: none;
      -ms-user-select: none;
      user-select: none;
    }
	</style>
	<title>Share</title>
</head>
<body>
  <form action="{{$self}}" method="POST" class="form_radio_sns_server">
    @foreach($servers as $i => $server)
    <label for="{{$i}}">
      @if($i===0||$server[1]===$sns_server_radio_cookie)
      <input type="radio" name="sns_server_radio" value="{{$server[1]}}" id="{{$i}}" checked="checked">
      @else
      <input type="radio" name="sns_server_radio" value="{{$server[1]}}" id="{{$i}}">
      @endif
      {{$server[0]}}
    </label>
    @endforeach
    <input type="text" name="sns_server_direct_input" value="{{$sns_server_direct_input_cookie}}">
    <br>
    例: https://mstdn.jp/
    <br>
    <input type="hidden" name="encoded_t" value="{{$encoded_t}}">
    <input type="hidden" name="encoded_u" value="{{$encoded_u}}">
    <input type="hidden" name="mode" value="post_share_server">
    <input type="submit" value="シェア" class="post_share_button">
  </form>
</body>
</html>
