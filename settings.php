<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = get_db_connection();
$settings = get_store_settings($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $storeName = trim($_POST['store_name'] ?? '');
        $storeNameMr = trim($_POST['store_name_mr'] ?? '');
        $tagline = trim($_POST['tagline'] ?? '');
        $taglineMr = trim($_POST['tagline_mr'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $cityPincode = trim($_POST['city_pincode'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $altPhone = trim($_POST['alt_phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $gstin = trim($_POST['gstin'] ?? '');
        $fssaiNo = trim($_POST['fssai_no'] ?? '');
        $upiId = trim($_POST['upi_id'] ?? '');
        $upiName = trim($_POST['upi_name'] ?? '');
        $billPrefix = strtoupper(trim($_POST['bill_prefix'] ?? 'FARAL'));
        $returnPolicy = trim($_POST['return_policy'] ?? '');

        if (!$storeName || !$phone) {
            $error = 'दुकानाचे नाव आणि फोन नंबर आवश्यक आहे.';
        } else {
            $stmt = $pdo->prepare("UPDATE settings SET 
                store_name = ?, store_name_mr = ?, tagline = ?, tagline_mr = ?,
                address = ?, city_pincode = ?, phone = ?, alt_phone = ?,
                email = ?, gstin = ?, fssai_no = ?, upi_id = ?, upi_name = ?,
                bill_prefix = ?, return_policy = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?");
            
            $stmt->execute([
                $storeName, $storeNameMr, $tagline, $taglineMr,
                $address, $cityPincode, $phone, $altPhone,
                $email, $gstin, $fssaiNo, $upiId, $upiName,
                $billPrefix, $returnPolicy, $settings['id']
            ]);

            $message = 'स्टोअर सेटिंग्ज यशस्वीरीत्या सेव्ह झाल्या!';
            $settings = get_store_settings($pdo);
        }
    } elseif ($action === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (strlen($newPass) < 6) {
            $error = 'नवीन पासवर्ड किमान ६ अक्षरांचा असावा.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'नवीन पासवर्ड आणि कन्फर्म पासवर्ड जुळत नाहीत.';
        } else {
            // Verify old password
            $stmtU = $pdo->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
            $admin = $stmtU->fetch();

            if ($admin && password_verify($oldPass, $admin['password_hash'])) {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmtUpd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmtUpd->execute([$newHash, $admin['id']]);
                $message = 'ॲडमिन पासवर्ड यशस्वीरीत्या बदलला!';
            } else {
                $error = 'जुना पासवर्ड चुकीचा आहे.';
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 1.5rem;">
    <h2 style="font-size: 1.4rem; color: var(--primary-dark); font-weight: 700;">⚙️ स्टोअर व बिलिंग सेटिंग्ज</h2>
    <p style="font-size: 0.85rem; color: var(--gray-600);">दुकानाचे नाव, पत्ता, संपर्क, GST/FSSAI, UPI QR आणि पासवर्ड</p>
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

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
    <!-- Store Details Form -->
    <div class="card" style="margin: 0;">
        <div class="card-header">
            <h3 class="card-title">🏪 दुकानाची माहिती व पावती स्वरूप</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_settings">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">दुकानाचे नाव (मराठी) *</label>
                        <input type="text" name="store_name_mr" class="form-control" value="<?= htmlspecialchars($settings['store_name_mr']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">दुकानाचे नाव (English) *</label>
                        <input type="text" name="store_name" class="form-control" value="<?= htmlspecialchars($settings['store_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">टॅगलाईन (मराठी)</label>
                        <input type="text" name="tagline_mr" class="form-control" value="<?= htmlspecialchars($settings['tagline_mr']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">टॅगलाईन (English)</label>
                        <input type="text" name="tagline" class="form-control" value="<?= htmlspecialchars($settings['tagline']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">पत्ता (बाजारपेठ / रस्ता)</label>
                        <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($settings['address']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">गाव / शहर व पिनकोड</label>
                        <input type="text" name="city_pincode" class="form-control" value="<?= htmlspecialchars($settings['city_pincode']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">मोबाईल नंबर (WhatsApp/Billing) *</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($settings['phone']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">पर्यायी नंबर (Alt Phone)</label>
                        <input type="text" name="alt_phone" class="form-control" value="<?= htmlspecialchars($settings['alt_phone']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">ईमेल आयडी</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($settings['email']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">बिल प्रीफिक्स (Prefix)</label>
                        <input type="text" name="bill_prefix" class="form-control" value="<?= htmlspecialchars($settings['bill_prefix']) ?>" placeholder="उदा. FARAL">
                    </div>

                    <div class="form-group">
                        <label class="form-label">GSTIN (वैकल्पिक)</label>
                        <input type="text" name="gstin" class="form-control" value="<?= htmlspecialchars($settings['gstin']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">FSSAI परवाना क्रमांक (अन्न सुरक्षा)</label>
                        <input type="text" name="fssai_no" class="form-control" value="<?= htmlspecialchars($settings['fssai_no']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">पेमेंट UPI ID (QR कोडसाठी)</label>
                        <input type="text" name="upi_id" class="form-control" value="<?= htmlspecialchars($settings['upi_id']) ?>" placeholder="उदा. 9876543210@upi">
                    </div>

                    <div class="form-group">
                        <label class="form-label">UPI धारकाचे नाव</label>
                        <input type="text" name="upi_name" class="form-control" value="<?= htmlspecialchars($settings['upi_name']) ?>">
                    </div>

                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">पावतीवरील संदेश / पॉलिसी (Notes on Bill)</label>
                        <textarea name="return_policy" class="form-control" rows="2"><?= htmlspecialchars($settings['return_policy']) ?></textarea>
                    </div>
                </div>

                <div style="margin-top: 1rem; text-align: right;">
                    <button type="submit" class="btn btn-gold" style="padding: 0.65rem 1.5rem; font-size: 1rem;">
                        💾 सेटिंग्ज साठवा (Save)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Password & Admin Side -->
    <div style="display: flex; flex-direction: column; gap: 1rem;">
        <div class="card" style="margin: 0;">
            <div class="card-header">
                <h3 class="card-title">🔒 ॲडमिन पासवर्ड बदला</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label class="form-label">सध्याचा पासवर्ड</label>
                        <input type="password" name="old_password" class="form-control" required placeholder="password123">
                    </div>

                    <div class="form-group">
                        <label class="form-label">नवीन पासवर्ड</label>
                        <input type="password" name="new_password" class="form-control" required placeholder="किमान ६ अक्षरे">
                    </div>

                    <div class="form-group">
                        <label class="form-label">नवीन पासवर्ड पुन्हा टाका</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        पासवर्ड अपडेट करा
                    </button>
                </form>
            </div>
        </div>

        <div class="card" style="margin: 0; background: #f8fafc;">
            <div class="card-body">
                <h4 style="font-size: 0.95rem; margin-bottom: 0.5rem; color: var(--primary);">🌐 Local Wi-Fi Mobile Billing:</h4>
                <p style="font-size: 0.82rem; color: #64748b; line-height: 1.4; margin-bottom: 0.5rem;">
                    दुकानदाराच्या संगणकाचा IP: <strong><?= htmlspecialchars(get_public_base_url()) ?></strong>
                </p>
                <p style="font-size: 0.8rem; color: #475569;">
                    जेव्हा तुम्ही ग्राहकाला WhatsApp वर डिजिटल बिल पाठवता, तेव्हा ग्राहक दुकानाच्या Wi-Fi वरून थेट बिल पाहू शकतो.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
