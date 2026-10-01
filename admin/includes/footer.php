<!-- ===== ADMIN FOOTER - SUPER EXTREME ULTIMATE ===== -->
<footer class="admin-footer" id="adminFooter">
    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" aria-label="Kembali ke atas" title="Kembali ke atas">
        <span>↑</span>
    </button>

    <!-- Main Footer Content -->
    <div class="footer-main">
        <!-- Left: Branding -->
        <div class="footer-branding">
            <div class="footer-logo">
                <span class="footer-logo-icon">🎓</span>
                <div class="footer-logo-text">
                    <strong>FKIP UNIMOF</strong>
                    <span>Admin Panel</span>
                </div>
            </div>
            <p class="footer-tagline">Mencerdaskan bangsa dengan teknologi.</p>
            <div class="footer-social">
                <a href="https://github.com/yolislibmanmof/fkip-unimof" target="_blank" rel="noopener" class="social-link" title="GitHub" aria-label="GitHub Repository">
                    <span>🐙</span>
                </a>
                <a href="<?= base_url() ?>" target="_blank" rel="noopener" class="social-link" title="Website Publik" aria-label="Website Publik">
                    <span>🌐</span>
                </a>
                <a href="mailto:fkip@unimof.ac.id" class="social-link" title="Email" aria-label="Email Kami">
                    <span>✉️</span>
                </a>
            </div>
        </div>

        <!-- Center: Quick Links -->
        <div class="footer-links">
            <div class="footer-links-group">
                <h4>Navigasi</h4>
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="berita.php">Berita</a></li>
                    <li><a href="dosen.php">Dosen</a></li>
                    <li><a href="program.php">Program Studi</a></li>
                </ul>
            </div>
            <div class="footer-links-group">
                <h4>Bantuan</h4>
                <ul>
                    <li><a href="#" onclick="openShortcutsModal(); return false;">Shortcuts</a></li>
                    <li><a href="pengaturan.php">Pengaturan</a></li>
                    <li><a href="#" onclick="showSystemInfo(); return false;">System Info</a></li>
                    <li><a href="https://github.com/yolislibmanmof/fkip-unimof/issues" target="_blank">Report Bug</a></li>
                </ul>
            </div>
        </div>

        <!-- Right: System Stats -->
        <div class="footer-stats">
            <div class="footer-stat">
                <span class="stat-label">Version</span>
                <span class="stat-value">v<?= defined('APP_VERSION') ? APP_VERSION : '1.0.0' ?></span>
            </div>
            <div class="footer-stat">
                <span class="stat-label">PHP</span>
                <span class="stat-value"><?= PHP_VERSION ?></span>
            </div>
            <div class="footer-stat">
                <span class="stat-label">Memory</span>
                <span class="stat-value" id="memoryUsage">-</span>
            </div>
            <div class="footer-stat">
                <span class="stat-label">Load Time</span>
                <span class="stat-value" id="loadTime">-</span>
            </div>
        </div>
    </div>

    <!-- Bottom Bar -->
    <div class="footer-bottom">
        <div class="footer-copyright">
            &copy; <?= date('Y') ?> <strong>FKIP UNIMOF</strong>. All rights reserved.
        </div>
        <div class="footer-meta">
            <span class="footer-clock" id="footerClock">--:--:--</span>
            <span class="footer-separator">•</span>
            <span class="footer-status" id="serverStatus">
                <span class="status-dot"></span>
                <span>Server Online</span>
            </span>
            <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
            <span class="footer-separator">•</span>
            <span class="footer-debug">
                🐛 Debug Mode
            </span>
            <?php endif; ?>
        </div>
    </div>
</footer>

<!-- ===== TOAST CONTAINER ===== -->
<div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

