<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = get_db_connection();
$settings = get_store_settings($pdo);

// Fetch categories
$stmtCats = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order ASC");
$categories = $stmtCats->fetchAll();

// Fetch all active products
$stmtProds = $pdo->query("SELECT p.*, c.name as category_name, c.name_en as category_name_en 
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.is_active = 1 
                          ORDER BY c.display_order ASC, p.name ASC");
$products = $stmtProds->fetchAll();

// Fetch today's recent bills for quick history on billing screen
$todayDate = date('Y-m-d');
$stmtRecent = $pdo->prepare("SELECT * FROM invoices WHERE DATE(created_at) = ? ORDER BY id DESC LIMIT 6");
$stmtRecent->execute([$todayDate]);
$recentInvoices = $stmtRecent->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Mobile View Tab Switcher (Visible only on iPhone / Mobile screens) -->
<div class="pos-mobile-nav" id="posMobileNav">
    <button type="button" class="pos-mobile-tab active" id="tabBtnProducts" onclick="posCart.switchMobileTab('products')">
        📦 <span data-i18n="mobile_tab_products">उत्पादने</span>
    </button>
    <button type="button" class="pos-mobile-tab" id="tabBtnCart" onclick="posCart.switchMobileTab('cart')">
        🛒 <span data-i18n="mobile_tab_cart">बिलिंग कार्ट</span> (<span id="mobileCartBadge">0</span>) • ₹<span id="mobileCartHeaderTotal">0.00</span>
    </button>
</div>

<div class="pos-layout show-products" id="posLayout">
    <!-- Left: Product Catalog & Quick Weight Selection -->
    <section class="pos-products-panel" id="posProductsPanel">
        <!-- Clean search bar without barcode scanner -->
        <div class="pos-search-bar">
            <div class="search-input-wrapper">
                <span class="search-icon-pos">🔍</span>
                <input type="text" id="posSearchInput" data-i18n-ph="search_ph" placeholder="शोधा: लाडू, चिवडा, चकली, फराळ... (Hotkey: /)" autocomplete="off" autofocus>
                <span class="shortcut-badge">/</span>
            </div>
        </div>

        <div class="pos-categories-pills">
            <button class="category-pill active" data-cat-id="all" data-i18n="cat_all">सर्व उत्पादने (All)</button>
            <?php foreach ($categories as $cat): ?>
                <button class="category-pill" data-cat-id="<?= $cat['id'] ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="pos-grid-container">
            <div class="product-grid" id="productGrid">
                <?php foreach ($products as $prod): 
                    $isLowStock = ($prod['stock_qty'] <= $prod['min_stock_alert']);
                    $isOutOfStock = ($prod['stock_qty'] <= 0);
                    $unitLabel = ($prod['unit'] === 'kg') ? '/किलो' : ('/' . $prod['unit']);
                ?>
                    <div class="product-card <?= $isLowStock ? 'low-stock' : '' ?> <?= $isOutOfStock ? 'out-of-stock' : '' ?>"
                         data-id="<?= $prod['id'] ?>"
                         data-name="<?= htmlspecialchars($prod['name']) ?>"
                         data-name-mr="<?= htmlspecialchars($prod['name']) ?>"
                         data-name-en="<?= htmlspecialchars($prod['name_en'] ?: $prod['name']) ?>"
                         data-price="<?= $prod['selling_price'] ?>"
                         data-unit="<?= $prod['unit'] ?>"
                         data-cat="<?= $prod['category_id'] ?>"
                         data-stock="<?= $prod['stock_qty'] ?>">
                        
                        <div class="prod-cat-tag"><?= htmlspecialchars($prod['category_name']) ?></div>
                        <div class="prod-title"><?= htmlspecialchars($prod['name']) ?></div>
                        <div class="prod-sub"><?= htmlspecialchars($prod['name_en'] ?: '') ?></div>
                        
                        <div class="prod-footer">
                            <div class="prod-price">
                                ₹<?= number_format($prod['selling_price'], 0) ?><span class="prod-unit"><?= $unitLabel ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 4px;">
                                <button class="btn btn-outline" onclick="event.stopPropagation(); posCart.quickEditBasePrice(<?= $prod['id'] ?>, '<?= htmlspecialchars($prod['name']) ?>', <?= $prod['selling_price'] ?>)" style="padding: 2px 6px; font-size: 0.7rem; border-color: #cbd5e1; border-radius: 4px; background: #fff;" title="दर बदला (Adjust Price)">
                                    ✏️ दर
                                </button>
                                <span class="prod-stock-badge <?= $isLowStock ? 'warning' : 'good' ?>">
                                    <?= ($prod['unit'] === 'kg') ? number_format($prod['stock_qty'], 2) . ' kg' : number_format($prod['stock_qty'], 0) . ' ' . $prod['unit'] ?>
                                </span>
                            </div>
                        </div>

                        <!-- Quick Weight Selector Buttons for Faral & Snacks -->
                        <?php if ($prod['unit'] === 'kg'): ?>
                            <div class="quick-weight-strip" onclick="event.stopPropagation();">
                                <button class="btn-wt" onclick="posCart.addItem(<?= $prod['id'] ?>, 0.250)">२५०g</button>
                                <button class="btn-wt" onclick="posCart.addItem(<?= $prod['id'] ?>, 0.500)">५००g</button>
                                <button class="btn-wt" onclick="posCart.addItem(<?= $prod['id'] ?>, 1.000)">१ kg</button>
                                <button class="btn-wt" onclick="posCart.addItem(<?= $prod['id'] ?>, 2.000)">२ kg</button>
                            </div>
                        <?php else: ?>
                            <div class="quick-weight-strip" onclick="event.stopPropagation();">
                                <button class="btn-wt" onclick="posCart.addItem(<?= $prod['id'] ?>, 1)">+१ नग/बॉक्स</button>
                                <button class="btn-wt" onclick="posCart.addItem(<?= $prod['id'] ?>, 2)">+२ नग</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Right: Active Billing Cart & Checkout -->
    <section class="pos-cart-panel" id="posCartPanel">
        <!-- Mobile Back to Products Bar -->
        <div class="mobile-cart-back-bar" onclick="posCart.switchMobileTab('products')">
            <span>← आणखी फराळ / उत्पादने निवडा</span>
            <span style="font-size: 0.75rem; opacity: 0.85;">(Back to Products)</span>
        </div>

        <div class="cart-header">
            <h2 data-i18n="cart_title">🛒 बिलिंग कार्ट</h2>
            <button class="btn btn-danger" id="btnClearCart" style="padding: 3px 8px; font-size: 0.75rem;" data-i18n="cart_clear" title="कार्ट रिकामे करा">
                रिकामा करा
            </button>
        </div>

        <!-- Customer autofill section -->
        <div class="cart-customer-section">
            <div class="cust-input-row">
                <input type="tel" id="custPhone" class="cust-phone" data-i18n-ph="cust_phone_ph" placeholder="ग्राहकाचा फोन (१० अंक)" maxlength="10">
                <input type="text" id="custName" class="cust-name" data-i18n-ph="cust_name_ph" placeholder="ग्राहकाचे नाव">
            </div>
            <div id="custCreditAlert" class="cust-credit-alert" style="display: none;">
                ⚠️ <span data-i18n="credit_warning">मागील उधारी शिल्लक: ₹</span><strong><span id="custCreditAmt">0.00</span></strong>
            </div>
        </div>

        <!-- Items Table -->
        <div class="cart-items-wrapper">
            <table class="cart-table" id="cartTable">
                <thead>
                    <tr>
                        <th data-i18n="table_item">वस्तू</th>
                        <th style="width: 130px; text-align: center;" data-i18n="table_weight_qty">वजन / नग</th>
                        <th style="text-align: right;" data-i18n="table_amount">रक्कम</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="cartTableBody">
                    <!-- Populated dynamically by JS -->
                </tbody>
            </table>
            
            <div id="cartEmptyState" style="text-align: center; padding: 2.5rem 1rem; color: #94a3b8;">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🛍️</div>
                <p><span data-i18n="cart_empty_title">कार्टमध्ये कोणतीही वस्तू नाही.</span><br><span data-i18n="cart_empty_sub">डाव्या बाजूने वस्तू निवडा.</span></p>
            </div>
        </div>

        <!-- Cart Summary & Payment -->
        <div class="cart-summary">
            <!-- Count & Weight Summary -->
            <div class="summary-line">
                <span><span data-i18n="total_items">एकूण वस्तू:</span> <strong id="cartTotalItems">0</strong></span>
                <span><span data-i18n="total_weight">एकूण वजन:</span> <strong id="cartTotalWeight">0.00 kg</strong></span>
            </div>
            <div class="summary-line">
                <span><span data-i18n="subtotal">उप-एकूण:</span></span>
                <span><strong>₹<span id="cartSubtotal">0.00</span></strong></span>
            </div>

            <div class="summary-line" style="align-items: center;">
                <span data-i18n="discount">सवलत (Discount):</span>
                <div style="display: flex; gap: 4px; align-items: center;">
                    <select id="discountType" style="padding: 2px 4px; font-size: 0.75rem; border-radius: 4px; border: 1px solid #ccc;">
                        <option value="flat">₹ Flat</option>
                        <option value="percent">% Off</option>
                    </select>
                    <input type="number" id="discountValue" value="0" min="0" style="width: 55px; padding: 2px 4px; font-size: 0.8rem; border: 1px solid #ccc; border-radius: 4px; text-align: right;">
                </div>
            </div>

            <div class="summary-line total-line">
                <span data-i18n="grand_total">अंतिम देय रक्कम:</span>
                <span>₹<span id="cartGrandTotal">0.00</span></span>
            </div>

            <!-- Payment Modes -->
            <div class="payment-modes-grid">
                <button type="button" class="pay-mode-btn active" data-mode="CASH" data-i18n="pay_cash">💵 Cash (रोख)</button>
                <button type="button" class="pay-mode-btn" data-mode="UPI" data-i18n="pay_upi">📱 UPI QR</button>
                <button type="button" class="pay-mode-btn" data-mode="CARD" data-i18n="pay_card">💳 Card</button>
                <button type="button" class="pay-mode-btn credit-mode" data-mode="CREDIT" data-i18n="pay_credit">📝 उधारी (Credit)</button>
            </div>

            <!-- Checkout Button -->
            <button type="button" class="checkout-btn" id="btnCheckout" disabled>
                <span data-i18n="btn_checkout">⚡ बिल पूर्ण करा (F9)</span>
            </button>
        </div>

        <!-- Today's Recent Invoices History Section -->
        <div style="background: #ffffff; border-top: 2px solid var(--gray-300); padding: 0.65rem 0.8rem; max-height: 180px; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                <strong style="font-size: 0.82rem; color: var(--primary-dark);" data-i18n="recent_bills_title">📋 आजची ताजी बिले (Recent Bills)</strong>
                <a href="invoices.php" style="font-size: 0.75rem; color: var(--primary); text-decoration: none; font-weight: 600;">सर्व पहा →</a>
            </div>
            <div id="recentBillsList" style="display: flex; flex-direction: column; gap: 4px;">
                <?php if (empty($recentInvoices)): ?>
                    <div style="font-size: 0.75rem; color: var(--gray-500); text-align: center; padding: 0.5rem;" data-i18n="recent_no_bills">
                        आज अद्याप बिले तयार झालेली नाहीत.
                    </div>
                <?php else: ?>
                    <?php foreach ($recentInvoices as $rInv): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 4px 6px; background: var(--gray-100); border-radius: 4px; font-size: 0.78rem;">
                            <div>
                                <strong style="color: var(--primary-dark);"><?= htmlspecialchars($rInv['invoice_no']) ?></strong>
                                <span style="color: var(--gray-600); margin-left: 4px;"><?= htmlspecialchars($rInv['customer_name'] ?: 'ग्राहक') ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <strong style="color: var(--primary);">₹<?= number_format($rInv['grand_total'], 2) ?></strong>
                                <a href="invoice-print.php?inv=<?= urlencode($rInv['invoice_no']) ?>" target="_blank" title="Print" style="text-decoration: none; font-size: 0.85rem;">🖨️</a>
                                <a href="bill.php?inv=<?= urlencode($rInv['invoice_no']) ?>" target="_blank" title="Digital Bill" style="text-decoration: none; font-size: 0.85rem;">🌐</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<!-- Mobile Sticky Bottom Floating Cart Bar (iPhone / Mobile view) -->
<div class="mobile-floating-cart-bar" id="mobileFloatingCartBar" onclick="posCart.switchMobileTab('cart')">
    <div class="mobile-cart-bar-left">
        <span class="mobile-bar-icon">🛒</span>
        <div class="mobile-bar-details">
            <div class="mobile-bar-items"><span id="mobileBarItems">0</span> वस्तू निवडल्या</div>
            <div class="mobile-bar-total">₹<span id="mobileBarTotal">0.00</span></div>
        </div>
    </div>
    <button type="button" class="mobile-bar-action-btn">
        <span>बिल करा</span> ➔
    </button>
</div>

<!-- Modal: Checkout Success & WhatsApp / Print Actions -->
<div class="modal-overlay" id="checkoutSuccessModal">
    <div class="modal-card">
        <div class="modal-header">
            <div class="modal-title" data-i18n="modal_success_title">🎉 बिल यशस्वीरीत्या तयार झाले!</div>
            <button class="modal-close" onclick="posCart.closeSuccessModal()">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div style="font-size: 3rem; color: var(--success); margin-bottom: 0.5rem;">✓</div>
            <h3 style="font-size: 1.3rem; margin-bottom: 0.2rem;" id="modalInvoiceNo">MMP-2026-0001</h3>
            <p style="color: var(--gray-600); margin-bottom: 1.25rem;">
                <span data-i18n="modal_final_amount">अंतिम रक्कम:</span> <strong style="font-size: 1.4rem; color: var(--primary-dark);">₹<span id="modalGrandTotal">0.00</span></strong>
                (<span id="modalPaymentMode">CASH</span>)
            </p>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <!-- WhatsApp Share Button -->
                <a id="btnWhatsAppDirect" href="#" target="_blank" class="btn btn-success" style="padding: 0.85rem; font-size: 1.05rem; justify-content: center; background: #25D366; border-color: #25D366;" data-i18n="modal_whatsapp_btn">
                    📲 ग्राहकाला WhatsApp वर संपूर्ण बिल पाठवा
                </a>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <!-- Thermal Print -->
                    <button class="btn btn-primary" id="btnPrintThermal" style="justify-content: center;" data-i18n="modal_print_btn">
                        🖨️ 80mm प्रिंट
                    </button>
                    <!-- Digital Bill Web View -->
                    <a id="btnViewDigitalBill" href="#" target="_blank" class="btn btn-gold" style="justify-content: center;" data-i18n="modal_view_bill">
                        🌐 डिजिटल बिल पहा
                    </a>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="justify-content: space-between;">
            <button class="btn btn-outline" onclick="posCart.closeSuccessModal()" data-i18n="modal_close">बंद करा</button>
            <button class="btn btn-gold" onclick="posCart.resetForNextBill()" data-i18n="modal_next_bill">
                ➕ पुढील नवीन बिल (Enter)
            </button>
        </div>
    </div>
</div>

<script src="assets/js/app.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
