<?php
/**
 * MCQG Player - Live Interactive Chat Drawer / Pop-up (Image 2 WhatsApp Unread Count Format)
 * Path: player/includes/player_chat_modal.php
 */
?>
<?php if (!isset($activeStep)): ?>
<!-- Floating Chatbot Toggle FAB Button (Only shown on Landing Page) -->
<div id="mcqg-live-chat-fab" onclick="toggleLiveChatWidget()" title="Toggle Moderator Chat">
  <div class="position-relative d-flex align-items-center gap-2">
    <i class="fa-solid fa-comments fs-5" id="chat-fab-icon"></i>
    <span id="chat-fab-text">Moderator Chat</span>
    <span id="chat-fab-unread-badge" class="whatsapp-badge position-absolute top-0 start-100 translate-middle" style="display:none;">0</span>
  </div>
</div>
<?php endif; ?>

<!-- Chat Box Drawer (Image 2 format) -->
<div id="mcqg-chat-widget-box" class="mcqg-chat-widget-hidden">
  
  <!-- Header with Avatar, Title & (X) Cross Mark Close Button -->
  <div class="chat-widget-header">
    <div class="d-flex align-items-center gap-2.5">
      <div class="chat-header-avatar">
        <i class="fa-solid fa-headset"></i>
      </div>
      <div>
        <h6 class="m-0 fw-bold text-dark" style="font-size:14.5px;">Moderator Support Chat</h6>
        <div class="d-flex align-items-center gap-1 mt-0.5">
          <span class="online-indicator"></span>
          <span class="text-muted small" style="font-size:11px;">Live Simulation Assistant</span>
        </div>
      </div>
    </div>
    
    <!-- (X) Cross Mark Close Button -->
    <button type="button" class="btn-chat-close" onclick="toggleLiveChatWidget()" title="Close Chat">
      <i class="fa-solid fa-xmark fs-5"></i>
    </button>
  </div>

  <!-- Chat Body (Left-Right conversation format matching Image 2) -->
  <div class="chat-stream-container" id="chatStreamBox">
    <div class="text-center py-5 text-muted small" id="chatLoadingState">
      <div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading conversation...
    </div>
  </div>

  <!-- Chat Input Footer -->
  <div class="chat-widget-footer">
    <form id="live-chat-form" onsubmit="sendChatMessage(event)" class="m-0">
      <div class="input-group">
        <input type="text" id="chat-input-text" class="form-control" placeholder="Type your message to Moderator..." autocomplete="off" required>
        <button type="submit" class="btn btn-primary fw-bold px-3" id="btn-submit-chat">
          <i class="fa-solid fa-paper-plane me-1"></i> Send
        </button>
      </div>
    </form>
  </div>

</div>

