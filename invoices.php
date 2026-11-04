<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = get_db_connection();
$settings = get_store_settings($pdo);

$search = trim($_GET['search'] ?? '');
$paymentFilter = trim($_GET['payment_mode'] ?? '');
$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');

$sql = "SELECT * FROM invoices WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (invoice_no LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ?)";
    $s = "%{$search}%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}

if ($paymentFilter) {
    $sql .= " AND payment_mode = ?";
    $params[] = $paymentFilter;
}

if ($fromDate) {
    $sql .= " AND DATE(created_at) >= ?";
    $params[] = $fromDate;
}

if ($toDate) {
    $sql .= " AND DATE(created_at) <= ?";
    $params[] = $toDate;
}

$sql .= " ORDER BY id DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$baseUrl = get_public_base_url();

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
    <div>
        <h2 style="font-size: 1.35rem; color: var(--primary-dark); font-weight: 700;">🧾 बिलांचा इतिहास (Invoices History)</h2>
        <p style="font-size: 0.85rem; color: var(--gray-600);">तयार झालेली सर्व किरकोळ व जीएसटी बिले, री-प्रिंट व व्हॉट्सॲप शेअर</p>
    </div>
    <a href="billing.php" class="btn btn-gold">
        ⚡ नवीन बिल बनवा (POS)
    </a>
</div>

<!-- Filter Bar -->
<div class="card" style="margin-bottom: 1rem;">
    <div class="card-body" style="padding: 0.75rem 1rem;">
        <form method="GET" style="display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center;">
            <div style="flex: 2; min-width: 180px;">
                <input type="text" name="search" class="form-control" placeholder="बिल क्र., ग्राहक नाव किंवा फोन..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div style="flex: 1; min-width: 140px;">
                <select name="payment_mode" class="form-control">
                    <option value="">-- सर्व पेमेंट्स --</option>
                    <option value="CASH" <?= ($paymentFilter === 'CASH') ? 'selected' : '' ?>>💵 CASH</option>
                    <option value="UPI" <?= ($paymentFilter === 'UPI') ? 'selected' : '' ?>>📱 UPI</option>
                    <option value="CARD" <?= ($paymentFilter === 'CARD') ? 'selected' : '' ?>>💳 CARD</option>
                    <option value="CREDIT" <?= ($paymentFilter === 'CREDIT') ? 'selected' : '' ?>>📝 CREDIT (उधारी)</option>
                </select>
            </div>
            <div>
                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($fromDate) ?>" title="या तारखेपासून">
            </div>
            <div>
                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($toDate) ?>" title="या तारखेपर्यंत">
            </div>
            <button type="submit" class="btn btn-primary">🔍 शोधा</button>
            <?php if ($search || $paymentFilter || $fromDate || $toDate): ?>
                <a href="invoices.php" class="btn btn-outline">रद्द करा</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body" style="padding: 0; overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>बिल क्र.</th>
                    <th>दिनांक व वेळ</th>
                    <th>ग्राहक</th>
                    <th>पेमेंट मोड</th>
                    <th style="text-align: right;">उप-एकूण</th>
                    <th style="text-align: right;">सवलत</th>
                    <th style="text-align: right;">अंतिम रक्कम</th>
                    <th style="text-align: center;">स्थिती</th>
                    <th style="text-align: right;">क्रिया</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 2rem; color: #94a3b8;">
                            कोणतेही बिल सापडले नाही.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): 
                        // Fetch items for whatsapp link
                        $stmtItems = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
                        $stmtItems->execute([$inv['id']]);
                        $invItems = $stmtItems->fetchAll();

                        $billUrl = "{$baseUrl}/bill.php?inv={$inv['invoice_no']}";
                        $waText = format_whatsapp_bill($inv, $invItems, $settings, $billUrl);
                        $cleanPhone = preg_replace('/[^0-9]/', '', $inv['customer_phone']);
                        if (strlen($cleanPhone) === 10) $cleanPhone = '91' . $cleanPhone;
                        $waUrl = "https://api.whatsapp.com/send?phone={$cleanPhone}&text=" . rawurlencode($waText);
                    ?>
                        <tr>
                            <td>
                                <strong><a href="bill.php?inv=<?= urlencode($inv['invoice_no']) ?>" style="color: var(--primary); text-decoration: none;"><?= htmlspecialchars($inv['invoice_no']) ?></a></strong>
                            </td>
                            <td style="font-size: 0.8rem; color: #64748b;">
                                <?= date('d-m-Y h:i A', strtotime($inv['created_at'])) ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($inv['customer_name'] ?: 'ग्राहक') ?></strong>
                                <?php if ($inv['customer_phone']): ?>
                                    <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($inv['customer_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= ($inv['payment_mode'] === 'CREDIT') ? 'badge-danger' : 'badge-primary' ?>">
                                    <?= htmlspecialchars($inv['payment_mode']) ?>
                                </span>
                            </td>
                            <td style="text-align: right; color: #64748b;">
                                ₹<?= number_format($inv['subtotal'], 2) ?>
                            </td>
                            <td style="text-align: right; color: #16a34a;">
                                <?= ($inv['discount_amount'] > 0) ? '-₹' . number_format($inv['discount_amount'], 2) : '-' ?>
                            </td>
                            <td style="text-align: right; font-weight: bold; font-size: 0.95rem; color: var(--primary-dark);">
                                ₹<?= number_format($inv['grand_total'], 2) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge <?= ($inv['payment_status'] === 'PAID') ? 'badge-success' : 'badge-danger' ?>">
                                    <?= ($inv['payment_status'] === 'PAID') ? 'भरले' : 'बाकी' ?>
                                </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="<?= $waUrl ?>" target="_blank" class="btn btn-success" style="padding: 3px 7px; font-size: 0.75rem; background: #25D366; border-color: #25D366;" title="WhatsApp वर पाठवा">
                                    📲 WhatsApp
                                </a>
                                <a href="bill.php?inv=<?= urlencode($inv['invoice_no']) ?>" target="_blank" class="btn btn-gold" style="padding: 3px 7px; font-size: 0.75rem;" title="डिजिटल बिल">
                                    🌐 बिल
                                </a>
                                <a href="invoice-print.php?inv=<?= urlencode($inv['invoice_no']) ?>&format=thermal" target="_blank" class="btn btn-primary" style="padding: 3px 7px; font-size: 0.75rem;" title="थर्मल प्रिंट">
                                    🖨️
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
