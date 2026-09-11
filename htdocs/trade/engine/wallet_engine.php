<?php
/**
 * UNMOOR CLUB - TRADING WALLET & CONVERSION ENGINE
 * Handles Club UC <-> BDT conversion (1 Club UC = 10 BDT with exact 1.345% fee),
 * Main BDT <-> Trading BDT transfers, and wallet balance accounting.
 */

if (!defined('UC_TRADING_CORE')) {
    define('UC_TRADING_CORE', true);
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/fee_engine.php';

class WalletEngine {

    /**
     * Get or initialize trading wallet for user
     */
    public static function getTradingWallet(PDO $db, int $userId): array {
        $stmt = $db->prepare("SELECT * FROM trading_wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet) {
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";
            $ins = $db->prepare("INSERT INTO trading_wallets (user_id, bdt_balance, tuc_balance, locked_margin, realized_pnl, total_trading_fees, total_conversion_fees, created_at, updated_at)
                VALUES (?, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, $nowExpr, $nowExpr)");
            $ins->execute([$userId]);

            $stmt->execute([$userId]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $bdt = (float)($wallet['bdt_balance'] ?? 0);
        $locked = (float)($wallet['locked_margin'] ?? 0);
        $avail = max(0.0, round($bdt - $locked, 4));

        $wallet['bdt_balance'] = $bdt;
        $wallet['tuc_balance'] = (float)($wallet['tuc_balance'] ?? 0);
        $wallet['locked_margin'] = $locked;
        $wallet['available_margin'] = $avail;
        $wallet['realized_pnl'] = (float)($wallet['realized_pnl'] ?? 0);
        $wallet['total_trading_fees'] = (float)($wallet['total_trading_fees'] ?? 0);
        $wallet['total_conversion_fees'] = (float)($wallet['total_conversion_fees'] ?? 0);

        return $wallet;
    }

    /**
     * Get comprehensive balance snapshot for UI & validations
     */
    public static function getComprehensiveBalances(PDO $db, int $userId): array {
        $uStmt = $db->prepare("SELECT id, name, phone, coins, balance, COALESCE(locked_coins, 0) as locked_coins FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return [
                'success' => false,
                'error' => 'User not found'
            ];
        }

        $tradingWallet = self::getTradingWallet($db, $userId);

        // Calculate active unrealized PnL from open positions
        $posStmt = $db->prepare("SELECT side, entry_price, position_size, margin, leverage FROM trading_positions WHERE user_id = ? AND status = 'open'");
        $posStmt->execute([$userId]);
        $openPositions = $posStmt->fetchAll(PDO::FETCH_ASSOC);

        $mState = $db->query("SELECT price FROM market_state WHERE symbol = 'UC' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $currentPrice = (float)($mState['price'] ?? 2.0000);

        $unrealizedPnl = 0.0;
        foreach ($openPositions as $pos) {
            $side = $pos['side'];
            $entry = (float)$pos['entry_price'];
            $size = (float)$pos['position_size'];

            if ($side === 'long') {
                $unrealizedPnl += ($currentPrice - $entry) * $size;
            } else {
                $unrealizedPnl += ($entry - $currentPrice) * $size;
            }
        }
        $unrealizedPnl = round($unrealizedPnl, 4);

        $clubUc = (float)($user['coins'] ?? 0);
        $lockedClubUc = (float)($user['locked_coins'] ?? 0);
        $mainBdt = (float)($user['balance'] ?? 0);
        $tradingBdt = (float)$tradingWallet['bdt_balance'];
        $tradingUc = (float)$tradingWallet['tuc_balance'];
        $lockedMargin = (float)$tradingWallet['locked_margin'];
        $availableMargin = (float)$tradingWallet['available_margin'];
        $realizedPnl = (float)$tradingWallet['realized_pnl'];

        // Total Equity in Trading Wallet = Trading BDT + Unrealized PnL + (Trading UC * Current Price)
        $tradingEquity = round($tradingBdt + $unrealizedPnl + ($tradingUc * $currentPrice), 4);

        return [
            'success' => true,
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'] ?? '',
                'phone' => $user['phone'] ?? ''
            ],
            'club_wallet' => [
                'club_uc' => $clubUc,
                'locked_club_uc' => $lockedClubUc,
                'total_club_uc' => round($clubUc + $lockedClubUc, 4),
                'conversion_rate_bdt' => 10.00, // 1 Club UC = 10 BDT
                'approx_bdt_value' => round($clubUc * 10.0, 2)
            ],
            'main_wallet' => [
                'bdt_balance' => $mainBdt
            ],
            'trading_wallet' => [
                'bdt_balance' => $tradingBdt,
                'tuc_balance' => $tradingUc,
                'locked_margin' => $lockedMargin,
                'available_margin' => $availableMargin,
                'realized_pnl' => $realizedPnl,
                'unrealized_pnl' => $unrealizedPnl,
                'trading_equity' => $tradingEquity,
                'total_trading_fees' => (float)$tradingWallet['total_trading_fees'],
                'total_conversion_fees' => (float)$tradingWallet['total_conversion_fees']
            ],
            'market' => [
                'symbol' => 'UC/BDT',
                'current_price' => $currentPrice
            ]
        ];
    }

    /**
     * Convert Club UC -> Main BDT
     * Rate: 1 Club UC = 10 BDT
     * Fee: 1.345% of gross BDT
     */
    public static function convertClubUcToBdt(PDO $db, int $userId, float $clubUcAmount): array {
        if ($clubUcAmount <= 0) {
            return ['success' => false, 'error' => 'Invalid Club UC conversion amount.'];
        }

        $db->beginTransaction();
        try {
            // Lock user row
            $stmt = $db->prepare("SELECT coins, balance FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $db->rollBack();
                return ['success' => false, 'error' => 'User not found.'];
            }

            $currentCoins = (float)$user['coins'];
            if ($currentCoins < $clubUcAmount) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Insufficient Club UC balance. Available: ' . number_format($currentCoins, 4) . ' UC'];
            }

            // Calculations
            $rate = 10.0000; // 1 Club UC = 10 BDT
            $grossBdt = round($clubUcAmount * $rate, 4);
            $feeData = FeeEngine::calculateConversionFee($db, $grossBdt);
            $feeBdt = $feeData['fee'];
            $feeRate = $feeData['rate'];
            $netBdt = $feeData['net'];

            $currentBdt = (float)($user['balance'] ?? 0);
            $newCoins = round($currentCoins - $clubUcAmount, 4);
            $newBdt = round($currentBdt + $netBdt, 4);

            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

            // Update user balances
            $upd = $db->prepare("UPDATE users SET coins = coins - ?, balance = balance + ? WHERE id = ? AND coins >= CAST(? AS NUMERIC)");
            $upd->execute([$clubUcAmount, $netBdt, $userId, $clubUcAmount]);
            if ($upd->rowCount() === 0) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Concurrent transaction conflict. Please try again.'];
            }

            // Insert into conversions
            $cStmt = $db->prepare("INSERT INTO conversions (user_id, from_asset, to_asset, from_amount, to_amount_gross, fee_rate, fee_amount, to_amount_net, rate, status, created_at)
                VALUES (?, 'Club_UC', 'BDT', ?, ?, ?, ?, ?, ?, 'completed', $nowExpr)");
            $cStmt->execute([$userId, $clubUcAmount, $grossBdt, $feeRate, $feeBdt, $netBdt, $rate]);
            $convId = (int)$db->lastInsertId();

            // Route fee to liquidity pool
            FeeEngine::routeFeeToLiquidityPool($db, $feeBdt, 'conversion', "Conversion #$convId: $clubUcAmount Club UC -> $netBdt BDT (Fee: $feeBdt BDT)", $userId, null, null, $convId, $feeRate);

            // Update trading wallet conversion fee stat
            $db->prepare("UPDATE trading_wallets SET total_conversion_fees = total_conversion_fees + ?, updated_at = $nowExpr WHERE user_id = ?")->execute([$feeBdt, $userId]);

            // Ledger records
            $refMsg = "Converted " . number_format($clubUcAmount, 4) . " Club UC to ৳ " . number_format($netBdt, 2) . " BDT (Fee: ৳ " . number_format($feeBdt, 2) . ")";
            $db->prepare("INSERT INTO coin_history (user_id, amount, type, reference, created_at)
                VALUES (?, ?, 'conversion_out', ?, $nowExpr)")->execute([$userId, -$clubUcAmount, $refMsg]);

            $db->prepare("INSERT INTO trading_transactions (user_id, type, amount, asset, reference, balance_before, balance_after, created_at)
                VALUES (?, 'conversion_in', ?, 'BDT', ?, ?, ?, $nowExpr)")->execute([$userId, $netBdt, $refMsg, $currentBdt, $newBdt]);

            $db->commit();

            return [
                'success' => true,
                'message' => "Successfully converted {$clubUcAmount} Club UC into ৳ {$netBdt} BDT.",
                'conversion' => [
                    'id' => $convId,
                    'from_amount' => $clubUcAmount,
                    'from_asset' => 'Club_UC',
                    'gross_bdt' => $grossBdt,
                    'fee_rate_pct' => ($feeRate * 100) . '%',
                    'fee_bdt' => $feeBdt,
                    'net_bdt' => $netBdt,
                    'rate' => '1 Club UC = 10 BDT'
                ],
                'balances' => self::getComprehensiveBalances($db, $userId)
            ];

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("convertClubUcToBdt Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Conversion failed: ' . $e->getMessage()];
        }
    }

    /**
     * Convert Main BDT -> Club UC
     * Rate: 10 BDT = 1 Club UC (i.e. 0.1 Club UC per BDT)
     * Fee: 1.345% of gross BDT
     */
    public static function convertBdtToClubUc(PDO $db, int $userId, float $bdtAmount): array {
        if ($bdtAmount <= 0) {
            return ['success' => false, 'error' => 'Invalid BDT conversion amount.'];
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT coins, balance FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $db->rollBack();
                return ['success' => false, 'error' => 'User not found.'];
            }

            $currentBdt = (float)($user['balance'] ?? 0);
            if ($currentBdt < $bdtAmount) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Insufficient Main BDT balance. Available: ৳ ' . number_format($currentBdt, 2)];
            }

            $rate = 0.1000; // 1 BDT = 0.1 Club UC (10 BDT = 1 Club UC)
            $grossClubUc = round($bdtAmount * $rate, 4);

            $feeData = FeeEngine::calculateConversionFee($db, $bdtAmount);
            $feeBdt = $feeData['fee'];
            $feeRate = $feeData['rate'];
            $feeClubUc = round($grossClubUc * $feeRate, 4);
            $netClubUc = round($grossClubUc - $feeClubUc, 4);

            $currentCoins = (float)($user['coins'] ?? 0);
            $newBdt = round($currentBdt - $bdtAmount, 4);
            $newCoins = round($currentCoins + $netClubUc, 4);

            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

            $upd = $db->prepare("UPDATE users SET balance = balance - ?, coins = coins + ? WHERE id = ? AND balance >= CAST(? AS NUMERIC)");
            $upd->execute([$bdtAmount, $netClubUc, $userId, $bdtAmount]);
            if ($upd->rowCount() === 0) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Concurrent transaction conflict. Please try again.'];
            }

            $cStmt = $db->prepare("INSERT INTO conversions (user_id, from_asset, to_asset, from_amount, to_amount_gross, fee_rate, fee_amount, to_amount_net, rate, status, created_at)
                VALUES (?, 'BDT', 'Club_UC', ?, ?, ?, ?, ?, ?, 'completed', $nowExpr)");
            $cStmt->execute([$userId, $bdtAmount, $grossClubUc, $feeRate, $feeBdt, $netClubUc, 10.0]);
            $convId = (int)$db->lastInsertId();

            FeeEngine::routeFeeToLiquidityPool($db, $feeBdt, 'conversion', "Conversion #$convId: $bdtAmount BDT -> $netClubUc Club UC (Fee: $feeBdt BDT)", $userId, null, null, $convId, $feeRate);

            $db->prepare("UPDATE trading_wallets SET total_conversion_fees = total_conversion_fees + ?, updated_at = $nowExpr WHERE user_id = ?")->execute([$feeBdt, $userId]);

            $refMsg = "Converted ৳ " . number_format($bdtAmount, 2) . " BDT to " . number_format($netClubUc, 4) . " Club UC (Fee: ৳ " . number_format($feeBdt, 2) . ")";
            $db->prepare("INSERT INTO trading_transactions (user_id, type, amount, asset, reference, balance_before, balance_after, created_at)
                VALUES (?, 'conversion_out', ?, 'BDT', ?, ?, ?, $nowExpr)")->execute([$userId, -$bdtAmount, $refMsg, $currentBdt, $newBdt]);

            $db->prepare("INSERT INTO coin_history (user_id, amount, type, reference, created_at)
                VALUES (?, ?, 'conversion_in', ?, $nowExpr)")->execute([$userId, $netClubUc, $refMsg]);

            $db->commit();

            return [
                'success' => true,
                'message' => "Successfully converted ৳ {$bdtAmount} BDT into {$netClubUc} Club UC.",
                'conversion' => [
                    'id' => $convId,
                    'from_amount' => $bdtAmount,
                    'from_asset' => 'BDT',
                    'gross_club_uc' => $grossClubUc,
                    'fee_rate_pct' => ($feeRate * 100) . '%',
                    'fee_bdt' => $feeBdt,
                    'net_club_uc' => $netClubUc,
                    'rate' => '10 BDT = 1 Club UC'
                ],
                'balances' => self::getComprehensiveBalances($db, $userId)
            ];

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("convertBdtToClubUc Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Conversion failed: ' . $e->getMessage()];
        }
    }

    /**
     * Transfer funds between Main BDT and Trading BDT
     * @param string $direction 'main_to_trading' | 'trading_to_main'
     */
    public static function transferBdtWallet(PDO $db, int $userId, float $amount, string $direction): array {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Invalid transfer amount.'];
        }

        $db->beginTransaction();
        try {
            $user = $db->prepare("SELECT balance FROM users WHERE id = ?");
            $user->execute([$userId]);
            $uRow = $user->fetch(PDO::FETCH_ASSOC);

            if (!$uRow) {
                $db->rollBack();
                return ['success' => false, 'error' => 'User not found.'];
            }

            $wallet = self::getTradingWallet($db, $userId);
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $nowExpr = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

            if ($direction === 'main_to_trading') {
                $mainBal = (float)$uRow['balance'];
                if ($mainBal < $amount) {
                    $db->rollBack();
                    return ['success' => false, 'error' => 'Insufficient Main BDT balance. Available: ৳ ' . number_format($mainBal, 2)];
                }

                $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= CAST(? AS NUMERIC)")->execute([$amount, $userId, $amount]);
                $db->prepare("UPDATE trading_wallets SET bdt_balance = bdt_balance + ?, updated_at = $nowExpr WHERE user_id = ?")->execute([$amount, $userId]);

                $ref = "Deposited ৳ " . number_format($amount, 2) . " into Trading BDT Wallet";
                $db->prepare("INSERT INTO trading_transactions (user_id, type, amount, asset, reference, balance_before, balance_after, created_at)
                    VALUES (?, 'deposit_trading', ?, 'BDT', ?, ?, ?, $nowExpr)")
                    ->execute([$userId, $amount, $ref, $wallet['bdt_balance'], $wallet['bdt_balance'] + $amount]);

            } elseif ($direction === 'trading_to_main') {
                $availMargin = (float)$wallet['available_margin'];
                if ($availMargin < $amount) {
                    $db->rollBack();
                    return ['success' => false, 'error' => 'Insufficient available margin in Trading Wallet. Available to withdraw: ৳ ' . number_format($availMargin, 2)];
                }

                $db->prepare("UPDATE trading_wallets SET bdt_balance = bdt_balance - ?, updated_at = $nowExpr WHERE user_id = ? AND (bdt_balance - locked_margin) >= CAST(? AS NUMERIC)")
                    ->execute([$amount, $userId, $amount]);
                $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$amount, $userId]);

                $ref = "Withdrew ৳ " . number_format($amount, 2) . " from Trading BDT Wallet to Main Balance";
                $db->prepare("INSERT INTO trading_transactions (user_id, type, amount, asset, reference, balance_before, balance_after, created_at)
                    VALUES (?, 'withdraw_trading', ?, 'BDT', ?, ?, ?, $nowExpr)")
                    ->execute([$userId, -$amount, $ref, $wallet['bdt_balance'], $wallet['bdt_balance'] - $amount]);

            } else {
                $db->rollBack();
                return ['success' => false, 'error' => 'Invalid transfer direction.'];
            }

            $db->commit();
            return [
                'success' => true,
                'message' => "Successfully transferred ৳ " . number_format($amount, 2) . " BDT.",
                'balances' => self::getComprehensiveBalances($db, $userId)
            ];

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("transferBdtWallet Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Transfer failed: ' . $e->getMessage()];
        }
    }
}
