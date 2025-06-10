/**
 * Dynamic Palette Manager for PaintBBS
 * モダンなES6+クラスベース設計でパレット機能を管理
 */

class DynamicPaletteManager {
	constructor() {
		this.DynamicColor = 1;	// パレットリストに色表示
		this.Palettes = [];
		this.customP = 0;
		this.init();
	}

	init() {
		// グローバル関数として公開（後方互換性のため）
		window.setPalette = this.setPalette.bind(this);
		window.PaletteSave = this.PaletteSave.bind(this);
		window.PaletteNew = this.PaletteNew.bind(this);
		window.PaletteRenew = this.PaletteRenew.bind(this);
		window.PaletteDel = this.PaletteDel.bind(this);
		window.P_Effect = this.P_Effect.bind(this);
		window.PaletteMatrixGet = this.PaletteMatrixGet.bind(this);
		window.PaletteMatrixSet = this.PaletteMatrixSet.bind(this);
		window.PaletteMatrixHelp = this.PaletteMatrixHelp.bind(this);
		window.PaletteSet = this.PaletteSet.bind(this);
		window.PaletteListSetColor = this.PaletteListSetColor.bind(this);
		window.GetBright = this.GetBright.bind(this);
		window.Change_ = this.Change_.bind(this);
		window.ChangeGrad = this.ChangeGrad.bind(this);
		window.Hex = this.Hex.bind(this);
		window.Hex_ = this.Hex_.bind(this);
		window.GetPalette = this.GetPalette.bind(this);
		window.GradSelC = this.GradSelC.bind(this);
		window.GradView = this.GradView.bind(this);
		window.showHideLayer = this.showHideLayer.bind(this);
	}

	// パレットデータを設定
	setPaletteData(paletteData) {
		if (paletteData && Array.isArray(paletteData)) {
			this.Palettes = paletteData;
		} else {
			this.Palettes = [];
		}
	}

	// パレットを設定
	setPalette() {
		const d = document;
		if (d.paintbbs && this.Palettes[d.Palette.select.selectedIndex]) {
			d.paintbbs.setColors(this.Palettes[d.Palette.select.selectedIndex]);
			if (!d.grad.view.checked) return;
			this.GetPalette();
		}
	}

	// パレットを一時保存
	PaletteSave() {
		if (document.paintbbs) {
			this.Palettes[0] = String(document.paintbbs.getColors());
		}
	}

	// 新しいパレットを作成
	PaletteNew() {
		const d = document;
		if (!d.paintbbs) return;
		
		const p = String(d.paintbbs.getColors());
		const s = d.Palette.select;
		this.Palettes[s.length] = p;
		this.customP++;
		
		const str = prompt("パレット名", "パレット " + this.customP);
		if (str == null || str === "") {
			this.customP--;
			return;
		}
		
		s.options[s.length] = new Option(str);
		if (30 > s.length) s.size = s.length;
		this.PaletteListSetColor();
	}

	// パレットを更新
	PaletteRenew() {
		const d = document;
		if (!d.paintbbs) return;
		
		this.Palettes[d.Palette.select.selectedIndex] = String(d.paintbbs.getColors());
		this.PaletteListSetColor();
	}

	// パレットを削除
	PaletteDel() {
		const p = this.Palettes.length;
		const s = document.Palette.select;
		const i = s.selectedIndex;
		
		if (i === -1) return;
		
		const flag = confirm("「" + s.options[i].text + "」を削除してよろしいですか？");
		if (!flag) return;
		
		s.options[i] = null;
		let j = i;
		while (p > j) {
			this.Palettes[j] = this.Palettes[j + 1];
			j++;
		}
		
		if (30 > s.length) s.size = s.length;
	}

