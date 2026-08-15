<p>投稿検索</p>
<form class="search" method="GET" action="{{$self}}">
  <input type="hidden" name="mode" value="search">
  <label>対象
    <select name="target">
      <option value="author"@if ($search_criteria['target'] === 'author') selected @endif>作者名</option><option value="subject"@if ($search_criteria['target'] === 'subject') selected @endif>題名</option>
      <option value="comment"@if ($search_criteria['target'] === 'comment') selected @endif>本文</option><option value="all"@if ($search_criteria['target'] === 'all') selected @endif>すべて</option>
    </select>
  </label>
  <label><input type="radio" name="match" value="partial"@if ($search_criteria['match'] === 'partial') checked @endif>部分一致</label>
  <label><input type="radio" name="match" value="exact"@if ($search_criteria['match'] === 'exact') checked @endif>完全一致</label>
  <br>
  <label>投稿 <select name="post_type"><option value="all"@if ($search_criteria['post_type'] === 'all') selected @endif>すべて</option><option value="thread"@if ($search_criteria['post_type'] === 'thread') selected @endif>親記事</option><option value="reply"@if ($search_criteria['post_type'] === 'reply') selected @endif>レス</option></select></label>
  <label>画像 <select name="image"><option value="any"@if ($search_criteria['image'] === 'any') selected @endif>指定なし</option><option value="with"@if ($search_criteria['image'] === 'with') selected @endif>あり</option><option value="without"@if ($search_criteria['image'] === 'without') selected @endif>なし</option></select></label>
  <label>NSFW <select name="nsfw"><option value="any"@if ($search_criteria['nsfw'] === 'any') selected @endif>指定なし</option><option value="safe"@if ($search_criteria['nsfw'] === 'safe') selected @endif>なし</option><option value="nsfw"@if ($search_criteria['nsfw'] === 'nsfw') selected @endif>あり</option></select></label>
  <label>順序 <select name="sort"><option value="newest"@if ($search_criteria['sort'] === 'newest') selected @endif>新着順</option><option value="oldest"@if ($search_criteria['sort'] === 'oldest') selected @endif>古い順</option></select></label>
  <br>
  <input type="search" name="search" placeholder="検索語（100文字まで）" size="20" maxlength="100" value="{{$search_criteria['query']}}">
  <input type="submit" value=" 検索 ">
</form>
