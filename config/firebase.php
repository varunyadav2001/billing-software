<?php
/**
 * Firebase Firestore Cloud Sync Module
 * Synchronizes Offline SQLite Records to Google Cloud Firestore in Real-Time
 */

define('FIREBASE_CONFIG', [
    'apiKey' => 'AIzaSyCGMJnvc3WapP3PwWfLcpMb6o4xPsw764A',
    'authDomain' => 'kiran-cf787.firebaseapp.com',
    'projectId' => 'kiran-cf787',
    'storageBucket' => 'kiran-cf787.firebasestorage.app',
    'messagingSenderId' => '808024977112',
    'appId' => '1:808024977112:web:2475233c3f5b066d72b1ad',
    'measurementId' => 'G-LZ55LSR3LE'
]);

/**
 * Convert PHP variable into proper Firestore typed field value
 */
function php_to_firestore_value($value) {
    if (is_null($value)) {
        return ['nullValue' => null];
    }
    if (is_bool($value)) {
        return ['booleanValue' => $value];
    }
    if (is_int($value)) {
        return ['integerValue' => (string)$value];
    }
    if (is_float($value)) {
        return ['doubleValue' => $value];
    }
    if (is_array($value)) {
        // Check if indexed array or associative array
        $isIndexed = (array_values($value) === $value);
        if ($isIndexed) {
            $arrayValues = [];
            foreach ($value as $item) {
                $arrayValues[] = php_to_firestore_value($item);
            }
            return ['arrayValue' => ['values' => $arrayValues]];
        } else {
            $fields = [];
            foreach ($value as $k => $v) {
                $fields[$k] = php_to_firestore_value($v);
            }
            return ['mapValue' => ['fields' => $fields]];
        }
    }
    return ['stringValue' => (string)$value];
}

/**
 * Upsert a document into Firestore using REST API
 */
function firestore_upsert_document(string $collection, string $documentId, array $data): array {
    $projectId = FIREBASE_CONFIG['projectId'];
    $apiKey = FIREBASE_CONFIG['apiKey'];

    // Convert flat associative array to Firestore fields structure
    $fields = [];
    foreach ($data as $key => $val) {
        $fields[$key] = php_to_firestore_value($val);
    }

    $payload = json_encode(['fields' => $fields]);
    $encodedDocId = rawurlencode($documentId);
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}/{$encodedDocId}?key={$apiKey}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4); // Quick timeout so offline checkout is never slowed down
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'response' => json_decode($response, true)];
    }

    return ['success' => false, 'http_code' => $httpCode, 'error' => $curlErr ?: $response];
}

/**
 * Sync Invoice & Its Purchased Items to Firestore collection 'invoices'
 */
function firestore_sync_invoice(array $invoice, array $items, array $settings): array {
    $formattedItems = [];
    foreach ($items as $item) {
        $formattedItems[] = [
            'product_name' => (string)($item['product_name'] ?? ''),
            'category_name' => (string)($item['category_name'] ?? 'इतर'),
            'unit' => (string)($item['unit'] ?? 'kg'),
            'unit_rate' => (float)($item['unit_rate'] ?? 0),
            'qty' => (float)($item['qty'] ?? 1),
            'weight_grams' => (float)($item['weight_grams'] ?? 0),
            'total_amount' => (float)($item['total_amount'] ?? 0)
        ];
    }

    $invoiceData = [
        'invoice_no' => (string)$invoice['invoice_no'],
        'invoice_type' => (string)($invoice['invoice_type'] ?? 'RETAIL'),
        'customer_name' => (string)($invoice['customer_name'] ?? 'ग्राहक'),
        'customer_phone' => (string)($invoice['customer_phone'] ?? ''),
        'subtotal' => (float)$invoice['subtotal'],
        'discount_amount' => (float)($invoice['discount_amount'] ?? 0),
        'tax_amount' => (float)($invoice['tax_amount'] ?? 0),
        'grand_total' => (float)$invoice['grand_total'],
        'payment_mode' => (string)$invoice['payment_mode'],
        'payment_status' => (string)$invoice['payment_status'],
        'items_count' => count($formattedItems),
        'items' => $formattedItems,
        'store_name' => (string)($settings['store_name_mr'] ?: $settings['store_name']),
        'created_at' => (string)($invoice['created_at'] ?? date('Y-m-d H:i:s')),
        'synced_at' => date('Y-m-d H:i:s')
    ];

    return firestore_upsert_document('invoices', $invoice['invoice_no'], $invoiceData);
}

/**
 * Sync Customer Details & Credit Balance to Firestore collection 'customers'
 */
function firestore_sync_customer(array $customer): array {
    $phone = preg_replace('/[^0-9]/', '', (string)$customer['phone']);
    if (!$phone) {
        $phone = 'CUST-' . ($customer['id'] ?? rand(1000, 9999));
    }

    $customerData = [
        'name' => (string)$customer['name'],
        'phone' => (string)$customer['phone'],
        'address' => (string)($customer['address'] ?? ''),
        'total_spent' => (float)($customer['total_spent'] ?? 0),
        'visit_count' => (int)($customer['visit_count'] ?? 1),
        'credit_balance' => (float)($customer['credit_balance'] ?? 0),
        'updated_at' => (string)($customer['updated_at'] ?? date('Y-m-d H:i:s')),
        'synced_at' => date('Y-m-d H:i:s')
    ];

    return firestore_upsert_document('customers', $phone, $customerData);
}

