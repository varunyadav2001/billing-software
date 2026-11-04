<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = get_db_connection();
$settings = get_store_settings($pdo);

$message = '';
$error = '';

// Handle Credit Payment Collection (उधारी जमा)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'receive_payment') {
        $customerId = (int)$_POST['customer_id'];
        $amount = (float)$_POST['amount'];
        $notes = trim($_POST['notes'] ?? 'उधारी रक्कम जमा केली');

        if ($customerId > 0 && $amount > 0) {
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $cust = $stmt->fetch();

            if ($cust) {
                $newBal = max(0, $cust['credit_balance'] - $amount);
                $pdo->beginTransaction();
                
                $updStmt = $pdo->prepare("UPDATE customers SET credit_balance = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $updStmt->execute([$newBal, $customerId]);

                $stmtLedger = $pdo->prepare("INSERT INTO customer_ledger (customer_id, type, amount, balance_after, notes) VALUES (?, 'PAYMENT_RECEIVED', ?, ?, ?)");
                $stmtLedger->execute([$customerId, $amount, $newBal, $notes]);

                $pdo->commit();
                $message = "ग्राहक '{$cust['name']}' यांच्याकडून ₹{$amount} यशस्वीरीत्या जमा झाले!";
            }
        } else {
            $error = 'अवैध रक्कम.';
        }
    }
}

// Fetch customers
$search = trim($_GET['search'] ?? '');
$creditOnly = isset($_GET['credit_only']) && $_GET['credit_only'] == '1';

$sql = "SELECT * FROM customers WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (name LIKE ? OR phone LIKE ?)";
    $s = "%{$search}%";
    $params[] = $s; $params[] = $s;
}

if ($creditOnly) {
    $sql .= " AND credit_balance > 0";
}

$sql .= " ORDER BY credit_balance DESC, total_spent DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// Total market credit outstanding
$stmtCreditTotal = $pdo->query("SELECT COALESCE(SUM(credit_balance), 0) as total_credit FROM customers");
$totalMarketCredit = $stmtCreditTotal->fetch()['total_credit'];

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
    <div>
        <h2 style="font-size: 1.35rem; color: var(--primary-dark); font-weight: 700;">👥 ग्राहक खाते व उधारी लेजर</h2>
        <p style="font-size: 0.85rem; color: var(--gray-600);">ग्राहकांची खरेदी माहिती, उधारी शिल्लक आणि जमा नोंदी</p>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
        <div class="card" style="margin: 0; padding: 0.45rem 1rem; border-color: var(--danger); background: #fff5f5;">
            <span style="font-size: 0.75rem; color: var(--danger); font-weight: bold;">एकूण उधारी येणे (Market Outstanding):</span>
            <div style="font-size: 1.15rem; font-weight: 800; color: var(--danger);">₹<?= number_format($totalMarketCredit, 2) ?></div>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div style="padding: 0.75rem 1rem; background: var(--success-light); color: var(--success); border-radius: var(--radius-sm); margin-bottom: 1rem; font-weight: 600;">
        ✓ <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="padding: 0.75rem 1rem; background: var(--danger-light); color: var(--danger); border-radius: var(--radius-sm); margin-bottom: 1rem; font-weight: 600;">
        ✕ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Search & Filters -->
