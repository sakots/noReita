@section('togglePaletteVisibility')
<script>
// パレットの表示/非表示を切り替える関数
function togglePaletteVisibility() {
  const toolsSelect = document.getElementById('tools');
  const paletteContainer = document.getElementById('palette-container');
  const selectedTool = toolsSelect.value;

  // PaintBBS NEOまたはしぃペインターが選択されている場合のみパレットを表示
  if (selectedTool === 'neo' || selectedTool === 'shi') {
    paletteContainer.style.display = 'inline';
  } else {
    paletteContainer.style.display = 'none';
  }
}

// ページ読み込み時に初期状態を設定
document.addEventListener('DOMContentLoaded', function() {
  togglePaletteVisibility();
});
</script>
@show
