<?php
/**
 * UNMOOR CLUB - WHATSAPP CHAT MESSENGER
 * Complete WhatsApp Web & Mobile Experience
 */

session_start();
require_once __DIR__ . "/../db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];
$now = date('Y-m-d H:i:s');

try {
    $db->prepare("UPDATE users SET last_seen = ? WHERE id = ?")->execute([$now, $uid]);
} catch (Throwable $t) {}

$chatUserId = isset($_GET['u']) ? (int)$_GET['u'] : 0;
$isApi = isset($_GET['api']) && $_GET['api'] === 'fetch';

/* SEND MESSAGE */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $chatUserId > 0) {
    $msg = trim($_POST['msg'] ?? '');
    if ($msg !== '') {
        $db->prepare("
            INSERT INTO chat_messages (sender_id, receiver_id, message, seen, created_at)
            VALUES (?, ?, ?, 0, ?)
        ")->execute([$uid, $chatUserId, $msg, $now]);
    }

    if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'timestamp' => date("h:i A")]);
        exit;
    }

    header("Location: indexx.php?u=" . $chatUserId . "#bottom");
    exit;
}

/* FETCH ACTIVE CHAT USER */
$chatUser = null;
if ($chatUserId > 0) {
    $stmt = $db->prepare("SELECT id, name, phone, photo, last_seen, status, role FROM users WHERE id = ?");
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
        SELECT id, sender_id, receiver_id, message, seen, created_at
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

/* CURRENT LOGGED IN USER INFO */
$myStmt = $db->prepare("SELECT id, name, photo, role FROM users WHERE id = ?");
$myStmt->execute([$uid]);
$currentUser = $myStmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => $uid, 'name' => 'Member', 'photo' => ''];

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
    ) AS last_message,
    (
        SELECT created_at FROM chat_messages
        WHERE (sender_id = u.id AND receiver_id = $uid) OR (sender_id = $uid AND receiver_id = u.id)
        ORDER BY id DESC LIMIT 1
    ) AS last_msg_time
    FROM users u
    WHERE u.id != $uid
      AND u.role NOT IN ('system', 'liquidity')
    ORDER BY unread DESC, last_msg_time DESC, u.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* INITIAL MESSAGES FOR ACTIVE CHAT */
