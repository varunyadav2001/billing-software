# Mangalmurti Products - Retail POS & Digital Billing System
> **मंगलमूर्ती प्रॉडक्ट्स - रिटेल POS आणि डिजिटल बिलिंग प्रणाली**  
> लाडू, चिवडा, फराळ आणि मिठाई किरकोळ दुकानांसाठी वेगवान, १००% ऑफलाइन आणि मोबाईल रिस्पॉन्सिव्ह बिलिंग सॉफ्टवेअर.

---

## 🌟 Key Features (मुख्य वैशिष्ट्ये)

- ⚡ **Superfast POS Billing**: Weight-based faral billing (`250g`, `500g`, `1kg`, `2kg`) with 1-click addition.
- 📱 **Mobile & iPhone Optimized**:
  - Touch-friendly 2-tab view: `📦 Products` & `🛒 Billing Cart`.
  - Sticky bottom floating bar (`🛒 X Items • ₹Total ➔ Bill Now`).
  - Native iOS Share Sheet integration (`Save to Photos` / Camera roll).
  - iOS Safari auto-zoom prevention and safe area insets support.
- 📲 **WhatsApp Bill & Digital Invoice**: Automatically sends full itemized receipt to customer's WhatsApp with digital bill link.
- 🖨️ **Dual Print Formats**: Supports 80mm thermal receipt and standard A4 tax invoice with FSSAI & GSTIN.
- 👥 **Customer & Credit (उधारी) Ledger**: Tracks market credit balances, phone lookup, and payment history.
- 📦 **Inventory & Stock Management**: Low-stock alerts, shelf-life tracker, and easy rate adjustment.
- 🌐 **Multilingual Support**: Instant toggle between Marathi (मराठी) and English.
- 💾 **100% Offline-First**: Powered by lightweight local SQLite database (zero external dependency).

---

## 🚀 Quick Start (सुरू कसे करावे)

1. **Requirements**: PHP 8.0+ with `pdo_sqlite` extension enabled.
2. **Start Local Server**:
   ```bash
   php -S 0.0.0.0:8000
   ```
3. **Open in Browser**:
   - On PC: [http://localhost:8000/billing.php](http://localhost:8000/billing.php)
   - On Mobile / iPhone (same Wi-Fi): `http://<YOUR_LOCAL_IP>:8000/billing.php`

---

## 📁 Project Structure

```
├── api/             # Backend AJAX REST APIs (checkout, products, customers)
├── assets/
│   ├── css/         # Responsive CSS & Print stylesheets
│   └── js/          # POS Cart engine, i18n translations, QR & Canvas
├── config/          # SQLite database connection, auto-migration & helpers
├── includes/        # Header, responsive mobile navigation & footer
├── storage/data/    # Local SQLite database (pos.db)
├── bill.php         # Digital customer bill receipt
├── billing.php      # Main POS billing register
├── customers.php    # Customer CRM & credit ledger
├── products.php     # Products catalog & stock
├── reports.php      # Business dashboard & sales analytics
└── settings.php     # Store settings, UPI, FSSAI & GST details
```

---

## 👤 Author
- **Varun Yadav** (GitHub: [@varunyadav2001](https://github.com/varunyadav2001))
