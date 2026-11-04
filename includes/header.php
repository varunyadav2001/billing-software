<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';

$pdo = get_db_connection();
$settings = get_store_settings($pdo);
$currentPage = basename($_SERVER['PHP_SELF']);

// Calculate today's quick summary for header
$todayDate = date('Y-m-d');
$stmtToday = $pdo->prepare("SELECT COUNT(*) as bills_count, COALESCE(SUM(grand_total), 0) as total_sales FROM invoices WHERE DATE(created_at) = ?");
$stmtToday->execute([$todayDate]);
$todayStats = $stmtToday->fetch();
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="मंगलमूर्ती POS">
    <meta name="theme-color" content="#0a3528">
    <meta name="format-detection" content="telephone=no">
    <title><?= htmlspecialchars($settings['store_name_mr'] ?: $settings['store_name']) ?> - रिटेल POS & डिजिटल बिलिंग</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/print.css">
    <script src="assets/js/qrcode.min.js"></script>
    <script src="assets/js/html2canvas.min.js"></script>
    <script src="assets/js/i18n.js"></script>
</head>
<body>

<header class="header-nav no-print">
    <div class="nav-container">
        <div class="brand-wrapper">
            <a href="billing.php" class="brand-section">
                <div class="brand-icon">✨</div>
                <div class="brand-info">
                    <h1 data-i18n="brand_name"><?= htmlspecialchars($settings['store_name_mr'] ?: $settings['store_name']) ?></h1>
                    <span data-i18n="brand_tagline"><?= htmlspecialchars($settings['tagline_mr'] ?: $settings['tagline']) ?></span>
                </div>
            </a>
            
            <div class="mobile-header-actions">
                <!-- Language Switcher for quick mobile access -->
                <button type="button" id="btnLangToggleMobile" class="btn btn-outline lang-toggle-btn mobile-only-btn" title="भाषा बदला / Switch Language">
                    🌐 <span class="lang-text">मराठी</span>
                </button>
                <!-- Hamburger menu button -->
                <button type="button" class="nav-toggle-btn" id="navToggleBtn" aria-label="मेनू उघडा">
                    <span class="hamburger-bar"></span>
                    <span class="hamburger-bar"></span>
                    <span class="hamburger-bar"></span>
                </button>
            </div>
        </div>

        <ul class="nav-links" id="mainNavLinks">
            <li>
                <a href="billing.php" class="nav-link pos-highlight <?= ($currentPage === 'billing.php') ? 'active' : '' ?>" data-i18n="nav_pos">
                    ⚡ POS बिलिंग
                </a>
            </li>
            <li>
                <a href="products.php" class="nav-link <?= ($currentPage === 'products.php') ? 'active' : '' ?>" data-i18n="nav_products">
                    📦 उत्पादने व स्टॉक
                </a>
            </li>
            <li>
                <a href="categories.php" class="nav-link <?= ($currentPage === 'categories.php') ? 'active' : '' ?>" data-i18n="nav_categories">
                    📂 कॅटेगरीज
                </a>
            </li>
            <li>
                <a href="invoices.php" class="nav-link <?= ($currentPage === 'invoices.php') ? 'active' : '' ?>" data-i18n="nav_invoices">
                    🧾 बिले इतिहास
                </a>
            </li>
            <li>
                <a href="customers.php" class="nav-link <?= ($currentPage === 'customers.php') ? 'active' : '' ?>" data-i18n="nav_customers">
                    👥 ग्राहक व उधारी
                </a>
            </li>
            <li>
                <a href="reports.php" class="nav-link <?= ($currentPage === 'reports.php' || $currentPage === 'dashboard.php') ? 'active' : '' ?>" data-i18n="nav_reports">
                    📊 रिपोर्ट्स
                </a>
            </li>
            <li>
                <a href="settings.php" class="nav-link <?= ($currentPage === 'settings.php') ? 'active' : '' ?>" data-i18n="nav_settings">
                    ⚙️ सेटिंग्ज
                </a>
            </li>
        </ul>

        <div class="nav-actions">
            <!-- Desktop Language Switcher -->
            <button type="button" id="btnLangToggle" class="btn btn-outline lang-toggle-btn desktop-only-btn" style="color: #f7e7a9; border-color: rgba(212,175,55,0.6); padding: 0.35rem 0.65rem; font-size: 0.82rem; cursor: pointer;" title="भाषा बदला / Switch Language">
                🌐 <strong>मराठी</strong> / EN
            </button>

            <!-- Today's Sales Badge -->
            <a href="invoices.php" class="btn btn-outline today-badge" style="color: #f7e7a9; border-color: rgba(212,175,55,0.4); padding: 0.35rem 0.65rem; font-size: 0.82rem;">
                <span data-i18n="today_sales_badge">आजची विक्री:</span> <strong>₹<?= number_format($todayStats['total_sales'], 0) ?></strong> (<?= $todayStats['bills_count'] ?>)
            </a>

            <?php if (is_logged_in()): ?>
                <a href="logout.php" class="btn btn-danger" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;" data-i18n="logout" title="लॉगआउट">
                    लॉगआउट
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-gold" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;" data-i18n="login">
                    लॉगिन
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="app-container">
