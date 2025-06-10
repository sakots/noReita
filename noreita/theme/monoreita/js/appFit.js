// NEOアプリケーションサイズの自動調整
class AppFitManager {
  constructor() {
    this.originalWidth = originalWidth;
    this.originalHeight = originalHeight;
    this.isExpanded = false;
    this.init();
  }

  init() {
    this.document = document;
    this.appstage = this.document.getElementById("appstage");
    this.target = this.document.getElementById("pageView");
    this.fitExp = this.document.getElementById("fit_exp");
    this.fitComp = this.document.getElementById("fit_comp");
  }

  getClientHeight() {
    const client = this.document.compatMode && this.document.compatMode !== "BackCompat" 
      ? this.document.documentElement 
      : this.document.body;
    return client.clientHeight - 10;
  }

  expand() {
    if (this.isExpanded) return;
    
    const clientHeight = this.getClientHeight();
    const contentWidth = this.appstage.scrollWidth - 360;
    
    // 幅と高さを拡張
    if (contentWidth > this.target.clientWidth) {
      this.target.style.width = `${contentWidth}px`;
    }
    if (clientHeight > this.target.clientHeight) {
      this.target.style.height = `${clientHeight}px`;
    }
    
    // UI状態を更新
    this.fitExp.style.display = "none";
    this.fitComp.style.display = "block";
    this.isExpanded = true;
    
    this.resetZoom();
  }

  compress() {
    if (!this.isExpanded) return;
    
    // 元のサイズに戻す
    this.target.style.width = `${this.originalWidth}px`;
    this.target.style.height = `${this.originalHeight}px`;
    
    // UI状態を更新
    this.fitExp.style.display = "block";
    this.fitComp.style.display = "none";
    this.isExpanded = false;
    
    this.resetZoom();
  }

  resetZoom() {
    // ズームをリセット
    if (typeof Neo !== 'undefined' && Neo.painter) {
      Neo.painter.setZoom(1);
      Neo.resizeCanvas();
      Neo.painter.updateDestCanvas();
    }
  }

  toggle() {
    if (this.isExpanded) {
      this.compress();
    } else {
      this.expand();
    }
  }
}

// DOMContentLoadedイベントで初期化
document.addEventListener('DOMContentLoaded', function() {
  window.appFitManager = new AppFitManager();
});

// 後方互換性のための関数
function appfit(f) {
  if (window.appFitManager) {
    if (f === 0) {
      window.appFitManager.expand();
    } else if (f === 1) {
      window.appFitManager.compress();
    }
  }
}
