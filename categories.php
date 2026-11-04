<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$pdo = get_db_connection();
$settings = get_store_settings($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_category') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $name = trim($_POST['name'] ?? '');
        $name_en = trim($_POST['name_en'] ?? '');
        $order = (int)($_POST['display_order'] ?? 0);

        if (!$name) {
            $error = 'कॅटेगरीचे नाव आवश्यक आहे.';
        } else {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, name_en = ?, display_order = ? WHERE id = ?");
                $stmt->execute([$name, $name_en, $order, $id]);
                $message = 'कॅटेगरी अपडेट झाली!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, name_en, display_order) VALUES (?, ?, ?)");
                $stmt->execute([$name, $name_en, $order]);
                $message = 'नवीन कॅटेगरी जोडली!';
            }
        }
    } elseif ($_POST['action'] === 'delete_category') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE categories SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'कॅटेगरी निष्क्रिय केली.';
    }
}

// Fetch categories with product count
$stmtCats = $pdo->query("SELECT c.*, COUNT(p.id) as product_count 
                          FROM categories c 
                          LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1 
                          WHERE c.is_active = 1 
                          GROUP BY c.id 
                          ORDER BY c.display_order ASC");
$categories = $stmtCats->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
    <div>
        <h2 style="font-size: 1.35rem; color: var(--primary-dark); font-weight: 700;">📂 कॅटेगरीज व्यवस्थापन</h2>
        <p style="font-size: 0.85rem; color: var(--gray-600);">लाडू, चिवडा, फराळ, मिठाई व गिफ्ट बॉक्स विभागांचे नियोजन</p>
    </div>
    <button class="btn btn-gold" onclick="openCatModal()">
        ➕ नवीन कॅटेगरी जोडा
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

<div class="card">
    <div class="card-body" style="padding: 0;">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 10%;">क्रम</th>
                    <th>कॅटेगरीचे नाव (मराठी)</th>
                    <th>इंग्रजी नाव</th>
                    <th style="text-align: center;">एकूण उत्पादने</th>
                    <th style="text-align: right;">क्रिया</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><strong>#<?= $cat['display_order'] ?></strong></td>
                        <td>
                            <strong style="font-size: 1rem; color: var(--primary);"><?= htmlspecialchars($cat['name']) ?></strong>
                        </td>
                        <td style="color: #64748b;">
                            <?= htmlspecialchars($cat['name_en'] ?: '-') ?>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge badge-primary"><?= $cat['product_count'] ?> उत्पादने</span>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <button class="btn btn-outline" style="padding: 3px 8px; font-size: 0.8rem;" onclick='editCategory(<?= json_encode($cat) ?>)'>
                                ✏️ बदला
                            </button>
                            <?php if ($cat['product_count'] == 0): ?>
                                <form method="POST" style="display: inline-block;" onsubmit="return confirm('ही कॅटेगरी नक्की काढायची आहे का?')">
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                    <button type="submit" class="btn btn-danger" style="padding: 3px 8px; font-size: 0.8rem;">
                                        🗑️
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add / Edit Category -->
<div class="modal-overlay" id="catModal">
    <div class="modal-card" style="max-width: 480px;">
        <div class="modal-header">
            <div class="modal-title" id="catModalTitle">नवीन कॅटेगरी जोडा</div>
            <button class="modal-close" onclick="closeCatModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="id" id="catId">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">कॅटेगरीचे नाव (मराठी) *</label>
                    <input type="text" name="name" id="catName" class="form-control" placeholder="उदा. चिवडा विशेष / लाडू विशेष" required>
                </div>

                <div class="form-group">
                    <label class="form-label">इंग्रजी नाव</label>
                    <input type="text" name="name_en" id="catNameEn" class="form-control" placeholder="उदा. Chivda Specials">
                </div>

                <div class="form-group">
                    <label class="form-label">डिस्प्ले क्रम (Display Order)</label>
                    <input type="number" name="display_order" id="catOrder" class="form-control" value="1">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeCatModal()">रद्द करा</button>
                <button type="submit" class="btn btn-gold">साठवा</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCatModal() {
    document.getElementById('catModalTitle').textContent = 'नवीन कॅटेगरी जोडा';
    document.getElementById('catId').value = '';
    document.getElementById('catName').value = '';
    document.getElementById('catNameEn').value = '';
    document.getElementById('catOrder').value = '1';
    document.getElementById('catModal').classList.add('open');
}

function editCategory(cat) {
    document.getElementById('catModalTitle').textContent = 'कॅटेगरी बदला';
    document.getElementById('catId').value = cat.id;
    document.getElementById('catName').value = cat.name;
    document.getElementById('catNameEn').value = cat.name_en || '';
    document.getElementById('catOrder').value = cat.display_order;
    document.getElementById('catModal').classList.add('open');
}

function closeCatModal() {
    document.getElementById('catModal').classList.remove('open');
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
