<?php
/**
 * UNMOOR CLUB - REGISTER
 * Modern, Secure & High-Conversion Registration Experience
 */

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/core/config.php";
require_once __DIR__ . "/core/components.php";
require_once __DIR__ . "/core/firebase.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$errors = [];
$name   = '';
$phone  = '';
$couponInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = trim($_POST['name'] ?? '');
    $phoneRaw    = trim($_POST['phone'] ?? '');
    $password    = $_POST['password'] ?? '';
    $couponInput = strtoupper(trim($_POST['coupon'] ?? ''));

    // Normalize phone number (handle spaces, dashes, +880 or 880 prefix)
    $phone = preg_replace('/[^0-9]/', '', $phoneRaw);
    if (str_starts_with($phone, '880')) {
        $phone = '0' . substr($phone, 3);
    } elseif (str_starts_with($phone, '88')) {
        $phone = substr($phone, 2);
    }

    /* VALIDATION */
    if (strlen($name) < 3) {
        $errors[] = "Full name must be at least 3 characters long.";
    } elseif (strlen($name) > 60) {
        $errors[] = "Full name cannot exceed 60 characters.";
    }

    if (!preg_match('/^01[3-9][0-9]{8}$/', $phone) && !preg_match('/^[0-9]{10,11}$/', $phone)) {
        $errors[] = "Please enter a valid 11-digit mobile number (e.g. 017XXXXXXXX).";
    }

    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters.";
    }

    /* CHECK EXISTING USER */
    if (!$errors) {
        $stmt = $db->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $errors[] = "This mobile number is already registered. Please log in instead.";
        }
    }

    /* COUPON VALIDATION */
    $coupon = null;
    if (!$errors && $couponInput !== '') {
        $stmt = $db->prepare("
            SELECT id, amount, used_by, status
            FROM coupons
            WHERE code = ? AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$couponInput]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            $errors[] = "The voucher code '$couponInput' is invalid or expired.";
        } elseif (!empty($coupon['used_by']) || $coupon['status'] !== 'active') {
            $errors[] = "This voucher code has already been redeemed.";
        } elseif ((float)$coupon['amount'] < 150) {
            $errors[] = "Voucher code value must be at least ৳150 for instant club registration.";
        }
    }

    /* CREATE USER */
    if (!$errors) {
        $db->beginTransaction();

        try {
            $status = 'pending';
            $apply  = 'none';
            $coins  = 0;
            $cycleStart = null;
            $systemCoins = 0;

            if ($coupon) {
                $status = 'active';
                $apply  = 'approved';
                $cycleStart = date('Y-m-d H:i:s');

                $extraCoins = floor(($coupon['amount'] - 150) / 10);
                $coins = 5 + max(0, $extraCoins);
                $systemCoins = ($coupon['amount'] / 10) - $coins;
            }

            $stmt = $db->prepare("
                INSERT INTO users
                    (name, phone, password, role, status, apply_status, coins, coin_cycle_start, created_at)
                VALUES
                    (?, ?, ?, 'user', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $name,
                $phone,
                password_hash($password, PASSWORD_DEFAULT),
                $status,
                $apply,
                $coins,
                $cycleStart
            ]);

            $uid = (int)$db->lastInsertId();

            if ($coupon) {
                $cpUpd = $db->prepare("
                    UPDATE coupons
                    SET used_by = ?, used_at = NOW(), status = 'used'
                    WHERE id = ? AND status = 'active' AND used_by IS NULL
                ");
                $cpUpd->execute([$uid, $coupon['id']]);

                if ($cpUpd->rowCount() === 0) {
                    throw new Exception("Coupon has already been used by another session.");
                }

                if ($systemCoins > 0) {
                    $db->prepare("
                        UPDATE users
                        SET coins = coins + ?
                        WHERE role = 'system'
                    ")->execute([$systemCoins]);
                }

                $db->prepare("
                    INSERT INTO payments
                    (user_id, type, amount, status, source, created_at)
                    VALUES (?, 'coupon', ?, 'approved', 'registration', NOW())
                ")->execute([
                    $uid,
                    $coupon['amount']
                ]);

                // Record welcome bonus in coin history ledger
                $db->prepare("
                    INSERT INTO coin_history
                    (user_id, amount, type, source, reference, created_at)
                    VALUES (?, ?, 'coupon_register', 'COUPON_REGISTRATION', 'Instant VIP Registration Coupon', NOW())
                ")->execute([$uid, $coins]);
            }

            $db->commit();

            session_regenerate_id(true);
            $_SESSION['uid']        = $uid;
            $_SESSION['user_id']    = $uid;
            $_SESSION['user_name']  = $name;
            $_SESSION['role']       = 'user';
            $_SESSION['login_time'] = time();

            if ($coupon) {
                header("Location: dashboard.php");
            } else {
                header("Location: apply_payment.php");
            }
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $errors[] = "Registration could not be completed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account • <?= htmlspecialchars(SITE_NAME) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body.register-body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: #1c0702 url('assets/bg/bg.svg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            position: relative;
            box-sizing: border-box;
        }
        body.register-body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(5, 7, 15, 0.52);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
            z-index: 1;
        }
        .register-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 440px;
            padding: 24px 16px;
            box-sizing: border-box;
        }
        .register-card {
            background: #ffffff;
            color: #1f2937;
            border-radius: 24px;
            padding: 32px 26px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(255, 255, 255, 0.1);
            text-align: center;
            position: relative;
        }
        .register-logo {
            width: 100%;
            max-width: 250px;
            margin: 0 auto 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(139, 92, 246, 0.1);
            color: #7c3aed;
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 12px;
            border-radius: 9999px;
            margin-bottom: 10px;
        }
        .register-title {
            font-size: 24px;
            font-weight: 900;
            color: #111827;
            margin: 0 0 6px 0;
            letter-spacing: -0.5px;
        }
        .register-subtitle {
            font-size: 13.5px;
            color: #6b7280;
            margin: 0 0 22px 0;
            line-height: 1.4;
        }
        .form-group-custom {
            text-align: left;
            margin-bottom: 16px;
        }
        .form-label-custom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 6px;
        }
        .input-box {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            color: #9ca3af;
            display: flex;
            align-items: center;
            pointer-events: none;
        }
        .register-input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            border: 1.5px solid #d1d5db;
            border-radius: 12px;
            font-size: 14.5px;
            color: #111827;
            background: #f9fafb;
            outline: none;
            transition: all 0.2s ease;
            box-sizing: border-box;
            font-family: inherit;
        }
        .register-input:focus {
            border-color: #8b5cf6;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
        }
        .input-password-toggle {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: color 0.2s;
        }
        .input-password-toggle:hover {
            color: #111827;
        }
        /* Password strength meter */
        .password-strength-bar {
            height: 4px;
            width: 100%;
            background: #e5e7eb;
            border-radius: 2px;
            margin-top: 6px;
            overflow: hidden;
            display: none;
        }
        .password-strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease, background 0.3s ease;
        }
        /* VIP Voucher Highlight Card */
        .coupon-banner {
            background: #fbfbfe;
            border: 1.5px dashed #c4b5fd;
            border-radius: 14px;
            padding: 12px 14px;
            margin-bottom: 20px;
            text-align: left;
            position: relative;
        }
        .coupon-banner-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            font-weight: 800;
            color: #6d28d9;
            margin-bottom: 4px;
        }
        .coupon-banner-sub {
            font-size: 11.5px;
            color: #6b7280;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        .coupon-input-group {
            position: relative;
        }
        .coupon-input {
            width: 100%;
            padding: 10px 12px 10px 38px;
            border: 1.5px solid #d8b4fe;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #4c1d95;
            background: #ffffff;
            outline: none;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .coupon-input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
        }
        .register-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: #ffffff;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(109, 40, 217, 0.3);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .register-btn:hover {
            opacity: 0.95;
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(109, 40, 217, 0.4);
        }
        .register-btn:active {
            transform: translateY(0);
        }
        .register-alert {
            background: #fee2e2;
            border: 1px solid #f87171;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 18px;
            text-align: left;
            line-height: 1.4;
        }
        .register-alert ul {
            margin: 0;
            padding-left: 18px;
        }
        .register-links {
            margin-top: 20px;
            font-size: 13.5px;
            color: #6b7280;
        }
        .register-links a {
            color: #7c3aed;
            font-weight: 800;
            text-decoration: none;
        }
        .register-links a:hover {
            text-decoration: underline;
        }
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 20px;
            padding-top: 14px;
            border-top: 1px solid #f3f4f6;
            font-size: 11.5px;
            color: #9ca3af;
            font-weight: 600;
        }
        .security-badge span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
    </style>