$messages = [];
if ($chatUserId > 0) {
    $stmt = $db->prepare("
        SELECT id, sender_id, receiver_id, message, seen, created_at
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
    <title><?= $chatUser ? htmlspecialchars($chatUser['name']) . " • WhatsApp Chat" : "WhatsApp • Unmoor Club" ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <style>
        :root {
            /* WhatsApp Signature Colors */
            --wa-teal: #008069;
            --wa-teal-dark: #005c4b;
            --wa-teal-light: #128c7e;
            --wa-app-bg: #efeae2;
            --wa-panel-bg: #f0f2f5;
            --wa-active-chat: #ebebeb;
            --wa-sent-bubble: #d9fdd3;
            --wa-received-bubble: #ffffff;
            --wa-bubble-shadow: 0 1px 0.5px rgba(11, 20, 26, 0.13);
            --wa-text-primary: #111b21;
            --wa-text-secondary: #667781;
            --wa-text-meta: #667781;
            --wa-green-badge: #25d366;
            --wa-blue-tick: #53bdeb;
            --wa-input-bg: #ffffff;
            --wa-border: #e9edef;
            --wa-btn-send: #00a884;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body, html {
            height: 100%;
            width: 100%;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #d1d7db;
            color: var(--wa-text-primary);
        }

        /* Full Height WhatsApp Container */
        .wa-container {
            display: flex;
            height: 100vh;
            width: 100vw;
            background: #ffffff;
            position: relative;
            overflow: hidden;
        }

        /* LEFT SIDEBAR: CONTACTS LIST */
        .wa-sidebar {
            width: 100%;
            max-width: 400px;
            height: 100%;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            border-right: 1px solid var(--wa-border);
            z-index: 10;
        }

        /* Sidebar Header */
        .wa-side-header {
            height: 60px;
            padding: 10px 16px;
            background: var(--wa-panel-bg);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--wa-border);
            flex-shrink: 0;
        }

        .wa-user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--wa-text-primary);
        }

        .wa-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .wa-header-icons {
            display: flex;
            align-items: center;
            gap: 16px;
            color: #54656f;
        }

        .wa-icon-btn {
            background: none;
            border: none;
            color: #54656f;
            font-size: 19px;
            cursor: pointer;
            padding: 6px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
            text-decoration: none;
        }
        .wa-icon-btn:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        /* Search Bar & Filters */
        .wa-search-wrap {
            padding: 8px 12px;
            background: #ffffff;
            border-bottom: 1px solid #f0f2f5;
        }

        .wa-search-box {
            display: flex;
            align-items: center;
            background: var(--wa-panel-bg);
            border-radius: 8px;
            padding: 6px 12px;
            gap: 10px;
        }

        .wa-search-box input {
            border: none;
            background: transparent;
            outline: none;
            width: 100%;
            font-size: 14.5px;
            color: var(--wa-text-primary);
        }

        .wa-search-box input::placeholder {
            color: var(--wa-text-secondary);
        }

        /* Contact List Container */
        .wa-contact-list {
            flex: 1;
            overflow-y: auto;
            background: #ffffff;
        }

        .wa-contact-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            gap: 14px;
            text-decoration: none;
            color: inherit;
            border-bottom: 1px solid #f5f6f6;
            transition: background 0.1s ease;
            position: relative;
        }

        .wa-contact-item:hover, .wa-contact-item.active {
            background: var(--wa-active-chat);
        }

        .wa-contact-avatar-wrap {
            position: relative;
            flex-shrink: 0;
        }

        .wa-contact-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .wa-online-dot {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            background: var(--wa-green-badge);
            border: 2px solid #ffffff;
            border-radius: 50%;
        }

        .wa-contact-info {
            flex: 1;
            min-width: 0;
        }

        .wa-contact-top {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 3px;
        }

        .wa-contact-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--wa-text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .wa-contact-time {
            font-size: 12px;
            color: var(--wa-text-secondary);
            flex-shrink: 0;
            margin-left: 8px;
        }

        .wa-contact-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .wa-contact-msg {
            font-size: 13.5px;
            color: var(--wa-text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 6px;
        }

        .wa-unread-badge {
            background: var(--wa-green-badge);
            color: #ffffff;
            font-size: 11.5px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 12px;
            min-width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        /* RIGHT PANEL: WHATSAPP ACTIVE CONVERSATION */
        .wa-chat-pane {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--wa-app-bg);
            height: 100%;
            position: relative;
        }

        /* WhatsApp Top Header */
        .wa-chat-header {
            height: 60px;
            padding: 8px 16px;
            background: var(--wa-panel-bg);
            border-bottom: 1px solid var(--wa-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
            z-index: 5;
        }

        .wa-chat-header-user {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            min-width: 0;
        }

        .wa-chat-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--wa-text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .wa-chat-status {
            font-size: 12px;
            color: #16a34a;
            font-weight: 500;
        }

        /* WhatsApp Doodle Wallpaper Canvas */
        .wa-messages-canvas {
            flex: 1;
            overflow-y: auto;
            padding: 16px 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            background-color: #efeae2;
            background-image: radial-gradient(rgba(17, 27, 33, 0.05) 1px, transparent 0);
            background-size: 20px 20px;
            position: relative;
        }

        /* WhatsApp Center Date Pill */
        .wa-date-pill {
            align-self: center;
            background: #ffffff;
            color: var(--wa-text-secondary);
            font-size: 11.5px;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 8px;
            box-shadow: 0 1px 1.5px rgba(11, 20, 26, 0.12);
            margin: 8px 0 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .wa-encryption-notice {
            align-self: center;
            background: #ffeecd;
            color: #54656f;
            font-size: 11px;
            line-height: 1.4;
            padding: 7px 14px;
            border-radius: 8px;
            max-width: 440px;
            text-align: center;
            box-shadow: 0 1px 0.5px rgba(11, 20, 26, 0.08);
            margin-bottom: 12px;
        }

        /* WhatsApp Message Bubbles */
        .wa-bubble {
            max-width: 68%;
            min-width: 80px;
            padding: 6px 9px 8px 10px;
            border-radius: 8px;
            position: relative;
            box-shadow: var(--wa-bubble-shadow);
            font-size: 14.5px;
            line-height: 1.42;
            word-break: break-word;
            display: flex;
            flex-direction: column;
        }

        /* Sent Message (Me - Green) */
        .wa-bubble.me {
            align-self: flex-end;
            background: var(--wa-sent-bubble);
            border-top-right-radius: 0px;
        }

        /* Tail on top right */
        .wa-bubble.me::before {
            content: "";
            position: absolute;
            top: 0;
            right: -8px;
            width: 0;
            height: 0;
            border: 8px solid transparent;
            border-top-color: var(--wa-sent-bubble);
            border-right: 0;
        }

        /* Received Message (Them - White) */
        .wa-bubble.them {
            align-self: flex-start;
            background: var(--wa-received-bubble);
            border-top-left-radius: 0px;
        }

        /* Tail on top left */
        .wa-bubble.them::before {
            content: "";
            position: absolute;
            top: 0;
            left: -8px;
            width: 0;
            height: 0;
            border: 8px solid transparent;
            border-top-color: var(--wa-received-bubble);
            border-left: 0;
        }

        .wa-bubble-text {
            color: var(--wa-text-primary);
            padding-right: 24px;
            font-size: 14.5px;
        }

        .wa-bubble-meta {
            align-self: flex-end;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 10.5px;
            color: var(--wa-text-meta);
            margin-top: -2px;
            margin-left: 12px;
        }

        .wa-ticks {
            font-size: 12px;
            font-weight: bold;
            letter-spacing: -2px;
            display: inline-block;
        }

        .wa-ticks.seen {
            color: var(--wa-blue-tick);
        }

        .wa-ticks.sent {
            color: #8696a0;
        }

        /* Bottom WhatsApp Input Bar */
        .wa-input-container {
            padding: 8px 16px;
            background: var(--wa-panel-bg);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            position: relative;
            z-index: 10;
        }

        .wa-input-pill {
            flex: 1;
            background: var(--wa-input-bg);
            border-radius: 24px;
            padding: 9px 16px;
            display: flex;
            align-items: center;
            box-shadow: 0 1px 1px rgba(11, 20, 26, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .wa-input-pill input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 15px;
            color: var(--wa-text-primary);
            background: transparent;
        }

        .wa-input-pill input::placeholder {
            color: var(--wa-text-secondary);
        }

        /* Circular Send / Mic Button */
        .wa-send-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--wa-btn-send);
            color: #ffffff;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,0.25);
            transition: transform 0.1s, background 0.15s;
            flex-shrink: 0;
        }

        .wa-send-btn:active {
            transform: scale(0.92);
        }

        /* Emoji Drawer */
        .wa-emoji-drawer {
            display: none;
            padding: 12px 16px;
            background: #ffffff;
            border-top: 1px solid var(--wa-border);
            grid-template-columns: repeat(8, 1fr);
            gap: 10px;
            max-height: 180px;
            overflow-y: auto;
        }

        .wa-emoji-drawer.open {
            display: grid;
        }

        .wa-emoji-item {
            font-size: 24px;
            text-align: center;
            cursor: pointer;
            border-radius: 6px;
            padding: 4px;
            user-select: none;
        }
        .wa-emoji-item:hover {
            background: #f0f2f5;
        }

        /* Attachment Menu Dropdown */
        .wa-attachment-menu {
            display: none;
            position: absolute;
            bottom: 64px;
            left: 56px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 8px 0;
            z-index: 100;
            min-width: 180px;
        }

        .wa-attachment-menu.open {
            display: block;
        }

        .wa-attachment-item {
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: var(--wa-text-primary);
            cursor: pointer;
            text-decoration: none;
        }

        .wa-attachment-item:hover {
            background: #f5f6f6;
        }

        /* Empty State */
        .wa-empty-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px;
            text-align: center;
            background: var(--wa-panel-bg);
            border-bottom: 6px solid var(--wa-btn-send);
        }

        .wa-empty-illustration {
            width: 260px;
            height: auto;
            margin-bottom: 24px;
            opacity: 0.85;
        }

        /* Quick Toast Feedback */
        .wa-toast {
            position: fixed;
            top: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: #111b21;
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 24px;
            font-size: 13.5px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.3);
            z-index: 999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s, transform 0.25s;
        }
        .wa-toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(10px);
        }

        /* Mobile Viewport Adaptation */
        @media (max-width: 768px) {
            .wa-sidebar {
                max-width: 100%;
                display: <?= $chatUser ? 'none' : 'flex' ?>;
            }
            .wa-chat-pane {
                display: <?= $chatUser ? 'flex' : 'none' ?>;
            }
            .wa-bubble {
                max-width: 82%;
            }
        }
    </style>
