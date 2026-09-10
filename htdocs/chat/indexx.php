<?php
/**
 * UNMOOR CLUB - REAL-TIME MEMBER MESSENGER
 */

session_start();
require_once __DIR__ . "/../db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

try {
    $db->prepare("UPDATE users SET last_seen = datetime('now') WHERE id = ?")->execute([$uid]);
} catch (Throwable $t) {}

$chatUserId = isset($_GET['u']) ? (int)$_GET['u'] : 0;
$isApi = isset($_GET['api']) && $_GET['api'] === 'fetch';

/* SEND MESSAGE */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $chatUserId > 0) {
    $msg = trim($_POST['msg'] ?? '');
    if ($msg !== '') {
        $db->prepare("
            INSERT INTO chat_messages (sender_id, receiver_id, message, seen, created_at)
            VALUES (?, ?, ?, 0, datetime('now'))
        ")->execute([$uid, $chatUserId, $msg]);
    }

    if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    header("Location: indexx.php?u=" . $chatUserId . "#bottom");
    exit;
}

/* FETCH ACTIVE CHAT USER */
$chatUser = null;
if ($chatUserId > 0) {
    $stmt = $db->prepare("SELECT id, name, photo, last_seen, status FROM users WHERE id = ?");
    $stmt->execute([$chatUserId]);
    $chatUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($chatUser) {
        $db->prepare("
            UPDATE chat_messages SET seen = 1
            WHERE sender_id = ? AND receiver_id = ? AND seen = 0
        ")->execute([$chatUserId, $uid]);
    }
}

/* API MESSAGES FETCH FOR AUTO-REFRESH */
if ($isApi && $chatUserId > 0) {
    $stmt = $db->prepare("
        SELECT id, sender_id, message, seen, created_at
        FROM chat_messages
        WHERE (sender_id = ? AND receiver_id = ?)
           OR (sender_id = ? AND receiver_id = ?)
        ORDER BY id ASC
    ");
    $stmt->execute([$uid, $chatUserId, $chatUserId, $uid]);
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode(['messages' => $msgs, 'uid' => $uid]);
    exit;
}

/* USER CONTACTS DIRECTORY */
$users = $db->query("
    SELECT u.id, u.name, u.photo, u.last_seen, u.role,
    (
        SELECT COUNT(*) FROM chat_messages
        WHERE sender_id = u.id AND receiver_id = $uid AND seen = 0
    ) AS unread,
    (
        SELECT message FROM chat_messages
        WHERE (sender_id = u.id AND receiver_id = $uid) OR (sender_id = $uid AND receiver_id = u.id)
        ORDER BY id DESC LIMIT 1
    ) AS last_message
    FROM users u
    WHERE u.id != $uid
      AND u.role NOT IN ('system', 'liquidity')
    ORDER BY unread DESC, u.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* INITIAL MESSAGES */
$messages = [];
if ($chatUserId > 0) {
    $stmt = $db->prepare("
        SELECT id, sender_id, message, seen, created_at
        FROM chat_messages
        WHERE (sender_id = ? AND receiver_id = ?)
           OR (sender_id = ? AND receiver_id = ?)
        ORDER BY id ASC
    ");
    $stmt->execute([$uid, $chatUserId, $chatUserId, $uid]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $chatUser ? htmlspecialchars($chatUser['name']) . " • Chat" : "Club Messenger • Unmoor Club" ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body, html {
            height: 100%;
            margin: 0;
            padding: 0;
            background: #090d16;
            overflow: hidden;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .chat-app-wrap {
            display: flex;
            height: 100vh;
            width: 100vw;
            background: #090d16;
        }
        /* SIDEBAR (CONTACTS) */
        .chat-sidebar {
            width: 100%;
            max-width: 360px;
            background: #0d131f;
            border-right: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .chat-side-header {
            padding: 14px 16px;
            background: #111827;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .search-box {
            padding: 8px 14px;
            background: #0b101b;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .search-box input {
            width: 100%;
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 999px;
            padding: 8px 14px;
            color: #ffffff;
            font-size: 13px;
            outline: none;
        }
        .contact-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: var(--radius-md);
            text-decoration: none;
            color: #ffffff;
            transition: background 0.15s;
        }
        .contact-item:hover, .contact-item.active {
            background: rgba(250, 204, 21, 0.1);
        }
        .contact-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-gold);
            background: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        /* MAIN CHAT PANE */
        .chat-pane {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #090d16;
            height: 100%;
            position: relative;
        }
        .chat-topbar {
            height: 60px;
            padding: 0 16px;
            background: #111827;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: radial-gradient(circle at center, #0f172a 0%, #090d16 100%);
        }
        .msg-bubble {
            max-width: 75%;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 14.5px;
            line-height: 1.45;
            position: relative;
            word-break: break-word;
        }
        .msg-bubble.me {
            align-self: flex-end;
            background: linear-gradient(135deg, #047857, #065f46);
            color: #ffffff;
            border-bottom-right-radius: 4px;
            border: 1px solid rgba(52, 211, 153, 0.3);
        }
        .msg-bubble.them {
            align-self: flex-start;
            background: #1e293b;
            color: #f1f5f9;
            border-bottom-left-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .msg-meta {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
            font-size: 10.5px;
            opacity: 0.75;
            margin-top: 4px;
        }
        .chat-input-bar {
            padding: 10px 14px;
            background: #111827;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            gap: 10px;
            align-items: center;
            flex-shrink: 0;
        }
        .chat-input-bar input {
            flex: 1;
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            padding: 10px 16px;
            color: #ffffff;
            font-size: 14px;
            outline: none;
        }
        .chat-input-bar button {
            background: var(--accent-gold);
            color: #000000;
            border: none;
            border-radius: 24px;
            padding: 10px 20px;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            transition: opacity 0.15s;
        }
        .chat-input-bar button:hover {
            opacity: 0.9;
        }
        /* RESPONSIVE TOGGLES */
        @media (max-width: 768px) {
            .chat-sidebar {
                max-width: 100%;
                display: <?= $chatUser ? 'none' : 'flex' ?>;
            }
            .chat-pane {
                display: <?= $chatUser ? 'flex' : 'none' ?>;
            }
        }
    </style>
</head>
<body>

<div class="chat-app-wrap">
    
    <!-- CONTACTS SIDEBAR -->
    <div class="chat-sidebar">
        <div class="chat-side-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <a href="../dashboard.php" style="color: var(--accent-gold); text-decoration: none; font-size: 18px; font-weight: 900;">←</a>
                <span style="font-weight: 800; font-size: 16px; color: #ffffff;">Club Messages</span>
            </div>
            <a href="../dashboard.php" class="btn btn-secondary btn-sm" style="padding: 4px 10px; font-size: 11px; text-decoration: none;">
                Dashboard
            </a>
        </div>

        <div class="search-box">
            <input type="text" id="contactSearch" placeholder="🔍 Search members..." oninput="filterContacts(this.value)">
        </div>

        <div class="contact-list" id="contactsContainer">
            <?php foreach ($users as $u): ?>
                <?php 
                    $isActive = ($chatUserId === (int)$u['id']);
                    $hasPhoto = !empty($u['photo']);
                ?>
                <a href="indexx.php?u=<?= $u['id'] ?>" class="contact-item <?= $isActive ? 'active' : '' ?>" data-name="<?= strtolower(htmlspecialchars($u['name'])) ?>">
                    <?php if ($hasPhoto): ?>
                        <img src="../uploads/avatars/<?= htmlspecialchars($u['photo']) ?>" alt="" class="contact-avatar" onerror="this.src='../assets/default-avatar.png'">
                    <?php else: ?>
                        <div class="contact-avatar">👤</div>
                    <?php endif; ?>

                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <strong style="font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($u['name']) ?>
                            </strong>
                            <?php if ($u['unread'] > 0): ?>
                                <span style="background: var(--accent-gold); color: #000; font-size: 11px; font-weight: 900; padding: 2px 7px; border-radius: 999px;">
                                    <?= $u['unread'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 12px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;">
                            <?= !empty($u['last_message']) ? htmlspecialchars($u['last_message']) : 'Tap to start chat' ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- CHAT PANE -->
    <div class="chat-pane">
        <?php if ($chatUser): ?>
            <!-- ACTIVE CHAT TOPBAR -->
            <div class="chat-topbar">
                <a href="indexx.php" style="color: var(--accent-gold); font-size: 22px; text-decoration: none; font-weight: 900; display: inline-flex; align-items: center;">
                    ←
                </a>

                <?php if (!empty($chatUser['photo'])): ?>
                    <img src="../uploads/avatars/<?= htmlspecialchars($chatUser['photo']) ?>" alt="" class="contact-avatar" style="width: 36px; height: 36px;" onerror="this.src='../assets/default-avatar.png'">
                <?php else: ?>
                    <div class="contact-avatar" style="width: 36px; height: 36px; font-size: 16px;">👤</div>
                <?php endif; ?>

                <div>
                    <div style="font-weight: 800; font-size: 15px; color: #ffffff;">
                        <?= htmlspecialchars($chatUser['name']) ?>
                    </div>
                    <div style="font-size: 11px; color: #22c55e;">
                        Online • Member #<?= $chatUser['id'] ?>
                    </div>
                </div>
            </div>

            <!-- MESSAGES CONTAINER -->
            <div class="messages-container" id="messagesContainer">
                <?php foreach ($messages as $m): ?>
                    <?php $isMe = ((int)$m['sender_id'] === $uid); ?>
                    <div class="msg-bubble <?= $isMe ? 'me' : 'them' ?>">
                        <div><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                        <div class="msg-meta">
                            <span><?= date("h:i A", strtotime($m['created_at'] ?? 'now')) ?></span>
                            <?php if ($isMe): ?>
                                <span style="color: <?= $m['seen'] ? '#86efac' : '#d1d5db' ?>;">
                                    <?= $m['seen'] ? '✓✓' : '✓' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div id="bottom"></div>
            </div>

            <!-- CHAT INPUT BAR -->
            <form id="chatSendForm" method="post" class="chat-input-bar" onsubmit="handleChatSubmit(event)">
                <input type="text" id="msgInput" name="msg" placeholder="Type a message..." autocomplete="off" required>
                <button type="submit" id="sendBtn">Send</button>
            </form>

        <?php else: ?>
            <!-- NO CONVERSATION SELECTED -->
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted); text-align: center; padding: 20px;">
                <div style="font-size: 48px; margin-bottom: 12px;">💬</div>
                <h3 style="font-size: 18px; font-weight: 800; color: #ffffff; margin-bottom: 6px;">Unmoor Club Messenger</h3>
                <p style="font-size: 13.5px; max-width: 320px; line-height: 1.5;">
                    Select a club member from the sidebar to begin private messaging.
                </p>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
const activeChatUserId = <?= (int)$chatUserId ?>;
const myUserId = <?= (int)$uid ?>;

function scrollToBottom() {
    const el = document.getElementById('messagesContainer');
    if (el) {
        el.scrollTop = el.scrollHeight;
    }
}
scrollToBottom();

function filterContacts(query) {
    const q = query.toLowerCase().trim();
    const items = document.querySelectorAll('.contact-item');
    items.forEach(it => {
        const name = it.getAttribute('data-name') || '';
        it.style.display = name.includes(q) ? 'flex' : 'none';
    });
}

function handleChatSubmit(e) {
    e.preventDefault();
    const input = document.getElementById('msgInput');
    const msg = input.value.trim();
    if (!msg || !activeChatUserId) return;

    input.value = '';
    
    // Optimistic append
    appendMessage(msg, true, new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));

    const fd = new FormData();
    fd.append('msg', msg);
    fd.append('ajax', '1');

    fetch('indexx.php?u=' + activeChatUserId, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
}

function appendMessage(text, isMe, timeStr) {
    const container = document.getElementById('messagesContainer');
    if (!container) return;

    const bubble = document.createElement('div');
    bubble.className = 'msg-bubble ' + (isMe ? 'me' : 'them');
    bubble.innerHTML = `
        <div>${escapeHtml(text)}</div>
        <div class="msg-meta">
            <span>${timeStr}</span>
            ${isMe ? '<span>✓</span>' : ''}
        </div>
    `;
    const bottom = document.getElementById('bottom');
    container.insertBefore(bubble, bottom);
    scrollToBottom();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}

// Auto poll for new messages every 3 seconds if active chat
if (activeChatUserId > 0) {
    setInterval(() => {
        fetch('indexx.php?u=' + activeChatUserId + '&api=fetch')
            .then(r => r.json())
            .then(data => {
                if (data && data.messages) {
                    const container = document.getElementById('messagesContainer');
                    const bottom = document.getElementById('bottom');
                    if (!container) return;

                    let html = '';
                    data.messages.forEach(m => {
                        const isMe = (parseInt(m.sender_id) === myUserId);
                        const time = new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                        html += `
                            <div class="msg-bubble ${isMe ? 'me' : 'them'}">
                                <div>${escapeHtml(m.message)}</div>
                                <div class="msg-meta">
                                    <span>${time}</span>
                                    ${isMe ? `<span style="color: ${m.seen ? '#86efac' : '#d1d5db'}">${m.seen ? '✓✓' : '✓'}</span>` : ''}
                                </div>
                            </div>
                        `;
                    });
                    html += '<div id="bottom"></div>';
                    
                    const isAtBottom = (container.scrollHeight - container.scrollTop <= container.clientHeight + 80);
                    container.innerHTML = html;
                    if (isAtBottom) {
                        scrollToBottom();
                    }
                }
            })
            .catch(() => {});
    }, 3000);
}
</script>
</body>
</html>
