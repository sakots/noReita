<h4 class="oya">
  <span class="oyaname"><a href="{{$self}}?mode=search&amp;similar=exact&amp;search={{$bbsline['a_name']}}">{{$bbsline['a_name']}}</a></span>
  @if ($bbsline['admins'] == 1)
    <span class="mingcute--user-star-fill"></span>
  @endif
  @if ($bbsline['modified'] == $bbsline['created'])
    {{$bbsline['modified']}}
  @else
    {{$bbsline['created']}} {{$updatemark}} {{$bbsline['modified']}}
  @endif
  @if ($bbsline['mail'])
    <span class="mail"><a href="mailto:{{$bbsline['mail']}}">[mail]</a></span>
  @endif
  @if ($bbsline['a_url'])
    <span class="url"><a href="{{$bbsline['a_url']}}" target="_blank" rel="nofollow noopener noreferrer">[URL]</a></span>
  @endif
  @if ($display_id)
    <span class="id">ID：{{$bbsline['id']}}</span>
  @endif
  <span class="sodane"><a href="{{$self}}?mode=sodane&amp;resto={{$bbsline['tid']}}">
  {{$sodane}}
  @if ($bbsline['sodane'] != 0)
    x{{$bbsline['sodane']}}
  @else
    +
  @endif
  </a></span>
</h4>
