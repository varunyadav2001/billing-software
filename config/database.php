<?php
/**
 * Database Connection & Auto-Migration for POS
 * Uses Local SQLite for 100% offline-first capability
 */

define('DB_FILE', dirname(__DIR__) . '/storage/data/pos.db');

function get_db_connection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dbDir = dirname(DB_FILE);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        
        $isNew = !file_exists(DB_FILE);
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // SQLite performance optimizations
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA synchronous = NORMAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');
        
        if ($isNew || filesize(DB_FILE) === 0) {
            init_database_schema($pdo);
            seed_initial_data($pdo);
        }
    }
    return $pdo;
}

function init_database_schema(PDO $pdo): void {
    // 1. Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        store_name TEXT NOT NULL,
        store_name_mr TEXT NOT NULL,
        tagline TEXT,
        tagline_mr TEXT,
        address TEXT,
        city_pincode TEXT,
        phone TEXT NOT NULL,
        alt_phone TEXT,
        email TEXT,
        gstin TEXT,
        fssai_no TEXT,
        upi_id TEXT,
        upi_name TEXT,
        currency_symbol TEXT DEFAULT '₹',
        bill_prefix TEXT DEFAULT 'FARAL',
        return_policy TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // 2. Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT DEFAULT 'admin',
        pin TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // 3. Categories Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        name_en TEXT,
        icon TEXT DEFAULT 'fa-cookie',
        display_order INTEGER DEFAULT 0,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // 4. Products Table (Weight/Unit based for Ladu, Chivda, Faral, Sweets)
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER,
        name TEXT NOT NULL,
        name_en TEXT,
        sku TEXT UNIQUE,
        barcode TEXT,
        unit TEXT DEFAULT 'kg', -- 'kg', 'g', 'pkt', 'box', 'piece'
        base_weight_grams REAL DEFAULT 1000, -- e.g. 1000 for kg
        selling_price REAL NOT NULL, -- price per unit (e.g. per 1 kg or per 1 pkt)
        mrp REAL,
        purchase_cost REAL DEFAULT 0,
        tax_percent REAL DEFAULT 0,
        stock_qty REAL DEFAULT 100, -- in units (e.g. 25.5 kg)
        min_stock_alert REAL DEFAULT 5,
        shelf_life_days INTEGER DEFAULT 30,
        is_active INTEGER DEFAULT 1,
        image_url TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    );");

    // 5. Customers Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        phone TEXT UNIQUE NOT NULL,
        address TEXT,
        total_spent REAL DEFAULT 0,
        visit_count INTEGER DEFAULT 0,
        credit_balance REAL DEFAULT 0, -- उधारी रक्कम
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // 6. Invoices Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        invoice_no TEXT UNIQUE NOT NULL,
        invoice_type TEXT DEFAULT 'RETAIL', -- 'RETAIL' or 'TAX_GST'
        customer_id INTEGER,
        customer_name TEXT,
        customer_phone TEXT,
        subtotal REAL NOT NULL,
        discount_type TEXT DEFAULT 'flat', -- 'percent' or 'flat'
        discount_value REAL DEFAULT 0,
        discount_amount REAL DEFAULT 0,
        tax_amount REAL DEFAULT 0,
        grand_total REAL NOT NULL,
        payment_mode TEXT DEFAULT 'CASH', -- 'CASH', 'UPI', 'CARD', 'CREDIT'
        payment_status TEXT DEFAULT 'PAID', -- 'PAID', 'PENDING', 'PARTIAL'
        paid_amount REAL DEFAULT 0,
        change_due REAL DEFAULT 0,
        notes TEXT,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
    );");

    // 7. Invoice Items Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        invoice_id INTEGER NOT NULL,
        product_id INTEGER,
        product_name TEXT NOT NULL,
        category_name TEXT,
        unit TEXT DEFAULT 'kg',
        weight_grams REAL DEFAULT 0,
        unit_rate REAL NOT NULL,
        qty REAL NOT NULL, -- e.g. 0.5 for 500g, 1.25 for 1.25kg, 2 for 2 pkts
        discount REAL DEFAULT 0,
        tax_amount REAL DEFAULT 0,
        total_amount REAL NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
    );");

    // 8. Inventory Movements Ledger
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_movements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        type TEXT NOT NULL, -- 'SALE', 'PURCHASE', 'ADJUSTMENT', 'RETURN'
        quantity_change REAL NOT NULL,
        previous_stock REAL NOT NULL,
        new_stock REAL NOT NULL,
        reference_id TEXT,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    );");

    // 9. Customer Ledger (उधारी व जमा)
    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_ledger (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id INTEGER NOT NULL,
        type TEXT NOT NULL, -- 'CREDIT_SALE', 'PAYMENT_RECEIVED'
        amount REAL NOT NULL,
        balance_after REAL NOT NULL,
        invoice_id INTEGER,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
    );");

    // 10. Concurrency Sequence Tracking
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_sequences (
        year INTEGER PRIMARY KEY,
        last_seq INTEGER DEFAULT 0
    );");
}

