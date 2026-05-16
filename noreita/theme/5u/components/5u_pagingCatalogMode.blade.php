<section class="paging">
  <p>
    @if ($back === 0)
      <span class="se">[START]</span>
    @else
      <span class="se">&lt;&lt;<a href="{{$self}}?mode=catalog&amp;page={{$back}}">[BACK]</a></span>
    @endif
    @foreach ($paging as $pp)
      @if ($pp['p'] == $nowpage)
        <em class="thispage">[{{$pp['p']}}]</em>
      @else
        <a href="{{$self}}?mode=catalog&amp;page={{$pp['p']}}">[{{$pp['p']}}]</a>
      @endif
    @endforeach
    @if ($next == ($max_page + 1))
      <span class="se">[END]</span>
    @else
      <span class="se"><a href="{{$self}}?mode=catalog&amp;page={{$next}}">[NEXT]</a>&gt;&gt;</span>
    @endif
  </p>
</section>