</head>
<body>

<div class="wa-container">

    <!-- 1. LEFT SIDEBAR: CONTACTS LIST -->
    <div class="wa-sidebar">
        <!-- Header -->
        <div class="wa-side-header">
            <a href="../dashboard.php" class="wa-user-profile" title="Return to Unmoor Club Dashboard">
                <?php if (!empty($currentUser['photo'])): ?>
                    <img src="../uploads/avatars/<?= htmlspecialchars($currentUser['photo']) ?>" alt="" class="wa-user-avatar" onerror="this.src='../assets/default-avatar.png'">
                <?php else: ?>
                    <div class="wa-user-avatar">👤</div>
                <?php endif; ?>
                <div>
                    <div style="font-size: 15px; font-weight: 700; color: #111b21;">WhatsApp</div>
                    <div style="font-size: 11.5px; color: #16a34a; font-weight: 600;">Unmoor Club Connected</div>
                </div>
            </a>

            <div class="wa-header-icons">
                <a href="../dashboard.php" class="wa-icon-btn" title="Club Home">🏠</a>
                <button type="button" class="wa-icon-btn" onclick="showToast('New Chat • Select a club member below')" title="New Chat">💬</button>
                <a href="../account/" class="wa-icon-btn" title="Account">⚙️</a>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="wa-search-wrap">
            <div class="wa-search-box">
                <span style="color: #54656f; font-size: 14px;">🔍</span>
                <input type="text" id="contactSearch" placeholder="Search or start new chat" oninput="filterContacts(this.value)" autocomplete="off">
            </div>
        </div>

        <!-- Contact Items -->
        <div class="wa-contact-list" id="contactsContainer">
            <?php foreach ($users as $u): ?>
                <?php 
                    $isActive = ($chatUserId === (int)$u['id']);
                    $hasPhoto = !empty($u['photo']);
                    $timeFormatted = !empty($u['last_msg_time']) ? date("h:i A", strtotime($u['last_msg_time'])) : '';
                ?>
                <a href="indexx.php?u=<?= $u['id'] ?>" class="wa-contact-item <?= $isActive ? 'active' : '' ?>" data-name="<?= strtolower(htmlspecialchars($u['name'])) ?>">
                    <div class="wa-contact-avatar-wrap">
                        <?php if ($hasPhoto): ?>
                            <img src="../uploads/avatars/<?= htmlspecialchars($u['photo']) ?>" alt="" class="wa-contact-avatar" onerror="this.src='../assets/default-avatar.png'">
                        <?php else: ?>
                            <div class="wa-contact-avatar">👤</div>
                        <?php endif; ?>
                        <div class="wa-online-dot"></div>
                    </div>

                    <div class="wa-contact-info">
                        <div class="wa-contact-top">
                            <span class="wa-contact-name"><?= htmlspecialchars($u['name']) ?></span>
                            <?php if (!empty($timeFormatted)): ?>
                                <span class="wa-contact-time"><?= $timeFormatted ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="wa-contact-bottom">
                            <span class="wa-contact-msg">
                                <?= !empty($u['last_message']) ? htmlspecialchars($u['last_message']) : 'Tap to chat' ?>
                            </span>
                            <?php if ($u['unread'] > 0): ?>
                                <span class="wa-unread-badge"><?= $u['unread'] ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 2. RIGHT PANEL: CHAT VIEW -->
    <div class="wa-chat-pane">
        <?php if ($chatUser): ?>
            <!-- WhatsApp Top Bar -->
            <div class="wa-chat-header">
                <div class="wa-chat-header-user">
                    <a href="indexx.php" class="wa-icon-btn" style="margin-right: -4px; font-weight: bold;" title="Back">
                        ←
                    </a>
                    
                    <div style="position: relative;">
                        <?php if (!empty($chatUser['photo'])): ?>
                            <img src="../uploads/avatars/<?= htmlspecialchars($chatUser['photo']) ?>" alt="" class="wa-user-avatar" onerror="this.src='../assets/default-avatar.png'">
                        <?php else: ?>
                            <div class="wa-user-avatar">👤</div>
                        <?php endif; ?>
                        <div class="wa-online-dot"></div>
                    </div>

                    <div style="min-width: 0;">
                        <div class="wa-chat-name"><?= htmlspecialchars($chatUser['name']) ?></div>
                        <div class="wa-chat-status">online • Member #<?= $chatUser['id'] ?></div>
                    </div>
                </div>

                <div class="wa-header-icons">
                    <button type="button" class="wa-icon-btn" onclick="showToast('📹 WhatsApp Encrypted Video Call connecting...')" title="Video Call">📹</button>
                    <button type="button" class="wa-icon-btn" onclick="showToast('📞 WhatsApp Voice Call connecting...')" title="Voice Call">📞</button>
                    <button type="button" class="wa-icon-btn" onclick="toggleAttachmentMenu()" title="Attach">📎</button>
                    <a href="../dashboard.php" class="wa-icon-btn" title="Back to Dashboard">✕</a>
                </div>
            </div>

            <!-- WhatsApp Message Wallpaper Canvas -->
            <div class="wa-messages-canvas" id="messagesContainer">
                <div class="wa-encryption-notice">
                    🔒 <b>End-to-End Encrypted</b><br>
                    Messages in this chat are securely transferred within Unmoor Club.
                </div>

                <div class="wa-date-pill">TODAY</div>

                <?php foreach ($messages as $m): ?>
                    <?php 
                        $isMe = ((int)$m['sender_id'] === $uid);
                        $time = date("h:i A", strtotime($m['created_at'] ?? 'now'));
                    ?>
                    <div class="wa-bubble <?= $isMe ? 'me' : 'them' ?>">
                        <div class="wa-bubble-text"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                        <div class="wa-bubble-meta">
                            <span><?= $time ?></span>
                            <?php if ($isMe): ?>
                                <span class="wa-ticks <?= $m['seen'] ? 'seen' : 'sent' ?>"><?= $m['seen'] ? '✓✓' : '✓' ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div id="bottom"></div>
            </div>

            <!-- Emoji Drawer -->
            <div class="wa-emoji-drawer" id="emojiDrawer">
                <span class="wa-emoji-item" onclick="insertEmoji('😀')">😀</span>
                <span class="wa-emoji-item" onclick="insertEmoji('😂')">😂</span>
                <span class="wa-emoji-item" onclick="insertEmoji('😍')">😍</span>
                <span class="wa-emoji-item" onclick="insertEmoji('👍')">👍</span>
                <span class="wa-emoji-item" onclick="insertEmoji('🙏')">🙏</span>
                <span class="wa-emoji-item" onclick="insertEmoji('🎉')">🎉</span>
                <span class="wa-emoji-item" onclick="insertEmoji('🔥')">🔥</span>
                <span class="wa-emoji-item" onclick="insertEmoji('❤️')">❤️</span>
                <span class="wa-emoji-item" onclick="insertEmoji('💰')">💰</span>
                <span class="wa-emoji-item" onclick="insertEmoji('🪙')">🪙</span>
                <span class="wa-emoji-item" onclick="insertEmoji('✨')">✨</span>
                <span class="wa-emoji-item" onclick="insertEmoji('💯')">💯</span>
                <span class="wa-emoji-item" onclick="insertEmoji('🚀')">🚀</span>
                <span class="wa-emoji-item" onclick="insertEmoji('🤝')">🤝</span>
                <span class="wa-emoji-item" onclick="insertEmoji('💎')">💎</span>
                <span class="wa-emoji-item" onclick="insertEmoji('⚡')">⚡</span>
            </div>

            <!-- Quick Attachment Menu -->
            <div class="wa-attachment-menu" id="attachmentMenu">
                <div class="wa-attachment-item" onclick="sendPreset('Hello! How are you?')">
                    <span>👋</span>
                    <span>Say Hello</span>
                </div>
                <div class="wa-attachment-item" onclick="sendPreset('Thanks for your support! 🪙')">
                    <span>🤝</span>
                    <span>Send Thanks</span>
                </div>
                <div class="wa-attachment-item" onclick="sendPreset('Let\'s play Head/Tail in the club! 🎲')">
                    <span>🎲</span>
                    <span>Invite to Game</span>
                </div>
                <a href="../transfer.php" class="wa-attachment-item">
                    <span>💰</span>
                    <span>Transfer Coins</span>
                </a>
            </div>

            <!-- WhatsApp Bottom Input Bar -->
            <form id="chatSendForm" method="post" class="wa-input-container" onsubmit="handleChatSubmit(event)">
                <button type="button" class="wa-icon-btn" onclick="toggleEmojiDrawer()" title="Emojis">😊</button>
                <button type="button" class="wa-icon-btn" onclick="toggleAttachmentMenu()" title="Attach">📎</button>

                <div class="wa-input-pill">
                    <input type="text" id="msgInput" name="msg" placeholder="Type a message" autocomplete="off" oninput="handleInputState(this.value)">
                </div>

                <button type="submit" class="wa-send-btn" id="sendBtn" title="Send">
                    <span id="sendBtnIcon">➤</span>
                </button>
            </form>

        <?php else: ?>
            <!-- No Chat Selected Placeholder -->
            <div class="wa-empty-chat">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: #008069; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 38px; margin-bottom: 20px; box-shadow: 0 4px 14px rgba(0,128,105,0.3);">
                    💬
                </div>
                <h2 style="font-size: 26px; font-weight: 400; color: #111b21; margin-bottom: 12px;">WhatsApp for Unmoor Club</h2>
                <p style="font-size: 14px; color: #667781; max-width: 440px; line-height: 1.6; margin-bottom: 20px;">
                    Send and receive messages directly with members and club admins in real time. Select any conversation from the list to start messaging.
                </p>
                <div style="font-size: 12px; color: #8696a0; display: flex; align-items: center; gap: 6px;">
                    <span>🔒</span>
                    <span>End-to-end encrypted messaging</span>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Feedback Toast Element -->