function seed_initial_data(PDO $pdo): void {
    // 1. Default Store Settings
    $stmt = $pdo->prepare("INSERT INTO settings (
        store_name, store_name_mr, tagline, tagline_mr, address, city_pincode, phone, alt_phone,
        email, gstin, fssai_no, upi_id, upi_name, currency_symbol, bill_prefix, return_policy
    ) VALUES (
        :store_name, :store_name_mr, :tagline, :tagline_mr, :address, :city_pincode, :phone, :alt_phone,
        :email, :gstin, :fssai_no, :upi_id, :upi_name, :currency_symbol, :bill_prefix, :return_policy
    )");
    
    $stmt->execute([
        ':store_name' => 'Mangalmurti Products',
        ':store_name_mr' => 'मंगलमूर्ती प्रॉडक्ट्स',
        ':tagline' => "Authentic Taste, Purity & Traditional Faral",
        ':tagline_mr' => 'उत्कृष्ट चव, शुद्धता आणि पारंपरिक फराळ',
        ':address' => 'मुख्य बाजारपेठ, मारुती चौकाजवळ',
        ':city_pincode' => 'तासगाव (महाराष्ट्र - ४१६३१२)',
        ':phone' => '+91 98765 43210',
        ':alt_phone' => '+91 91234 56789',
        ':email' => 'contact@mangalmurti.com',
        ':gstin' => '27ABCDE1234F1Z5',
        ':fssai_no' => '11524036000123',
        ':upi_id' => '9876543210@upi',
        ':upi_name' => 'Mangalmurti Products',
        ':currency_symbol' => '₹',
        ':bill_prefix' => 'MMP',
        ':return_policy' => 'खाद्यपदार्थांची गुणवत्ता आमची प्राथमिकता आहे. खरेदीनंतर पॅकिंग सीलबंद असल्यास २४ तासांत संपर्क साधावा.'
    ]);

    // 2. Default Admin User
    $pwdHash = password_hash('password123', PASSWORD_DEFAULT);
    $stmtUser = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, pin) VALUES (?, ?, ?, 'admin', '1234')");
    $stmtUser->execute(['Admin', 'admin@yourstore.com', $pwdHash]);

    // 3. Pre-populate Categories (Laddu, Chivda, Shev)
    $categories = [
        ['name' => 'लाडू', 'name_en' => 'Laddu', 'icon' => 'fa-certificate', 'order' => 1],
        ['name' => 'चिवडा', 'name_en' => 'Chivda', 'icon' => 'fa-bowl-rice', 'order' => 2],
        ['name' => 'शेव', 'name_en' => 'Shev', 'icon' => 'fa-utensils', 'order' => 3],
    ];

    $catIds = [];
    $stmtCat = $pdo->prepare("INSERT INTO categories (name, name_en, icon, display_order) VALUES (?, ?, ?, ?)");
    foreach ($categories as $cat) {
        $stmtCat->execute([$cat['name'], $cat['name_en'], $cat['icon'], $cat['order']]);
        $catIds[$cat['name']] = $pdo->lastInsertId();
    }

    // 4. Products Pre-populated (Only 3 Items: Laddu, Chivda, Shev)
    $products = [
        [
            'cat' => 'लाडू',
            'name' => 'लाडू',
            'name_en' => 'Laddu',
            'sku' => 'MMP-LADDU-1KG',
            'barcode' => '8901001',
            'unit' => 'kg',
            'price' => 220,
            'mrp' => 240,
            'cost' => 160,
            'stock' => 100,
            'shelf' => 30
        ],
        [
            'cat' => 'चिवडा',
            'name' => 'चिवडा',
            'name_en' => 'Chivda',
            'sku' => 'MMP-CHIVDA-1KG',
            'barcode' => '8901002',
            'unit' => 'kg',
            'price' => 80,
            'mrp' => 90,
            'cost' => 55,
            'stock' => 100,
            'shelf' => 45
        ],
        [
            'cat' => 'शेव',
            'name' => 'शेव',
            'name_en' => 'Shev',
            'sku' => 'MMP-SHEV-1KG',
            'barcode' => '8901003',
            'unit' => 'kg',
            'price' => 150,
            'mrp' => 160,
            'cost' => 100,
            'stock' => 100,
            'shelf' => 45
        ],
    ];

    $stmtProd = $pdo->prepare("INSERT INTO products (
        category_id, name, name_en, sku, barcode, unit, selling_price, mrp, purchase_cost, stock_qty, shelf_life_days
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($products as $p) {
        $cId = $catIds[$p['cat']] ?? null;
        $stmtProd->execute([
            $cId,
            $p['name'],
            $p['name_en'],
            $p['sku'],
            $p['barcode'],
            $p['unit'],
            $p['price'],
            $p['mrp'],
            $p['cost'],
            $p['stock'],
            $p['shelf']
        ]);
    }

    // 5. Sample Customer for testing
    $stmtCust = $pdo->prepare("INSERT INTO customers (name, phone, address, total_spent, visit_count, credit_balance) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtCust->execute(['सचिन पाटील', '9822012345', 'तासगाव, सांगली', 1250, 2, 0]);
    $stmtCust->execute(['आनंद जोशी', '9423054321', 'मिरज रोड, तासगाव', 850, 1, 200]);
}
