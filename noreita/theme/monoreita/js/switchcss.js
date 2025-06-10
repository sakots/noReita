// モダンなCSS切り替え機能
class CssSwitcher {
    constructor() {
        this.cookieName = "_monoreita_colorIdx";
        this.init();
    }

    init() {
        const colorIdx = this.getCookie(this.cookieName);
        if (colorIdx) {
            this.enableCss(Number(colorIdx));
        }
    }

    enableCss(index) {
        const cssElement = document.getElementById(`css${index}`);
        if (cssElement) {
            cssElement.removeAttribute("disabled");
        }
    }

    setCss(selectElement) {
        const index = selectElement.selectedIndex;
        this.setCookie(this.cookieName, index);
        window.location.reload();
    }

    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) {
            return decodeURIComponent(parts.pop().split(';').shift());
        }
        return "";
    }

    setCookie(name, value) {
        const maxAge = 365 * 24 * 60 * 60; // 1年
        document.cookie = `${name}=${encodeURIComponent(value)};max-age=${maxAge};path=/`;
    }
}

// 初期化
const cssSwitcher = new CssSwitcher();

// グローバル関数として公開（既存のHTMLとの互換性のため）
function SetCss(obj) {
    cssSwitcher.setCss(obj);
}

function GetCookie(key) {
    return cssSwitcher.getCookie(key);
}

function SetCookie(key, val) {
    cssSwitcher.setCookie(key, val);
}
