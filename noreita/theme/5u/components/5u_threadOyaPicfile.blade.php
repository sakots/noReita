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
  @if ($bbsline['pchfile'] && (!isset($bbsline['ctype']) || $bbsline['ctype'] !== 'img') && ($bbsline['tool'] == ("neo" || "PaintBBS NEO" || "Tegaki" || "Tegaki.js")))
    <a href="{{$self}}?mode=anime&amp;pch={{$bbsline['pchfile']}}" target="_blank">●動画</a>
  @endif
  @if ($use_continue)
    <a href="{{$self}}?mode=continue&amp;no={{$bbsline['picfile']}}">●続きを描く</a>
  @endif
</h5>
<div class="item_image">
  <a class="luminous" href="{{$path}}{{$bbsline['picfile']}}">
    <span @if ($bbsline['nsfw'] == 1) class="nsfw" @endif>
      @if ($bbsline['thumb'])
        <img src="{{$path}}{{$bbsline['thumb']}}" alt="{{$bbsline['picfile']}}" loading="lazy" class="image">
      @else
        <img src="{{$path}}{{$bbsline['picfile']}}" alt="{{$bbsline['picfile']}}" loading="lazy" class="image">
      @endif
    </span>
  </a>
</div>
