<!-- embed start -->
<script src="{{$klecks_dir}}embed.js?{{$stime}}"></script>
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

  /*
  Using Klecks in a drawing community:
  - on first time opening, start with a manually created project (klecks.openProject)
  - on submit, upload psd (and png) to the server
  - on continuing a drawing, read psd that was stored on server (klecks.readPsd -> klecks.openProject)
  */

  const psdURL = '@if (isset($imgfile)) {{$imgfile}} @endif';

  let saveData = (function() {
    let a = document.createElement("a");
    document.body.appendChild(a);
    a.style = "display: none";
    return function(blob, fileName) {
      let url = window.URL.createObjectURL(blob);
      console.log(url);
      a.href = url;
      a.download = fileName;
      a.click();
      window.URL.revokeObjectURL(url);
    };

  }());

  const klecks = new Klecks({

    disableAutoFit: true,

    onSubmit: (onSuccess, onError) => {
      // download png
      // saveData(klecks.getPNG(), 'drawing.png');

      /*// download psd
      klecks.getPSD().then((blob) => {
      saveData(blob, 'drawing.psd');
      });*/

      setTimeout(() => {
        onSuccess();
        //Petit Note 2021-2024 (c)satopian MIT License
        //この箇所はさとぴあが作成したMIT Licenseのコードです。
        const postData = (path, data) => {
          fetch(path, {
            method: 'post',
            mode: 'same-origin',
            headers: {
            'X-Requested-With': 'klecks',
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
                  return window.location.href = "{{$self}}?mode=piccom" + (resto ? "&resto=" + resto : "");
                }
                return alert(text);
              })
            } else {

              const HttpStatusMessage = getHttpStatusMessage(response.status);

              return alert(
                @if (isset($en))
                  `Your picture upload failed!\nPlease try again!\n( HTTP status code ${response.status} : ${HttpStatusMessage} )`
                @else
                  `投稿に失敗。\n時間を置いて再度投稿してみてください。\n( HTTPステータスコード ${response.status} : ${HttpStatusMessage} )`
                @endif
              );
            }
          })
          .catch((error) => {
            return alert(
              @if (isset($en))
                'Server or line is unstable.\nPlease try again!'
              @else
                'サーバまたは回線が不安定です。\n時間を置いて再度投稿してみてください。'
              @endif
            );
          })
        }
        Promise.all([klecks.getPNG(), klecks.getPSD()]).then(([png, psd]) => {
          const TotalSiz = ((png.size + psd.size) / 1024 / 1024).toFixed(3);
          const max_pch = Number(2000); // 最大サイズ
          if (max_pch && TotalSiz > max_pch) {
            return alert(`
              @if (isset($en))
                File size is too large.
              @else
                ファイルサイズが大きすぎます。
              @endif \n
              @if (isset($en))
                limit size
              @else
                制限値
              @endif
              :${max_pch}MB \n
              @if (isset($en))
                Current size
              @else
                現在値
              @endif
              :${TotalSiz}MB
            `)
          }
          const formData = new FormData();
          formData.append("picture", png, 'blob');
          formData.append("psd", psd, 'blob');
          @if (isset($rep))
            formData.append("repcode", "{{$repcode}}");
          @endif
          formData.append("tool", "klecks");
          formData.append("stime", {{time()}});
          formData.append("resto", resto);
          postData("./?mode=saveimage&tool=klecks", formData);
        });
        // (c)satopian MIT License ここまで
        // location.reload();
      }, 500);
    }
  });
  //Petit Note 2021-2025 (c)satopian MIT License
  //この箇所はさとぴあが作成したMIT Licenseのコードです。
  @if (isset($rep))
    const repData = () => {
      // 画像差し換えに必要なフォームデータをセット
      const formData = new FormData();
      formData.append("mode", "picrep");
      formData.append("no", "{{$no}}");
      formData.append("enc_pwd", "{{$enc_pwd}}");
      formData.append("repcode", "{{$repcode}}");
      formData.append("paint_picrep", true);

      // 画像差し換え
      fetch("./", {
        method: 'POST',
        mode: 'same-origin',
        headers: {
        'X-Requested-With': 'klecks',
        },
        body: formData
      })
      .then(response => {
        if (response.ok) {
          if (response.redirected) {
            return window.location.href = response.url;
          }
          response.text().then((text) => {
            if (text.startsWith("error\n")) {
              console.log(text);
              return window.location.href = "{{$self}}?mode=piccom" + (resto ? "&resto=" + resto : "");
            }
          })
        }
      })
      .catch(error => {
        console.error('There was a problem with the fetch operation:', error);
        return window.location.href = "{{$self}}?mode=piccom" + (resto ? "&resto=" + resto : "");
      });
    }
  @endif
  // (c)satopian MIT License ここまで

  @if (isset($imgfile))
    // PSDファイルがある場合はPSDとして読み込み
    if (psdURL && psdURL.trim() !== '') {
      fetch(new Request(psdURL)).then(response => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.arrayBuffer();
      }).then(buffer => {
        return klecks.readPSD(buffer); // resolves to Klecks project
      }).then(project => {
        klecks.openProject(project);
      }).catch(e => {
        console.error('PSD読み込みエラー:', e);
        // PSD読み込みに失敗した場合は画像として読み込みを試行
        loadImageAsBackground();
      });
    } else {
      // 画像ファイルがある場合は背景画像として読み込み
      loadImageAsBackground();
    }
  @else
    // 新規作成
    loadImageAsBackground();
  @endif

  function loadImageAsBackground() {
    const loadImage = (src) => {
      return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = (error) => {
          console.error('画像読み込みエラー:', error);
          reject(error);
        };
        img.src = src;
      });
    };

    (async () => {
      const createCanvasWithImage = async () => {
        const canvas = document.createElement('canvas');
        canvas.width = {{$picw}};
        canvas.height = {{$pich}};
        const ctx = canvas.getContext('2d');

        @if (isset($imgfile))
        try {
          const img = await loadImage("{{$imgfile}}");
          ctx.drawImage(img, 0, 0);
        } catch (error) {
          console.error('画像読み込みエラー:', error);
          // エラーが発生した場合は白い背景を作成
          ctx.save();
          ctx.fillStyle = '#fff';
          ctx.fillRect(0, 0, canvas.width, canvas.height);
          ctx.restore();
        }
        @else
          ctx.save();
          ctx.fillStyle = '#fff';
          ctx.fillRect(0, 0, canvas.width, canvas.height);
          ctx.restore();
        @endif

        return canvas;
      };

      try {
        const backgroundCanvas = await createCanvasWithImage();
        const emptyCanvas = document.createElement('canvas');
        emptyCanvas.width = {{$picw}};
        emptyCanvas.height = {{$pich}};

        klecks.openProject({
          width: {{$picw}},
          height: {{$pich}},
          layers: [{
            name:
            @if (isset($en))
              'Background'
            @else
              '背景'
            @endif,
            opacity: 1,
            mixModeStr: 'source-over',
            image: backgroundCanvas
          }, {
            name: '1',
            opacity: 1,
            mixModeStr: 'source-over',
            image: emptyCanvas
          }]
        });
      } catch (error) {
        console.error('klecks初期化エラー:', error);
        klecks.initError(
          @if (isset($en))
            'failed to initialize klecks'
          @else
            'klecksの初期化に失敗しました。'
          @endif
        );
      }
    })();
  }
</script>
<!-- embed end -->
