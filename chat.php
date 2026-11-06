<?php
require_once "session_config.php";
include "db_connect.php";
require_once "auto_responder.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$support_res = mysqli_query($conn, "SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
$support_row = mysqli_fetch_assoc($support_res);
$support_id  = $support_row ? (int)$support_row['id'] : 1;

$official_email = "shelflifepantry@gmail.com";

$user_res  = mysqli_query($conn, "SELECT username FROM users WHERE id = '$user_id'");
$user_info = mysqli_fetch_assoc($user_res);

$chat_history = mysqli_query($conn, "SELECT * FROM messages
               WHERE (sender_id = '$user_id' AND receiver_id = '$support_id')
               OR (sender_id = '$support_id' AND receiver_id = '$user_id')
               ORDER BY timestamp ASC");

$last_q = mysqli_query($conn, "SELECT MAX(id) AS last_id FROM messages
                               WHERE (sender_id = '$user_id' AND receiver_id = '$support_id')
                               OR (sender_id = '$support_id' AND receiver_id = '$user_id')");
$last_id = $last_q ? (int)(mysqli_fetch_assoc($last_q)['last_id'] ?? 0) : 0;

mysqli_query($conn, "UPDATE messages SET is_read = 1 WHERE sender_id = '$support_id' AND receiver_id = '$user_id'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Support · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; padding: 24px; height: 100vh; margin: 0; }
        .chat-container { width: 100%; max-width: 560px; height: 90vh; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-xl); display: flex; flex-direction: column; overflow: hidden; box-shadow: var(--sh-lg); }
        .chat-header { padding: 14px 20px; display: flex; align-items: center; gap: 14px; border-bottom: 1px solid var(--border); background: var(--surface); flex-shrink: 0; }
        .back-btn { display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: var(--r-sm); color: var(--ink-600); }
        .back-btn:hover { background: var(--sand-100); color: var(--ink-900); }
        .chat-profile { display: flex; align-items: center; gap: 12px; cursor: pointer; flex-grow: 1; }
        .chat-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--green-500); display: flex; align-items: center; justify-content: center; color: white; flex-shrink: 0; }
        .chat-name { font-family: var(--font-display); font-weight: 700; font-size: 14px; color: var(--ink-900); }
        .status-tag { font-size: 11px; color: var(--green-600); font-weight: 600; display: flex; align-items: center; gap: 4px; margin-top: 1px; }
        .status-dot { width: 6px; height: 6px; background: var(--green-500); border-radius: 50%; }
        .search-toggle { width: 34px; height: 34px; border-radius: var(--r-sm); border: none; background: none; color: var(--ink-500); cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .search-toggle:hover { background: var(--sand-100); color: var(--ink-900); }
        .search-bar { display: none; padding: 10px 20px; background: var(--sand-50); border-bottom: 1px solid var(--border); }
        .search-bar.open { display: block; }
        .search-bar input { width: 100%; padding: 9px 14px; border: 1px solid var(--border); border-radius: var(--r-full); font-size: 13px; background: var(--surface); box-sizing: border-box; font-family: var(--font-body); }
        .search-bar input:focus { outline: none; border-color: var(--green-400); box-shadow: 0 0 0 4px var(--green-50); }
        .chat-box { flex-grow: 1; overflow-y: auto; padding: 24px 20px; display: flex; flex-direction: column; gap: 4px; background: var(--sand-50); position: relative; }
        .msg-wrapper { display: flex; flex-direction: column; margin-bottom: 6px; }
        .msg { padding: 10px 14px; border-radius: 16px; max-width: 78%; font-size: 13.5px; line-height: 1.45; word-wrap: break-word; }
        .sent { align-self: flex-end; background: var(--green-500); color: white; border-bottom-right-radius: 4px; }
        .received { align-self: flex-start; background: var(--surface); color: var(--ink-800); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
        .auto-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--green-700); background: var(--green-50); padding: 2px 8px; border-radius: var(--r-full); margin-bottom: 6px; }
        .date-divider { display: flex; align-items: center; justify-content: center; margin: 18px 0 10px; }
        .date-divider span { background: var(--sand-100); color: var(--ink-500); font-size: 10.5px; font-weight: 600; padding: 4px 12px; border-radius: var(--r-full); text-transform: uppercase; letter-spacing: 0.05em; }
        .seen-status, .timestamp { font-size: 10.5px; color: var(--ink-400); margin-top: 4px; }
        .seen-status { color: var(--green-600); font-weight: 600; text-align: right; }
        .sent + .timestamp, .sent + .seen-status { align-self: flex-end; }
        .received + .timestamp { align-self: flex-start; }
        .new-msg-banner { position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%); background: var(--green-500); color: white; padding: 8px 16px; border-radius: var(--r-full); font-size: 12px; font-weight: 700; cursor: pointer; box-shadow: var(--sh-md); display: none; font-family: var(--font-body); border: none; }
        .new-msg-banner.show { display: flex; align-items: center; gap: 6px; }
        .input-area { padding: 14px 16px; background: var(--surface); border-top: 1px solid var(--border); flex-shrink: 0; }
        .input-wrapper { display: flex; gap: 10px; align-items: center; }
        .chat-input { flex-grow: 1; padding: 11px 18px; border: 1px solid var(--border); border-radius: var(--r-full); outline: none; background: var(--sand-50); font-family: var(--font-body); }
        .chat-input:focus { background: var(--surface); border-color: var(--green-400); box-shadow: 0 0 0 4px var(--green-50); }
        .send-btn { background: var(--green-500); color: white; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: background 0.15s; }
        .send-btn:hover { background: var(--green-700); }

        .attach-btn { background: var(--sand-100); color: var(--ink-600); border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: background 0.15s; }
        .attach-btn:hover { background: var(--sand-200); color: var(--ink-900); }
        .attach-btn:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Image messages */
        .msg .msg-image {
            display: block;
            max-width: 240px;
            max-height: 280px;
            border-radius: 12px;
            cursor: zoom-in;
            margin-bottom: 6px;
        }
        .msg.sent .msg-image { margin-left: auto; }

        /* Image-ONLY messages: strip the bubble entirely */
        .msg.msg-image-only {
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
            box-shadow: none !important;
        }
        .msg.msg-image-only .msg-image {
            border-radius: 16px;
            max-width: 260px;
            max-height: 320px;
            margin-bottom: 0;
        }

        .lightbox { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.9); display: none; justify-content: center; align-items: center; z-index: 5000; padding: 20px; cursor: zoom-out; }
        .lightbox.open { display: flex; }
        .lightbox img { max-width: 95%; max-height: 95%; border-radius: 8px; }

        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(26, 31, 28, 0.5); backdrop-filter: blur(4px); z-index: 1999; }
        .modal-overlay.open { display: block; }
        .support-modal { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--surface); padding: 32px; border-radius: var(--r-xl); box-shadow: var(--sh-lg); z-index: 2000; text-align: center; width: 320px; max-width: 90vw; }
        .support-modal.open { display: block; }
        .support-modal h3 { margin-top: 14px; margin-bottom: 4px; }
        .support-modal .sub { font-size: 12px; color: var(--ink-500); margin-bottom: 16px; }
        .support-modal hr { border: none; border-top: 1px solid var(--border); margin: 16px 0; }
        .support-modal p { font-size: 13px; text-align: left; color: var(--ink-700); margin-bottom: 6px; }
        .support-modal p b { color: var(--ink-900); }

        /* ============ MOBILE: fullscreen chat ============ */
        @media (max-width: 640px) {
            html, body {
                height: 100dvh;
                overflow: hidden;
                padding: 0;
                margin: 0;
            }
            body {
                display: block;
                padding: 0;
            }
            .chat-container {
                max-width: 100%;
                width: 100%;
                height: 100dvh;
                border-radius: 0;
                border: none;
                box-shadow: none;
            }
            .chat-header { padding: 12px 16px; }
            .chat-box { padding: 16px 14px; }
            .input-area { padding: 12px 14px; padding-bottom: max(70px, calc(12px + env(safe-area-inset-bottom, 12px))); }
            .msg { font-size: 14px; max-width: 82%; }
            .msg .msg-image { max-width: 220px; max-height: 260px; }
            .msg.msg-image-only .msg-image { max-width: 240px; max-height: 300px; }
        }
    </style>