<div class="card" style="margin-bottom: 1rem;">
    <div class="card-body" style="padding: 0.75rem 1rem;">
        <form method="GET" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <div style="flex: 2; min-width: 220px;">
                <input type="text" name="search" class="form-control" placeholder="ग्राहकाचे नाव किंवा मोबाईल नंबर..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.88rem; cursor: pointer;">
                <input type="checkbox" name="credit_only" value="1" <?= $creditOnly ? 'checked' : '' ?> onchange="this.form.submit()">
                <strong>फक्त उधारी शिल्लक असलेले ग्राहक (Only Outstanding)</strong>
            </label>
            <button type="submit" class="btn btn-primary">🔍 शोधा</button>
            <?php if ($search || $creditOnly): ?>
                <a href="customers.php" class="btn btn-outline">रद्द करा</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-body" style="padding: 0; overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ग्राहक नाव</th>
                    <th>मोबाईल नंबर</th>
                    <th>पत्ता</th>
                    <th style="text-align: center;">एकूण भेटी (Visits)</th>
                    <th style="text-align: right;">एकूण खरेदी (Spent)</th>
                    <th style="text-align: right;">उधारी शिल्लक (Balance)</th>
                    <th style="text-align: right;">क्रिया</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: #94a3b8;">
                            कोणताही ग्राहक सापडला नाही.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): 
                        $hasCredit = ($c['credit_balance'] > 0);
                    ?>
                        <tr>
                            <td>
                                <strong style="color: var(--gray-800); font-size: 0.95rem;"><?= htmlspecialchars($c['name']) ?></strong>
                            </td>
                            <td>
                                <a href="tel:<?= htmlspecialchars($c['phone']) ?>" style="color: var(--primary); text-decoration: none; font-weight: 500;">
                                    <?= htmlspecialchars($c['phone']) ?>
                                </a>
                            </td>
                            <td style="color: #64748b; font-size: 0.85rem;">
                                <?= htmlspecialchars($c['address'] ?: '-') ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-info"><?= $c['visit_count'] ?> वेळा</span>
                            </td>
                            <td style="text-align: right; font-weight: 600;">
                                ₹<?= number_format($c['total_spent'], 2) ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($hasCredit): ?>
                                    <span style="font-weight: 800; color: var(--danger); font-size: 1rem;">
                                        ₹<?= number_format($c['credit_balance'], 2) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-success">✓ निरंक</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if ($hasCredit): ?>
                                    <button class="btn btn-success" style="padding: 4px 10px; font-size: 0.8rem;" onclick='openPaymentModal(<?= json_encode($c) ?>)'>
                                        💰 पैसे जमा करा
                                    </button>
                                <?php endif; ?>
                                <a href="invoices.php?search=<?= urlencode($c['phone']) ?>" class="btn btn-outline" style="padding: 4px 8px; font-size: 0.8rem;">
                                    बिले
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Receive Payment -->
<div class="modal-overlay" id="paymentModal">
    <div class="modal-card" style="max-width: 450px;">
        <div class="modal-header">
            <div class="modal-title">💰 उधारी रक्कम जमा करा</div>
            <button class="modal-close" onclick="closePaymentModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="receive_payment">
            <input type="hidden" name="customer_id" id="modalCustId">

            <div class="modal-body">
                <div style="background: var(--gray-100); padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <div>ग्राहक: <strong id="modalCustName">-</strong></div>
                    <div>सध्याची एकूण उधारी शिल्लक: <strong style="color: var(--danger); font-size: 1.15rem;">₹<span id="modalCustBalance">0.00</span></strong></div>
                </div>

                <div class="form-group">
                    <label class="form-label">जमा करायची रक्कम ₹ *</label>
                    <input type="number" step="0.01" name="amount" id="modalPayAmount" class="form-control" placeholder="उदा. 500" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label">नोंद / तपशील (Notes)</label>
                    <input type="text" name="notes" class="form-control" value="उधारी रक्कम रोख जमा केली">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closePaymentModal()">रद्द करा</button>
                <button type="submit" class="btn btn-success">✓ रक्कम जमा नोंदवा</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPaymentModal(cust) {
    document.getElementById('modalCustId').value = cust.id;
    document.getElementById('modalCustName').textContent = cust.name + ' (' + cust.phone + ')';
    document.getElementById('modalCustBalance').textContent = parseFloat(cust.credit_balance).toFixed(2);
    document.getElementById('modalPayAmount').value = cust.credit_balance;
    document.getElementById('paymentModal').classList.add('open');
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.remove('open');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
