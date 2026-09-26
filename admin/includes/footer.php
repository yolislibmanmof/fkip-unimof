        <!-- ===== ADMIN FOOTER ===== -->
        <footer class="admin-footer" style="margin-top: 4rem; padding: 2rem 0 1rem; border-top: 1px solid var(--glass-border);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; color: #64748b; font-size: 0.85rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.2rem;">🎓</span>
                    <div>
                        &copy; <?= date('Y') ?> <strong style="color: var(--primary, #0a6847);">FKIP UNIMOF</strong> Admin Panel.
                        <span style="opacity: 0.7; display: block; font-size: 0.75rem; margin-top: 0.2rem;">Mencerdaskan bangsa dengan teknologi.</span>
                    </div>
                </div>
                <div style="display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap;">
                    <span style="background: #f1f5f9; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; color: #475569;">
                        v<?= defined('APP_VERSION') ? APP_VERSION : '1.0.0' ?>
                    </span>
                    <a href="<?= base_url() ?>" target="_blank" style="color: #64748b; text-decoration: none; transition: all 0.2s; display: flex; align-items: center; gap: 0.3rem;" onmouseover="this.style.color='#0a6847'; this.style.transform='translateY(-1px)'" onmouseout="this.style.color='#64748b'; this.style.transform='translateY(0)'">
                        🌐 Website Publik
                    </a>
                    <a href="https://github.com/yolislibmanmof/fkip-unimof" target="_blank" style="color: #64748b; text-decoration: none; transition: all 0.2s; display: flex; align-items: center; gap: 0.3rem;" onmouseover="this.style.color='#0a6847'; this.style.transform='translateY(-1px)'" onmouseout="this.style.color='#64748b'; this.style.transform='translateY(0)'">
                        🐙 GitHub
                    </a>
                </div>
            </div>
        </footer>
    </main>
</div>

<!-- ===== GLOBAL ADMIN SCRIPTS ===== -->
<script>
// 1. Sidebar Mobile Toggle
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const btn = document.getElementById('mobileMenuBtn');
    
    if (sidebar) sidebar.classList.toggle('open');
    if (overlay) overlay.style.display = (sidebar && sidebar.classList.contains('open')) ? 'block' : 'none';
    
    if (window.innerWidth <= 1024 && btn) {
        btn.style.display = (sidebar && sidebar.classList.contains('open')) ? 'none' : 'block';
    }
}

// Show mobile menu button on small screens
if (window.innerWidth <= 1024) {
    const btn = document.getElementById('mobileMenuBtn');
    if (btn) btn.style.display = 'block';
}
window.addEventListener('resize', () => {
    const btn = document.getElementById('mobileMenuBtn');
    if (btn) btn.style.display = window.innerWidth <= 1024 ? 'block' : 'none';
});

// 2. Notification Dropdown Toggle
const notifBtn = document.getElementById('notifBtn');
const notifDropdown = document.getElementById('notifDropdown');

if (notifBtn && notifDropdown) {
    notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        notifDropdown.classList.toggle('show');
    });

    document.addEventListener('click', (e) => {
        if (!notifDropdown.contains(e.target) && e.target !== notifBtn) {
            notifDropdown.classList.remove('show');
        }
    });
}

function clearNotifs() {
    if (notifDropdown) {
        notifDropdown.innerHTML = '<div style="padding: 2rem; text-align: center; color: #64748b; font-size: 0.85rem;">🎉 Tidak ada notifikasi baru</div>';
    }
    const badge = document.querySelector('.notification-badge');
    if (badge) badge.style.display = 'none';
}

// 3. Auto-hide alerts & UI Enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Animate chart bars/progress rings on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const fills = entry.target.querySelectorAll('.chart-bar-fill, .progress-fill, .ring-fill, .perf-ring-fill');
                fills.forEach(fill => {
                    const width = fill.style.width || fill.dataset.width || fill.getAttribute('style').match(/stroke-dasharray:([^,]+)/)?.[1];
                    if (width) {
                        fill.style.width = '0%';
                        if (fill.tagName === 'circle') {
                            fill.style.strokeDasharray = '0, 100';
                            setTimeout(() => fill.style.strokeDasharray = width, 100);
                        } else {
                            setTimeout(() => fill.style.width = width + (width.includes('%') ? '' : '%'), 100);
                        }
                    }
                });
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 });
    
    document.querySelectorAll('.card, .stat-ultimate-card').forEach(card => observer.observe(card));
    
    // Add loading state to submit buttons
    document.querySelectorAll('button[type="submit"], .btn-sm').forEach(btn => {
        btn.addEventListener('click', function() {
            if (this.type === 'submit' && this.form && !this.form.checkValidity()) return;
            const originalText = this.innerHTML;
            this.innerHTML = '⏳ Memproses...';
            this.disabled = true;
            setTimeout(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            }, 2000);
        });
    });
});

// 4. Keyboard Shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K for search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const search = document.querySelector('.toolbar .form-input[name="q"], input[type="search"], #dosenSearch, #risetSearch');
        if (search) search.focus();
    }
    // Escape to close modals, alerts, and dropdowns
    if (e.key === 'Escape') {
        document.querySelectorAll('.alert').forEach(a => a.remove());
        if (notifDropdown) notifDropdown.classList.remove('show');
        
        const palette = document.getElementById('commandPalette');
        if (palette) {
            palette.classList.remove('open');
            const cmdInput = document.getElementById('commandInput');
            if (cmdInput) cmdInput.value = '';
        }
    }
});

// 5. Helper function for time ago (available globally)
window.timeAgo = function(timestamp) {
    const seconds = Math.floor((new Date() - new Date(timestamp * 1000)) / 1000);
    let interval = seconds / 31536000;
    if (interval > 1) return Math.floor(interval) + ' tahun lalu';
    interval = seconds / 2592000;
    if (interval > 1) return Math.floor(interval) + ' bulan lalu';
    interval = seconds / 86400;
    if (interval > 1) return Math.floor(interval) + ' hari lalu';
    interval = seconds / 3600;
    if (interval > 1) return Math.floor(interval) + ' jam lalu';
    interval = seconds / 60;
    if (interval > 1) return Math.floor(interval) + ' menit lalu';
    return 'Baru saja';
};

console.log('%c🎓 FKIP UNIMOF Admin', 'color:#0a6847;font-size:16px;font-weight:bold');
console.log('%cSistem siap dan berjalan dengan optimal!', 'color:#64748b');
</script>

</body>
</html>