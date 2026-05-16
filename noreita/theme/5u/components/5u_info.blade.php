@section('info')
<ul>
  <li>iPadやスマートフォンでも描けるお絵かき掲示板です。</li>
  <li>お絵かきできるサイズは幅300～{{$pmax_w}}px、高さ300～{{$pmax_h}}pxです。</li>
  @foreach ($addinfo as $info) @if (!empty($info[$loop->index]))
  <li>{!! $addinfo[$loop->index] !!}</li>
  @endif @endforeach
</ul>
@show
