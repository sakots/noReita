<div class="timeid">
  <form class="watch" action="index.html" name="watch">
    <p>
      PaintTime :
      <input type="text" size="24" name="count">
    </p>
    <script type="text/javascript">
      timerID = 10;
      stime = new Date();
      function SetTimeCount() {
        now = new Date();
        s = Math.floor((now.getTime() - stime.getTime())/1000);
        disp = '';
        if(s >= 86400) {
          d = Math.floor(s/86400);
          disp += d+"day ";
          s -= d*86400;
        }
        if(s >= 3600) {
          h = Math.floor(s/3600);
          disp += h+"hr ";
          s -= h*3600;
        }
        if(s >= 60) {
          m = Math.floor(s/60);
          disp += m+"min ";
          s -= m*60;
        }
        document.watch.count.value = disp+s+"sec";
        clearTimeout(timerID);
        timerID = setTimeout('SetTimeCount()',250);
      }
      SetTimeCount();
      if (window.dynamicPaletteManager && window.dynamicPaletteManager.DynamicColor) {
        window.dynamicPaletteManager.PaletteListSetColor();
      }
    </script>
  </form>
  <hr>
</div>