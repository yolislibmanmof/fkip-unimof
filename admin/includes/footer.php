        </main>
    </div>

    <script>
    // === EXTREME MULTIMATE ADMIN SCRIPTS ===

    // 1. Sidebar Mobile Toggle
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const btn = document.getElementById('mobileMenuBtn');
        
        sidebar.classList.toggle('open');
        overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
        
        if (window.innerWidth <= 1024) {
            btn.style.display = sidebar.classList.contains('open') ? 'none' : 'block';
        }
    }

    // Show mobile menu button on small screens
    if (window.innerWidth <= 1024) {
        document.getElementById('mobileMenuBtn').style.display = 'block';
    }
    window.addEventListener('resize', () => {
        document.getElementById('mobileMenuBtn').style.display = window.innerWidth <= 1024 ? 'block' : 'none';
    });

    // 2. Notification Dropdown Toggle
    const notifBtn = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    
    notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        notifDropdown.classList.toggle('show');
    });

    document.addEventListener('click', (e) => {
        if (!notifDropdown.contains(e.target) && e.target !== notifBtn) {
            notifDropdown.classList.remove('show');
        }
    });

    function clearNotifs() {
        // Simulasi tandai dibaca (bisa dihubungkan ke AJAX nanti)
        notifDropdown.innerHTML = '<div style="padding: 2rem; text-align: center; color: #64748b; font-size: 0.85rem;">🎉 Tidak ada notifikasi baru</div>';
        const badge = document.querySelector('.notification-badge');
        if (badge) badge.style.display = 'none';
    }

    // 3. Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
        
        // Animate chart bars on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const fills = entry.target.querySelectorAll('.chart-bar-fill, .progress-fill');
                    fills.forEach(fill => {
                        const width = fill.style.width || fill.dataset.width;
                        fill.style.width = '0%';
                        setTimeout(() => fill.style.width = width + (width.includes('%') ? '' : '%'), 100);
                    });
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });
        
        document.querySelectorAll('.card').forEach(card => observer.observe(card));
        
        // Add loading state to buttons
        document.querySelectorAll('button[type="submit"], .btn-sm').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.type === 'submit' && !this.form.checkValidity()) return;
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

    // 4. Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const search = document.querySelector('.toolbar .form-input[name="q"]');
            if (search) search.focus();
        }
        if (e.key === 'Escape') {
            document.querySelectorAll('.alert').forEach(a => a.remove());
            notifDropdown.classList.remove('show');
        }
    });

    // Helper function for time ago
    function timeAgo(timestamp) {
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
    }
    </script>
</body>
</html>