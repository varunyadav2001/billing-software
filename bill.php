<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Ensure Indian Standard Time (IST) is always used
date_default_timezone_set('Asia/Kolkata');

$pdo = get_db_connection();
$settings = get_store_settings($pdo);

$invoiceNo = trim($_GET['inv'] ?? '');
if (!$invoiceNo) {
    die("अवैध बिल क्रमांक.");
}

$stmtInv = $pdo->prepare("SELECT * FROM invoices WHERE invoice_no = ?");
$stmtInv->execute([$invoiceNo]);
$invoice = $stmtInv->fetch();

if (!$invoice) {
    die("हे बिल डेटाबेसमध्ये सापडले नाही.");
}

$stmtItems = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$stmtItems->execute([$invoice['id']]);
$items = $stmtItems->fetchAll();

$baseUrl = get_public_base_url();
$digitalBillUrl = "{$baseUrl}/bill.php?inv={$invoice['invoice_no']}";
$whatsappText = format_whatsapp_bill($invoice, $items, $settings, $digitalBillUrl);

$cleanPhone = preg_replace('/[^0-9]/', '', $invoice['customer_phone']);
if (strlen($cleanPhone) === 10) {
    $cleanPhone = '91' . $cleanPhone;
}
$whatsappUrl = !empty($cleanPhone) 
    ? "https://wa.me/{$cleanPhone}?text=" . rawurlencode($whatsappText)
    : "https://api.whatsapp.com/send?text=" . rawurlencode($whatsappText);

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
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0a3528">
    <meta name="format-detection" content="telephone=no">
    <title>डिजिटल बिल - <?= htmlspecialchars($invoice['invoice_no']) ?> - <?= htmlspecialchars($settings['store_name_mr']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/print.css">
    <script src="assets/js/qrcode.min.js"></script>
    <script src="assets/js/html2canvas.min.js"></script>
    <style>
        body {
            background-color: #f1f5f9;
            padding: 1.25rem 0.6rem;
            padding-bottom: max(1.5rem, env(safe-area-inset-bottom));
        }
        .bill-card-container {
            max-width: 540px;
            margin: 0 auto;
        }
        .digital-bill-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 2px solid #0f4c3a;
        }
        .bill-top-banner {
            background: linear-gradient(135deg, #0a3528 0%, #0f4c3a 100%);
            color: #ffffff;
            padding: 1.4rem 1rem;
            text-align: center;
            border-bottom: 4px solid #d4af37;
        }
        .bill-store-name {
            font-size: 1.45rem;
            font-weight: 800;
            color: #f7e7a9;
            margin-bottom: 0.2rem;
            letter-spacing: 0.5px;
        }
        .bill-store-name-en {
            font-size: 0.85rem;
            color: #cbd5e1;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.35rem;
        }
        .bill-store-tag {
            font-size: 0.82rem;
            color: #f3e5ab;
            font-style: italic;
            margin-bottom: 0.5rem;
        }
        .bill-store-address {
            font-size: 0.78rem;
            color: #e2e8f0;
            line-height: 1.4;
        }
        .bill-meta-bar {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.85rem;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.4rem;
        }
        .bill-items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        .bill-items-table th {
            background: #f1f5f9;
            padding: 0.65rem 0.5rem;
            color: #0f4c3a;
            font-size: 0.78rem;
            font-weight: 700;
            border-bottom: 2px solid #cbd5e1;
            text-align: left;
        }
        .bill-items-table td {
            padding: 0.65rem 0.5rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .bill-totals-section {
            padding: 1rem 1rem;
            background: #fafaf9;
            border-top: 2px dashed #cbd5e1;
        }
        .bill-action-bar {
            margin-top: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        @media (max-width: 480px) {
            body {
                padding: 0.5rem 0.35rem;
            }
            .bill-store-name {
                font-size: 1.25rem;
            }
            .bill-top-banner {
                padding: 1.1rem 0.75rem;
            }
            .bill-items-table {
                font-size: 0.82rem;
            }
            .bill-items-table th, .bill-items-table td {
                padding: 0.55rem 0.35rem;
            }
        }
    </style>
</head>
<body>

<div class="bill-card-container">
    <!-- Action buttons above card -->
    <div class="bill-action-bar no-print" style="margin-bottom: 1rem;">
        <a href="<?= $whatsappUrl ?>" target="_blank" class="btn btn-success" style="padding: 0.75rem; justify-content: center; font-size: 1rem; background: #25D366; border-color: #25D366;">
            📲 WhatsApp वर पाठवा (Share)
        </a>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
            <button id="btnDownloadImage" class="btn btn-gold" style="justify-content: center;">
                📸 फोटो (PNG) डाउनलोड
            </button>
            <a href="invoice-print.php?inv=<?= urlencode($invoice['invoice_no']) ?>" target="_blank" class="btn btn-primary" style="justify-content: center;">
                🖨️ पावती प्रिंट करा
            </a>
        </div>
        <div style="text-align: center; margin-top: 0.25rem;">
            <a href="billing.php" style="color: var(--primary); font-size: 0.88rem; font-weight: 600; text-decoration: none;">
                ← मुख्य POS बिलिंग काउंटरवर जा
            </a>
        </div>
    </div>

    <!-- The Actual Bill Card (Captured by html2canvas) -->
    <div class="digital-bill-card" id="digitalBillCard">
        <div class="bill-top-banner">
            <div style="font-size: 2rem; margin-bottom: 0.25rem;">✨</div>
            <div class="bill-store-name"><?= htmlspecialchars($settings['store_name_mr']) ?></div>
            <div class="bill-store-name-en"><?= htmlspecialchars($settings['store_name']) ?></div>
            <div class="bill-store-tag"><?= htmlspecialchars($settings['tagline_mr']) ?></div>
            <div class="bill-store-address" style="margin-top: 5px; font-size: 0.85rem;">
                📍 <strong>तासगाव</strong><br>
                👤 नाव (Name): <strong>किरण माळी (Kiran Mali)</strong> &nbsp;|&nbsp; 📞 मो.: <strong><?= htmlspecialchars($settings['phone'] ?: '9689588375') ?></strong>
            </div>
        </div>

        <div class="bill-meta-bar">
            <div>
                <div><strong>बिल क्र.:</strong> <?= htmlspecialchars($invoice['invoice_no']) ?></div>
                <div style="color: #64748b; font-size: 0.8rem;"><?= date('d-m-Y h:i A', strtotime($invoice['created_at'])) ?></div>
            </div>
            <div style="text-align: right;">
                <div><strong>ग्राहक:</strong> <?= htmlspecialchars($invoice['customer_name'] ?: 'ग्राहक') ?></div>
                <?php if (!empty($invoice['customer_phone'])): ?>
                    <div style="color: #64748b; font-size: 0.8rem;">फोन: <?= htmlspecialchars($invoice['customer_phone']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Purchased items -->
        <table class="bill-items-table">
            <thead>
                <tr>
                    <th style="width: 8%;">क्र.</th>
                    <th>फराळ / पदार्थ</th>
                    <th style="text-align: center;">वजन / नग</th>
                    <th style="text-align: right;">दर</th>
                    <th style="text-align: right;">रक्कम</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($items as $item): 
                    $qtyDisplay = '';
                    if ($item['unit'] === 'kg') {
                        $qtyDisplay = ($item['qty'] < 1) ? round($item['qty'] * 1000) . 'g' : $item['qty'] . ' kg';
                    } elseif ($item['unit'] === 'box') {
                        $qtyDisplay = $item['qty'] . ' बॉक्स';
                    } else {
                        $qtyDisplay = $item['qty'] . ' ' . $item['unit'];
                    }
                ?>
                    <tr>
                        <td style="text-align: center; color: #64748b;"><?= $i++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                        </td>
                        <td style="text-align: center; color: #475569; font-weight: 500;">
                            <?= $qtyDisplay ?>
                        </td>
                        <td style="text-align: right; color: #64748b;">
                            ₹<?= number_format($item['unit_rate'], 0) ?>
                        </td>
                        <td style="text-align: right; font-weight: 700; color: #0a3528;">
                            ₹<?= number_format($item['total_amount'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Count & Weight Breakdown -->
        <div style="background: #f8fafc; padding: 0.5rem 1.25rem; display: flex; justify-content: space-between; font-size: 0.8rem; border-top: 1px solid #e2e8f0; color: #475569;">
            <span>एकूण पदार्थ: <strong><?= count($items) ?></strong></span>
            <?php if ($totalWeightKg > 0): ?>
                <span>एकूण फराळ वजन: <strong><?= number_format($totalWeightKg, 2) ?> किलो (kg)</strong></span>
            <?php endif; ?>
        </div>

        <!-- Totals -->
        <div class="bill-totals-section">
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem; color: #64748b;">
                <span>उप-एकूण (Subtotal):</span>
                <span>₹<?= number_format($invoice['subtotal'], 2) ?></span>
            </div>
            <?php if ($invoice['discount_amount'] > 0): ?>
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem; color: #16a34a;">
                    <span>सवलत (Discount):</span>
                    <span>-₹<?= number_format($invoice['discount_amount'], 2) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($invoice['tax_amount'] > 0): ?>
                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem; color: #64748b;">
                    <span>कर (Tax):</span>
                    <span>+₹<?= number_format($invoice['tax_amount'], 2) ?></span>
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; font-size: 1.4rem; font-weight: 800; color: #0a3528; border-top: 2px dashed #cbd5e1; padding-top: 0.5rem; margin-top: 0.5rem;">
                <span>एकूण देय रक्कम:</span>
                <span>₹<?= number_format($invoice['grand_total'], 2) ?></span>
            </div>

            <div style="margin-top: 0.6rem; font-size: 0.82rem; color: #475569; display: flex; justify-content: space-between;">
                <span>पेमेंट प्रकार: <strong><?= htmlspecialchars($invoice['payment_mode']) ?></strong></span>
                <span class="badge <?= ($invoice['payment_status'] === 'PAID') ? 'badge-success' : 'badge-danger' ?>">
                    <?= ($invoice['payment_status'] === 'PAID') ? '✓ पूर्ण भरले (Paid)' : '⚠️ बाकी (उधारी)' ?>
                </span>
            </div>
        </div>

        <div style="padding: 1rem; text-align: center; background: #f8fafc; border-top: 1px dashed #e2e8f0; font-size: 0.78rem; color: #64748b;">
            <p>🙏 <strong>आमच्याकडे खरेदी केल्याबद्दल मनःपूर्वक धन्यवाद!</strong></p>
            <p style="margin-top: 0.2rem;">पुन्हा अवश्य भेट द्या &bull; मंगलमूर्ती प्रॉडक्ट्स &bull; शुद्धता आणि आपुलकी!</p>
        </div>
    </div>
</div>

<!-- iOS / Mobile Image Save Modal Preview -->
<div class="modal-overlay" id="iosImagePreviewModal">
    <div class="modal-card" style="max-width: 480px;">
        <div class="modal-header">
            <div class="modal-title">📸 बिल फोटो (Save Image)</div>
            <button class="modal-close" onclick="closeImageModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 1rem;">
            <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 0.65rem; margin-bottom: 0.85rem; font-size: 0.82rem; color: #92400e; text-align: left;">
                📱 <strong>आयफोनवर (iPhone) फोटो सेव्ह करण्यासाठी:</strong><br>
                खालील फोटोवर १-२ सेकंद बोट दाबून धरा (Long Press) आणि <strong>"Save to Photos"</strong> किंवा <strong>"Add to Photos"</strong> निवडा.
            </div>
            <img id="previewModalImg" src="" alt="Bill Image" style="max-width: 100%; height: auto; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
        </div>
        <div class="modal-footer" style="justify-content: space-between;">
            <button class="btn btn-outline" onclick="closeImageModal()">बंद करा</button>
            <a id="modalDirectDownloadLink" href="#" download="Bill-<?= $invoice['invoice_no'] ?>.png" class="btn btn-gold">
                📥 थेट डाउनलोड करा
            </a>
        </div>
    </div>
</div>

<script>
function closeImageModal() {
    const modal = document.getElementById('iosImagePreviewModal');
    if (modal) modal.classList.remove('open');
}

document.addEventListener('DOMContentLoaded', () => {
    const btnDownload = document.getElementById('btnDownloadImage');
    if (btnDownload) {
        btnDownload.addEventListener('click', async () => {
            btnDownload.innerText = '📸 फोटो तयार करत आहे...';
            btnDownload.disabled = true;

            const billCard = document.getElementById('digitalBillCard');
            try {
                const canvas = await html2canvas(billCard, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#ffffff'
                });

                const fileName = `Bill-<?= $invoice['invoice_no'] ?>.png`;
                const dataUrl = canvas.toDataURL('image/png');

                // Try iOS / Mobile Web Share API first
                let sharedSuccessfully = false;
                if (navigator.share && navigator.canShare) {
                    try {
                        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
                        const file = new File([blob], fileName, { type: 'image/png' });
                        if (navigator.canShare({ files: [file] })) {
                            await navigator.share({
                                files: [file],
                                title: `बिल <?= $invoice['invoice_no'] ?>`,
                                text: `<?= addslashes($settings['store_name_mr']) ?> - बिल क्र. <?= $invoice['invoice_no'] ?>`
                            });
                            sharedSuccessfully = true;
                        }
                    } catch (shareErr) {
                        // User cancelled share sheet or error
                        console.log('Share dismissed or not supported', shareErr);
                    }
                }

                if (!sharedSuccessfully) {
                    // Detect if on iOS / iPhone
                    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || 
                                  (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

                    if (isIOS) {
                        // Show friendly preview modal with long-press instruction on iPhone
                        const previewImg = document.getElementById('previewModalImg');
                        const directLink = document.getElementById('modalDirectDownloadLink');
                        if (previewImg) previewImg.src = dataUrl;
                        if (directLink) directLink.href = dataUrl;
                        
                        const modal = document.getElementById('iosImagePreviewModal');
                        if (modal) modal.classList.add('open');
                    } else {
                        // Standard desktop / Android direct download
                        const link = document.createElement('a');
                        link.download = fileName;
                        link.href = dataUrl;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    }
                }
            } catch (err) {
                console.error(err);
                alert('फोटो डाउनलोड करताना त्रुटी आली.');
            } finally {
                btnDownload.innerText = '📸 फोटो (PNG) डाउनलोड';
                btnDownload.disabled = false;
            }
        });
    }
});
</script>

</body>
</html>
