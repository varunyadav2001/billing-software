</div><!-- end app-container -->

<div id="toast-container"></div>

<footer class="no-print" style="text-align: center; padding: 1.25rem; font-size: 0.8rem; color: #64748b; border-top: 1px solid #e2e8f0; margin-top: auto; background: #fff; padding-bottom: max(1.25rem, env(safe-area-inset-bottom));">
    <p>
        <strong><?= htmlspecialchars($settings['store_name_mr'] ?: $settings['store_name']) ?></strong> &bull;
        लाडू, चिवडा, फराळ & मिठाई रिटेल POS बिलिंग सिस्टीम &bull; 100% ऑफलाइन कार्यक्षम
    </p>
</footer>

<script>
// Global Toast Notification Helper
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    let icon = '✓';
    if (type === 'error') icon = '✕';
    if (type === 'info') icon = 'ℹ';
    
    toast.innerHTML = `<span style="font-weight:bold;">${icon}</span> <span>${message}</span>`;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// Mobile Hamburger Navigation Toggle
document.addEventListener('DOMContentLoaded', () => {
    const navToggleBtn = document.getElementById('navToggleBtn');
    const mainNavLinks = document.getElementById('mainNavLinks');
    const btnLangToggleMobile = document.getElementById('btnLangToggleMobile');

    if (navToggleBtn && mainNavLinks) {
        navToggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            mainNavLinks.classList.toggle('mobile-open');
            navToggleBtn.classList.toggle('active');
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!mainNavLinks.contains(e.target) && !navToggleBtn.contains(e.target)) {
                mainNavLinks.classList.remove('mobile-open');
                navToggleBtn.classList.remove('active');
            }
        });

        // Close menu when a link inside is clicked
        mainNavLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                mainNavLinks.classList.remove('mobile-open');
                navToggleBtn.classList.remove('active');
            });
        });
    }

    // Connect mobile language button to i18n
    if (btnLangToggleMobile && window.posI18n) {
        btnLangToggleMobile.addEventListener('click', () => {
            const newLang = (window.posI18n.currentLang === 'mr') ? 'en' : 'mr';
            window.posI18n.setLanguage(newLang);
            const langSpan = btnLangToggleMobile.querySelector('.lang-text');
            if (langSpan) {
                langSpan.textContent = (newLang === 'mr') ? 'मराठी' : 'EN';
            }
        });
    }
});
</script>

</body>
</html>
