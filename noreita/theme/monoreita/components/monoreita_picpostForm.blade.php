<form action="{{$self}}" method="post" enctype="multipart/form-data">
  <p>
    <label>幅：<input class="form" type="number" min="300" max="{{$pmax_w}}" name="picw" value="{{$pdef_w}}" required></label>
    <label>高さ：<input class="form" type="number" min="300" max="{{$pmax_h}}" name="pich" value="{{$pdef_h}}" required></label>
    <input type="hidden" name="mode" value="paint">
    <label for="tools">ツール</label>
    <select name="tools" id="tools" onchange="togglePaletteVisibility()">
      <option value="neo">PaintBBS NEO</option>
      @if ($use_shi_painter)<option value="shi">しぃペインター</option> @endif
      @if ($use_chicken)<option value="chicken">litaChix</option> @endif
      @if ($use_klecks)<option value="klecks">Klecks</option> @endif
      @if ($use_tegaki)<option value="tegaki">Tegaki.js</option> @endif
      @if ($use_axnos)<option value="axnos">AxnosPaint</option> @endif
    </select>
    <span id="palette-container" style="display: none;">
      <label for="palettes">パレット</label>
      @if ($select_palettes)
      <select name="palettes" id="palettes">
        @foreach ($pallets_dat as $palette)
        <option value="{{$pallets_dat[$loop->index][1]}}" id="{{$loop->index}}">{{$pallets_dat[$loop->index][0]}}</option>
        @endforeach
      </select>
      @else
      <select name="palettes" id="palettes">
        <option value="neo" id="0">標準</option>
      </select>
      @endif
    </span>
    @if ($useanime)
    <label><input type="checkbox" value="true" name="anime" title="動画記録" @if ($defanime) checked @endif>アニメーション記録</label>
    @endif
    <input class="button" type="submit" value="お絵かき">
    @if (isset($resno)) <input type="hidden" name="modid" value="{{$resno}}"> @endif
    @if (isset($resno)) <input type="hidden" name="resto" value="{{$resno}}"> @endif
  </p>
  <ul>
    <li>お絵かきできるサイズは幅300～{{$pmax_w}}px、高さ300～{{$pmax_h}}pxです。</li>
  </ul>
</form>
