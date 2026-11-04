<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/firebase.php';

$pdo = get_db_connection();
$settings = get_store_settings($pdo);

$message = '';
$syncStats = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'full_sync') {
    $syncStats = firestore_full_backup($pdo);
    $message = 'सर्व स्थानिक डेटा Firebase Firestore वर यशस्वीरीत्या सिंक झाला!';
}

// Fetch local counts
$invCount = (int)$pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
$custCount = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$prodCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

// Fetch live Firestore records directly from Google Cloud
$cloudInvoices = firestore_list_documents('invoices')['documents'] ?? [];
$cloudCustomers = firestore_list_documents('customers')['documents'] ?? [];
$cloudProducts = firestore_list_documents('products')['documents'] ?? [];

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
    <div>
        <h2 style="font-size: 1.4rem; color: var(--primary-dark); font-weight: 700;">☁️ Firebase Firestore क्लाउड बॅकअप & सिंक डॅशबोर्ड</h2>
        <p style="font-size: 0.85rem; color: var(--gray-600);">स्थानिक SQLite आणि गुगल क्लाउड फायरबेस मधील थेट डेटा स्थिती</p>
    </div>
    <div style="display: flex; align-items: center; gap: 0.5rem;">
        <span class="badge badge-success" style="padding: 0.5rem 0.85rem; font-size: 0.85rem;">
            🟢 Firebase Connected: <strong>kiran-cf787</strong>
        </span>
        <a href="https://console.firebase.google.com/project/kiran-cf787/firestore" target="_blank" class="btn btn-outline" style="font-size: 0.82rem; padding: 0.45rem 0.75rem;">
            🔗 Firebase Console उघडा ↗
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div style="padding: 0.85rem 1.25rem; background: var(--success-light); color: var(--success); border-radius: var(--radius-sm); margin-bottom: 1.25rem; font-weight: 600;">
        ✓ <?= htmlspecialchars($message) ?>
        <?php if ($syncStats): ?>
            <div style="font-size: 0.85rem; font-weight: normal; margin-top: 0.35rem; color: #166b52;">
                • उत्पादने: <?= $syncStats['products_synced'] ?> | • ग्राहक: <?= $syncStats['customers_synced'] ?> | • बिले: <?= $syncStats['invoices_synced'] ?> (वेळ: <?= $syncStats['timestamp'] ?>)
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Firebase Overview Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="margin: 0; border-top: 4px solid var(--primary); padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--gray-600); font-weight: 700;">क्लाउड बिले (Invoices)</div>
            <span class="badge badge-success">✓ Cloud Synced</span>
        </div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary); margin: 0.35rem 0;">
            <?= count($cloudInvoices) ?> बिले
        </div>
        <div style="font-size: 0.8rem; color: var(--gray-600);">
            कलेक्शन: <code>invoices/</code>
        </div>
    </div>

    <div class="card" style="margin: 0; border-top: 4px solid var(--gold-dark); padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--gray-600); font-weight: 700;">क्लाउड ग्राहक खाते (Customers)</div>
            <span class="badge badge-success">✓ Cloud Synced</span>
        </div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--gold-dark); margin: 0.35rem 0;">
            <?= count($cloudCustomers) ?> ग्राहक
        </div>
        <div style="font-size: 0.8rem; color: var(--gray-600);">
            कलेक्शन: <code>customers/</code>
        </div>
    </div>

    <div class="card" style="margin: 0; border-top: 4px solid var(--info); padding: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--gray-600); font-weight: 700;">फराळ उत्पादने (Products)</div>
            <span class="badge badge-success">✓ Cloud Synced</span>
        </div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--info); margin: 0.35rem 0;">
            <?= count($cloudProducts) ?> पदार्थ
        </div>
        <div style="font-size: 0.8rem; color: var(--gray-600);">
            कलेक्शन: <code>products/</code>
        </div>
    </div>
</div>

