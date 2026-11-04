<?php
/**
 * Helper Functions for POS & WhatsApp Digital Billing
 */

// Set Indian Standard Time (IST) for exact real-time billing
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/database.php';

// Start session if not active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Auto-detect Machine LAN IP for Local Wi-Fi Mobile Billing
 * Allows customers on store Wi-Fi to open the digital bill instantly
 */
function get_public_base_url(): string {
    $port = $_SERVER['SERVER_PORT'] ?? 8000;
    $httpProto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';

    // Check if host in request is not localhost/127.0.0.1
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    if (!str_contains($host, 'localhost') && !str_contains($host, '127.0.0.1')) {
        return "{$httpProto}://{$host}";
    }

    // Attempt to discover local Wi-Fi / Ethernet IPv4 address on Windows or Linux
    $detectedIp = null;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $ipconfig = @shell_exec('ipconfig');
        if ($ipconfig) {
            // Find IPv4 Address . . . . . . . . . . . : 192.168.x.x or 10.x.x.x
            if (preg_match_all('/IPv4 Address[^\:]*:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/i', $ipconfig, $matches)) {
                foreach ($matches[1] as $ip) {
                    if ($ip !== '127.0.0.1' && !str_starts_with($ip, '169.254')) {
                        $detectedIp = $ip;
                        break;
                    }
                }
            }
        }
    } else {
        $hostIp = @shell_exec("hostname -I | awk '{print $1}'");
        if ($hostIp && filter_var(trim($hostIp), FILTER_VALIDATE_IP)) {
            $detectedIp = trim($hostIp);
        }
    }

    if ($detectedIp) {
        return "{$httpProto}://{$detectedIp}:{$port}";
    }

    return "{$httpProto}://{$host}";
}

/**
 * Fetch Store Settings
 */
function get_store_settings(PDO $pdo = null): array {
    if (!$pdo) {
        $pdo = get_db_connection();
    }
    $stmt = $pdo->query("SELECT * FROM settings ORDER BY id ASC LIMIT 1");
    $settings = $stmt->fetch();
    if (!$settings) {
        seed_initial_data($pdo);
        $stmt = $pdo->query("SELECT * FROM settings ORDER BY id ASC LIMIT 1");
        $settings = $stmt->fetch();
    }
    return $settings ?: [];
}

/**
 * Concurrency-Safe Sequential Invoice Number Generator
 * Example: FARAL-2026-0001 or GST-2026-0001
 */
function generate_invoice_no(PDO $pdo, string $type = 'RETAIL'): string {
    $currentYear = (int)date('Y');
    $settings = get_store_settings($pdo);
    $prefix = ($type === 'TAX_GST') ? 'GST' : ($settings['bill_prefix'] ?? 'FARAL');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT last_seq FROM invoice_sequences WHERE year = ?");
        $stmt->execute([$currentYear]);
        $row = $stmt->fetch();

        if ($row) {
            $nextSeq = (int)$row['last_seq'] + 1;
            $updateStmt = $pdo->prepare("UPDATE invoice_sequences SET last_seq = ? WHERE year = ?");
            $updateStmt->execute([$nextSeq, $currentYear]);
        } else {
            $nextSeq = 1;
            $insertStmt = $pdo->prepare("INSERT INTO invoice_sequences (year, last_seq) VALUES (?, ?)");
            $insertStmt->execute([$currentYear, $nextSeq]);
        }

        $pdo->commit();
        return sprintf('%s-%d-%04d', $prefix, $currentYear, $nextSeq);
    } catch (Exception $e) {
        $pdo->rollBack();
        // Fallback timestamp code
        return sprintf('%s-%d-%04d', $prefix, $currentYear, rand(1000, 9999));
    }
}

/**
 * Format Indian Currency
 */
function format_currency(float $amount, string $symbol = '₹'): string {
    return $symbol . ' ' . number_format($amount, 2, '.', ',');
}

/**
 * Formatted Itemized WhatsApp Text Bill
 */
