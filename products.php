<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = get_db_connection();
$settings = get_store_settings($pdo);

// Handle form submission for add/edit product
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_product') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $name = trim($_POST['name'] ?? '');
        $name_en = trim($_POST['name_en'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'kg');
        $sellingPrice = (float)($_POST['selling_price'] ?? 0);
        $mrp = (float)($_POST['mrp'] ?? $sellingPrice);
        $purchaseCost = (float)($_POST['purchase_cost'] ?? 0);
        $stockQty = (float)($_POST['stock_qty'] ?? 0);
        $minStock = (float)($_POST['min_stock_alert'] ?? 5);
        $shelfLife = (int)($_POST['shelf_life_days'] ?? 30);
        $sku = trim($_POST['sku'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');

        if (!$name || $sellingPrice <= 0) {
            $error = 'उत्पादनाचे नाव आणि विक्री दर आवश्यक आहे.';
        } else {
            if (!$sku) {
                $sku = 'FRL-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name_en ?: $name), 0, 4)) . '-' . rand(100, 999);
            }
            if (!$barcode) {
                $barcode = (string)rand(8900000, 8999999);
            }

            if ($id) {
                $stmt = $pdo->prepare("UPDATE products SET 
                    category_id = ?, name = ?, name_en = ?, sku = ?, barcode = ?, 
                    unit = ?, selling_price = ?, mrp = ?, purchase_cost = ?, 
                    stock_qty = ?, min_stock_alert = ?, shelf_life_days = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?");
                $stmt->execute([
                    $categoryId, $name, $name_en, $sku, $barcode,
                    $unit, $sellingPrice, $mrp, $purchaseCost,
                    $stockQty, $minStock, $shelfLife, $id
                ]);
                $message = 'उत्पादन यशस्वीरीत्या अपडेट केले!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO products (
                    category_id, name, name_en, sku, barcode, unit, 
                    selling_price, mrp, purchase_cost, stock_qty, min_stock_alert, shelf_life_days
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $categoryId, $name, $name_en, $sku, $barcode, $unit,
                    $sellingPrice, $mrp, $purchaseCost, $stockQty, $minStock, $shelfLife
                ]);
                $message = 'नवीन उत्पादन यशस्वीरीत्या जोडले!';
            }
        }
    } elseif ($_POST['action'] === 'delete_product') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'उत्पादन काढून टाकण्यात आले.';
    }
}

// Fetch categories
$stmtCats = $pdo->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order ASC");
$categories = $stmtCats->fetchAll();

// Fetch products with filter
$catFilter = isset($_GET['cat']) && is_numeric($_GET['cat']) ? (int)$_GET['cat'] : null;
$search = trim($_GET['search'] ?? '');

$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1";
$params = [];

if ($catFilter) {
    $sql .= " AND p.category_id = ?";
    $params[] = $catFilter;
}
if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.name_en LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
    $s = "%{$search}%";
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
}

$sql .= " ORDER BY c.display_order ASC, p.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
    <div>
        <h2 style="font-size: 1.35rem; color: var(--primary-dark); font-weight: 700;">📦 उत्पादने व स्टॉक व्यवस्थापन</h2>
        <p style="font-size: 0.85rem; color: var(--gray-600);">लाडू, चिवडा, फराळ व मिठाई यांचे दर, वजन आणि शिल्लक साठा</p>
    </div>
    <button class="btn btn-gold" onclick="openProductModal()">
        ➕ नवीन पदार्थ / उत्पादन जोडा
    </button>
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