<style>
  /* WhatsApp-style Red Circular Badge */
  .whatsapp-badge {
    background: #ef4444;
    color: #ffffff;
    font-weight: 800;
    font-size: 11px;
    border-radius: 50%;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 3px 8px rgba(239, 68, 68, 0.45);
    border: 2px solid #ffffff;
    line-height: 1;
    z-index: 10;
  }

  /* Floating Toggle FAB Button */
  #mcqg-live-chat-fab {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 99998;
    cursor: pointer;
    background: linear-gradient(135deg, #0f172a 0%, #2563eb 100%);
    color: #ffffff;
    border-radius: 50px;
    padding: 12px 22px;
    box-shadow: 0 10px 25px rgba(37, 99, 235, 0.35);
    font-weight: 700;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  }
  #mcqg-live-chat-fab:hover {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 14px 30px rgba(37, 99, 235, 0.45);
  }

  /* Chat Box Drawer Window */
  #mcqg-chat-widget-box {
    position: fixed;
    bottom: 80px;
    right: 24px;
    width: 410px;
    max-width: calc(100vw - 32px);
    height: 530px;
    max-height: calc(100vh - 100px);
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(15, 23, 42, 0.28), 0 0 0 1px rgba(0, 0, 0, 0.08);
    z-index: 99999;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    transform-origin: bottom right;
  }

  #mcqg-chat-widget-box.mcqg-chat-widget-hidden {
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px) scale(0.92);
    pointer-events: none;
  }

  /* Header */
  .chat-widget-header {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .chat-header-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #2563eb;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
  }

  .online-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    display: inline-block;
  }

  .btn-chat-close {
    background: #f1f5f9;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  .btn-chat-close:hover {
    background: #e2e8f0;
    color: #0f172a;
  }

  /* Chat Stream Container */
  .chat-stream-container {
    background: #f8fafc;
    flex: 1;
    overflow-y: auto;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  /* Left bubble: Moderator / System (Image 2 style) */
  .chat-msg-row-left {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    max-width: 85%;
  }

  .chat-avatar-left {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #2563eb;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
    margin-top: 2px;
  }

  .chat-bubble-left {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px 14px 14px 2px;
    padding: 12px 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  }

  .chat-meta-left {
    font-size: 12px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 4px;
  }

  .chat-meta-time {
    font-weight: 400;
    color: #94a3b8;
    font-size: 11px;
    margin-left: 6px;
  }

  .chat-text-left {
    font-size: 13.5px;
    color: #334155;
    line-height: 1.5;
    margin: 0;
  }

  /* Right bubble: Team / You (Image 2 style) */
  .chat-msg-row-right {
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    gap: 10px;
    margin-left: auto;
    max-width: 85%;
  }

  .chat-bubble-right {
    background: #2563eb;
    color: #ffffff;
    border-radius: 14px 14px 2px 14px;
    padding: 12px 16px;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
  }

  .chat-meta-right {
    font-size: 12px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.95);
    margin-bottom: 4px;
    text-align: right;
  }

  .chat-meta-time-right {
    font-weight: 400;
    color: rgba(255, 255, 255, 0.75);
    font-size: 11px;
    margin-left: 6px;
  }

  .chat-text-right {
    font-size: 13.5px;
    color: #ffffff;
    line-height: 1.5;
    margin: 0;
  }

  .chat-avatar-right {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #38bdf8;
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: bold;
    flex-shrink: 0;
    margin-top: 2px;
  }

  /* Footer */
  .chat-widget-footer {
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    padding: 12px 16px;
  }

  .chat-widget-footer .form-control {
    border-radius: 24px 0 0 24px;
    padding: 10px 16px;
    font-size: 13.5px;
    border-color: #cbd5e1;
  }

  .chat-widget-footer .btn-primary {
    border-radius: 0 24px 24px 0;
    background: #2563eb;
    border-color: #2563eb;
  }
</style>

<script>
function toggleLiveChatWidget() {
  const box = document.getElementById('mcqg-chat-widget-box');
  const fabIcon = document.getElementById('chat-fab-icon');
  const fabText = document.getElementById('chat-fab-text');

  if (box.classList.contains('mcqg-chat-widget-hidden')) {
    box.classList.remove('mcqg-chat-widget-hidden');
    if (fabIcon) fabIcon.className = 'fa-solid fa-xmark fs-5';
    if (fabText) fabText.textContent = 'Close Chat';
    fetchChatHistory();
    markMessagesAsRead();
  } else {
    box.classList.add('mcqg-chat-widget-hidden');
    if (fabIcon) fabIcon.className = 'fa-solid fa-comments fs-5';
    if (fabText) fabText.textContent = 'Moderator Chat';
  }
}

function openLiveChatModal() {
  const box = document.getElementById('mcqg-chat-widget-box');
  if (box.classList.contains('mcqg-chat-widget-hidden')) {
    toggleLiveChatWidget();
  } else {
    fetchChatHistory();
    markMessagesAsRead();
  }
}

function fetchChatHistory() {
  const ajaxUrl = typeof AJAX_PLAYER_URL !== 'undefined' ? AJAX_PLAYER_URL : '<?php echo PLAYER_URL; ?>../ajax/player_ajax/';
  fetch(ajaxUrl + 'chat_actions.php?action=get_chat_history')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        renderChatStream(data.messages);
        const box = document.getElementById('mcqg-chat-widget-box');
        if (box && !box.classList.contains('mcqg-chat-widget-hidden')) {
          markMessagesAsRead();
        } else {
          updateUnreadBadges(data.unread_count);
        }
      }
    })
    .catch(err => console.error('Chat history fetch error:', err));
}