/**
 * Sync Product Catalog to Firestore collection 'products'
 */
function firestore_sync_product(array $product): array {
    $docId = (string)($product['sku'] ?: ('PROD-' . $product['id']));

    $productData = [
        'id' => (int)$product['id'],
        'name' => (string)$product['name'],
        'name_en' => (string)($product['name_en'] ?? ''),
        'category_name' => (string)($product['category_name'] ?? ''),
        'sku' => (string)$product['sku'],
        'barcode' => (string)($product['barcode'] ?? ''),
        'unit' => (string)$product['unit'],
        'selling_price' => (float)$product['selling_price'],
        'mrp' => (float)($product['mrp'] ?? $product['selling_price']),
        'purchase_cost' => (float)($product['purchase_cost'] ?? 0),
        'stock_qty' => (float)$product['stock_qty'],
        'shelf_life_days' => (int)($product['shelf_life_days'] ?? 30),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    return firestore_upsert_document('products', $docId, $productData);
}

/**
 * Full Offline SQLite to Firebase Cloud Sync
 */
function firestore_full_backup(PDO $pdo): array {
    $settings = get_store_settings($pdo);

    // 1. Sync Settings
    $settingsData = [
        'store_name' => (string)$settings['store_name'],
        'store_name_mr' => (string)$settings['store_name_mr'],
        'tagline' => (string)$settings['tagline'],
        'tagline_mr' => (string)$settings['tagline_mr'],
        'phone' => (string)$settings['phone'],
        'address' => (string)$settings['address'],
        'city_pincode' => (string)$settings['city_pincode'],
        'upi_id' => (string)($settings['upi_id'] ?? ''),
        'fssai_no' => (string)($settings['fssai_no'] ?? ''),
        'gstin' => (string)($settings['gstin'] ?? ''),
        'backup_timestamp' => date('Y-m-d H:i:s')
    ];
    firestore_upsert_document('settings', 'store_profile', $settingsData);

    // 2. Sync Products
    $stmtProds = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1");
    $prods = $stmtProds->fetchAll();
    $prodCount = 0;
    foreach ($prods as $prod) {
        $res = firestore_sync_product($prod);
        if ($res['success']) $prodCount++;
    }

    // 3. Sync Customers
    $stmtCust = $pdo->query("SELECT * FROM customers");
    $customers = $stmtCust->fetchAll();
    $custCount = 0;
    foreach ($customers as $cust) {
        $res = firestore_sync_customer($cust);
        if ($res['success']) $custCount++;
    }

    // 4. Sync Invoices
    $stmtInvs = $pdo->query("SELECT * FROM invoices ORDER BY id ASC");
    $invoices = $stmtInvs->fetchAll();
    $invCount = 0;
    foreach ($invoices as $inv) {
        $stmtItems = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
        $stmtItems->execute([$inv['id']]);
        $items = $stmtItems->fetchAll();

        $res = firestore_sync_invoice($inv, $items, $settings);
        if ($res['success']) $invCount++;
    }

    return [
        'success' => true,
        'products_synced' => $prodCount,
        'customers_synced' => $custCount,
        'invoices_synced' => $invCount,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * Fetch documents directly from Firestore collection for Cloud Viewer
 */
function firestore_list_documents(string $collection, int $pageSize = 50): array {
    $projectId = FIREBASE_CONFIG['projectId'];
    $apiKey = FIREBASE_CONFIG['apiKey'];

    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}?pageSize={$pageSize}&key={$apiKey}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $json = json_decode($res, true);
        $docs = [];
        if (!empty($json['documents'])) {
            foreach ($json['documents'] as $d) {
                $docs[] = firestore_extract_fields($d);
            }
        }
        return ['success' => true, 'documents' => $docs];
    }

    return ['success' => false, 'documents' => []];
}

/**
 * Extract Firestore typed fields into regular PHP array
 */
function firestore_extract_fields(array $rawDoc): array {
    $out = ['_name' => basename($rawDoc['name'] ?? ''), '_createTime' => $rawDoc['createTime'] ?? ''];
    if (!empty($rawDoc['fields'])) {
        foreach ($rawDoc['fields'] as $key => $typeVal) {
            if (isset($typeVal['stringValue'])) {
                $out[$key] = $typeVal['stringValue'];
            } elseif (isset($typeVal['integerValue'])) {
                $out[$key] = (int)$typeVal['integerValue'];
            } elseif (isset($typeVal['doubleValue'])) {
                $out[$key] = (float)$typeVal['doubleValue'];
            } elseif (isset($typeVal['booleanValue'])) {
                $out[$key] = (bool)$typeVal['booleanValue'];
            } elseif (isset($typeVal['arrayValue']['values'])) {
                $arr = [];
                foreach ($typeVal['arrayValue']['values'] as $subItem) {
                    if (isset($subItem['mapValue']['fields'])) {
                        $arr[] = firestore_extract_fields(['fields' => $subItem['mapValue']['fields']]);
                    } elseif (isset($subItem['stringValue'])) {
                        $arr[] = $subItem['stringValue'];
                    }
                }
                $out[$key] = $arr;
            } else {
                $out[$key] = null;
            }
        }
    }
    return $out;
}