<!-- Filters -->
<div class="card" style="margin-bottom: 1rem;">
    <div class="card-body" style="padding: 0.75rem 1rem;">
        <form method="GET" style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <div style="flex: 2; min-width: 200px;">
                <input type="text" name="search" class="form-control" placeholder="नावाने, SKU ने किंवा बारकोडने शोधा..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div style="flex: 1; min-width: 150px;">
                <select name="cat" class="form-control" onchange="this.form.submit()">
                    <option value="">-- सर्व कॅटेगरीज --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($catFilter == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 शोधा</button>
            <?php if ($search || $catFilter): ?>
                <a href="products.php" class="btn btn-outline">रद्द करा</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-body" style="padding: 0; overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>कॅटेगरी</th>
                    <th>उत्पादनाचे नाव</th>
                    <th>बारकोड / SKU</th>
                    <th>परिमाण</th>
                    <th style="text-align: right;">विक्री दर (Selling Rate)</th>
                    <th style="text-align: right;">खरेदी खर्च</th>
                    <th style="text-align: center;">शिल्लक स्टॉक</th>
                    <th style="text-align: center;">शेल्फ लाईफ</th>
                    <th style="text-align: right;">क्रिया</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 2rem; color: #94a3b8;">
                            कोणतेही उत्पादन सापडले नाही.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): 
                        $isLow = ($p['stock_qty'] <= $p['min_stock_alert']);
                    ?>
                        <tr>
                            <td>
                                <span class="badge badge-primary"><?= htmlspecialchars($p['category_name']) ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($p['name']) ?></strong>
                                <?php if ($p['name_en']): ?>
                                    <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($p['name_en']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code style="font-size: 0.8rem; background: #f1f5f9; padding: 2px 5px; border-radius: 4px;"><?= htmlspecialchars($p['barcode'] ?: $p['sku']) ?></code>
                            </td>
                            <td>
                                <?= htmlspecialchars($p['unit'] === 'kg' ? 'किलो (kg)' : $p['unit']) ?>
                            </td>
                            <td style="text-align: right; font-weight: bold; color: var(--primary);">
                                ₹<?= number_format($p['selling_price'], 2) ?>
                            </td>
                            <td style="text-align: right; color: #64748b;">
                                ₹<?= number_format($p['purchase_cost'], 2) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge <?= $isLow ? 'badge-danger' : 'badge-success' ?>">
                                    <?= ($p['unit'] === 'kg') ? number_format($p['stock_qty'], 2) . ' kg' : number_format($p['stock_qty'], 0) . ' ' . $p['unit'] ?>
                                </span>
                            </td>
                            <td style="text-align: center; color: #64748b;">
                                <?= $p['shelf_life_days'] ?> दिवस
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <button class="btn btn-outline" style="padding: 3px 8px; font-size: 0.8rem;" onclick='editProduct(<?= json_encode($p) ?>)'>
                                    ✏️ बदला
                                </button>
                                <form method="POST" style="display: inline-block;" onsubmit="return confirm('हे उत्पादन नक्की काढून टाकायचे आहे का?')">
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-danger" style="padding: 3px 8px; font-size: 0.8rem;">
                                        🗑️
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add / Edit Product -->
<div class="modal-overlay" id="productModal">
    <div class="modal-card" style="max-width: 650px;">
        <div class="modal-header">
            <div class="modal-title" id="modalProductTitle">नवीन पदार्थ / उत्पादन जोडा</div>
            <button class="modal-close" onclick="closeProductModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save_product">
            <input type="hidden" name="id" id="prodId">

            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">पदार्थाचे नाव (मराठी) *</label>
                        <input type="text" name="name" id="prodName" class="form-control" placeholder="उदा. शुद्ध तुपातील बेसन लाडू / कुरकुरीत चकली" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">नाव (इंग्रजी)</label>
                        <input type="text" name="name_en" id="prodNameEn" class="form-control" placeholder="उदा. Pure Ghee Besan Laddu">
                    </div>

                    <div class="form-group">
                        <label class="form-label">कॅटेगरी *</label>
                        <select name="category_id" id="prodCatId" class="form-control" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">परिमाण (Unit) *</label>
                        <select name="unit" id="prodUnit" class="form-control">
                            <option value="kg">किलो (kg)</option>
                            <option value="box">डबा / बॉक्स (box)</option>
                            <option value="pkt">पॅकेट (pkt)</option>
                            <option value="piece">नग (piece)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">विक्री दर (प्रति एकक) ₹ *</label>
                        <input type="number" step="0.01" name="selling_price" id="prodSellingPrice" class="form-control" placeholder="उदा. 420.00" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">MRP ₹</label>
                        <input type="number" step="0.01" name="mrp" id="prodMrp" class="form-control" placeholder="उदा. 450.00">
                    </div>

                    <div class="form-group">
                        <label class="form-label">खरेदी / तयार खर्च ₹</label>
                        <input type="number" step="0.01" name="purchase_cost" id="prodPurchaseCost" class="form-control" placeholder="उदा. 280.00">
                    </div>

                    <div class="form-group">
                        <label class="form-label">सध्याचा शिल्लक साठा</label>
                        <input type="number" step="0.01" name="stock_qty" id="prodStockQty" class="form-control" value="50">
                    </div>

                    <div class="form-group">
                        <label class="form-label">कमी स्टॉक अलर्ट मर्यादा</label>
                        <input type="number" step="0.01" name="min_stock_alert" id="prodMinStock" class="form-control" value="5">
                    </div>

                    <div class="form-group">
                        <label class="form-label">शेल्फ लाईफ (दिवस)</label>
                        <input type="number" name="shelf_life_days" id="prodShelfLife" class="form-control" value="30">
                    </div>

                    <div class="form-group">
                        <label class="form-label">बारकोड (Barcode)</label>
                        <input type="text" name="barcode" id="prodBarcode" class="form-control" placeholder="स्कॅन करा किंवा रिकामा ठेवा">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeProductModal()">रद्द करा</button>
                <button type="submit" class="btn btn-gold">साठवा (Save)</button>
            </div>
        </form>
    </div>
</div>

<script>
function openProductModal() {
    document.getElementById('modalProductTitle').textContent = 'नवीन पदार्थ / उत्पादन जोडा';
    document.getElementById('prodId').value = '';
    document.getElementById('prodName').value = '';
    document.getElementById('prodNameEn').value = '';
    document.getElementById('prodSellingPrice').value = '';
    document.getElementById('prodMrp').value = '';
    document.getElementById('prodPurchaseCost').value = '';
    document.getElementById('prodStockQty').value = '50';
    document.getElementById('prodMinStock').value = '5';
    document.getElementById('prodShelfLife').value = '30';
    document.getElementById('prodBarcode').value = '';
    document.getElementById('productModal').classList.add('open');
}

function editProduct(p) {
    document.getElementById('modalProductTitle').textContent = 'उत्पादन बदला - ' + p.name;
    document.getElementById('prodId').value = p.id;
    document.getElementById('prodName').value = p.name;
    document.getElementById('prodNameEn').value = p.name_en || '';
    document.getElementById('prodCatId').value = p.category_id;
    document.getElementById('prodUnit').value = p.unit;
    document.getElementById('prodSellingPrice').value = p.selling_price;
    document.getElementById('prodMrp').value = p.mrp || p.selling_price;
    document.getElementById('prodPurchaseCost').value = p.purchase_cost || 0;
    document.getElementById('prodStockQty').value = p.stock_qty;
    document.getElementById('prodMinStock').value = p.min_stock_alert;
    document.getElementById('prodShelfLife').value = p.shelf_life_days || 30;
    document.getElementById('prodBarcode').value = p.barcode || '';
    document.getElementById('productModal').classList.add('open');
}

function closeProductModal() {
    document.getElementById('productModal').classList.remove('open');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