	// パレット効果（明度調整・反転）
	P_Effect(v) {
		v = parseInt(v);
		const x = v === 255 ? -1 : 1;
		const d = document.paintbbs;
		
		if (!d) return;
		
		const p = String(d.getColors()).split("\n");
		const l = p.length;
		let s = "";
		
		for (let n = 0; l > n; n++) {
			let R = v + (parseInt("0x" + p[n].substring(1, 3)) * x);
			let G = v + (parseInt("0x" + p[n].substring(3, 5)) * x);
			let B = v + (parseInt("0x" + p[n].substring(5, 7)) * x);
			
			if (R > 255) R = 255;
			else if (0 > R) R = 0;
			if (G > 255) G = 255;
			else if (0 > G) G = 0;
			if (B > 255) B = 255;
			else if (0 > B) B = 0;
			
			s += "#" + this.Hex(R) + this.Hex(G) + this.Hex(B) + "\n";
		}
		
		d.setColors(s);
		this.PaletteListSetColor();
	}

	// パレットマトリクス取得
	PaletteMatrixGet() {
		const d = document.Palette;
		const p = this.Palettes.length;
		const s = d.select;
		const m = d.m_m.selectedIndex;
		const t = d.setr;
		
		switch (m) {
			case 0:
			case 2:
			default:
				t.value = "";
				let n = 0, c = 0;
				while (p > n) {
					if (s.options[n] != null) {
						t.value = t.value + "\n!" + s.options[n].text + "\n" + this.Palettes[n];
						c++;
					}
					n++;
				}
				alert("パレット数：" + c + "\nパレットマトリクスを取得しました");
				break;
			case 1:
				t.value = "!Palette\n" + String(document.paintbbs.getColors());
				alert("現在使用されているパレット情報を取得しました");
				break;
		}
		t.value = t.value.trim() + "\n!Matrix";
	}

	// パレットマトリクス設定
	PaletteMatrixSet() {
		const m = document.Palette.m_m.selectedIndex;
		const str = "パレットマトリクスをセットします。";
		let flag;
		
		switch (m) {
			case 0:
			default:
				flag = confirm(str + "\n現在の全パレット情報は失われますがよろしいですか？");
				break;
			case 1:
				flag = confirm(str + "\n現在使用しているパレットと置き換えますがよろしいですか？");
				break;
			case 2:
				flag = confirm(str + "\n現在のパレット情報に追加しますがよろしいですか？");
				break;
		}
		
		if (!flag) return;
		
		this.PaletteSet();
		const s = document.Palette.select;
		if (s.length < 30) {
			s.size = s.length;
		} else {
			s.size = 30;
		}
		if (this.DynamicColor) this.PaletteListSetColor();
	}

	// パレットマトリクスヘルプ
	PaletteMatrixHelp() {
		alert("★PALETTE MATRIX\nパレットマトリクスとはパレット情報を列挙したテキストを用いる事により\n自由なパレット設定を使用する事が出来ます。\n\n□マトリクスの取得\n1)「取得」ボタンよりパレットマトリクスを取得します。\n2)取得された情報が下のテキストエリアに出ます、これを全てコピーします。\n3)このマトリクス情報をテキストとしてファイルに保存しておくなりしましょう。\n\n□マトリクスのセット\n1）コピーしたマトリクスを下のテキストエリアに貼り付け(ペースト)します。\n2)ファイルに保存してある場合は、それをコピーし貼り付けます。\n3)「セット」ボタンを押せば保存されたパレットが使用できます。\n\n余分な情報があるとパレットが正しくセットされませんのでご注意下さい。");
	}