function format_whatsapp_bill(array $invoice, array $items, array $settings, string $billUrl): string {
    $storeName = $settings['store_name_mr'] ?: $settings['store_name'];
    $tagline = $settings['tagline_mr'] ?: $settings['tagline'];
    $phone = $settings['phone'];
    $owner = !empty($settings['alt_phone']) ? " - {$settings['alt_phone']}" : " - किरण माळी (Kiran Mali)";
    $address = 'तासगाव';

    $msg = "✨ *{$storeName}* ✨\n";
    if ($tagline) {
        $msg .= "_{$tagline}_\n";
    }
    $msg .= "📍 {$address}\n";
    $msg .= "📞 संपर्क: {$phone}{$owner}\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "🧾 *डिजिटल बिल पावती*\n";
    $msg .= "• बिल क्र.: *{$invoice['invoice_no']}*\n";
    $msg .= "• दिनांक: " . date('d-m-Y h:i A', strtotime($invoice['created_at'])) . "\n";
    $msg .= "• ग्राहक: *" . ($invoice['customer_name'] ?: 'ग्राहक') . "*\n";
    if (!empty($invoice['customer_phone'])) {
        $msg .= "• मो.: {$invoice['customer_phone']}\n";
    }
    $msg .= "• पेमेंट: *" . $invoice['payment_mode'] . "* (" . $invoice['payment_status'] . ")\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "🛍️ *खरेदी तपशील:*\n";

    $count = 1;
    foreach ($items as $item) {
        // Format weight/quantity nicely
        $qtyText = '';
        if ($item['unit'] === 'kg') {
            if ($item['qty'] < 1) {
                $grams = round($item['qty'] * 1000);
                $qtyText = "{$grams}g (ग्रॅम)";
            } else {
                $qtyText = $item['qty'] . " kg (किलो)";
            }
        } elseif ($item['unit'] === 'g') {
            $qtyText = $item['qty'] . "g";
        } elseif ($item['unit'] === 'box') {
            $qtyText = $item['qty'] . " बॉक्स";
        } else {
            $qtyText = $item['qty'] . " " . $item['unit'];
        }

        $rate = number_format($item['unit_rate'], 2);
        $total = number_format($item['total_amount'], 2);
        $msg .= "{$count}. *{$item['product_name']}*\n";
        $msg .= "   ↳ {$qtyText} × ₹{$rate} = *₹{$total}*\n";
        $count++;
    }

    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $subtotal = number_format($invoice['subtotal'], 2);
    $grandTotal = number_format($invoice['grand_total'], 2);
    
    $msg .= "• एकूण रक्कम: ₹{$subtotal}\n";
    if ($invoice['discount_amount'] > 0) {
        $discount = number_format($invoice['discount_amount'], 2);
        $msg .= "• सवलत (Discount): -₹{$discount}\n";
    }
    if ($invoice['tax_amount'] > 0) {
        $tax = number_format($invoice['tax_amount'], 2);
        $msg .= "• कर (GST): +₹{$tax}\n";
    }
    $msg .= "💰 *अंतिम देय रक्कम: ₹{$grandTotal}*\n";
    
    if ($invoice['payment_mode'] === 'CREDIT' || $invoice['payment_status'] === 'PENDING') {
        $msg .= "⚠️ *उधारी नोंद: देय रक्कम लवकर जमा करावी.*\n";
    }
    
    $msg .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📱 *आपले डिजिटल बिल पाहण्यासाठी खालील लिंकवर क्लिक करा:*\n";
    $msg .= "👉 {$billUrl}\n\n";
    $msg .= "🙏 *मंगलमूर्ती प्रॉडक्ट्सकडे खरेदी केल्याबद्दल मनःपूर्वक धन्यवाद! पुन्हा अवश्य भेट द्या.* 😊";

    return $msg;
}

/**
 * JSON Response Formatter
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Authentication Helpers
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? 'Admin',
        'email' => $_SESSION['user_email'] ?? 'admin@yourstore.com',
        'role' => $_SESSION['user_role'] ?? 'admin'
    ];
}
