<ul>
  <li>iPadやスマートフォンでも描けるお絵かき掲示板です。</li>
  @foreach ($addinfo as $info)
    @if (!empty($info[$loop->index]))
    <li>{!! $addinfo[$loop->index] !!}</li>
    @endif
  @endforeach
</ul>
