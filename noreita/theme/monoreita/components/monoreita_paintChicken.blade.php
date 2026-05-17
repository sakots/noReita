<section id="cp">
  <div id="chickenpaint-parent"></div>
  <p></p>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      new ChickenPaint({
        uiElem: document.getElementById("chickenpaint-parent"),
        canvasWidth: {{$picw}},
        canvasHeight: {{$pich}},

        @if (isset($imgfile)) loadImageUrl: "{{$imgfile}}", @endif
        @if (isset($pchfile)) loadChibiFileUrl: "{{$pchfile}}", @endif
        saveUrl: "{{$self}}?mode=saveimage&tool=chi&stime={{$stime}}{!! (isset($resto) && $resto != null) ? '&resto='.(int)$resto : '' !!}",
        postUrl: "{{$self}}?mode={!!$mode!!}&stime={{$stime}}@if (isset($resto) && $resto != null)&resto={{$resto}}@endif",
        exitUrl: "{{$self}}" + (resto ? "?resto=" + resto : ""),

        allowDownload: true,
        resourcesRoot: "{{$chicken_dir}}",
        disableBootstrapAPI: true,
        fullScreenMode: "force"

      });
    })
  </script>
</section>