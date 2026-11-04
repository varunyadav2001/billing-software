/**
 * Multilingual Translation Engine (Marathi & English)
 * For Mangalmurti Products Retail POS
 */

const translations = {
    // Header & Navigation
    'brand_name': { mr: 'मंगलमूर्ती प्रॉडक्ट्स', en: 'Mangalmurti Products' },
    'brand_tagline': { mr: 'उत्कृष्ट चव, शुद्धता आणि पारंपरिक फराळ', en: 'Authentic Taste, Purity & Traditional Faral' },
    'nav_pos': { mr: '⚡ POS बिलिंग', en: '⚡ POS Billing' },
    'nav_products': { mr: '📦 उत्पादने व स्टॉक', en: '📦 Products & Stock' },
    'nav_categories': { mr: '📂 कॅटेगरीज', en: '📂 Categories' },
    'nav_invoices': { mr: '🧾 बिले इतिहास', en: '🧾 Invoices History' },
    'nav_customers': { mr: '👥 ग्राहक व उधारी', en: '👥 Customers & Credit' },
    'nav_reports': { mr: '📊 रिपोर्ट्स', en: '📊 Reports' },
    'nav_settings': { mr: '⚙️ सेटिंग्ज', en: '⚙️ Settings' },
    'today_sales_badge': { mr: 'आजची विक्री:', en: "Today's Sales:" },
    'login': { mr: 'लॉगिन', en: 'Login' },
    'logout': { mr: 'लॉगआउट', en: 'Logout' },

    // POS Billing
    'mobile_tab_products': { mr: 'उत्पादने', en: 'Products' },
    'mobile_tab_cart': { mr: 'बिलिंग कार्ट', en: 'Billing Cart' },
    'search_ph': { mr: 'शोधा: लाडू, चिवडा, चकली, फराळ... (Hotkey: /)', en: 'Search: Ladu, Chivda, Chakli, Faral... (Hotkey: /)' },
    'cat_all': { mr: 'सर्व उत्पादने (All)', en: 'All Products' },
    'cart_title': { mr: '🛒 बिलिंग कार्ट', en: '🛒 Billing Cart' },
    'cart_clear': { mr: 'रिकामा करा', en: 'Clear Cart' },
    'cust_phone_ph': { mr: 'ग्राहकाचा फोन (१० अंक)', en: 'Customer Phone (10 digits)' },
    'cust_name_ph': { mr: 'ग्राहकाचे नाव', en: 'Customer Name' },
    'credit_warning': { mr: '⚠️ मागील उधारी शिल्लक: ₹', en: '⚠️ Previous Credit Due: ₹' },
    'table_item': { mr: 'वस्तू', en: 'Item' },
    'table_weight_qty': { mr: 'वजन / नग', en: 'Weight / Qty' },
    'table_amount': { mr: 'रक्कम', en: 'Amount' },
    'cart_empty_title': { mr: 'कार्टमध्ये कोणतीही वस्तू नाही.', en: 'No items in cart.' },
    'cart_empty_sub': { mr: 'डाव्या बाजूने वस्तू निवडा.', en: 'Select products from the left.' },
    'total_items': { mr: 'एकूण वस्तू:', en: 'Total Items:' },
    'total_weight': { mr: 'एकूण वजन:', en: 'Total Weight:' },
    'subtotal': { mr: 'उप-एकूण:', en: 'Subtotal:' },
    'discount': { mr: 'सवलत (Discount):', en: 'Discount:' },
    'grand_total': { mr: 'अंतिम देय रक्कम:', en: 'Grand Total:' },
    'btn_checkout': { mr: '⚡ बिल पूर्ण करा (F9)', en: '⚡ Complete Bill (F9)' },
    'btn_checkout_processing': { mr: 'प्रक्रिया सुरू आहे...', en: 'Processing...' },
    'pay_cash': { mr: '💵 Cash (रोख)', en: '💵 Cash' },
    'pay_upi': { mr: '📱 UPI QR', en: '📱 UPI QR' },
    'pay_card': { mr: '💳 Card', en: '💳 Card' },
    'pay_credit': { mr: '📝 उधारी (Credit)', en: '📝 Credit' },

    // Recent Invoices section in POS
    'recent_bills_title': { mr: '📋 आजची ताजी बिले (Recent Bills)', en: "📋 Today's Recent Bills" },
    'recent_no_bills': { mr: 'आज अद्याप बिले तयार झालेली नाहीत.', en: 'No bills created yet today.' },

    // Success Modal
    'modal_success_title': { mr: '🎉 बिल यशस्वीरीत्या तयार झाले!', en: '🎉 Bill Generated Successfully!' },
    'modal_final_amount': { mr: 'अंतिम रक्कम:', en: 'Grand Total:' },
    'modal_whatsapp_btn': { mr: '📲 ग्राहकाला WhatsApp वर संपूर्ण बिल पाठवा', en: '📲 Send Full Bill on WhatsApp' },
    'modal_print_btn': { mr: '🖨️ 80mm प्रिंट', en: '🖨️ 80mm Print' },
    'modal_view_bill': { mr: '🌐 डिजिटल बिल पहा', en: '🌐 View Digital Bill' },
    'modal_close': { mr: 'बंद करा', en: 'Close' },
    'modal_next_bill': { mr: '➕ पुढील नवीन बिल (Enter)', en: '➕ Next New Bill (Enter)' },

    // Categories
    'cat_ladu': { mr: 'लाडू', en: 'Laddu' },
    'cat_chivda': { mr: 'चिवडा', en: 'Chivda' },
    'cat_shev': { mr: 'शेव', en: 'Shev' }
};

