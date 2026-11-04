/**
 * POS Billing Application Logic
 * Supports Fast Weight-Based Retail Billing for Ladu, Chivda & Faral
 */

class PosBillingCart {
    constructor() {
        this.cart = [];
        this.paymentMode = 'CASH';
        this.currentCustomer = null;
        this.lastInvoice = null;

        this.initDOMElements();
        this.bindEvents();
    }

    initDOMElements() {
        this.searchBar = document.getElementById('posSearchInput');
        this.grid = document.getElementById('productGrid');
        this.catPills = document.querySelectorAll('.category-pill');
        this.cartTableBody = document.getElementById('cartTableBody');
        this.cartEmptyState = document.getElementById('cartEmptyState');
        this.totalItemsEl = document.getElementById('cartTotalItems');
        this.totalWeightEl = document.getElementById('cartTotalWeight');
        this.subtotalEl = document.getElementById('cartSubtotal');
        this.grandTotalEl = document.getElementById('cartGrandTotal');
        this.discountType = document.getElementById('discountType');
        this.discountVal = document.getElementById('discountValue');
        this.btnCheckout = document.getElementById('btnCheckout');
        this.btnClearCart = document.getElementById('btnClearCart');
        this.custPhone = document.getElementById('custPhone');
        this.custName = document.getElementById('custName');
        this.custCreditAlert = document.getElementById('custCreditAlert');
        this.custCreditAmt = document.getElementById('custCreditAmt');
        this.successModal = document.getElementById('checkoutSuccessModal');
        this.recentBillsList = document.getElementById('recentBillsList');

        // Mobile responsive elements
        this.posLayout = document.getElementById('posLayout');
        this.tabBtnProducts = document.getElementById('tabBtnProducts');
        this.tabBtnCart = document.getElementById('tabBtnCart');
        this.mobileCartBadge = document.getElementById('mobileCartBadge');
        this.mobileCartHeaderTotal = document.getElementById('mobileCartHeaderTotal');
        this.mobileFloatingCartBar = document.getElementById('mobileFloatingCartBar');
        this.mobileBarItems = document.getElementById('mobileBarItems');
        this.mobileBarTotal = document.getElementById('mobileBarTotal');
        this.currentMobileTab = 'products';
    }

