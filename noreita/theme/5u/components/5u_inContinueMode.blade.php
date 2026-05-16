<section>
  <div class="thread">
    <h1 class="oekaki">続きから描く</h1>
    @foreach ($oya as $bbsline)
      <figure>
        <img src="{{$path}}{{$bbsline['picfile']}}">
        <figcaption>
          {{$bbsline['picfile']}}
          @if ($display_painttime && ($bbsline['psec'] != null))
            描画時間：{{$bbsline['utime']}}
          @endif
        </figcaption>
      </figure>
      <hr class="hr">
      <form action="{{$self}}" method="post" enctype="multipart/form-data">
        <input type="hidden" name="mode" value="contpaint">
        <input type="hidden" name="anime" value="true">
        <input type="hidden" name="picw" value="{{$bbsline['img_w']}}">
        <input type="hidden" name="pich" value="{{$bbsline['img_h']}}">
        <input type="hidden" name="no" value="{{$bbsline['tid']}}">
        @if ($ctype_pch)
          <input type="hidden" name="pch" value="{{$bbsline['pchfile']}}">
        @endif
        <input type="hidden" name="img" value="{{$bbsline['picfile']}}">
        <select class="form" name="ctype">
          @if ($ctype_pch)
            <option value="pch" selected>動画から</option>
          @endif
          <option value="img" @if (!$ctype_pch) selected @endif>画像から</option>
        </select>
        <select class="form" name="type">
          <option value="rep">差し替え</option>
          <option value="new">新規投稿</option>
        </select>
        @if ($passflag) Pass<input class="form" type="password" name="pwd" size="8" value=""> @endif
        @if (!$ctype_pch)
          <label for="tools">ツール</label>
          <select name="tools">
            <option value="neo">PaintBBS NEO</option>
            @if ($use_shi_painter)<option value="shi">ShiPainter</option> @endif
            @if ($use_chicken)<option value="chicken">ChickenPaint</option> @endif
            @if ($use_klecks)<option value="klecks">Klecks</option> @endif
            @if ($use_tegaki)<option value="tegaki">Tegaki</option> @endif
            @if ($use_axnos)<option value="axnos">Axnos</option> @endif
          </select>
        @else
          <input type="hidden" name="tools" value="{{$tool}}">
        @endif
        @if ($tool == 'neo' || $tool == 'shi')
          <label for="palettes">パレット</label>
          @if ($select_palettes)
            <select name="palettes" id="palettes">
            @foreach ($pallets_dat as $palette)
              <option value="{{$pallets_dat[$loop->index][1]}}" id="{{$loop->index}}">{{$pallets_dat[$loop->index][0]}}</option>
            @endforeach
          </select>
          @else
            <select name="palettes" id="palettes">
            <option value="neo">標準</option>
          </select>
          @endif
        @endif
        <input class="button" type="submit" value="続きを描く">
      </form>
      <ul>
        @if ($passflag)
          @if ($newpost_nopassword)
            <li>新規投稿なら削除キーがなくても続きを描く事ができます。</li>
          @else
            <li>続きを描くには描いたときの削除キーが必要です。</li>
          @endif
        @endif
      </ul>
    @endforeach
  </div>
</section>