class PosI18n {
    constructor() {
        this.currentLang = localStorage.getItem('pos_language') || 'mr';
        this.init();
    }

    init() {
        this.applyLanguage(this.currentLang);
        
        // Listen to toggle clicks
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('#btnLangToggle');
            if (btn) {
                const nextLang = (this.currentLang === 'mr') ? 'en' : 'mr';
                this.setLanguage(nextLang);
            }
        });
    }

    setLanguage(lang) {
        this.currentLang = lang;
        localStorage.setItem('pos_language', lang);
        this.applyLanguage(lang);
    }

    applyLanguage(lang) {
        // 1. Update text elements with data-i18n
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            if (translations[key] && translations[key][lang]) {
                el.textContent = translations[key][lang];
            }
        });

        // 2. Update placeholder attributes with data-i18n-ph
        document.querySelectorAll('[data-i18n-ph]').forEach(el => {
            const key = el.getAttribute('data-i18n-ph');
            if (translations[key] && translations[key][lang]) {
                el.placeholder = translations[key][lang];
            }
        });

        // 3. Update Language Switcher Button Text
        const toggleBtn = document.getElementById('btnLangToggle');
        if (toggleBtn) {
            toggleBtn.innerHTML = (lang === 'mr') 
                ? '🌐 <strong>मराठी</strong> / EN' 
                : '🌐 MR / <strong>English</strong>';
        }

        // 4. Update Product Cards Titles on Billing Screen
        document.querySelectorAll('.product-card').forEach(card => {
            const titleEl = card.querySelector('.prod-title');
            const subEl = card.querySelector('.prod-sub');
            const nameMr = card.getAttribute('data-name-mr');
            const nameEn = card.getAttribute('data-name-en');

            if (titleEl && nameMr) {
                if (lang === 'en' && nameEn) {
                    titleEl.textContent = nameEn;
                    if (subEl) subEl.textContent = nameMr;
                } else {
                    titleEl.textContent = nameMr;
                    if (subEl) subEl.textContent = nameEn || '';
                }
            }
        });

        // 5. Update HTML lang attribute
        document.documentElement.lang = lang;
    }
}

// Global initialization
window.posI18n = null;
document.addEventListener('DOMContentLoaded', () => {
    window.posI18n = new PosI18n();
});