	// パレット設定
	PaletteSet() {
		const d = document.Palette;
		const se = d.setr.value;
		const s = d.select;
		const m = d.m_m.selectedIndex;
		const l = se.length;
		
		if (l < 1) {
			alert("マトリクス情報がありません。");
			return;
		}
		
		let n = 0, o = 0, e = 0;
		
		switch (m) {
			case 0:
			default:
				n = s.length;
				while (n > 0) {
					n--;
					s.options[n] = null;
				}
			case 2:
				let i = s.options.length;
				n = se.indexOf("!", 0) + 1;
				if (n === 0) return;
				
				const Matrix1 = 1;
				const Matrix2 = -1;
				
				while (n < l) {
					e = se.indexOf("\n#", n);
					if (e === -1) return;
					
					const pn = se.substring(n, e + Matrix1);
					o = se.indexOf("!", e);
					if (o === -1) return;
					
					const pa = se.substring(e + 1, o + Matrix2);
					if (pn !== "Palette") {
						if (i >= 0) s.options[i] = new Option(pn);
						this.Palettes[i] = pa;
						i++;
					} else {
						document.paintbbs.setColors(pa);
					}
					n = o + 1;
				}
				break;
			case 1:
				n = se.indexOf("!", 0) + 1;
				if (n === 0) return;
				e = se.indexOf("\n#", n);
				o = se.indexOf("!", e);
				if (e >= 0) {
					const pa = se.substring(e + 1, o - 1);
					document.paintbbs.setColors(pa);
				}
		}
		this.PaletteListSetColor();
	}

	// パレットリストに色を設定
	PaletteListSetColor() {
		const s = document.Palette ? document.Palette.select : null;
		if (!s || !this.Palettes || this.Palettes.length === 0) {
			return;
		}
		
		for (let i = 1; s.options.length > i; i++) {
			if (this.Palettes[i]) {
				const c = this.Palettes[i].split("\n");
				
				// パレットデータから有効な色を探す
				let backgroundColor = null;
				
				// 5番目の要素（インデックス4）を優先
				if (c[4] && c[4].startsWith('#') && c[4].length === 7) {
					backgroundColor = c[4];
				} else {
					// 5番目がなければ最後の要素を探す
					for (let j = c.length - 1; j >= 0; j--) {
						if (c[j] && c[j].startsWith('#') && c[j].length === 7) {
							backgroundColor = c[j];
							break;
						}
					}
				}
				
				if (backgroundColor) {
					s.options[i].style.background = backgroundColor;
					s.options[i].style.color = this.GetBright(backgroundColor);
				}
			}
		}
	}

	// 明度を取得
	GetBright(c) {
		const r = parseInt("0x" + c.substring(1, 3));
		const g = parseInt("0x" + c.substring(3, 5));
		const b = parseInt("0x" + c.substring(5, 7));
		const max = (r >= g) ? (r >= b) ? r : b : (g >= b) ? g : b;
		return 128 > max ? "#FFFFFF" : "#000000";
	}

	// グラデーション変更
	Change_() {
		const st = document.grad.pst.value;
		const ed = document.grad.ped.value;
		
		if (isNaN(parseInt("0x" + st))) return;
		if (isNaN(parseInt("0x" + ed))) return;
		this.GradView("#" + st, "#" + ed);
	}

	// グラデーション生成
	ChangeGrad() {
		const d = document;
		const st = d.grad.pst.value;
		const ed = d.grad.ped.value;
		this.Change_();
		
		const degi_R = parseInt("0x" + st.substring(0, 2));
		const degi_G = parseInt("0x" + st.substring(2, 4));
		const degi_B = parseInt("0x" + st.substring(4, 6));
		
		let R = parseInt((degi_R - parseInt("0x" + ed.substring(0, 2))) / 15);
		let G = parseInt((degi_G - parseInt("0x" + ed.substring(2, 4))) / 15);
		let B = parseInt((degi_B - parseInt("0x" + ed.substring(4, 6))) / 15);
		
		if (isNaN(R)) R = 1;
		if (isNaN(G)) G = 1;
		if (isNaN(B)) B = 1;
		
		let p = "";
		for (let cnt = 0, m1 = degi_R, m2 = degi_G, m3 = degi_B; 14 > cnt; cnt++, m1 -= R, m2 -= G, m3 -= B) {
			if ((m1 > 255) || (0 > m1)) {
				R *= -1;
				m1 -= R;
			}
			if ((m2 > 255) || (0 > m2)) {
				G *= -1;
				m2 -= G;
			}
			if ((m3 > 255) || (0 > m3)) {
				B *= -1;
				m2 -= B;
			}
			p += "#" + this.Hex(m1) + this.Hex(m2) + this.Hex(m3) + "\n";
		}
		d.paintbbs.setColors(p);
	}