<div id="waToast" class="wa-toast">Notification</div>

<script>
const activeChatUserId = <?= (int)$chatUserId ?>;
const myUserId = <?= (int)$uid ?>;

function showToast(msg) {
    const t = document.getElementById('waToast');
    if (!t) return;
    t.innerText = msg;
    t.classList.add('show');
    setTimeout(() => {
        t.classList.remove('show');
    }, 2800);
}

function scrollToBottom() {
    const el = document.getElementById('messagesContainer');
    if (el) {
        el.scrollTop = el.scrollHeight;
    }
}
scrollToBottom();

function filterContacts(query) {
    const q = query.toLowerCase().trim();
    const items = document.querySelectorAll('.wa-contact-item');
    items.forEach(it => {
        const name = it.getAttribute('data-name') || '';
        it.style.display = name.includes(q) ? 'flex' : 'none';
    });
}

function toggleEmojiDrawer() {
    const drawer = document.getElementById('emojiDrawer');
    if (!drawer) return;
    drawer.classList.toggle('open');
    closeAttachmentMenu();
}

function closeEmojiDrawer() {
    const drawer = document.getElementById('emojiDrawer');
    if (drawer) drawer.classList.remove('open');
}

function toggleAttachmentMenu() {
    const menu = document.getElementById('attachmentMenu');
    if (!menu) return;
    menu.classList.toggle('open');
    closeEmojiDrawer();
}