function updateUnreadBadges(count) {
  const cnt = parseInt(count) || 0;
  const headerBadge = document.getElementById('header-unread-badge');
  const fabBadge = document.getElementById('chat-fab-unread-badge');

  [headerBadge, fabBadge].forEach(b => {
    if (b) {
      if (cnt > 0) {
        b.textContent = cnt;
        b.style.display = 'inline-flex';
      } else {
        b.style.display = 'none';
      }
    }
  });
}

function markMessagesAsRead() {
  const ajaxUrl = typeof AJAX_PLAYER_URL !== 'undefined' ? AJAX_PLAYER_URL : '<?php echo PLAYER_URL; ?>../ajax/player_ajax/';
  fetch(ajaxUrl + 'chat_actions.php?action=mark_read')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        updateUnreadBadges(0);
      }
    })
    .catch(err => console.error('Error marking messages as read:', err));
}

function renderChatStream(messages) {
  const box = document.getElementById('chatStreamBox');
  if (!messages || messages.length === 0) {
    box.innerHTML = `
      <div class="text-center py-5 text-muted">
        <i class="fa-solid fa-comments fs-2 text-secondary mb-2" style="opacity:0.4;"></i>
        <p class="mb-0 small">No messages yet. Send a message below to connect with the Moderator!</p>
      </div>
    `;
    return;
  }

  box.innerHTML = messages.map(m => {
    if (m.is_me) {
      // Right Side (Team / You)
      return `
        <div class="chat-msg-row-right">
          <div class="chat-bubble-right">
            <div class="chat-meta-right">
              You <span class="chat-meta-time-right">${escapeChatHtml(m.created_at)}</span>
            </div>
            <p class="chat-text-right">${escapeChatHtml(m.text)}</p>
          </div>
          <div class="chat-avatar-right">
            <i class="fa-solid fa-user"></i>
          </div>
        </div>
      `;
    } else {
      // Left Side (Moderator / System)
      const badgeHtml = m.is_broadcast ? '<span class="badge bg-info-subtle text-info-emphasis ms-1" style="font-size:9px;">Broadcast</span>' : '';
      return `
        <div class="chat-msg-row-left">
          <div class="chat-avatar-left">
            <i class="fa-solid fa-headset"></i>
          </div>
          <div class="chat-bubble-left">
            <div class="chat-meta-left">
              ${escapeChatHtml(m.sender_name)} ${badgeHtml} <span class="chat-meta-time">${escapeChatHtml(m.created_at)}</span>
            </div>
            <p class="chat-text-left">${escapeChatHtml(m.text)}</p>
          </div>
        </div>
      `;
    }
  }).join('');

  box.scrollTop = box.scrollHeight;
}

function sendChatMessage(e) {
  e.preventDefault();
  const input = document.getElementById('chat-input-text');
  const text = input.value.trim();
  if (!text) return;

  const btn = document.getElementById('btn-submit-chat');
  btn.disabled = true;

  const formData = new FormData();
  formData.append('action', 'send_message');
  formData.append('message_text', text);

  const ajaxUrl = typeof AJAX_PLAYER_URL !== 'undefined' ? AJAX_PLAYER_URL : '<?php echo PLAYER_URL; ?>../ajax/player_ajax/';

  fetch(ajaxUrl + 'chat_actions.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    btn.disabled = false;
    if (data.success) {
      input.value = '';
      fetchChatHistory();
    } else {
      alert(data.message || 'Error sending message');
    }
  })
  .catch(err => {
    btn.disabled = false;
    console.error('Error sending message:', err);
  });
}

function escapeChatHtml(str) {
  return str ? str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;") : '';
}

// Check for unread messages / history every 4 seconds
document.addEventListener('DOMContentLoaded', function() {
  fetchChatHistory();
  setInterval(fetchChatHistory, 4000);
});
</script>
