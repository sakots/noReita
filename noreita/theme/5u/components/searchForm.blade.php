<p>作者名/本文(ハッシュタグ)検索</p>
<form class="search" method="GET" action="{{$self}}">
  <input type="hidden" name="mode" value="search">
  <label><input type="radio" name="similar" value="similar">部分一致</label>
  <label><input type="radio" name="similar" value="exact">完全一致</label>
  <label><input type="radio" name="tag" value="tag">本文(ハッシュタグ)</label>
  <br>
  <input type="text" name="search" placeholder="検索" size="20">
  <input type="submit" value=" 検索 ">
</form>