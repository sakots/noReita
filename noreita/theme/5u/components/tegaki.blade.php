<script>
  const getHttpStatusMessage = (response_status) => {
    // HTTP ステータスコードに基づいてメッセージを返す関数
    switch (response_status) {
      case 400:
        return "Bad Request";
      case 401:
        return "Unauthorized";
      case 403:
        return "Forbidden";
      case 404:
        return "Not Found";
      case 500:
        return "Internal Server Error";
      case 502:
        return "Bad Gateway";
      case 503:
        return "Service Unavailable";
      default:
        return "Unknown Error";
    }
  }

  const showAlert = (text) => {
    if (Tegaki.saveReplay) {
      Tegaki.replayRecorder.start();
    }
    alert(text);
  }
  Tegaki.open({
    // when the user clicks on Finish
    onDone: function() {

      //Petit Note 2021-2025 (c)satopian MIT License
      //この箇所はさとぴあが作成したMIT Licenseのコードです。

      if (Tegaki.saveReplay) {
        Tegaki.replayRecorder.stop();
      }
      const postData = (path, data) => {

        fetch(path, {
          method: 'post',
          mode: 'same-origin',
          headers: {
            'X-Requested-With': 'tegaki',
          },
          body: data,
        })
        .then((response) => {
          if (response.ok) {
            response.text().then((text) => {
              console.log(text)
              if (text === 'ok') {
                @if (isset($rep))
                  return repData();
                @endif
                Tegaki.hide(); //｢このサイトを離れますか?｣を解除
                return window.location.href = "{{$self}}?mode=piccom" + (resto ? "&resto=" + resto : "");
              }
              return showAlert(text);
            })
          } else {
            const HttpStatusMessage = getHttpStatusMessage(response.status);

            return showAlert(
              @if (isset($en))
                `Your picture upload failed!\nPlease try again!\n( HTTP status code ${response.status} : ${HttpStatusMessage} )`
              @else
                `投稿に失敗。\n時間を置いて再度投稿してみてください。\n( HTTPステータスコード ${response.status} : ${HttpStatusMessage} )`
              @endif
            );
          }
        })
        .catch((error) => {
          return showAlert(
            @if (isset($en))
              'Server or line is unstable.\nPlease try again!'
            @else
              'サーバまたは回線が不安定です。\n時間を置いて再度投稿してみてください。'
            @endif
          );
        })
      }

      @if (isset($rep))
        const repData = () => {

          // 画像差し換えに必要なフォームデータをセット
          const formData = new FormData();
          formData.append("mode", "picrep");
          formData.append("no", "{{$no}}");
          formData.append("id", "{{$id}}");
          formData.append("enc_pwd", "{{$pwd}}");
          formData.append("repcode", "{{$repcode}}");
          formData.append("paint_picrep", true);

          // 画像差し換え
          fetch("./", {
            method: 'POST',
            mode: 'same-origin',
            headers: {
              'X-Requested-With': 'tegaki',
            },
            body: formData
          })
          .then(response => {
            if (response.ok) {
              if (response.redirected) {
                Tegaki.hide(); //｢このサイトを離れますか?｣を解除
                return window.location.href = response.url;
              }
              response.text().then((text) => {
                if (text.startsWith("error\n")) {
                  console.log(text);
                  Tegaki.hide(); //｢このサイトを離れますか?｣を解除
                  return window.location.href = "{{$self}}?mode=piccom";
                }
              })
            }
          })
          .catch(error => {
            console.error('There was a problem with the fetch operation:', error);
            Tegaki.hide(); //｢このサイトを離れますか?｣を解除
            return window.location.href = "{{$self}}?mode=piccom";
          });
        }
      @endif

      Tegaki.flatten().toBlob(
        function(blob) {
          // console.log(blob);
          const tgkr = Tegaki.replayRecorder ? Tegaki.replayRecorder.toBlob() : null;
          const formData = new FormData();
          let DataSize = 1000;
          let max_pch = 2000;
          max_pch = Number(max_pch) * 1024 * 1024;
          if (tgkr) {
            DataSize = DataSize + blob.size + tgkr.size;
            if (!max_pch || isNaN(max_pch) || (DataSize < max_pch)) {
              formData.append("tgkr", tgkr, 'blob');
            }
          }
          formData.append("picture", blob, 'blob');
          @if (isset($rep))
            formData.append("repcode", "{{$repcode}}");
          @endif
          formData.append("tool", "tegaki");
          formData.append("stime", {{time()}});
          formData.append("resto", resto);
          postData("{{$self}}?mode=saveimage&tool=tegaki", formData);
        },
        'image/png'
      );
    },
    // (c)satopian MIT License ここまで

    // when the user clicks on Cancel
    onCancel: function() {
      console.log('Closing...')
    },
    // initial canvas size
    width: {{$picw}},
    height: {{$pich}},
    saveReplay: @if (isset($imgfile)) false @else true @endif,

  });

  @if (isset($imgfile))
    var self = Tegaki;
    var image = new Image();
    image.onload = function() {
      self.activeLayer.ctx.drawImage(image, 0, 0);
      TegakiLayers.syncLayerImageData(self.activeLayer);
    };
    image.src = "{{$imgfile}}"; // image URL
  @endif
</script>