<!-- ===== SYSTEM INFO MODAL ===== -->
<div class="modal-overlay" id="systemInfoModal" onclick="if(event.target===this)closeSystemInfoModal()">
    <div class="system-info-modal">
        <div class="system-info-header">
            <h3>🖥️ System Information</h3>
            <button class="modal-close-btn" onclick="closeSystemInfoModal()" aria-label="Tutup">✕</button>
        </div>
        <div class="system-info-body">
            <div class="info-section">
                <h4>📊 Application</h4>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Version</span>
                        <span class="info-value">v<?= defined('APP_VERSION') ? APP_VERSION : '1.0.0' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Environment</span>
                        <span class="info-value"><?= defined('APP_ENV') ? APP_ENV : 'production' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Debug Mode</span>
                        <span class="info-value"><?= defined('DEBUG_MODE') && DEBUG_MODE ? 'Enabled' : 'Disabled' ?></span>
                    </div>
                </div>
            </div>
            <div class="info-section">
                <h4>🐘 PHP</h4>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Version</span>
                        <span class="info-value"><?= PHP_VERSION ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Memory Limit</span>
                        <span class="info-value"><?= ini_get('memory_limit') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Max Execution</span>
                        <span class="info-value"><?= ini_get('max_execution_time') ?>s</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Upload Max</span>
                        <span class="info-value"><?= ini_get('upload_max_filesize') ?></span>
                    </div>
                </div>
            </div>
            <div class="info-section">
                <h4>💾 Database</h4>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Driver</span>
                        <span class="info-value">MySQL / PDO</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Server</span>
                        <span class="info-value"><?= defined('DB_HOST') ? DB_HOST : 'localhost' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Database</span>
                        <span class="info-value"><?= defined('DB_NAME') ? DB_NAME : 'fkip' ?></span>
                    </div>
                </div>
            </div>
            <div class="info-section">
                <h4>🖥️ Server</h4>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Server Software</span>
                        <span class="info-value"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Server IP</span>
                        <span class="info-value"><?= $_SERVER['SERVER_ADDR'] ?? 'Unknown' ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Document Root</span>
                        <span class="info-value" style="word-break: break-all;"><?= $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown' ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="system-info-footer">
            <button class="btn-secondary" onclick="copySystemInfo()">📋 Copy Info</button>
            <button class="btn-primary" onclick="closeSystemInfoModal()">Tutup</button>
        </div>
    </div>
</div>

<style>
/* ========================================
   ADMIN FOOTER - SUPER EXTREME STYLES
   ======================================== */

.admin-footer {
    margin-top: 4rem;
    background: var(--bg-primary);
    border-top: 1px solid var(--border);
    position: relative;
}

/* Back to Top Button */
.back-to-top {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(10,104,71,0.3);
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px);
    transition: all 0.3s var(--ease-out);
    z-index: 50;
}

.back-to-top.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.back-to-top:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(10,104,71,0.4);
}

.back-to-top:active {
    transform: translateY(-2px);
}