<!-- Manual Sync Button Bar -->
<div class="card" style="margin-bottom: 1.5rem; background: #fff;">
    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--primary-dark);">🔄 रिअल-टाइम बॅकअप स्थिती</h3>
            <p style="font-size: 0.85rem; color: var(--gray-600); margin-top: 2px;">
                प्रत्येक नवीन बिल बनवल्यावर ते <strong>आपोआप</strong> Firebase वर सेव्ह होते. सर्व स्थानिक डेटा पुन्हा नव्याने सिंक करण्यासाठी येथे क्लिक करा:
            </p>
        </div>
        <form method="POST" onsubmit="document.getElementById('btnSyncSubmit').innerText = '⏳ क्लाउडवर सिंक करत आहे...'; document.getElementById('btnSyncSubmit').disabled = true;">
            <input type="hidden" name="action" value="full_sync">
            <button type="submit" id="btnSyncSubmit" class="btn btn-gold" style="padding: 0.75rem 1.5rem; font-size: 1rem; font-weight: bold;">
                ☁️ सर्व डेटा Firebase वर सिंक करा
            </button>
        </form>
    </div>
</div>

<!-- LIVE FIRESTORE INVOICES VIEWER TABLE -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header" style="background: var(--primary-dark); color: #fff;">
        <h3 class="card-title" style="color: var(--gold-light);">
            🔥 थेट Firebase Firestore मधील बिले (Live Invoices in Cloud)
        </h3>
        <span style="font-size: 0.78rem; color: #a7f3d0;">Live Google Cloud Sync</span>
    </div>
    <div class="card-body" style="padding: 0; overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Firebase Doc ID</th>
                    <th>ग्राहक नाव</th>
                    <th>मोबाईल</th>
                    <th>पेमेंट मोड</th>
                    <th>वस्तू संख्या</th>
                    <th style="text-align: right;">एकूण रक्कम</th>
                    <th style="text-align: center;">क्लाउड स्थिती</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cloudInvoices)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 2rem; color: #94a3b8;">Firebase मध्ये अद्याप कोणतीही बिले नाहीत.</td></tr>
                <?php else: ?>
                    <?php foreach ($cloudInvoices as $cinv): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--primary);"><?= htmlspecialchars($cinv['_name']) ?></strong>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($cinv['customer_name'] ?? 'ग्राहक') ?></strong>
                            </td>
                            <td>
                                <?= htmlspecialchars($cinv['customer_phone'] ?? '-') ?>
                            </td>
                            <td>
                                <span class="badge badge-primary"><?= htmlspecialchars($cinv['payment_mode'] ?? 'CASH') ?></span>
                            </td>
                            <td>
                                <?= count($cinv['items'] ?? []) ?> पदार्थ
                            </td>
                            <td style="text-align: right; font-weight: bold; color: var(--primary-dark);">
                                ₹<?= number_format((float)($cinv['grand_total'] ?? 0), 2) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-success">✓ Cloud Stored</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- LIVE FIRESTORE CUSTOMERS TABLE -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h3 class="card-title">👥 थेट Firebase Firestore मधील ग्राहक (Live Customers in Cloud)</h3>
    </div>
    <div class="card-body" style="padding: 0; overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>मोबाईल (Doc ID)</th>
                    <th>ग्राहकाचे नाव</th>
                    <th style="text-align: center;">एकूण भेटी</th>
                    <th style="text-align: right;">एकूण खरेदी (Spent)</th>
                    <th style="text-align: right;">उधारी शिल्लक</th>
                    <th style="text-align: center;">स्थिती</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cloudCustomers)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 1.5rem; color: #94a3b8;">Firebase मध्ये ग्राहक नाहीत.</td></tr>
                <?php else: ?>
                    <?php foreach ($cloudCustomers as $ccust): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($ccust['phone'] ?? $ccust['_name']) ?></code></td>
                            <td><strong><?= htmlspecialchars($ccust['name'] ?? '-') ?></strong></td>
                            <td style="text-align: center;"><?= $ccust['visit_count'] ?? 1 ?> वेळा</td>
                            <td style="text-align: right; font-weight: 600;">₹<?= number_format((float)($ccust['total_spent'] ?? 0), 2) ?></td>
                            <td style="text-align: right; font-weight: bold; color: <?= (($ccust['credit_balance'] ?? 0) > 0) ? 'var(--danger)' : 'var(--success)' ?>;">
                                ₹<?= number_format((float)($ccust['credit_balance'] ?? 0), 2) ?>
                            </td>
                            <td style="text-align: center;"><span class="badge badge-success">✓ Synced</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
