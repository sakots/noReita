<section>
  @include('components.threadOyaName', ['bbsline' => $bbsline])
  @if ($bbsline['picfile'])
    @include('components.threadOyaPicfile', ['bbsline' => $bbsline])
  @endif
    <div class="item_comment">
      <p class="comment oya">
        {!! $bbsline['com'] !!}
      </p>
      @if (!empty($bbsline['rflag']))
        <div class="res">
          <p class="limit">
            レス{{$res_d_su}}件省略。すべて見るには
            <a href="{{$self}}?mode=res&amp;res={{$bbsline['tid']}}">
              @if ($elapsed_time === 0 || $nowtime - $bbsline['past'] < $elapsed_time) 返信 @else すべて見る @endif
            </a>を押してください。
          </p>
        </div>
      @endif
      @if (!empty($thread_res))
      @foreach ($thread_res as $res)
      @if (!isset($res['resno']) || $res['resno'] > $res_d_su)
      <section class="res">
        @include('components.threadRepName', ['res' => $res])
        @if ($res['picfile'])
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
          @if ($res['pchfile'] && (!isset($res['ctype']) || $res['ctype'] !== 'img') && ($res['tool'] !== "Chicken Paint"))
            <a href="{{$self}}?mode=anime&amp;pch={{$res['pchfile']}}" target="_blank">●動画</a>
          @endif
          @if ($use_continue)
            <a href="{{$self}}?mode=continue&amp;no={{$res['picfile']}}">●続きを描く</a>
          @endif
        </h5>
        @if ($res['nsfw'] == 1)
          <a class="luminous" href="{{$path}}{{$res['picfile']}}"><span class="nsfw">
          @if ($res['thumb'])
            <img src="{{$path}}{{$res['thumb']}}" alt="{{$res['picfile']}}" loading="lazy" class="image">
        @else
          <img src="{{$path}}{{$res['picfile']}}" alt="{{$res['picfile']}}" loading="lazy" class="image">
      @endif
    </span></a>
      @else
      <a class="luminous" href="{{$path}}{{$res['picfile']}}">
      @if ($res['thumb'])
        <img src="{{$path}}{{$res['thumb']}}" alt="{{$res['picfile']}}" loading="lazy" class="image">
      @else
        <img src="{{$path}}{{$res['picfile']}}" alt="{{$res['picfile']}}" loading="lazy" class="image">
      @endif
      </a>
        @endif
        @endif
        <p class="comment">{!! $res['com'] !!}</p>
      </section>
      @endif
      @endforeach
      @endif
    </div>
  @endif
  @include('components.threadFooter', ['bbsline' => $bbsline])
</section>
