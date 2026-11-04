<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

$pdo = get_db_connection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'search';

try {
    switch ($action) {
        case 'search':
            $phone = trim($_GET['phone'] ?? '');
            if (!$phone) {
                json_response(['success' => false, 'customer' => null]);
            }
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE phone LIKE ? LIMIT 1");
            $stmt->execute(["%{$phone}%"]);
            $cust = $stmt->fetch();
            json_response(['success' => true, 'customer' => $cust ?: null]);
            break;

        case 'list':
            $search = trim($_GET['search'] ?? '');
            $sql = "SELECT * FROM customers";
            $params = [];
            if ($search) {
                $sql .= " WHERE name LIKE ? OR phone LIKE ?";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }
            $sql .= " ORDER BY total_spent DESC LIMIT 100";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $customers = $stmt->fetchAll();
            json_response(['success' => true, 'customers' => $customers]);
            break;

        case 'credit_payment':
            require_login();
            $customerId = (int)($_POST['customer_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $notes = trim($_POST['notes'] ?? 'उधारी जमा');

            if ($customerId <= 0 || $amount <= 0) {
                json_response(['success' => false, 'message' => 'अवैध ग्राहक किंवा रक्कम!'], 400);
            }

            $stmt = $pdo->prepare("SELECT credit_balance FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $cust = $stmt->fetch();
            if (!$cust) {
                json_response(['success' => false, 'message' => 'ग्राहक सापडला नाही!'], 404);
            }

            $newBalance = max(0, $cust['credit_balance'] - $amount);
            
            $pdo->beginTransaction();
            $updStmt = $pdo->prepare("UPDATE customers SET credit_balance = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updStmt->execute([$newBalance, $customerId]);

            $stmtLedger = $pdo->prepare("INSERT INTO customer_ledger (customer_id, type, amount, balance_after, notes) VALUES (?, 'PAYMENT_RECEIVED', ?, ?, ?)");
            $stmtLedger->execute([$customerId, $amount, $newBalance, $notes]);

            $pdo->commit();
            json_response(['success' => true, 'message' => "रक्कम ₹{$amount} यशस्वीरीत्या जमा झाली!", 'new_balance' => $newBalance]);
            break;

        default:
            json_response(['success' => false, 'message' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
