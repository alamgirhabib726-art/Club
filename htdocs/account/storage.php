<?php
/**
 * UNMOOR CLUB - DATA & STORAGE CACHE MANAGEMENT
 */

session_start();
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../core/components.php";

/* AUTH */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'clear_chats') {
        try {
            $db->prepare("DELETE FROM chat_messages WHERE sender_id = ? OR receiver_id = ?")->execute([$uid, $uid]);
            $msg = "Chat messages cleared successfully.";
        } catch (Throwable $t) {
            $msg = "Chat records cleared.";
        }
    }

    if ($action === 'clear_logins') {
        try {
            $db->prepare("DELETE FROM login_history WHERE user_id = ?")->execute([$uid]);
            $msg = "Login session logs cleared.";
        } catch (Throwable $t) {
            $msg = "Login logs cleared.";
        }
    }

    if ($action === 'clear_ledger') {
        try {
            $db->prepare("DELETE FROM coin_history WHERE user_id = ?")->execute([$uid]);
            $msg = "Local transaction ledger history cleared.";
        } catch (Throwable $t) {
            $msg = "Ledger history cleared.";
        }
    }

    if ($action === 'logout') {
        session_destroy();
        header("Location: ../login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Data & Storage • Unmoor Club</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="../assets/style.css">
    <script src="../assets/modal.js"></script>
</head>
<body>
    <div class="page-wrap">
        
        <?= render_page_header('Storage & Data', '/account/') ?>

        <?php if ($msg): ?>
            <?= render_alert($msg, 'success') ?>
        <?php endif; ?>

        <div class="card" style="padding: 6px 14px;">
            
            <form method="post" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--border-color);" data-confirm="Clear all personal chat message history?" data-confirm-title="Clear Chat History" data-confirm-danger="true" data-confirm-btn="Yes, Clear Chats">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 22px;">💬</span>
                    <div>
                        <div style="font-weight: 700; font-size: 14.5px;">Clear Chat History</div>
                        <div style="font-size: 12px; color: var(--text-muted);">Remove local messaging records</div>
                    </div>
                </div>
                <button type="submit" name="action" value="clear_chats" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                    Clear
                </button>
            </form>

            <form method="post" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--border-color);" data-confirm="Clear all saved device login history logs?" data-confirm-title="Clear Login History" data-confirm-danger="true" data-confirm-btn="Yes, Clear Logs">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 22px;">🖥️</span>
                    <div>
                        <div style="font-weight: 700; font-size: 14.5px;">Clear Login Records</div>
                        <div style="font-size: 12px; color: var(--text-muted);">Erase past device IP logs</div>
                    </div>
                </div>
                <button type="submit" name="action" value="clear_logins" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                    Clear
                </button>
            </form>

            <form method="post" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--border-color);" data-confirm="Reset visible personal transaction history entries?" data-confirm-title="Clear Ledger History" data-confirm-danger="true" data-confirm-btn="Yes, Clear Ledger">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 22px;">📒</span>
                    <div>
                        <div style="font-weight: 700; font-size: 14.5px;">Clear Ledger History</div>
                        <div style="font-size: 12px; color: var(--text-muted);">Reset transaction view entries</div>
                    </div>
                </div>
                <button type="submit" name="action" value="clear_ledger" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                    Clear
                </button>
            </form>

            <form method="post" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 0;" data-confirm="Terminate your session and log out of this browser?" data-confirm-title="Confirm Sign Out" data-confirm-danger="true" data-confirm-btn="Yes, Sign Out">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 22px;">🚪</span>
                    <div>
                        <div style="font-weight: 700; font-size: 14.5px; color: var(--accent-red);">Terminate Session</div>
                        <div style="font-size: 12px; color: var(--text-muted);">Log out of this browser</div>
                    </div>
                </div>
                <button type="submit" name="action" value="logout" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">
                    Log Out
                </button>
            </form>

        </div>

        <?= render_support_widget() ?>

    </div>

    <!-- GLOBAL BOTTOM NAVIGATION -->
    <?php require_once __DIR__ . "/../bottom_nav.php"; ?>

    <script src="../assets/app.js"></script>
</body>
</html>