	// 16進数変換
	Hex(n) {
		n = parseInt(n);
		if (0 > n) n *= -1;
		let hex = "";
		let m, k;
		
		while (n > 16) {
			m = n;
			if (n > 16) {
				n = parseInt(n / 16);
				m -= (n * 16);
			}
			k = this.Hex_(m);
			hex = k + hex;
		}
		k = this.Hex_(n);
		hex = k + hex;
		while (2 > hex.length) {
			hex = "0" + hex;
		}
		return hex;
	}

	// 16進数変換（内部関数）
	Hex_(n) {
		if (!isNaN(n)) {
			if (n === 10) n = "A";
			else if (n === 11) n = "B";
			else if (n === 12) n = "C";
			else if (n === 13) n = "D";
			else if (n === 14) n = "E";
			else if (n === 15) n = "F";
		} else {
			n = "";
		}
		return n;
	}

	// パレット取得
	GetPalette() {
		const d = document;
		if (!d.paintbbs) return;
		
		const p = String(d.paintbbs.getColors());
		if (p === "null" || p === "") return;
		
		const ps = p.split("\n");
		window.ps = ps; // グローバル変数として設定（後方互換性のため）
		
		const st = d.grad.p_st.selectedIndex;
		const ed = d.grad.p_ed.selectedIndex;
		
		d.grad.pst.value = ps[st].substring(1, 7);
		d.grad.ped.value = ps[ed].substring(1, 7);
		
		this.GradSelC();
		this.GradView(ps[st], ps[ed]);
		this.PaletteListSetColor();
	}

	// グラデーション選択色
	GradSelC() {
		if (!document.grad.view.checked) return;
		
		const d = document.grad;
		const l = window.ps ? window.ps.length : 0;
		let pe = "";
		
		for (let n = 0; l > n; n++) {
			let R = 255 + (parseInt("0x" + window.ps[n].substring(1, 3)) * -1);
			let G = 255 + (parseInt("0x" + window.ps[n].substring(3, 5)) * -1);
			let B = 255 + (parseInt("0x" + window.ps[n].substring(5, 7)) * -1);
			
			if (R > 255) R = 255;
			else if (0 > R) R = 0;
			if (G > 255) G = 255;
			else if (0 > G) G = 0;
			if (B > 255) B = 255;
			else if (0 > B) B = 0;
			
			pe += "#" + this.Hex(R) + this.Hex(G) + this.Hex(B) + "\n";
		}
		
		pe = pe.split("\n");
		for (let n = 0; l > n; n++) {
			d.p_st.options[n].style.background = window.ps[n];
			d.p_st.options[n].style.color = pe[n];
			d.p_ed.options[n].style.background = window.ps[n];
			d.p_ed.options[n].style.color = pe[n];
		}
	}

	// グラデーションビュー
	GradView(st, ed) {
		const d = document;
		if (!d.grad.view.checked) return;
	}

	// レイヤー表示/非表示切り替え
	showHideLayer() {
		const d = document;
		let l;
		
		if (d.layers) {
			l = d.layers["psft"];
		} else {
			l = d.all("psft").style;
		}
		
		if (!d.grad.view.checked) {
			l.visibility = "hidden";
		}
		if (d.grad.view.checked) {
			l.visibility = "visible";
			this.GetPalette();
		}
	}
}

// グローバルインスタンスを作成
window.dynamicPaletteManager = new DynamicPaletteManager(); 