</head>
<body class="page page--chat">
<?php include "session_guard.php"; ?>
<div class="modal-overlay" id="overlay" onclick="toggleModal()"></div>

<div class="chat-container">
    <div class="chat-header">
        <a href="dashboard.php" class="back-btn"><i data-lucide="arrow-left"></i></a>
        <div class="chat-profile" onclick="toggleModal()">
            <div class="chat-avatar"><i data-lucide="headset"></i></div>
            <div>
                <div class="chat-name">ShelfLife Support</div>
                <div class="status-tag"><span class="status-dot"></span> Official account</div>
            </div>
        </div>
        <button type="button" class="search-toggle" onclick="toggleSearch()" title="Search messages">
            <i data-lucide="search" style="width:16px;height:16px;"></i>
        </button>
    </div>

    <div class="search-bar" id="searchBar">
        <input type="text" id="chatSearch" placeholder="Search messages..." autocomplete="off">
    </div>

    <div class="chat-box" id="chatBox"
         data-my-id="<?php echo (int)$user_id; ?>"
         data-other-id="<?php echo (int)$support_id; ?>"
         data-last-id="<?php echo (int)$last_id; ?>"
         data-is-admin="0">

        <?php
        $has_messages  = false;
        $prev_date     = null;
        $today_str     = date('Y-m-d');
        $yesterday_str = date('Y-m-d', strtotime('-1 day'));

        while ($m = mysqli_fetch_assoc($chat_history)) {
            $has_messages = true;
            $msg_date = date('Y-m-d', strtotime($m['timestamp']));

            if ($msg_date !== $prev_date) {
                if ($msg_date === $today_str)         $label = 'Today';
                elseif ($msg_date === $yesterday_str) $label = 'Yesterday';
                else                                  $label = date('F j, Y', strtotime($m['timestamp']));
                echo '<div class="date-divider"><span>' . htmlspecialchars($label) . '</span></div>';
                $prev_date = $msg_date;
            }

            $has_image = !empty($m['attachment_path']) && file_exists($m['attachment_path']);
            $has_text  = trim((string)$m['message_text']) !== '';
            $extra     = ($has_image && !$has_text) ? ' msg-image-only' : '';
        ?>
            <div class="msg-wrapper">
                <div class="msg <?php echo ($m['sender_id'] == $user_id) ? 'sent' : 'received'; ?><?php echo $extra; ?>">
                    <?php if (!empty($m['is_auto_reply'])): ?>
                        <div class="auto-badge">
                            <i data-lucide="bot" style="width:11px;height:11px;"></i>
                            Automated reply
                        </div>
                    <?php endif; ?>
                    <?php if ($has_image): ?>
                        <img src="<?php echo htmlspecialchars($m['attachment_path']); ?>"
                             class="msg-image"
                             alt="attachment"
                             onclick="openLightbox(this.src)">
                    <?php endif; ?>
                    <?php if ($has_text): ?>
                        <?php echo format_bot_message($m['message_text']); ?>
                    <?php endif; ?>
                </div>
                <?php if ($m['sender_id'] == $user_id): ?>
                    <?php if ($m['is_read'] == 1 && $m['id'] == $last_id): ?>
                        <div class="seen-status">✓ Seen</div>
                    <?php else: ?>
                        <span class="timestamp"><?php echo date('h:i A', strtotime($m['timestamp'])); ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="timestamp"><?php echo date('h:i A', strtotime($m['timestamp'])); ?></span>
                <?php endif; ?>
            </div>
        <?php } ?>

        <?php if (!$has_messages): ?>
            <div style="margin:auto; text-align:center; color: var(--ink-400); font-size: 13px;">
                <i data-lucide="message-circle" style="width:32px;height:32px;margin-bottom:10px;opacity:0.5;"></i>
                <div>No messages yet. Say hello!</div>
            </div>
        <?php endif; ?>

        <button type="button" class="new-msg-banner" id="newMsgBanner" onclick="scrollToBottom()">
            <i data-lucide="arrow-down" style="width:14px;height:14px;"></i>
            New messages
        </button>
    </div>

    <div class="input-area">
        <form action="send_message.php" method="POST" class="input-wrapper">
            <input type="hidden" name="receiver_id" value="<?php echo $support_id; ?>">
            <input type="file" id="attachInput" accept="image/jpeg,image/png,image/gif" style="display:none;">
            <button type="button" id="attachBtn" class="attach-btn" title="Send image">
                <i data-lucide="image" style="width:18px;height:18px;"></i>
            </button>
            <input type="text" name="message_text" class="chat-input" placeholder="Message support..." autocomplete="off">
            <button type="submit" class="send-btn"><i data-lucide="send"></i></button>
        </form>
    </div>
