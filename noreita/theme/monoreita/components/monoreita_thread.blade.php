<section>
  @include('components.monoreita_threadOyaName', ['bbsline' => $bbsline])
  @if ($bbsline['picfile'])
    @include('components.monoreita_threadOyaPicfile', ['bbsline' => $bbsline])
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
          </a>
          を押してください。
        </p>
      </div>
    @endif
    @if (!empty($bbsline['res']))
      @foreach ($bbsline['res'] as $res)
        @if (!isset($res['resno']) || $res['resno'] > $bbsline['res_d_su'])
          <section class="res">
            @include('components.monoreita_threadRepName', ['res' => $res])
            @if ($res['picfile'])
              @include('components.monoreita_threadRepPicfile', ['res' => $res])
            @endif
            <p class="comment">{!! $res['com'] !!}</p>
          </section>
        @endif
      @endforeach
    @endif
  </div>
  @include('components.monoreita_threadFooter', ['bbsline' => $bbsline])
</section>
