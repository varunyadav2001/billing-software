<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Ensure Indian Standard Time (IST) is always used
date_default_timezone_set('Asia/Kolkata');

$pdo = get_db_connection();
$settings = get_store_settings($pdo);

$invoiceNo = trim($_GET['inv'] ?? '');
$format = trim($_GET['format'] ?? 'thermal'); // 'thermal' or 'a4'
$autoPrint = isset($_GET['auto_print']) && $_GET['auto_print'] == '1';

if (!$invoiceNo) {
    die("अवैध बिल क्रमांक.");
}

$stmtInv = $pdo->prepare("SELECT * FROM invoices WHERE invoice_no = ?");
$stmtInv->execute([$invoiceNo]);
$invoice = $stmtInv->fetch();

if (!$invoice) {
    die("हे बिल सापडले नाही.");
}

$stmtItems = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$stmtItems->execute([$invoice['id']]);
$items = $stmtItems->fetchAll();

$totalWeightKg = 0;
foreach ($items as $it) {
    if ($it['unit'] === 'kg') {
        $totalWeightKg += $it['qty'];
    }
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <title>Print - <?= htmlspecialchars($invoice['invoice_no']) ?> - <?= htmlspecialchars($settings['store_name_mr']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Mukta:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/print.css">
    <style>
        .print-control-bar {
            background: #1e293b;
            color: #fff;
            padding: 10px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .btn-print-action {
            background: #d4af37;
            color: #000;
            border: none;
            padding: 6px 14px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
        }
        .btn-toggle-format {
            background: #334155;
            color: #fff;
            border: 1px solid #64748b;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
        }
        @media (max-width: 600px) {
            .print-control-bar {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            .print-control-bar > div:last-child {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="print-control-bar no-print">
    <div>
        <strong>बिल: <?= htmlspecialchars($invoice['invoice_no']) ?> (<?= htmlspecialchars($settings['store_name_mr']) ?>)</strong>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="?inv=<?= urlencode($invoiceNo) ?>&format=thermal" class="btn-toggle-format <?= ($format === 'thermal') ? 'active' : '' ?>">80mm थर्मल पावती</a>
        <a href="?inv=<?= urlencode($invoiceNo) ?>&format=a4" class="btn-toggle-format <?= ($format === 'a4') ? 'active' : '' ?>">A4 टॅक्स इनव्हॉइस</a>
        <button class="btn-print-action" onclick="window.print()">🖨️ प्रिंट करा (Print)</button>
        <button class="btn-toggle-format" onclick="window.close()">✕ बंद करा</button>
    </div>
</div>

<div class="print-preview-wrapper">

<?php if ($format === 'thermal'): ?>
    <!-- 80mm Thermal Receipt Layout -->
    <div class="receipt-thermal screen-view">
        <div class="store-header">
            <div class="store-title"><?= htmlspecialchars($settings['store_name_mr'] ?: $settings['store_name']) ?></div>
            <div class="store-sub-en"><?= htmlspecialchars($settings['store_name']) ?></div>
            <div class="store-tagline"><?= htmlspecialchars($settings['tagline_mr'] ?: $settings['tagline']) ?></div>
            <div class="store-location">📍 तासगाव</div>
            <div class="store-owner-info">नाव (Name): <strong>किरण माळी (Kiran Mali)</strong></div>
            <div class="store-owner-info">मो.: <strong><?= htmlspecialchars($settings['phone'] ?: '9689588375') ?></strong></div>
        </div>

        <div class="bill-meta">
            <div class="meta-row">
                <span>बिल क्र.: <strong><?= htmlspecialchars($invoice['invoice_no']) ?></strong></span>
                <span>दिनांक: <strong><?= date('d/m/Y', strtotime($invoice['created_at'])) ?></strong></span>
            </div>
            <div class="meta-row">
                <span>वेळ: <strong><?= date('h:i A', strtotime($invoice['created_at'])) ?></strong></span>
                <span>मोड: <span class="mode-badge"><?= htmlspecialchars($invoice['payment_mode']) ?></span></span>
            </div>
            <?php if (!empty($invoice['customer_name']) && $invoice['customer_name'] !== 'ग्राहक'): ?>
                <div class="meta-row" style="margin-top: 3px;">
                    <span>ग्राहक: <strong><?= htmlspecialchars($invoice['customer_name']) ?></strong></span>
                    <?php if (!empty($invoice['customer_phone'])): ?>
                        <span>मो.: <strong><?= htmlspecialchars($invoice['customer_phone']) ?></strong></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th class="col-item">वस्तू</th>
                    <th class="col-qty">वजन/नग</th>
                    <th class="col-amount">रक्कम</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $qtyDisplay = '';
                    if ($item['unit'] === 'kg') {
                        $qtyDisplay = ($item['qty'] < 1) ? round($item['qty'] * 1000) . 'g' : $item['qty'] . ' kg';
                    } else {
                        $qtyDisplay = $item['qty'] . ' ' . $item['unit'];
                    }
                ?>
                    <tr>
                        <td class="col-item">
                            <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                            <div class="item-rate">@Rs.<?= number_format($item['unit_rate'], 0) ?></div>
                        </td>
                        <td class="col-qty"><?= $qtyDisplay ?></td>
                        <td class="col-amount">Rs.<?= number_format($item['total_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Count & Total Weight Breakdown -->
        <div class="summary-strip">
            <span>एकूण वस्तू: <?= count($items) ?></span>
            <?php if ($totalWeightKg > 0): ?>
                <span>एकूण वजन: <?= number_format($totalWeightKg, 2) ?> kg</span>
            <?php endif; ?>
        </div>

        <div class="total-section">
            <div class="total-row">
                <span>उप-एकूण (Subtotal):</span>
                <span>Rs.<?= number_format($invoice['subtotal'], 2) ?></span>
            </div>
            <?php if ($invoice['discount_amount'] > 0): ?>
                <div class="total-row">
                    <span>सवलत (Discount):</span>
                    <span>-Rs.<?= number_format($invoice['discount_amount'], 2) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($invoice['tax_amount'] > 0): ?>
                <div class="total-row">
                    <span>कर (Tax):</span>
                    <span>+Rs.<?= number_format($invoice['tax_amount'], 2) ?></span>
                </div>
            <?php endif; ?>
            
            <div class="grand-total-box">
                <div>
                    <div class="grand-total-label">एकूण देय रक्कम:</div>
                    <div class="grand-total-sub">TOTAL AMOUNT PAYABLE</div>
                </div>
                <div class="grand-total-amount">Rs.<?= number_format($invoice['grand_total'], 2) ?></div>
            </div>
        </div>

        <div class="footer-note">
            <?php if ($invoice['payment_mode'] === 'CREDIT'): ?>
                <div class="footer-credit-warning">*** उधारी नोंद (CREDIT) ***</div>
            <?php endif; ?>
            <div class="footer-thankyou">धन्यवाद! पुन्हा अवश्य भेट द्या!</div>
            <div class="footer-brand">मंगलमूर्ती प्रॉडक्ट्स &bull; सण आनंदाचा!</div>
        </div>
    </div>

<?php else: ?>
    <!-- A4 Size Professional Invoice Layout -->
    <div class="invoice-a4 screen-view">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0f4c3a; padding-bottom: 15px;">
            <div>
                <h1 style="color: #0f4c3a; margin: 0; font-size: 26px; font-weight: 800;"><?= htmlspecialchars($settings['store_name_mr'] ?: $settings['store_name']) ?></h1>
                <div style="font-size: 14px; font-weight: bold; color: #b8972e; letter-spacing: 0.5px;"><?= htmlspecialchars($settings['store_name']) ?></div>
                <div style="color: #666; font-size: 13px; margin-top: 3px; font-style: italic;"><?= htmlspecialchars($settings['tagline_mr'] ?: $settings['tagline']) ?></div>
                <div style="font-size: 13px; margin-top: 5px; line-height: 1.5;">
                    📍 <strong>तासगाव</strong><br>
                    👤 नाव (Name): <strong>किरण माळी (Kiran Mali)</strong> &bull; 📞 मो.: <strong><?= htmlspecialchars($settings['phone'] ?: '9689588375') ?></strong>
                </div>
            </div>
            <div style="text-align: right; background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <h2 style="margin: 0; color: #0f4c3a; font-size: 18px; text-transform: uppercase;">किरकोळ विक्री बिल</h2>
                <div style="font-size: 15px; margin-top: 6px;"><strong>बिल क्र.:</strong> <?= htmlspecialchars($invoice['invoice_no']) ?></div>
                <div style="font-size: 13px; margin-top: 2px;"><strong>दिनांक:</strong> <?= date('d-m-Y', strtotime($invoice['created_at'])) ?></div>
                <div style="font-size: 13px; margin-top: 2px;"><strong>वेळ:</strong> <?= date('h:i A', strtotime($invoice['created_at'])) ?></div>
                <div style="font-size: 13px; margin-top: 2px;"><strong>पेमेंट:</strong> <?= htmlspecialchars($invoice['payment_mode']) ?> (<?= htmlspecialchars($invoice['payment_status']) ?>)</div>
            </div>
        </div>

        <div style="margin-top: 15px; padding: 10px 14px; background: #f8fafc; border-radius: 6px; font-size: 13px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between;">
            <div>
                <strong>ग्राहक नाव:</strong> <?= htmlspecialchars($invoice['customer_name'] ?: 'ग्राहक') ?><br>
                <strong>मोबाईल नंबर:</strong> <?= htmlspecialchars($invoice['customer_phone'] ?: '-') ?>
            </div>
            <div style="text-align: right;">
                <strong>एकूण वस्तू:</strong> <?= count($items) ?><br>
                <?php if ($totalWeightKg > 0): ?>
                    <strong>एकूण फराळ वजन:</strong> <?= number_format($totalWeightKg, 2) ?> kg
                <?php endif; ?>
            </div>
        </div>

        <table style="width: 100%; margin-top: 20px; border-collapse: collapse;">
            <thead>
                <tr style="background: #0f4c3a; color: #fff;">
                    <th style="width: 8%; padding: 10px; border: 1px solid #0f4c3a;">क्र.</th>
                    <th style="width: 45%; padding: 10px; border: 1px solid #0f4c3a; text-align: left;">पदार्थाचे नाव (Product Description)</th>
                    <th style="width: 15%; padding: 10px; border: 1px solid #0f4c3a; text-align: center;">वजन / नग</th>
                    <th style="width: 15%; padding: 10px; border: 1px solid #0f4c3a; text-align: right;">दर प्रति एकक</th>
                    <th style="width: 17%; padding: 10px; border: 1px solid #0f4c3a; text-align: right;">एकूण रक्कम</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($items as $item): 
                    $qtyDisplay = '';
                    if ($item['unit'] === 'kg') {
                        $qtyDisplay = ($item['qty'] < 1) ? round($item['qty'] * 1000) . ' ग्रॅम' : $item['qty'] . ' किलो';
                    } elseif ($item['unit'] === 'box') {
                        $qtyDisplay = $item['qty'] . ' बॉक्स';
                    } else {
                        $qtyDisplay = $item['qty'] . ' ' . $item['unit'];
                    }
                ?>
                    <tr>
                        <td style="text-align: center; border: 1px solid #ddd; padding: 8px;"><?= $i++ ?></td>
                        <td style="border: 1px solid #ddd; padding: 8px;"><strong><?= htmlspecialchars($item['product_name']) ?></strong></td>
                        <td style="text-align: center; border: 1px solid #ddd; padding: 8px;"><?= $qtyDisplay ?></td>
                        <td style="text-align: right; border: 1px solid #ddd; padding: 8px;">Rs. <?= number_format($item['unit_rate'], 2) ?></td>
                        <td style="text-align: right; font-weight: bold; border: 1px solid #ddd; padding: 8px; color: #0f4c3a;">Rs. <?= number_format($item['total_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="display: flex; justify-content: flex-end; margin-top: 15px;">
            <div style="width: 320px; font-size: 14px; background: #fafaf9; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span>उप-एकूण:</span>
                    <span>Rs. <?= number_format($invoice['subtotal'], 2) ?></span>
                </div>
                <?php if ($invoice['discount_amount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px; color: #16a34a;">
                        <span>सवलत (Discount):</span>
                        <span>-Rs. <?= number_format($invoice['discount_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($invoice['tax_amount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>कर (GST):</span>
                        <span>+Rs. <?= number_format($invoice['tax_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between; font-size: 19px; font-weight: 800; border-top: 2px solid #0f4c3a; padding-top: 8px; margin-top: 8px; color: #0f4c3a;">
                    <span>अंतिम देय रक्कम:</span>
                    <span>Rs. <?= number_format($invoice['grand_total'], 2) ?></span>
                </div>
            </div>
        </div>

        <div style="margin-top: 40px; display: flex; justify-content: space-between; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 12px;">
            <div>
                <?= htmlspecialchars($settings['return_policy'] ?? '') ?>
            </div>
            <div style="text-align: right;">
                अधिकृत स्वाक्षरी<br><br><br>
                <strong><?= htmlspecialchars($settings['store_name_mr']) ?></strong>
            </div>
        </div>
    </div>
<?php endif; ?>

</div>

<?php if ($autoPrint): ?>
<script>
    window.addEventListener('load', () => {
        window.print();
    });
</script>
<?php endif; ?>

</body>
</html>
