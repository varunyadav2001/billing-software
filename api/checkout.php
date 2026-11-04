<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/config/firebase.php';

$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Only POST method is allowed'], 405);
}

// Receive JSON payload
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || empty($data['items'])) {
    json_response(['success' => false, 'message' => 'कार्ट रिकामे आहे. कृपया उत्पादने जोडा.'], 400);
}

$items = $data['items'];
$customerName = trim($data['customer_name'] ?? '');
$customerPhone = trim($data['customer_phone'] ?? '');
$paymentMode = strtoupper($data['payment_mode'] ?? 'CASH');
$invoiceType = strtoupper($data['invoice_type'] ?? 'RETAIL');
$discountType = $data['discount_type'] ?? 'flat';
$discountVal = (float)($data['discount_value'] ?? 0);
$taxAmount = (float)($data['tax_amount'] ?? 0);
$notes = trim($data['notes'] ?? '');

$validModes = ['CASH', 'UPI', 'CARD', 'CREDIT'];
if (!in_array($paymentMode, $validModes)) {
    $paymentMode = 'CASH';
}

try {
    // 1. Calculate items total and verify products
    $subtotal = 0;
    $processedItems = [];

    foreach ($items as $item) {
        $prodId = (int)$item['id'];
        $qty = (float)$item['qty'];
        if ($qty <= 0) continue;

        $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
        $stmt->execute([$prodId]);
        $prod = $stmt->fetch();

        if (!$prod) {
            throw new Exception("उत्पादन सापडले नाही (ID: {$prodId})");
        }

        $rate = (isset($item['custom_rate']) && is_numeric($item['custom_rate']) && (float)$item['custom_rate'] >= 0)
            ? (float)$item['custom_rate']
            : (float)$prod['selling_price'];
        $itemTotal = round($rate * $qty, 2);
        $subtotal += $itemTotal;

        $processedItems[] = [
            'product_id' => $prod['id'],
            'product_name' => $prod['name'],
            'category_name' => $prod['category_name'] ?: 'इतर',
            'unit' => $prod['unit'],
            'weight_grams' => ($prod['unit'] === 'kg') ? ($qty * 1000) : 0,
            'unit_rate' => $rate,
            'qty' => $qty,
            'discount' => 0,
            'tax_amount' => 0,
            'total_amount' => $itemTotal,
            'current_stock' => (float)$prod['stock_qty']
        ];
    }

    if (empty($processedItems)) {
        throw new Exception("वैध उत्पादने निवडा.");
    }

    // 2. Compute Discounts & Grand Total
    $discountAmount = 0;
    if ($discountType === 'percent') {
        $discountAmount = round(($subtotal * $discountVal) / 100, 2);
    } else {
        $discountAmount = min($subtotal, $discountVal);
    }

    $grandTotal = round(max(0, ($subtotal - $discountAmount + $taxAmount)), 2);

    // 3. Concurrency-Safe Invoice Number
    $invoiceNo = generate_invoice_no($pdo, $invoiceType);

    // 4. Customer Handling (Save or lookup)
    $customerId = null;
    if (!empty($customerPhone)) {
        $stmtCust = $pdo->prepare("SELECT id, credit_balance, total_spent, visit_count FROM customers WHERE phone = ?");
        $stmtCust->execute([$customerPhone]);
        $custRow = $stmtCust->fetch();

        if ($custRow) {
            $customerId = $custRow['id'];
            $newSpent = $custRow['total_spent'] + $grandTotal;
            $newVisits = $custRow['visit_count'] + 1;
            $newCredit = $custRow['credit_balance'] + (($paymentMode === 'CREDIT') ? $grandTotal : 0);

            $stmtUpd = $pdo->prepare("UPDATE customers SET name = COALESCE(NULLIF(?, ''), name), total_spent = ?, visit_count = ?, credit_balance = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$customerName, $newSpent, $newVisits, $newCredit, $customerId]);
        } else {
            $newCredit = ($paymentMode === 'CREDIT') ? $grandTotal : 0;
            $stmtNewCust = $pdo->prepare("INSERT INTO customers (name, phone, total_spent, visit_count, credit_balance) VALUES (?, ?, ?, 1, ?)");
            $stmtNewCust->execute([$customerName ?: 'ग्राहक', $customerPhone, $grandTotal, $newCredit]);
            $customerId = $pdo->lastInsertId();
        }
    }

    // 5. Payment status
    $paymentStatus = ($paymentMode === 'CREDIT') ? 'PENDING' : 'PAID';
    $paidAmount = ($paymentMode === 'CREDIT') ? 0 : $grandTotal;
    $changeDue = 0;

    // 6. Begin Transaction for Invoice & Inventory Ledger
    $pdo->beginTransaction();

    $createdAt = date('Y-m-d H:i:s');
    $stmtInv = $pdo->prepare("INSERT INTO invoices (
        invoice_no, invoice_type, customer_id, customer_name, customer_phone,
        subtotal, discount_type, discount_value, discount_amount, tax_amount,
        grand_total, payment_mode, payment_status, paid_amount, change_due, notes, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmtInv->execute([
        $invoiceNo,
        $invoiceType,
        $customerId,
        $customerName ?: 'ग्राहक',
        $customerPhone,
        $subtotal,
        $discountType,
        $discountVal,
        $discountAmount,
        $taxAmount,
        $grandTotal,
        $paymentMode,
        $paymentStatus,
        $paidAmount,
        $changeDue,
        $notes,
        $createdAt
    ]);

    $invoiceId = $pdo->lastInsertId();

    // 7. Insert Invoice Items & Decrement Inventory
    $stmtItem = $pdo->prepare("INSERT INTO invoice_items (
        invoice_id, product_id, product_name, category_name, unit, weight_grams,
        unit_rate, qty, discount, tax_amount, total_amount
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmtStock = $pdo->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
    $stmtMvmt = $pdo->prepare("INSERT INTO inventory_movements (
        product_id, type, quantity_change, previous_stock, new_stock, reference_id, notes
    ) VALUES (?, 'SALE', ?, ?, ?, ?, ?)");

    foreach ($processedItems as $pItem) {
        $stmtItem->execute([
            $invoiceId,
            $pItem['product_id'],
            $pItem['product_name'],
            $pItem['category_name'],
            $pItem['unit'],
            $pItem['weight_grams'],
            $pItem['unit_rate'],
            $pItem['qty'],
            $pItem['discount'],
            $pItem['tax_amount'],
            $pItem['total_amount']
        ]);

        // Stock Update
        $stmtStock->execute([$pItem['qty'], $pItem['product_id']]);
        $newStock = $pItem['current_stock'] - $pItem['qty'];
        $stmtMvmt->execute([
            $pItem['product_id'],
            -$pItem['qty'],
            $pItem['current_stock'],
            $newStock,
            $invoiceNo,
            "विक्री बिलातून कमी केले ({$invoiceNo})"
        ]);
    }

    // 8. If Credit, record Customer Ledger entry
    if ($paymentMode === 'CREDIT' && $customerId) {
        $stmtLedger = $pdo->prepare("INSERT INTO customer_ledger (
            customer_id, type, amount, balance_after, invoice_id, notes
        ) VALUES (?, 'CREDIT_SALE', ?, ?, ?, ?)");
        $balanceAfter = $custRow ? ($custRow['credit_balance'] + $grandTotal) : $grandTotal;
        $stmtLedger->execute([
            $customerId,
            $grandTotal,
            $balanceAfter,
            $invoiceId,
            "उधारी खरेदी बिल क्र. {$invoiceNo}"
        ]);
    }

    $pdo->commit();

    // 8.1 Real-time Cloud Sync to Firebase Firestore
    $cloudSyncResult = null;
    try {
        $invRow = [
            'invoice_no' => $invoiceNo,
            'invoice_type' => $invoiceType,
            'created_at' => date('Y-m-d H:i:s'),
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'payment_mode' => $paymentMode,
            'payment_status' => $paymentStatus,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'grand_total' => $grandTotal
        ];
        $cloudSyncResult = firestore_sync_invoice($invRow, $processedItems, get_store_settings($pdo));
        
        if ($customerId) {
            $stmtC = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $stmtC->execute([$customerId]);
            $cData = $stmtC->fetch();
            if ($cData) firestore_sync_customer($cData);
        }
    } catch (Exception $fbEx) {
        // Log silently without breaking local offline checkout
        error_log('Firebase cloud sync note: ' . $fbEx->getMessage());
    }

    // 9. Generate WhatsApp Link and Payload
    $settings = get_store_settings($pdo);
    $baseUrl = get_public_base_url();
    $digitalBillUrl = "{$baseUrl}/bill.php?inv={$invoiceNo}";
    
    $invRow = [
        'invoice_no' => $invoiceNo,
        'created_at' => date('Y-m-d H:i:s'),
        'customer_name' => $customerName,
        'customer_phone' => $customerPhone,
        'payment_mode' => $paymentMode,
        'payment_status' => $paymentStatus,
        'subtotal' => $subtotal,
        'discount_amount' => $discountAmount,
        'tax_amount' => $taxAmount,
        'grand_total' => $grandTotal
    ];

    $whatsappText = format_whatsapp_bill($invRow, $processedItems, $settings, $digitalBillUrl);
    
    // Construct WhatsApp click link
    $cleanPhone = preg_replace('/[^0-9]/', '', $customerPhone);
    if (strlen($cleanPhone) === 10) {
        $cleanPhone = '91' . $cleanPhone;
    }
    $whatsappApiUrl = !empty($cleanPhone) 
        ? "https://api.whatsapp.com/send?phone={$cleanPhone}&text=" . rawurlencode($whatsappText)
        : "https://api.whatsapp.com/send?text=" . rawurlencode($whatsappText);

    json_response([
        'success' => true,
        'message' => 'बिल यशस्वीरीत्या तयार झाले!',
        'invoice' => [
            'id' => $invoiceId,
            'invoice_no' => $invoiceNo,
            'grand_total' => $grandTotal,
            'payment_mode' => $paymentMode,
            'bill_url' => $digitalBillUrl,
            'whatsapp_url' => $whatsappApiUrl,
            'whatsapp_text' => $whatsappText
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['success' => false, 'message' => $e->getMessage()], 400);
}
