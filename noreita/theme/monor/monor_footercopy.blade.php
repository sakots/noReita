@section('footercopy')
<div class="copy">
	<!-- 著作権表示 -->
	<p>
		<a href="https://dev.oekakibbs.net/" target="_blank">noReita {{$ver}}</a>
		Web Style by <a href="https://oekakibbs.moe/" target="_blanc" title="monor {{$tver}} (by sakots,お絵かきBBSラボ)">monor</a>
	</p>
	<p>
		OekakiApplet -
		@if ($use_shi_painter)
		<!-- https://hp.vector.co.jp/authors/VA016309/ -->
		<span title="by しぃちゃん">Shi-Painter, </span>
		@endif
		<a href="https://github.com/funige/neo/" target="_blank" rel="noopener noreferrer" title="by funige">PaintBBS NEO</a>
		@if ($use_chicken)
		, <a href="https://github.com/satopian/ChickenPaint_Be" target="_blank" rel="nofollow noopener noreferrer" title="by Nicholas Sherlock">ChickenPaint Be</a>
		@endif
		@if ($use_tegaki)
		, <a href="https://github.com/desuwa/tegaki" target="_blank" rel="noopener noreferrer" title="by Maxime Youdine">Tegaki</a>
		@endif
		@if ($use_klecks)
		, <a href="https://github.com/bitbof/klecks" target="_blank" rel="noopener noreferrer" title="by bitbof">Klecks</a>
		@endif
		@if ($use_axnos)
		, <a href="https://github.com/axnospaint/axnospaint-lib" target="_blank" rel="noopener noreferrer" title="by Axnos Paint">Axnos Paint</a>
		@endif
	</p>
	<p>
		UseFunction -
		<!-- http://wondercatstudio.com/ -->DynamicPalette,
		<a href="https://github.com/imgix/luminous" target="_blank" rel="noopener noreferrer" title="by imgix">Luminous</a>,
		<a href="https://github.com/EFTEC/BladeOne" target="_blank" rel="noopener noreferrer" title="by EFTEC">BladeOne</a>
	</p>
</div>
@show