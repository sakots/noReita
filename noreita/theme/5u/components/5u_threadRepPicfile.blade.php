<h5>
  {{$res['tool']}} ({{$res['img_w']}}x{{$res['img_h']}})
  @if ($display_painttime && $res['psec'] != null)
    描画時間：{{$res['utime']}}
  @endif
  @if ($res['nsfw'] == 1)
    ★NSFW
  @endif
</h5>
<h5>
  <a target="_blank" href="{{$path}}{{$res['picfile']}}">{{$res['picfile']}}</a>
  @if ($res['pchfile'] && (!isset($res['ctype']) || $res['ctype'] !== 'img') && ($res['tool'] == ("neo" || "PaintBBS NEO" || "Tegaki" || "Tegaki.js")))
    <a href="{{$self}}?mode=anime&amp;pch={{$res['pchfile']}}" target="_blank">●動画</a>
  @endif
  @if ($use_continue)
    <a href="{{$self}}?mode=continue&amp;no={{$res['picfile']}}">●続きを描く</a>
  @endif
  @if ($use_misskey_note)
    <a href="{{$self}}?mode=before_misskey_note&amp;no={{$res['tid']}}"><span class="simple-icons--misskey"></span> Misskeyにノート</a>
  @endif
</h5>
@if ($res['nsfw'] == 1)
  <a class="luminous" href="{{$path}}{{$res['picfile']}}">
    <span class="nsfw">
      @if ($res['thumb'])
        <img src="{{$path}}{{$res['thumb']}}" alt="{{$res['picfile']}}" loading="lazy" class="image">
      @else
        <img src="{{$path}}{{$res['picfile']}}" alt="{{$res['picfile']}}" loading="lazy" class="image">
      @endif
    </span>
  </a>
@else
  <a class="luminous" href="{{$path}}{{$res['picfile']}}">
  @if ($res['thumb'])
    <img src="{{$path}}{{$res['thumb']}}" alt="{{$res['picfile']}}" loading="lazy" class="image">
  @else
    <img src="{{$path}}{{$res['picfile']}}" alt="{{$res['picfile']}}" loading="lazy" class="image">
  @endif
  </a>
@endif