/* Footer Main */
.footer-main {
    display: grid;
    grid-template-columns: 1.5fr 2fr 1fr;
    gap: 3rem;
    padding: 3rem 2rem 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Footer Branding */
.footer-branding {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.footer-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.footer-logo-icon {
    font-size: 2rem;
}

.footer-logo-text {
    display: flex;
    flex-direction: column;
}

.footer-logo-text strong {
    font-size: 1.1rem;
    color: var(--text-primary);
    font-weight: 800;
}

.footer-logo-text span {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
}

.footer-tagline {
    font-size: 0.85rem;
    color: var(--text-muted);
    line-height: 1.5;
    max-width: 280px;
}

.footer-social {
    display: flex;
    gap: 0.5rem;
}

.social-link {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-tertiary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 1.1rem;
    text-decoration: none;
    transition: all 0.2s var(--ease-out);
}

.social-link:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(10,104,71,0.2);
}

/* Footer Links */
.footer-links {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.footer-links-group h4 {
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.footer-links-group ul {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.footer-links-group a {
    font-size: 0.85rem;
    color: var(--text-muted);
    text-decoration: none;
    transition: color 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.footer-links-group a:hover {
    color: var(--primary);
}

/* Footer Stats */
.footer-stats {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.footer-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0.75rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}

.footer-stat .stat-label {
    font-size: 0.72rem;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.footer-stat .stat-value {
    font-size: 0.85rem;
    color: var(--text-primary);
    font-weight: 700;
    font-family: var(--font-mono);
}

/* Footer Bottom */
.footer-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    border-top: 1px solid var(--border);
    background: var(--bg-secondary);
    flex-wrap: wrap;
    gap: 1rem;
}

.footer-copyright {
    font-size: 0.8rem;
    color: var(--text-muted);
}

.footer-copyright strong {
    color: var(--primary);
    font-weight: 700;
}

.footer-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.footer-separator {
    color: var(--border);
}

.footer-clock {
    font-family: var(--font-mono);
    font-weight: 600;
    color: var(--text-secondary);
}

.footer-status {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
    animation: pulse 2s infinite;
}

.footer-debug {
    color: #f59e0b;
    font-weight: 600;
}

/* ========================================
   TOAST NOTIFICATIONS
   ======================================== */

.toast-container {
    position: fixed;
    top: 5rem;
    right: 1.5rem;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    max-width: 400px;
    pointer-events: none;
}

.toast {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1rem 1.25rem;
    box-shadow: var(--shadow-xl);
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    pointer-events: auto;
    animation: toastSlideIn 0.3s var(--ease-out);
    position: relative;
    overflow: hidden;
}

@keyframes toastSlideIn {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.toast.removing {
    animation: toastSlideOut 0.3s var(--ease-out) forwards;
}

@keyframes toastSlideOut {
    to {
        opacity: 0;
        transform: translateX(100%);
    }
}

.toast::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
}

.toast.success::before { background: #10b981; }
.toast.error::before { background: #ef4444; }
.toast.warning::before { background: #f59e0b; }
.toast.info::before { background: #3b82f6; }

.toast-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.toast.success .toast-icon { background: #d1fae5; color: #065f46; }
.toast.error .toast-icon { background: #fee2e2; color: #991b1b; }
.toast.warning .toast-icon { background: #fef3c7; color: #92400e; }
.toast.info .toast-icon { background: #dbeafe; color: #1e40af; }

.toast-content {
    flex: 1;
    min-width: 0;
}

.toast-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.15rem;
}

.toast-message {
    font-size: 0.8rem;
    color: var(--text-secondary);
    line-height: 1.4;
}

.toast-close {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0.25rem;
    font-size: 1rem;
    line-height: 1;
    border-radius: 4px;
    transition: all 0.15s;
}

.toast-close:hover {
    background: var(--bg-tertiary);
    color: var(--text-primary);
}

.toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: var(--primary);
    animation: toastProgress linear forwards;
}

@keyframes toastProgress {
    from { width: 100%; }
    to { width: 0%; }
}

/* ========================================
   SYSTEM INFO MODAL
   ======================================== */

.system-info-modal {
    width: 100%;
    max-width: 700px;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-xl);
    overflow: hidden;
    animation: modalSlideUp 0.3s var(--ease-out);
}

@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.system-info-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border);
    background: var(--bg-secondary);
}

.system-info-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-close-btn {
    width: 32px;
    height: 32px;
    background: var(--bg-tertiary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: var(--text-muted);
    transition: all 0.15s;
}

.modal-close-btn:hover {
    background: var(--bg-primary);
    color: var(--text-primary);
    border-color: var(--border-strong);
}

.system-info-body {
    padding: 1.5rem;
    max-height: 60vh;
    overflow-y: auto;
}

.info-section {
    margin-bottom: 1.5rem;
}

.info-section:last-child {
    margin-bottom: 0;
}

.info-section h4 {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.6rem 0.75rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}

.info-label {
    font-size: 0.78rem;
    color: var(--text-muted);
    font-weight: 600;
}

.info-value {
    font-size: 0.82rem;
    color: var(--text-primary);
    font-weight: 600;
    font-family: var(--font-mono);
    text-align: right;
    max-width: 60%;
    overflow: hidden;
    text-overflow: ellipsis;
}

.system-info-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border);
    background: var(--bg-secondary);
}

.btn-primary, .btn-secondary {
    padding: 0.6rem 1.25rem;
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid transparent;
    font-family: inherit;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    box-shadow: 0 2px 8px rgba(10,104,71,0.2);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(10,104,71,0.3);
}

.btn-secondary {
    background: var(--bg-primary);
    color: var(--text-primary);
    border-color: var(--border);
}

.btn-secondary:hover {
    background: var(--bg-tertiary);
    border-color: var(--border-strong);
}

/* ========================================
   RESPONSIVE
   ======================================== */

@media (max-width: 1024px) {
    .footer-main {
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
    }
    
    .footer-stats {
        grid-column: 1 / -1;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .footer-main {
        grid-template-columns: 1fr;
        padding: 2rem 1.5rem;
    }
    
    .footer-links {
        grid-template-columns: 1fr 1fr;
    }
    
    .footer-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .footer-bottom {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .back-to-top {
        bottom: 1.5rem;
        right: 1.5rem;
        width: 42px;
        height: 42px;
    }
    
    .toast-container {
        left: 1rem;
        right: 1rem;
        max-width: none;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .footer-links {
        grid-template-columns: 1fr;
    }
    
    .footer-stats {
        grid-template-columns: 1fr;
    }
    
    .footer-meta {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .footer-separator {
        display: none;
    }
}

/* ========================================
   PRINT STYLES
   ======================================== */

@media print {
    .admin-footer,
    .back-to-top,
    .toast-container,
    .system-info-modal {
        display: none !important;
    }
}
</style>

<script>
// ========================================
// ADMIN FOOTER - SUPER EXTREME SCRIPTS
// ========================================

(function() {
    'use strict';

    // ===== TOAST NOTIFICATION SYSTEM =====
    const ToastSystem = {
        container: null,
        queue: [],
        maxToasts: 5,

        init() {
            this.container = document.getElementById('toastContainer');
        },

        show(message, type = 'info', title = null, duration = 5000) {
            if (!this.container) this.init();

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');

            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };

            const titles = {
                success: 'Berhasil',
                error: 'Error',
                warning: 'Peringatan',
                info: 'Informasi'
            };

            toast.innerHTML = `
                <div class="toast-icon">${icons[type] || icons.info}</div>
                <div class="toast-content">
                    <div class="toast-title">${title || titles[type] || 'Notifikasi'}</div>
                    <div class="toast-message">${this.escapeHtml(message)}</div>
                </div>
                <button class="toast-close" aria-label="Tutup notifikasi">&times;</button>
                <div class="toast-progress" style="animation-duration: ${duration}ms"></div>
            `;

            // Close button handler
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.addEventListener('click', () => this.remove(toast));

            // Auto remove
            if (duration > 0) {
                setTimeout(() => this.remove(toast), duration);
            }

            this.container.appendChild(toast);

            // Remove old toasts if too many
            const toasts = this.container.querySelectorAll('.toast');
            if (toasts.length > this.maxToasts) {
                this.remove(toasts[0]);
            }

            return toast;
        },

        remove(toast) {
            if (!toast || !toast.parentNode) return;
            toast.classList.add('removing');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Expose globally
    window.Toast = ToastSystem;
    window.showToast = (message, type, title, duration) => ToastSystem.show(message, type, title, duration);

    // ===== BACK TO TOP BUTTON =====
    const backToTopBtn = document.getElementById('backToTop');
    
    if (backToTopBtn) {
        let ticking = false;

        window.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    if (window.pageYOffset > 400) {
                        backToTopBtn.classList.add('show');
                    } else {
                        backToTopBtn.classList.remove('show');
                    }
                    ticking = false;
                });
                ticking = true;
            }
        });

        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // ===== LIVE CLOCK =====
    function updateClock() {
        const clockEl = document.getElementById('footerClock');
        if (!clockEl) return;

        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        
        clockEl.textContent = `${hours}:${minutes}:${seconds} WIB`;
    }

    updateClock();
    setInterval(updateClock, 1000);

    // ===== SYSTEM STATS =====
    function updateSystemStats() {
        // Memory usage
        const memoryEl = document.getElementById('memoryUsage');
        if (memoryEl && performance && performance.memory) {
            const usedMB = (performance.memory.usedJSHeapSize / 1048576).toFixed(1);
            memoryEl.textContent = `${usedMB} MB`;
        } else if (memoryEl) {
            memoryEl.textContent = 'N/A';
        }

        // Load time
        const loadTimeEl = document.getElementById('loadTime');
        if (loadTimeEl && performance && performance.timing) {
            const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
            if (loadTime > 0) {
                loadTimeEl.textContent = `${(loadTime / 1000).toFixed(2)}s`;
            }
        } else if (loadTimeEl) {
            loadTimeEl.textContent = 'N/A';
        }
    }

    // Update stats after page load
    if (document.readyState === 'complete') {
        updateSystemStats();
    } else {
        window.addEventListener('load', updateSystemStats);
    }

    // ===== SYSTEM INFO MODAL =====
    window.showSystemInfo = function() {
        document.getElementById('systemInfoModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    };

    window.closeSystemInfoModal = function() {
        document.getElementById('systemInfoModal').classList.remove('show');
        document.body.style.overflow = '';
    };

    window.copySystemInfo = function() {
        const info = {
            version: '<?= defined("APP_VERSION") ? APP_VERSION : "1.0.0" ?>',
            php: '<?= PHP_VERSION ?>',
            memory: document.getElementById('memoryUsage')?.textContent || 'N/A',
            loadTime: document.getElementById('loadTime')?.textContent || 'N/A',
            server: '<?= $_SERVER["SERVER_SOFTWARE"] ?? "Unknown" ?>',
            browser: navigator.userAgent
        };

        const text = JSON.stringify(info, null, 2);
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('System info copied to clipboard', 'success');
            });
        } else {
            // Fallback
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showToast('System info copied to clipboard', 'success');
        }
    };

    // Close modal on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const modal = document.getElementById('systemInfoModal');
            if (modal && modal.classList.contains('show')) {
                closeSystemInfoModal();
            }
        }
    });

    // ===== AUTO-HIDE ALERTS =====
    document.addEventListener('DOMContentLoaded', () => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    });

    // ===== FORM SUBMIT LOADING STATE (SAFE) =====
    document.querySelectorAll('button[type="submit"]').forEach(btn => {
        btn.addEventListener('click', function() {
            if (this.form && !this.form.checkValidity()) return;
            if (!this.dataset.orig) this.dataset.orig = this.innerHTML;
            this.innerHTML = '⏳ Memproses...';
            // JANGAN disable tombol - bisa batalkan submit di Chrome
        });
    });

    // ===== TIME AGO HELPER =====
    window.timeAgo = function(timestamp) {
        const seconds = Math.floor((new Date() - new Date(timestamp * 1000)) / 1000);
        
        const intervals = [
            { label: 'tahun', seconds: 31536000 },
            { label: 'bulan', seconds: 2592000 },
            { label: 'minggu', seconds: 604800 },
            { label: 'hari', seconds: 86400 },
            { label: 'jam', seconds: 3600 },
            { label: 'menit', seconds: 60 }
        ];

        for (const interval of intervals) {
            const count = Math.floor(seconds / interval.seconds);
            if (count >= 1) {
                return `${count} ${interval.label} lalu`;
            }
        }

        return 'Baru saja';
    };

    // ===== CHART ANIMATION ON SCROLL =====
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const fills = entry.target.querySelectorAll('.chart-bar-fill, .progress-fill, .ring-fill, .perf-ring-fill');
                fills.forEach(fill => {
                    const width = fill.style.width || fill.dataset.width || fill.getAttribute('style')?.match(/stroke-dasharray:([^,]+)/)?.[1];
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

    // ===== INITIALIZATION =====
    console.log('%c🦶 FKIP UNIMOF Admin Footer', 'color: #0a6847; font-size: 14px; font-weight: bold;');
    console.log('%cSuper Extreme Ultimate Edition', 'color: #64748b; font-size: 11px;');
    console.log('%cFeatures: Toast System, Back to Top, Live Clock, System Stats', 'color: #64748b;');

})();
</script>

</main>
</div>

<script src="<?= base_url('admin/assets/js/admin.js') ?>"></script>

</body>
</html>