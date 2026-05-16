<h3>[{{$res['tid']}}] {{$res['sub']}}</h3>
<h4>
  名前：
  <span class="resname">{{$res['a_name']}}
    @if ($res['admins'] == 1)
      <span class="mingcute--user-star-fill"></span>
    @endif
  </span>：
  @if ($res['modified'] == $res['created'])
    {{$res['modified']}}
  @else
    {{$res['created']}} {{$updatemark}} {{$res['modified']}}
  @endif
  @if ($res['mail'])
    <span class="mail"><a href="mailto:{{$res['mail']}}">[mail]</a></span>
  @endif
  @if ($res['a_url'])
    <span class="url"><a href="{{$res['a_url']}}" target="_blank" rel="nofollow noopener noreferrer">[URL]</a></span>
  @endif
  @if ($display_id)
    <span class="id">ID：{{$res['id']}}</span>
  @endif
    <span class="sodane">
    <a href="{{$self}}?mode=sodane&amp;resto={{$res['tid']}}">{{$sodane}}
      @if ($res['sodane'] != 0)
        x{{$res['sodane']}}
      @else
        +
      @endif
    </a>
  </span>
</h4>