@section('paging')
<p>
  @if ($back === 0)
  <span class="se">[START]</span>
  @else
  <span class="se">&lt;&lt;<a href="{{$self}}?page={{$back}}">[BACK]</a></span>
  @endif
  @foreach ($paging as $pp)
  @if ($pp['p'] == $nowpage)
  <em class="thispage">[{{$pp['p']}}]</em>
  @else
  <a href="{{$self}}?page={{$pp['p']}}">[{{$pp['p']}}]</a>
  @endif
  @endforeach
  @if ($next == ($max_page + 1))
  <span class="se">[END]</span>
  @else
  <span class="se"><a href="{{$self}}?page={{$next}}">[NEXT]</a>&gt;&gt;</span>
  @endif
</p>
@show
