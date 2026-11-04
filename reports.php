<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = get_db_connection();
$settings = get_store_settings($pdo);

$today = date('Y-m-d');
$monthStart = date('Y-m-01');

// 1. Today's stats
$stmtToday = $pdo->prepare("SELECT 
    COUNT(*) as total_bills, 
    COALESCE(SUM(grand_total), 0) as total_sales,
    COALESCE(SUM(discount_amount), 0) as total_discount
    FROM invoices WHERE DATE(created_at) = ?");
$stmtToday->execute([$today]);
$todayStats = $stmtToday->fetch();

// 2. This Month's stats
$stmtMonth = $pdo->prepare("SELECT 
    COUNT(*) as total_bills, 
    COALESCE(SUM(grand_total), 0) as total_sales
    FROM invoices WHERE DATE(created_at) >= ?");
$stmtMonth->execute([$monthStart]);
$monthStats = $stmtMonth->fetch();

// 3. Sales by Payment Mode Today
$stmtPay = $pdo->prepare("SELECT payment_mode, COUNT(*) as count, COALESCE(SUM(grand_total), 0) as total 
                          FROM invoices WHERE DATE(created_at) = ? GROUP BY payment_mode");
$stmtPay->execute([$today]);
$payModes = $stmtPay->fetchAll();

// 4. Top Selling Products (Overall)
$stmtTop = $pdo->query("SELECT 
    product_name, 
    unit, 
    SUM(qty) as total_qty, 
    SUM(total_amount) as total_revenue
    FROM invoice_items 
    GROUP BY product_name 
    ORDER BY total_qty DESC 
    LIMIT 7");
$topProducts = $stmtTop->fetchAll();

// 5. Low stock alerts
$stmtLow = $pdo->query("SELECT p.*, c.name as category_name 
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        WHERE p.stock_qty <= p.min_stock_alert AND p.is_active = 1 
                        ORDER BY p.stock_qty ASC");
$lowStockProducts = $stmtLow->fetchAll();

// 6. Total Market Credit
$stmtCredit = $pdo->query("SELECT COALESCE(SUM(credit_balance), 0) as total_credit FROM customers");
$totalMarketCredit = $stmtCredit->fetch()['total_credit'];

include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 1.5rem;">
    <h2 style="font-size: 1.4rem; color: var(--primary-dark); font-weight: 700;">📊 व्यवसाय डॅशबोर्ड व विक्री रिपोर्ट</h2>
    <p style="font-size: 0.85rem; color: var(--gray-600);">आजचा व्यवसाय, नफा, सर्वाधिक विकला गेलेला फराळ आणि कमी साठा सूचना</p>
</div>

<!-- KPI Metric Cards Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <div class="card" style="margin: 0; border-top: 4px solid var(--primary); padding: 1.25rem;">
        <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--gray-600); font-weight: 700;">आजची एकूण विक्री (Today)</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--primary); margin: 0.35rem 0;">
            ₹<?= number_format($todayStats['total_sales'], 2) ?>
        </div>
        <div style="font-size: 0.82rem; color: var(--gray-600);">
            एकूण बिले: <strong><?= $todayStats['total_bills'] ?></strong>
        </div>
    </div>

    <div class="card" style="margin: 0; border-top: 4px solid var(--gold-dark); padding: 1.25rem;">
        <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--gray-600); font-weight: 700;">या महिन्याची विक्री (Monthly)</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--gold-dark); margin: 0.35rem 0;">
            ₹<?= number_format($monthStats['total_sales'], 2) ?>
        </div>
        <div style="font-size: 0.82rem; color: var(--gray-600);">
            या महिन्यातील एकूण बिले: <strong><?= $monthStats['total_bills'] ?></strong>
        </div>
    </div>

    <div class="card" style="margin: 0; border-top: 4px solid var(--danger); padding: 1.25rem;">
        <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--gray-600); font-weight: 700;">एकूण बाकी उधारी (Credit Due)</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--danger); margin: 0.35rem 0;">
            ₹<?= number_format($totalMarketCredit, 2) ?>
        </div>
        <div style="font-size: 0.82rem; color: var(--gray-600);">
            <a href="customers.php?credit_only=1" style="color: var(--danger); text-decoration: none;">ग्राहकांची यादी पहा →</a>
        </div>
    </div>

    <div class="card" style="margin: 0; border-top: 4px solid var(--warning); padding: 1.25rem;">
        <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--gray-600); font-weight: 700;">कमी साठा सूचना (Low Stock)</div>
        <div style="font-size: 1.8rem; font-weight: 800; color: var(--warning); margin: 0.35rem 0;">
            <?= count($lowStockProducts) ?> पदार्थ
        </div>
        <div style="font-size: 0.82rem; color: var(--gray-600);">
            लवकरच संपणारे पदार्थ
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
    <!-- Today's Payment Modes -->
    <div class="card" style="margin: 0;">
        <div class="card-header">
            <h3 class="card-title">💵 आजची विक्री - पेमेंट मोडनुसार</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>पेमेंट मोड</th>
                        <th style="text-align: center;">बिले संख्या</th>
                        <th style="text-align: right;">एकूण रक्कम</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payModes)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #94a3b8; padding: 1.5rem;">आज अद्याप विक्री झालेली नाही.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payModes as $pm): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($pm['payment_mode']) ?></strong></td>
                                <td style="text-align: center;"><?= $pm['count'] ?></td>
                                <td style="text-align: right; font-weight: bold; color: var(--primary);">₹<?= number_format($pm['total'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Selling Items -->
    <div class="card" style="margin: 0;">
        <div class="card-header">
            <h3 class="card-title">🏆 सर्वाधिक विकले गेलेले पदार्थ (Top Sellers)</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>पदार्थ</th>
                        <th style="text-align: center;">विक्री वजन / नग</th>
                        <th style="text-align: right;">एकूण विक्री ₹</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topProducts)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #94a3b8; padding: 1.5rem;">अद्याप कोणतीही विक्री झालेली नाही.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topProducts as $top): 
                            $qtyTxt = ($top['unit'] === 'kg') ? number_format($top['total_qty'], 2) . ' kg' : number_format($top['total_qty'], 0) . ' ' . $top['unit'];
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($top['product_name']) ?></strong></td>
                                <td style="text-align: center;"><span class="badge badge-success"><?= $qtyTxt ?></span></td>
                                <td style="text-align: right; font-weight: bold; color: var(--primary);">₹<?= number_format($top['total_revenue'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Low Stock Warning Table -->
<?php if (!empty($lowStockProducts)): ?>
    <div class="card" style="border-left: 5px solid var(--danger);">
        <div class="card-header" style="background: var(--danger-light);">
            <h3 class="card-title" style="color: var(--danger);">⚠️ कमी स्टॉक अलर्ट - त्वरित उत्पादन / खरेदी करावी लागेल</h3>
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>कॅटेगरी</th>
                        <th>पदार्थाचे नाव</th>
                        <th>किमान मर्यादा</th>
                        <th style="text-align: center;">सध्याचा शिल्लक साठा</th>
                        <th style="text-align: right;">क्रिया</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStockProducts as $low): ?>
                        <tr>
                            <td><span class="badge badge-primary"><?= htmlspecialchars($low['category_name']) ?></span></td>
                            <td><strong><?= htmlspecialchars($low['name']) ?></strong></td>
                            <td><?= $low['min_stock_alert'] ?> <?= $low['unit'] ?></td>
                            <td style="text-align: center;">
                                <span class="badge badge-danger" style="font-size: 0.9rem;">
                                    <?= ($low['unit'] === 'kg') ? number_format($low['stock_qty'], 2) . ' kg' : number_format($low['stock_qty'], 0) . ' ' . $low['unit'] ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="products.php?search=<?= urlencode($low['name']) ?>" class="btn btn-outline" style="padding: 3px 8px; font-size: 0.8rem;">
                                    स्टॉक वाढवा →
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