</div>

<div id="adminModal" class="support-modal">
    <div class="chat-avatar" style="margin: 0 auto;"><i data-lucide="headset"></i></div>
    <h3>ShelfLife Support</h3>
    <p class="sub">Official admin channel</p>
    <hr>
    <p><b>Email:</b> <?php echo htmlspecialchars($official_email); ?></p>
    <p><b>Hours:</b> 8:00 AM – 5:00 PM</p>
    <button onclick="toggleModal()" class="btn btn-primary" style="margin-top:18px; width: 100%;">Close</button>
</div>

<div id="lightbox" class="lightbox" onclick="closeLightbox()">
    <img id="lightboxImg" src="" alt="">
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();

    const chatBox      = document.getElementById('chatBox');
    const myId         = parseInt(chatBox.dataset.myId);
    const otherId      = parseInt(chatBox.dataset.otherId);
    const newMsgBanner = document.getElementById('newMsgBanner');
    let lastId         = parseInt(chatBox.dataset.lastId);

    chatBox.scrollTop = chatBox.scrollHeight;

    function toggleModal() {
        document.getElementById('adminModal').classList.toggle('open');
        document.getElementById('overlay').classList.toggle('open');
    }

    function toggleSearch() {
        const bar = document.getElementById('searchBar');
        bar.classList.toggle('open');
        if (bar.classList.contains('open')) {
            document.getElementById('chatSearch').focus();
        } else {
            document.getElementById('chatSearch').value = '';
            filterMessages('');
        }
    }

    document.getElementById('chatSearch').addEventListener('input', function () {
        filterMessages(this.value);
    });

    function filterMessages(query) {
        const q = query.toLowerCase().trim();
        document.querySelectorAll('.msg-wrapper').forEach(wrapper => {
            const text = wrapper.querySelector('.msg').textContent.toLowerCase();
            wrapper.style.display = (!q || text.includes(q)) ? '' : 'none';
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatBotMessage(text) {
        let safe = escapeHtml(text);
        safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        return safe.replace(/\n/g, '<br>');
    }

    function formatTime(ts) {
        const parts = ts.split(' ');
        if (parts.length < 2) return ts;
        const [h, m] = parts[1].split(':').map(Number);
        const ampm = h >= 12 ? 'PM' : 'AM';
        const h12 = h % 12 || 12;
        return `${h12}:${String(m).padStart(2, '0')} ${ampm}`;
    }

    function appendMessage(m) {
        const wrapper = document.createElement('div');
        wrapper.className = 'msg-wrapper';

        const isMine   = m.sender_id === myId;
        const hasImage = !!m.attachment;
        const hasText  = !!(m.text && m.text.trim() !== '');

        let cls = 'msg ' + (isMine ? 'sent' : 'received');
        if (hasImage && !hasText) cls += ' msg-image-only';

        const msgDiv = document.createElement('div');
        msgDiv.className = cls;

        if (m.is_auto) {
            const badge = document.createElement('div');
            badge.className = 'auto-badge';
            badge.innerHTML = '<i data-lucide="bot" style="width:11px;height:11px;"></i> Automated reply';
            msgDiv.appendChild(badge);
        }

        if (hasImage) {
            const img = document.createElement('img');
            img.src = m.attachment;
            img.className = 'msg-image';
            img.alt = 'attachment';
            img.onclick = function() { openLightbox(this.src); };
            msgDiv.appendChild(img);
        }

        if (hasText) {
            const span = document.createElement('span');
            span.innerHTML = formatBotMessage(m.text);
            msgDiv.appendChild(span);
        }
        wrapper.appendChild(msgDiv);

        const time = document.createElement('span');
        time.className = 'timestamp';
        time.textContent = formatTime(m.timestamp);
        wrapper.appendChild(time);

        chatBox.appendChild(wrapper);
    }

    function isAtBottom() {
        return (chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight) < 60;
    }

    function scrollToBottom() {
        chatBox.scrollTop = chatBox.scrollHeight;
        newMsgBanner.classList.remove('show');
    }

    chatBox.addEventListener('scroll', function () {
        if (isAtBottom()) newMsgBanner.classList.remove('show');
    });

    // ---------- Real-time delivery: SSE with polling fallback ----------
    let sse = null;
    let pollTimer = null;
    let sseRetryTimeout = null;

    function stopPolling() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }
    function startPolling() { if (pollTimer) return; pollTimer = setInterval(pollMessages, 5000); }
    function stopSSE() {
        if (sse) { try { sse.close(); } catch(e) {} sse = null; }
        if (sseRetryTimeout) { clearTimeout(sseRetryTimeout); sseRetryTimeout = null; }
    }

    function handleNewMessage(m) {
        if (!m || !m.id || m.id <= lastId) return;
        const wasAtBottom = isAtBottom();
        const hadSearch   = document.getElementById('chatSearch').value.trim() !== '';
        appendMessage(m);
        lastId = m.id;
        chatBox.dataset.lastId = lastId;
        lucide.createIcons();
        if (hadSearch) filterMessages(document.getElementById('chatSearch').value);
        if (wasAtBottom && !hadSearch) {
            chatBox.scrollTop = chatBox.scrollHeight;
        } else if (!wasAtBottom) {
            newMsgBanner.classList.add('show');
        }
    }

    function pollMessages() {
        fetch(`get_messages.php?with=${otherId}&after=${lastId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.messages.length) return;
                data.messages.forEach(handleNewMessage);
            })
            .catch(() => {});
    }

    function startSSE() {
        stopSSE();
        if (typeof EventSource === 'undefined') { startPolling(); return; }
        const url = `stream_messages.php?with=${otherId}&after=${lastId}`;
        try { sse = new EventSource(url); } catch (err) { startPolling(); return; }
        sse.onopen = function () { stopPolling(); };
        sse.onmessage = function (e) { try { handleNewMessage(JSON.parse(e.data)); } catch (err) {} };
        sse.addEventListener('reconnect', function () {
            if (sse) { try { sse.close(); } catch(e) {} sse = null; }
            startSSE();
        });
        sse.onerror = function () {
            stopSSE();
            startPolling();
            sseRetryTimeout = setTimeout(function () { stopPolling(); startSSE(); }, 5000);
        };
    }

    // ---------- Lightbox ----------
    function openLightbox(src) {
        document.getElementById('lightboxImg').src = src;
        document.getElementById('lightbox').classList.add('open');
    }
    function closeLightbox() {
        document.getElementById('lightbox').classList.remove('open');
        document.getElementById('lightboxImg').src = '';
    }
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeLightbox(); });

    // ---------- Attach image ----------
    (function () {
        const attachBtn    = document.getElementById('attachBtn');
        const attachInput  = document.getElementById('attachInput');
        const captionInput = document.querySelector('.chat-input');
        if (!attachBtn || !attachInput) return;

        const ICON_IMAGE   = '<i data-lucide="image" style="width:18px;height:18px;"></i>';
        const ICON_LOADING = '<i data-lucide="loader-2" style="width:18px;height:18px;"></i>';

        attachBtn.addEventListener('click', function () { attachInput.click(); });

        attachInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            if (file.size > 5 * 1024 * 1024) { alert('Image must be under 5MB.'); this.value = ''; return; }

            const caption = captionInput ? captionInput.value.trim() : '';
            const fd = new FormData();
            fd.append('image', file);
            fd.append('receiver_id', otherId);
            fd.append('caption', caption);
            if (window.SHELFLIFE_CSRF) fd.append('csrf_token', window.SHELFLIFE_CSRF);

            attachBtn.disabled = true;
            attachBtn.innerHTML = ICON_LOADING;
            lucide.createIcons();

            fetch('upload_chat_image.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    attachBtn.disabled = false;
                    attachBtn.innerHTML = ICON_IMAGE;
                    lucide.createIcons();
                    if (data.success && data.message) {
                        handleNewMessage(data.message);
                        chatBox.scrollTop = chatBox.scrollHeight;
                        if (captionInput) captionInput.value = '';
                    } else {
                        alert(data.error || 'Upload failed');
                    }
                })
                .catch(() => {
                    attachBtn.disabled = false;
                    attachBtn.innerHTML = ICON_IMAGE;
                    lucide.createIcons();
                    alert('Upload failed. Check your connection.');
                });

            this.value = '';
        });
    })();

    if (typeof EventSource !== 'undefined') { startSSE(); } else { startPolling(); }
</script>
</body>
</html>