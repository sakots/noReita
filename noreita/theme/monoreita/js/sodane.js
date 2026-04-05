// そうだね機能のAjax実装
document.addEventListener('DOMContentLoaded', function() {
  // そうだねボタンにイベントリスナーを追加
  const sodaneButtons = document.querySelectorAll('.sodane a');

  sodaneButtons.forEach(function(button) {
    button.addEventListener('click', function(e) {
      e.preventDefault(); // デフォルトのリンク動作を無効化

      const href = this.getAttribute('href');

      // ボタンを一時的に無効化
      this.style.pointerEvents = 'none';
      this.style.opacity = '0.5';

      // Ajaxリクエストを送信
      fetch(href, {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const originalText = this.textContent.trim();
          const baseText = originalText.replace(/\s*x\d+$|\s*\++$/, '').trim();
          const sodaneCount = parseInt(data.sodane) || 0;
          this.innerHTML = sodaneCount > 0 ? baseText + ' x' + sodaneCount : baseText + ' +';
        } else {
          showMessage(data.error || 'エラーが発生しました', 'error');
        }
      })
      .catch(error => {
        showMessage('通信エラーが発生しました', 'error');
      })
      .finally(() => {
        this.style.pointerEvents = 'auto';
        this.style.opacity = '1';
      });
    });
  });
});

// メッセージ表示関数
function showMessage(message, type) {
  // 既存のメッセージを削除
  const existingMessage = document.querySelector('.sodane-message');
  if (existingMessage) {
    existingMessage.remove();
  }

  // 新しいメッセージを作成
  const messageDiv = document.createElement('div');
  messageDiv.className = 'sodane-message ' + type;
  messageDiv.textContent = message;
  messageDiv.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 10px 15px;
    border-radius: 5px;
    color: white;
    font-weight: bold;
    z-index: 1000;
    animation: slideIn 0.3s ease-out;
  `;

  // メッセージタイプに応じて背景色を設定
  if (type === 'success') {
    messageDiv.style.backgroundColor = '#4CAF50';
  } else {
    messageDiv.style.backgroundColor = '#f44336';
  }

  document.body.appendChild(messageDiv);

  // 3秒後にメッセージを削除
  setTimeout(() => {
    if (messageDiv.parentNode) {
      messageDiv.style.animation = 'slideOut 0.3s ease-in';
      setTimeout(() => {
        if (messageDiv.parentNode) {
          messageDiv.remove();
        }
      }, 300);
    }
  }, 3000);
}

// CSSアニメーションを追加
const style = document.createElement('style');
style.textContent = `
  @keyframes slideIn {
    from {
      transform: translateX(100%);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }

  @keyframes slideOut {
    from {
      transform: translateX(0);
      opacity: 1;
    }
    to {
      transform: translateX(100%);
      opacity: 0;
    }
  }
`;
document.head.appendChild(style);
