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
  <a class="copy-image" href="{{$path}}{{$res['picfile']}}" data-image-url="{{$path}}{{$res['picfile']}}"><span class="carbon--copy-to-clipboard" aria-hidden="true"></span> <span class="copy-image-label">画像をコピー</span></a>
  @if ($res['pchfile'] && (!isset($res['ctype']) || $res['ctype'] !== 'img') && in_array($res['tool'], ['neo', 'PaintBBS NEO', 'Tegaki', 'Tegaki.js'], true))
    <a href="{{$self}}?mode=anime&amp;pch={{$res['pchfile']}}" target="_blank"><span class="mdi--animation-play"></span>動画</a>
  @endif
  @if ($use_continue)
    <a href="{{$self}}?mode=continue&amp;no={{$res['picfile']}}"><span class="fa7-solid--paint-brush"></span>続きを描く</a>
  @endif
  @if ($use_misskey_note)
    <a href="{{$self}}?mode=before_misskey_note&amp;no={{$res['tid']}}"><span class="simple-icons--misskey"></span> Misskeyにノート</a>
  @endif
</h5>
@if ($res['nsfw'] == 1)
  <a class="luminous" href="{{$path}}{{$res['picfile']}}">
    <span class="nsfw@if (str_ends_with($res['picfile'], '.avif')) nsfw-browser-blur@endif">
      @if (str_ends_with($res['picfile'], '.avif'))
        <img src="{{$path}}{{$res['picfile']}}" alt="{{$res['picfile']}}" loading="lazy" class="image">
      @elseif ($res['thumb'])
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
