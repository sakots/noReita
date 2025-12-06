<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{$btitle}}</title>
	<script>
		let resto = "{{$resto}}";
	</script>
  @if ($tool == 'chicken')
  <style>
    body { overscroll-behavior-x: none !important; }
    :not(input),div#chickenpaint-parent :not(input){
      -moz-user-select: none;
      -webkit-user-select: none;
      -ms-user-select: none;
      user-select: none;
    }
  </style>
  <script>
    document.addEventListener('DOMContentLoaded',()=>{
      document.addEventListener('dblclick', (e)=>{ e.preventDefault()}, { passive: false });
      const chicken=document.querySelector('#chickenpaint-parent');
      chicken.addEventListener('contextmenu', (e)=>{
        e.preventDefault();
        e.stopPropagation();
      }, { passive: false });
    });
  </script>
  <script src="{{$chicken_dir}}js/chickenpaint.min.js?{{$stime}}"></script>
  <link rel="stylesheet" type="text/css" href="{{$chicken_dir}}css/chickenpaint.css?{{$stime}}">
  @endif
  @if ($tool == 'klecks')
  <style>
		:not(input) {
			-moz-user-select: none;
			-webkit-user-select: none;
			-ms-user-select: none;
			user-select: none;
		}
	</style>
	<script>
		//ブラウザデフォルトのキー操作をキャンセル
		document.addEventListener("keydown", (e) => {
			const keys = ["+", ";", "=", "-", "s", "h", "r", "o"];
			if ((e.ctrlKey || e.metaKey) && keys.includes(e.key.toLowerCase())) {
				// console.log("e.key",e.key);
				e.preventDefault();
			}
		});
		//ブラウザデフォルトのコンテキストメニューをキャンセル
		document.addEventListener("contextmenu", (e) => {
			e.preventDefault();
		});
	</script>
  @endif
  @if ($tool == 'tegaki')
  <script src="{{$tegaki_dir}}tegaki.js?{{$stime}}"></script>
	<link rel="stylesheet" href="{{$tegaki_dir}}tegaki.css?{{$stime}}">
  <style>
		:not(input) {
			-moz-user-select: none;
			-webkit-user-select: none;
			-ms-user-select: none;
			user-select: none;
		}
	</style>
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			document.addEventListener('dblclick', (e) => {
				e.preventDefault()
			}, {
				passive: false
			});
		});
	</script>
  @endif
  @if ($tool == 'axnos')
  <script src="{{$axnos_dir}}axnospaint-lib.min.js?{{$stime}}"></script>
  <style>
		body {
			overscroll-behavior-x: none !important;
		}
	</style>
  <script>
		// 画面上部のお知らせ領域に表示するテキスト（掲示板名を想定）
		const HEADER_TEXT = "AXNOS Paint（アクノスペイント）";
		// ページ遷移を防止する場合アンコメントする
		window.onbeforeunload = (e) => {
			e.preventDefault();
		}

		//ブラウザデフォルトのキー操作をキャンセル
		document.addEventListener("keydown", (e) => {
			const keys = ["+", ";", "=", "-", "s", "h", "r", "o"];
			if ((e.ctrlKey || e.metaKey) && keys.includes(e.key.toLowerCase())) {
				// console.log("e.key",e.key);
				e.preventDefault();
			}
		});
		document.addEventListener('keyup', (e) => {
			// e.key を利用して特定のキーのアップイベントを検知する
			if (e.key.toLowerCase() === 'alt') {
				e.preventDefault(); // Alt キーのデフォルトの動作をキャンセル
			}
		});

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

		document.addEventListener("DOMContentLoaded", () => {

			var axp = new AXNOSPaint({
				bodyId: 'axnospaint_body',
				minWidth: 300,
				minHeight: 300,
				maxWidth: {{$pmaxw}},
				maxHeight: {{$pmaxh}},
				width: {{$picw}},
				height: {{$pich}},
				checkSameBBS: true,
				draftImageFile: '{{$imgfile}}',
				headerText: HEADER_TEXT,
				expansionTab: {
					name: @if (isset($en)) 'Help' @else 'ヘルプ' @endif,
				msg: '説明書（ニコニコ大百科のAXNOS Paint:ヘルプの記事）を別タブで開きます。',
				link: 'https://dic.nicovideo.jp/id/5703111',
				},
				postForm: {
					// 投稿フォーム
					input: {
						isDisplay: false,
					},
					// 注意事項
					notice: {
						isDisplay: true,
						// 文章はユーザー辞書を使用して書き換えが可能
					},
				},
				dictionary: @if (isset($en)) "{{$axnos_dir}}en.txt?{{$stime}}" @else null @endif,
				post: axnospaint_post,
			});

			// 投稿処理

			//Base64からBlob
			const toBlob = (base64) => {
				try {
					const binaryString = atob(base64);
					const len = binaryString.length;
					const bytes = new Uint8Array(len);

					for (let i = 0; i < len; i++) {
						bytes[i] = binaryString.charCodeAt(i);
					}

					return new Blob([bytes], {
						type: 'image/png'
					});
				} catch (error) {
					console.error('Error converting base64 to Blob:', error);
					throw error;
				}
			}

			function axnospaint_post(postObj) {

				return new Promise(resolve => {

					const BlobPng = toBlob(postObj.strEncodeImg)
					// console.log(BlobPng);
					//2022-2025 (c)satopian MIT License
					//この箇所はさとぴあが作成したMIT Licenseのコードです。
					const postData = (path, data) => {
						fetch(path, {
								method: 'post',
								mode: 'same-origin',
								headers: {
									'X-Requested-With': 'axnos',
								},
								body: data,
							})
							.then((response) => {
								if (response.ok) {
									response.text().then((text) => {
										console.log(text)
										if (text === 'ok') {
											window.onbeforeunload = null;
											@if (isset($rep))
												return repData();
											@endif
											return window.location.href = "{{$self}}?mode=piccom";
										}
										resolve(false);
										return alert(text);
									})
								} else {
									resolve(false);
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
								resolve(false);
								return alert(
                  @if (isset($en))
                  'Server or line is unstable.\nPlease try again!'
                  @else
                  'サーバまたは回線が不安定です。\n時間を置いて再度投稿してみてください。'
                  @endif
                );
							})
					}
					const formData = new FormData();
					formData.append("picture", BlobPng, 'blob');
					@if (isset($rep))
					formData.append("repcode", "{{$repcode}}");
					@endif
				formData.append("tool", "axnos");
				formData.append("stime", {{time()}});
				formData.append("resto", resto);
				postData("{{$self}}?mode=saveimage&tool=axnos", formData);
				// (c)satopian MIT License ここまで
				// location.reload();
				})
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
				formData.append("id", "{{$id}}");
				formData.append("enc_pwd", "{{$pwd}}");
				formData.append("repcode", "{{$repcode}}");
				formData.append("paint_picrep", true);

				// 画像差し換え
				fetch("./", {
						method: 'POST',
						mode: 'same-origin',
						headers: {
							'X-Requested-With': 'axnos',
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
									return window.location.href = "{{$self}}?mode=piccom";
								}
							})
						}
					})
					.catch(error => {
						console.error('There was a problem with the fetch operation:', error);
						return window.location.href = "{{$self}}?mode=piccom";
					});
			}
		@endif
		// (c)satopian MIT License ここまで
	</script>
  @endif
</head>
<body>
  @if ($tool == 'chicken')
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
          saveUrl: "save.php?usercode={!!$usercode!!}@if (isset($resto) && $resto != null)&resto={{$resto}}@endif",
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
  @endif
  @if ($tool == 'klecks')
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
										@endif);
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
                @endif\n
                @if (isset($en))
                limit size
                @else
                制限値
                @endif
                :${max_pch}MB\n
                @if (isset($en))
                Current size
                @else 現在値
                @endif
                :${TotalSiz}MB`
              )
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
				formData.append("id", "{{$id}}");
				formData.append("enc_pwd", "{{$pwd}}");
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
  @endif
  @if ($tool == 'tegaki')
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
										return window.location.href = "{{$self}}?mode=piccom";
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
  @endif
  @if ($tool == 'axnos')
  <div id="axnospaint_body"></div>
  @endif
</body>
</html>