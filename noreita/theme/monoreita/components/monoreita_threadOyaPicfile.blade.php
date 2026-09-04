<h5>
  {{$bbsline['tool']}} ({{$bbsline['img_w']}}x{{$bbsline['img_h']}})
  @if ($bbsline['psec'] != null)
    @if ($display_painttime) 描画時間：{{$bbsline['utime']}} @endif
  @endif
  @if ($bbsline['nsfw'] == 1)
    ★NSFW
  @endif
</h5>
<h5>
  <a target="_blank" href="{{$path}}{{$bbsline['picfile']}}">{{$bbsline['picfile']}}</a>
  <a class="copy-image" href="{{$path}}{{$bbsline['picfile']}}" data-image-url="{{$path}}{{$bbsline['picfile']}}"><span class="carbon--copy-to-clipboard" aria-hidden="true"></span> <span class="copy-image-label">画像をコピー</span></a>
  @if ($bbsline['pchfile'] && (!isset($bbsline['ctype']) || $bbsline['ctype'] !== 'img') && ($bbsline['tool'] == ("neo" || "PaintBBS NEO" || "Tegaki" || "Tegaki.js")))
    <a href="{{$self}}?mode=anime&amp;pch={{$bbsline['pchfile']}}" target="_blank"><span class="mdi--animation-play"></span>動画</a>
  @endif
  @if ($use_continue)
    <a href="{{$self}}?mode=continue&amp;no={{$bbsline['picfile']}}"><span class="fa7-solid--paint-brush"></span>続きを描く</a>
  @endif
</h5>
<div class="item_image">
  <a class="luminous" href="{{$path}}{{$bbsline['picfile']}}">
    <span @if ($bbsline['nsfw'] == 1) class="nsfw@if (str_ends_with($bbsline['picfile'], '.avif')) nsfw-browser-blur@endif" @endif>
      @if ($bbsline['nsfw'] == 1 && str_ends_with($bbsline['picfile'], '.avif'))
        <img src="{{$path}}{{$bbsline['picfile']}}" alt="{{$bbsline['picfile']}}" loading="lazy" class="image">
      @elseif ($bbsline['thumb'])
        <img src="{{$path}}{{$bbsline['thumb']}}" alt="{{$bbsline['picfile']}}" loading="lazy" class="image">
      @else
        <img src="{{$path}}{{$bbsline['picfile']}}" alt="{{$bbsline['picfile']}}" loading="lazy" class="image">
      @endif
    </span>
  </a>
</div>
