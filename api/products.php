<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/helpers.php';
require_once dirname(__DIR__) . '/config/firebase.php';

$pdo = get_db_connection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            $catId = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : null;
            $search = trim($_GET['search'] ?? '');
            
            $sql = "SELECT p.*, c.name as category_name, c.name_en as category_name_en 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.is_active = 1";
            $params = [];
            
            if ($catId) {
                $sql .= " AND p.category_id = ?";
                $params[] = $catId;
            }
            
            if ($search !== '') {
                $sql .= " AND (p.name LIKE ? OR p.name_en LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
                $s = "%{$search}%";
                $params[] = $s;
                $params[] = $s;
                $params[] = $s;
                $params[] = $s;
            }
            
            $sql .= " ORDER BY c.display_order ASC, p.name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll();
            
            json_response(['success' => true, 'products' => $products]);
            break;

        case 'find_by_barcode':
            $code = trim($_GET['code'] ?? '');
            if (!$code) {
                json_response(['success' => false, 'message' => 'Barcode code required'], 400);
            }
            $stmt = $pdo->prepare("SELECT p.*, c.name as category_name 
                                   FROM products p 
                                   LEFT JOIN categories c ON p.category_id = c.id 
                                   WHERE (p.barcode = ? OR p.sku = ?) AND p.is_active = 1 LIMIT 1");
            $stmt->execute([$code, $code]);
            $product = $stmt->fetch();
            if ($product) {
                json_response(['success' => true, 'product' => $product]);
            } else {
                json_response(['success' => false, 'message' => 'Product not found'], 404);
            }
            break;

        case 'update_price':
            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            $newPrice = (float)($_POST['new_price'] ?? $_GET['new_price'] ?? 0);
            if ($id <= 0 || $newPrice <= 0) {
                json_response(['success' => false, 'message' => 'अवैध उत्पादन किंवा किंमत!'], 400);
            }

            $stmt = $pdo->prepare("UPDATE products SET selling_price = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$newPrice, $id]);

            // Sync updated product to Firebase
            $stmtP = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
            $stmtP->execute([$id]);
            $updatedP = $stmtP->fetch();
            if ($updatedP) {
                @firestore_sync_product($updatedP);
            }

            json_response([
                'success' => true,
                'message' => 'दर यशस्वीरीत्या बदलला!',
                'id' => $id,
                'new_price' => $newPrice
            ]);
            break;

        case 'save':
            require_login();
            $id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : null;
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
                json_response(['success' => false, 'message' => 'उत्पादनाचे नाव आणि विक्री किंमत आवश्यक आहे.'], 400);
            }

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
                $msg = 'उत्पादन यशस्वीरीत्या अपडेट केले!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO products (
                    category_id, name, name_en, sku, barcode, unit, 
                    selling_price, mrp, purchase_cost, stock_qty, min_stock_alert, shelf_life_days
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $categoryId, $name, $name_en, $sku, $barcode, $unit,
                    $sellingPrice, $mrp, $purchaseCost, $stockQty, $minStock, $shelfLife
                ]);
                $id = $pdo->lastInsertId();
                $msg = 'नवीन उत्पादन यशस्वीरीत्या जोडले!';
            }

            json_response(['success' => true, 'message' => $msg, 'product_id' => $id]);
            break;

        case 'delete':
            require_login();
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $stmt = $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                json_response(['success' => true, 'message' => 'उत्पादन काढून टाकले!']);
            }
            json_response(['success' => false, 'message' => 'Invalid ID'], 400);
            break;

        default:
            json_response(['success' => false, 'message' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