    bindEvents() {
        // Search Filter
        if (this.searchBar) {
            this.searchBar.addEventListener('input', (e) => this.filterProducts(e.target.value));
        }

        // Category Pills Filter
        if (this.catPills) {
            this.catPills.forEach(pill => {
                pill.addEventListener('click', () => {
                    this.catPills.forEach(p => p.classList.remove('active'));
                    pill.classList.add('active');
                    this.filterProducts(this.searchBar ? this.searchBar.value : '');
                });
            });
        }

        // Product Card Click -> Default 1 unit or 0.5kg
        if (this.grid) {
            this.grid.addEventListener('click', (e) => {
                const card = e.target.closest('.product-card');
                if (card && !card.classList.contains('out-of-stock')) {
                    const id = parseInt(card.dataset.id);
                    const unit = card.dataset.unit;
                    const defaultQty = (unit === 'kg') ? 0.5 : 1;
                    this.addItem(id, defaultQty);
                }
            });
        }

        // Discount Changes
        if (this.discountType) this.discountType.addEventListener('change', () => this.renderCart());
        if (this.discountVal) this.discountVal.addEventListener('input', () => this.renderCart());

        // Clear Cart
        if (this.btnClearCart) {
            this.btnClearCart.addEventListener('click', () => {
                if (this.cart.length > 0 && confirm('संपूर्ण कार्ट रिकामा करायचा आहे का?')) {
                    this.clearCart();
                }
            });
        }

        // Payment Mode Buttons
        const payBtns = document.querySelectorAll('.pay-mode-btn');
        payBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                payBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.paymentMode = btn.dataset.mode;
            });
        });

        // Customer Lookup on 10 Digits
        if (this.custPhone) {
            this.custPhone.addEventListener('input', (e) => {
                const phone = e.target.value.replace(/[^0-9]/g, '');
                if (phone.length === 10) {
                    this.lookupCustomer(phone);
                } else {
                    if (this.custCreditAlert) this.custCreditAlert.style.display = 'none';
                }
            });
        }

        // Checkout Button
        if (this.btnCheckout) {
            this.btnCheckout.addEventListener('click', () => this.checkout());
        }

        // Thermal Print Button inside modal
        const btnPrint = document.getElementById('btnPrintThermal');
        if (btnPrint) {
            btnPrint.addEventListener('click', () => {
                if (this.lastInvoice) {
                    window.open(`invoice-print.php?inv=${this.lastInvoice.invoice_no}&auto_print=1`, '_blank', 'width=450,height=600');
                }
            });
        }

        // Global Hotkeys
        window.addEventListener('keydown', (e) => {
            // Hotkey '/' to focus search
            if (e.key === '/' && document.activeElement !== this.searchBar && document.activeElement !== this.custPhone && document.activeElement !== this.custName) {
                e.preventDefault();
                if (this.searchBar) this.searchBar.focus();
            }

            // Hotkey 'F9' to checkout
            if (e.key === 'F9') {
                e.preventDefault();
                if (this.cart.length > 0 && !this.btnCheckout.disabled) {
                    this.checkout();
                }
            }

            // Enter key on success modal
            if (e.key === 'Enter' && this.successModal && this.successModal.classList.contains('open')) {
                e.preventDefault();
                this.resetForNextBill();
            }
        });
    }

    filterProducts(searchTerm) {
        searchTerm = searchTerm.toLowerCase().trim();
        const activeCatPill = document.querySelector('.category-pill.active');
        const activeCatId = activeCatPill ? activeCatPill.dataset.catId : 'all';

        const cards = document.querySelectorAll('.product-card');
        cards.forEach(card => {
            const name = (card.dataset.name || '').toLowerCase();
            const cat = card.dataset.cat;

            const matchesCategory = (activeCatId === 'all' || cat === activeCatId);
            const matchesSearch = !searchTerm || name.includes(searchTerm);

            if (matchesCategory && matchesSearch) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    addItem(productId, qtyToAdd = 1) {
        const card = document.querySelector(`.product-card[data-id="${productId}"]`);
        if (!card) return;

        const name = card.dataset.nameMr || card.dataset.name;
        const nameEn = card.dataset.nameEn || name;
        const price = parseFloat(card.dataset.price);
        const unit = card.dataset.unit;
        const maxStock = parseFloat(card.dataset.stock);

        const existing = this.cart.find(item => item.id === productId);
        if (existing) {
            existing.qty = Math.round((existing.qty + qtyToAdd) * 1000) / 1000;
        } else {
            this.cart.push({
                id: productId,
                name: name,
                name_en: nameEn,
                price: price,
                unit: unit,
                qty: qtyToAdd,
                maxStock: maxStock
            });
        }

        this.renderCart();
        const activeName = (window.posI18n && window.posI18n.currentLang === 'en') ? nameEn : name;
        showToast(`${activeName} (${qtyToAdd} ${unit}) added!`, 'success');
    }

    updateQty(productId, newQty) {
        const item = this.cart.find(i => i.id === productId);
        if (!item) return;

        if (newQty <= 0) {
            this.removeItem(productId);
            return;
        }

        item.qty = Math.round(newQty * 1000) / 1000;
        this.renderCart();
    }

    updatePrice(productId, newPrice) {
        const item = this.cart.find(i => i.id === productId);
        if (!item) return;
        if (newPrice < 0) newPrice = 0;
        item.price = Math.round(newPrice * 100) / 100;
        this.renderCart();
        showToast(`${item.name}: दर बदलून ₹${item.price} केला!`, 'info');
    }

    async quickEditBasePrice(productId, currentName, currentPrice) {
        const input = prompt(`'${currentName}' चा नवीन विक्री दर (प्रति किलो) टाका:`, currentPrice);
        if (input === null) return;
        const newPrice = parseFloat(input);
        if (isNaN(newPrice) || newPrice <= 0) {
            alert('कृपया वैध किंमत टाका!');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'update_price');
            formData.append('id', productId);
            formData.append('new_price', newPrice);

            const res = await fetch('api/products.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                // Update the product card on screen
                const card = document.querySelector(`.product-card[data-id="${productId}"]`);
                if (card) {
                    card.dataset.price = newPrice;
                    const priceEl = card.querySelector('.prod-price');
                    if (priceEl) {
                        const unit = card.dataset.unit || 'kg';
                        priceEl.innerHTML = `₹${Math.round(newPrice)}<span class="prod-unit">/${unit}</span>`;
                    }
                }
                showToast(`${currentName} चा कायमस्वरूपी दर ₹${newPrice} सेव्ह झाला!`, 'success');
            } else {
                alert('त्रुटी: ' + data.message);
            }
        } catch (err) {
            alert('दर अपडेट करताना त्रुटी आली.');
        }
    }

    removeItem(productId) {
        this.cart = this.cart.filter(item => item.id !== productId);
        this.renderCart();
    }

    clearCart() {
        this.cart = [];
        this.renderCart();
    }

    renderCart() {
        if (!this.cartTableBody) return;

        this.cartTableBody.innerHTML = '';
        let subtotal = 0;
        let totalCount = 0;
        let totalWeightKg = 0;
        const currentLang = (window.posI18n) ? window.posI18n.currentLang : 'mr';

        if (this.cart.length === 0) {
            this.cartEmptyState.style.display = 'block';
            this.btnCheckout.disabled = true;
        } else {
            this.cartEmptyState.style.display = 'none';
            this.btnCheckout.disabled = false;

            this.cart.forEach(item => {
                const itemTotal = Math.round(item.price * item.qty * 100) / 100;
                subtotal += itemTotal;
                totalCount += 1;
                if (item.unit === 'kg') {
                    totalWeightKg += item.qty;
                }

                const displayName = (currentLang === 'en' && item.name_en) ? item.name_en : item.name;
                const step = (item.unit === 'kg') ? 0.25 : 1;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <div class="cart-item-title">${displayName}</div>
                        <div class="cart-item-rate" style="display: flex; align-items: center; gap: 3px; margin-top: 3px;">
                            <span style="font-weight: bold; color: var(--primary);">₹</span>
                            <input type="number" value="${item.price}" step="1" min="1" 
                                   title="दर बदला (Adjust Price)" 
                                   onchange="posCart.updatePrice(${item.id}, parseFloat(this.value) || 0)"
                                   style="width: 58px; padding: 1px 3px; font-size: 0.8rem; font-weight: bold; color: var(--primary-dark); border: 1px solid #94a3b8; border-radius: 4px; background: #fff; text-align: right;">
                            <span style="font-size: 0.72rem; color: #64748b;">/${item.unit}</span>
                        </div>
                    </td>
                    <td style="text-align: center;">
                        <div class="cart-qty-control" style="justify-content: center;">
                            <button class="qty-btn" onclick="posCart.updateQty(${item.id}, ${item.qty - step})">-</button>
                            <input type="number" class="qty-input" value="${item.qty}" step="${step}" min="0.05" onchange="posCart.updateQty(${item.id}, parseFloat(this.value) || 0.1)">
                            <button class="qty-btn" onclick="posCart.updateQty(${item.id}, ${item.qty + step})">+</button>
                        </div>
                    </td>
                    <td class="cart-item-total">₹${itemTotal.toFixed(2)}</td>
                    <td style="text-align: right;">
                        <button class="btn-remove-item" onclick="posCart.removeItem(${item.id})" title="काढून टाका">&times;</button>
                    </td>
                `;
                this.cartTableBody.appendChild(tr);
            });
        }

        // Calculations
        const discType = this.discountType ? this.discountType.value : 'flat';
        const discVal = this.discountVal ? parseFloat(this.discountVal.value) || 0 : 0;
        let discountAmt = (discType === 'percent') ? (subtotal * discVal) / 100 : discVal;
        discountAmt = Math.min(subtotal, discountAmt);

        const grandTotal = Math.max(0, subtotal - discountAmt);

        this.totalItemsEl.textContent = totalCount;
        if (this.totalWeightEl) {
            this.totalWeightEl.textContent = totalWeightKg.toFixed(2) + ' kg';
        }
        this.subtotalEl.textContent = subtotal.toFixed(2);
        this.grandTotalEl.textContent = grandTotal.toFixed(2);

        // Update Mobile Badges and Floating Bottom Bar
        if (this.mobileCartBadge) this.mobileCartBadge.textContent = totalCount;
        if (this.mobileCartHeaderTotal) this.mobileCartHeaderTotal.textContent = grandTotal.toFixed(2);
        if (this.mobileBarItems) this.mobileBarItems.textContent = totalCount;
        if (this.mobileBarTotal) this.mobileBarTotal.textContent = grandTotal.toFixed(2);

        if (this.mobileFloatingCartBar) {
            if (totalCount > 0 && this.currentMobileTab === 'products') {
                this.mobileFloatingCartBar.style.display = 'flex';
            } else {
                this.mobileFloatingCartBar.style.display = 'none';
            }
        }
    }

    switchMobileTab(tab) {
        this.currentMobileTab = tab;
        if (!this.posLayout) return;

        if (tab === 'cart') {
            this.posLayout.classList.remove('show-products');
            this.posLayout.classList.add('show-cart');
            if (this.tabBtnCart) this.tabBtnCart.classList.add('active');
            if (this.tabBtnProducts) this.tabBtnProducts.classList.remove('active');
            if (this.mobileFloatingCartBar) this.mobileFloatingCartBar.style.display = 'none';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            this.posLayout.classList.remove('show-cart');
            this.posLayout.classList.add('show-products');
            if (this.tabBtnProducts) this.tabBtnProducts.classList.add('active');
            if (this.tabBtnCart) this.tabBtnCart.classList.remove('active');
            if (this.mobileFloatingCartBar && this.cart.length > 0) {
                this.mobileFloatingCartBar.style.display = 'flex';
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    async lookupCustomer(phone) {
        try {
            const res = await fetch(`api/customers.php?action=search&phone=${phone}`);
            const data = await res.json();
            if (data.success && data.customer) {
                this.custName.value = data.customer.name;
                if (data.customer.credit_balance > 0) {
                    this.custCreditAlert.style.display = 'flex';
                    this.custCreditAmt.textContent = parseFloat(data.customer.credit_balance).toFixed(2);
                } else {
                    this.custCreditAlert.style.display = 'none';
                }
            }
        } catch (err) {
            console.error('Customer lookup failed', err);
        }
    }

    async checkout() {
        if (this.cart.length === 0) return;

        this.btnCheckout.disabled = true;
        this.btnCheckout.innerHTML = '<span>प्रक्रिया सुरू आहे...</span>';

        const payload = {
            items: this.cart.map(i => ({ id: i.id, qty: i.qty, custom_rate: i.price })),
            customer_name: this.custName.value.trim(),
            customer_phone: this.custPhone.value.trim(),
            payment_mode: this.paymentMode,
            discount_type: this.discountType.value,
            discount_value: parseFloat(this.discountVal.value) || 0,
            invoice_type: 'RETAIL'
        };

        try {
            const res = await fetch('api/checkout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (data.success) {
                this.lastInvoice = data.invoice;
                this.showSuccessModal(data.invoice);

                // Add to Recent Bills History List on screen immediately
                if (this.recentBillsList) {
                    const row = document.createElement('div');
                    row.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 4px 6px; background: var(--gray-100); border-radius: 4px; font-size: 0.78rem; animation: slideUp 0.3s ease;';
                    row.innerHTML = `
                        <div>
                            <strong style="color: var(--primary-dark);">${data.invoice.invoice_no}</strong>
                            <span style="color: var(--gray-600); margin-left: 4px;">${this.custName.value.trim() || 'ग्राहक'}</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <strong style="color: var(--primary);">₹${parseFloat(data.invoice.grand_total).toFixed(2)}</strong>
                            <a href="invoice-print.php?inv=${data.invoice.invoice_no}" target="_blank" title="Print" style="text-decoration: none; font-size: 0.85rem;">🖨️</a>
                            <a href="bill.php?inv=${data.invoice.invoice_no}" target="_blank" title="Digital Bill" style="text-decoration: none; font-size: 0.85rem;">🌐</a>
                        </div>
                    `;
                    const emptyPlaceholder = this.recentBillsList.querySelector('[data-i18n="recent_no_bills"]');
                    if (emptyPlaceholder) emptyPlaceholder.remove();
                    this.recentBillsList.prepend(row);
                }
            } else {
                alert('त्रुटी: ' + (data.message || 'बिल तयार करता आले नाही.'));
            }
        } catch (err) {
            alert('सर्व्हर एरर: कृपया नेटवर्क तपासा.');
            console.error(err);
        } finally {
            this.btnCheckout.disabled = false;
            this.btnCheckout.innerHTML = '<span>⚡ बिल पूर्ण करा (F9)</span>';
        }
    }

    showSuccessModal(invoice) {
        document.getElementById('modalInvoiceNo').textContent = invoice.invoice_no;
        document.getElementById('modalGrandTotal').textContent = invoice.grand_total.toFixed(2);
        document.getElementById('modalPaymentMode').textContent = invoice.payment_mode;

        // Setup WhatsApp link
        const btnWa = document.getElementById('btnWhatsAppDirect');
        if (btnWa) {
            btnWa.href = invoice.whatsapp_url;
        }

        // Setup Digital Bill link
        const btnBill = document.getElementById('btnViewDigitalBill');
        if (btnBill) {
            btnBill.href = invoice.bill_url;
        }

        // Auto-open WhatsApp chat if valid customer phone is entered
        if (invoice.whatsapp_url) {
            const cleanPhone = (this.custPhone ? this.custPhone.value.trim() : '').replace(/[^0-9]/g, '');
            if (cleanPhone.length === 10) {
                window.open(invoice.whatsapp_url, '_blank');
            }
        }

        this.successModal.classList.add('open');
    }

    closeSuccessModal() {
        this.successModal.classList.remove('open');
    }

    resetForNextBill() {
        this.closeSuccessModal();
        this.clearCart();
        this.switchMobileTab('products');
        if (this.custName) this.custName.value = '';
        if (this.custPhone) this.custPhone.value = '';
        if (this.discountVal) this.discountVal.value = '0';
        if (this.custCreditAlert) this.custCreditAlert.style.display = 'none';
        if (this.searchBar) {
            this.searchBar.value = '';
            this.searchBar.focus();
        }
        this.filterProducts('');
    }
}

// Global instance initialization
let posCart = null;
document.addEventListener('DOMContentLoaded', () => {
    posCart = new PosBillingCart();
});