function closeAttachmentMenu() {
    const menu = document.getElementById('attachmentMenu');
    if (menu) menu.classList.remove('open');
}

function insertEmoji(emoji) {
    const input = document.getElementById('msgInput');
    if (input) {
        input.value += emoji;
        input.focus();
        handleInputState(input.value);
    }
}

function sendPreset(text) {
    const input = document.getElementById('msgInput');
    if (input) {
        input.value = text;
        closeAttachmentMenu();
        handleChatSubmit(new Event('submit'));
    }
}

function handleInputState(val) {
    const icon = document.getElementById('sendBtnIcon');
    if (!icon) return;
    if (val.trim().length > 0) {
        icon.innerText = '➤';
    } else {
        icon.innerText = '🎙️';
    }
}

// Gentle audio synthesizer for message pop feedback
function playSendTone() {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(800, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(400, ctx.currentTime + 0.08);
        gain.gain.setValueAtTime(0.12, ctx.currentTime);
        gain.gain.linearRampToValueAtTime(0.01, ctx.currentTime + 0.08);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.08);
    } catch (e) {}
}

function handleChatSubmit(e) {
    if (e && e.preventDefault) e.preventDefault();
    const input = document.getElementById('msgInput');
    if (!input) return;
    const msg = input.value.trim();
    if (!msg || !activeChatUserId) return;

    input.value = '';
    handleInputState('');
    closeEmojiDrawer();
    closeAttachmentMenu();

    playSendTone();

    const nowTime = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    appendMessage(msg, true, nowTime);

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
    bubble.className = 'wa-bubble ' + (isMe ? 'me' : 'them');
    bubble.innerHTML = `
        <div class="wa-bubble-text">${escapeHtml(text)}</div>
        <div class="wa-bubble-meta">
            <span>${timeStr}</span>
            ${isMe ? '<span class="wa-ticks sent">✓</span>' : ''}
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

// Auto poll for new messages every 2.5 seconds if active chat
if (activeChatUserId > 0) {
    setInterval(() => {
        fetch('indexx.php?u=' + activeChatUserId + '&api=fetch')
            .then(r => r.json())
            .then(data => {
                if (data && data.messages) {
                    const container = document.getElementById('messagesContainer');
                    const bottom = document.getElementById('bottom');
                    if (!container) return;

                    let html = `
                        <div class="wa-encryption-notice">
                            🔒 <b>End-to-End Encrypted</b><br>
                            Messages in this chat are securely transferred within Unmoor Club.
                        </div>
                        <div class="wa-date-pill">TODAY</div>
                    `;

                    data.messages.forEach(m => {
                        const isMe = (parseInt(m.sender_id) === myUserId);
                        const time = new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                        html += `
                            <div class="wa-bubble ${isMe ? 'me' : 'them'}">
                                <div class="wa-bubble-text">${escapeHtml(m.message)}</div>
                                <div class="wa-bubble-meta">
                                    <span>${time}</span>
                                    ${isMe ? `<span class="wa-ticks ${m.seen ? 'seen' : 'sent'}">${m.seen ? '✓✓' : '✓'}</span>` : ''}
                                </div>
                            </div>
                        `;
                    });
                    html += '<div id="bottom"></div>';
                    
                    const isAtBottom = (container.scrollHeight - container.scrollTop <= container.clientHeight + 100);
                    container.innerHTML = html;
                    if (isAtBottom) {
                        scrollToBottom();
                    }
                }
            })
            .catch(() => {});
    }, 2500);
}
</script>
</body>
</html>