</head>
<body class="register-body">
    <div class="register-wrapper">
        <div class="register-card" id="registerCard">
            
            <div class="register-logo">
                <?php render_unmoor_logo_svg('240px'); ?>
            </div>

            <div class="badge-pill">
                <span>✨ Official Club Access</span>
            </div>

            <h2 class="register-title">Create Account</h2>
            <p class="register-subtitle">
                Join our premium community in under 60 seconds
            </p>

            <?php if ($errors): ?>
                <div class="register-alert" id="registerAlert">
                    <ul>
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="register.php" id="registerForm" novalidate>
                <!-- Full Name -->
                <div class="form-group-custom">
                    <label class="form-label-custom" for="regName">Full Name</label>
                    <div class="input-box">
                        <span class="input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <input 
                            type="text" 
                            name="name" 
                            id="regName"
                            class="register-input" 
                            placeholder="e.g. Johnathan Doe" 
                            value="<?= htmlspecialchars($name) ?>" 
                            required 
                            autocomplete="name"
                        >
                    </div>
                </div>

                <!-- Mobile Number -->
                <div class="form-group-custom">
                    <label class="form-label-custom" for="regPhone">
                        <span>Mobile Number</span>
                        <span style="font-size: 11px; color: #9ca3af; font-weight: normal;">BD (+880)</span>
                    </label>
                    <div class="input-box">
                        <span class="input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                            </svg>
                        </span>
                        <input 
                            type="tel" 
                            name="phone" 
                            id="regPhone"
                            class="register-input" 
                            placeholder="01XXXXXXXXX" 
                            value="<?= htmlspecialchars($phone) ?>" 
                            required 
                            inputmode="numeric"
                            autocomplete="tel"
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group-custom">
                    <label class="form-label-custom" for="regPassword">
                        <span>Password</span>
                        <span style="font-size: 11px; color: #9ca3af; font-weight: normal;">Min. <?= PASSWORD_MIN_LENGTH ?> chars</span>
                    </label>
                    <div class="input-box">
                        <span class="input-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input 
                            type="password" 
                            name="password" 
                            id="regPassword"
                            class="register-input" 
                            style="padding-right: 44px;"
                            placeholder="Create a strong password" 
                            required 
                            autocomplete="new-password"
                        >
                        <button type="button" class="input-password-toggle" id="togglePasswordBtn" title="Show or hide password" aria-label="Toggle password visibility">
                            <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <div class="password-strength-bar" id="strengthBar">
                        <div class="password-strength-fill" id="strengthFill"></div>
                    </div>
                </div>

                <!-- VIP Coupon Voucher Highlight Card -->
                <div class="coupon-banner">
                    <div class="coupon-banner-header">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                        <span>VIP Voucher Code (Optional)</span>
                    </div>
                    <div class="coupon-banner-sub">
                        Have a VIP access code? Enter it below to unlock instant approval & starter coins.
                    </div>
                    <div class="coupon-input-group">
                        <span class="input-icon" style="left: 12px; color: #a855f7;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                <line x1="7" y1="7" x2="7.01" y2="7"></line>
                            </svg>
                        </span>
                        <input 
                            type="text" 
                            name="coupon" 
                            id="regCoupon"
                            class="coupon-input" 
                            placeholder="e.g. VIPCLUB2026" 
                            value="<?= htmlspecialchars($couponInput) ?>" 
                            autocomplete="off"
                        >
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="register-btn" id="submitBtn">
                    <span>Create My Account</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </form>

            <div class="register-links">
                Already have an account? 
                <a href="login.php" id="toLoginLink">Log In here →</a>
            </div>

            <div class="security-badge">
                <span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    256-Bit Encrypted
                </span>
                <span>•</span>
                <span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                    Instant Setup
                </span>
            </div>

        </div>
    </div>

    <script>
        // Password visibility toggle
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passInput = document.getElementById('regPassword');
        const eyeIcon = document.getElementById('eyeIcon');

        if (toggleBtn && passInput) {
            toggleBtn.addEventListener('click', function() {
                const isPassword = passInput.type === 'password';
                passInput.type = isPassword ? 'text' : 'password';
                if (isPassword) {
                    // Show eye-off icon
                    eyeIcon.innerHTML = `
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    `;
                } else {
                    // Show standard eye icon
                    eyeIcon.innerHTML = `
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    `;
                }
            });
        }

        // Live password strength feedback
        const strengthBar = document.getElementById('strengthBar');
        const strengthFill = document.getElementById('strengthFill');

        if (passInput && strengthBar && strengthFill) {
            passInput.addEventListener('input', function() {
                const val = passInput.value;
                if (!val) {
                    strengthBar.style.display = 'none';
                    return;
                }
                strengthBar.style.display = 'block';

                let score = 0;
                if (val.length >= 6) score += 25;
                if (val.length >= 8) score += 25;
                if (/[0-9]/.test(val)) score += 25;
                if (/[A-Z]/.test(val) || /[^A-Za-z0-9]/.test(val)) score += 25;

                strengthFill.style.width = score + '%';
                if (score <= 25) {
                    strengthFill.style.background = '#ef4444'; // Red
                } else if (score <= 50) {
                    strengthFill.style.background = '#f59e0b'; // Amber
                } else if (score <= 75) {
                    strengthFill.style.background = '#3b82f6'; // Blue
                } else {
                    strengthFill.style.background = '#10b981'; // Green
                }
            });
        }

        // Phone normalization on input
        const phoneInput = document.getElementById('regPhone');
        if (phoneInput) {
            phoneInput.addEventListener('blur', function() {
                let v = phoneInput.value.replace(/[^0-9]/g, '');
                if (v.startsWith('880')) {
                    v = '0' + v.substring(3);
                } else if (v.startsWith('88')) {
                    v = v.substring(2);
                }
                phoneInput.value = v;
            });
        }
    </script>
    <?php render_firebase_sdk_scripts(); ?>
</body>
</html>
