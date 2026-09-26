// =====================================================
// FKIP UNIMOF - Admin Panel SUPER EXTREME ULTIMATE
// Enterprise-Grade Interactions & Features
// Version: 2.0.0
// Compatible with footer.php & header.php features
// =====================================================

(function() {
    'use strict';

    // ================================================
    // UTILITY HELPERS
    // ================================================
    const Utils = {
        // Debounce function
        debounce(fn, delay = 300) {
            let timer;
            return function(...args) {
                clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), delay);
            };
        },

        // Throttle function
        throttle(fn, limit = 100) {
            let inThrottle;
            return function(...args) {
                if (!inThrottle) {
                    fn.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        // Safe querySelector
        $(selector, context = document) {
            return context.querySelector(selector);
        },

        // Safe querySelectorAll
        $$(selector, context = document) {
            return Array.from(context.querySelectorAll(selector));
        },

        // Generate unique ID
        uid(prefix = 'id') {
            return prefix + '_' + Math.random().toString(36).substr(2, 9);
        },

        // Format file size
        formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(decimals)) + ' ' + sizes[i];
        },

        // Escape HTML
        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        // Copy to clipboard
        async copyToClipboard(text) {
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                }
                return true;
            } catch {
                return false;
            }
        },

        // Download data as file
        downloadFile(content, filename, type = 'text/plain') {
            const blob = new Blob([content], { type });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            URL.revokeObjectURL(url);
        },

        // LocalStorage with expiry
        storage: {
            set(key, value, ttlMinutes = 0) {
                const data = {
                    value,
                    expiry: ttlMinutes ? Date.now() + (ttlMinutes * 60000) : null
                };
                try { localStorage.setItem(key, JSON.stringify(data)); } catch {}
            },
            get(key) {
                try {
                    const item = JSON.parse(localStorage.getItem(key));
                    if (!item) return null;
                    if (item.expiry && Date.now() > item.expiry) {
                        localStorage.removeItem(key);
                        return null;
                    }
                    return item.value;
                } catch {
                    return null;
                }
            },
            remove(key) {
                try { localStorage.removeItem(key); } catch {}
            }
        }
    };

    // ================================================
    // 1. 🎯 TOAST NOTIFICATION SYSTEM (ENHANCED)
    // ================================================
    const Toast = {
        container: null,
        queue: [],
        maxVisible: 5,

        init() {
            if (this.container) return;
            this.container = document.createElement('div');
            this.container.className = 'admin-toast-container';
            this.container.style.cssText = `
                position: fixed; top: 5rem; right: 1.5rem; z-index: 10001;
                display: flex; flex-direction: column; gap: 0.6rem;
                max-width: 380px; pointer-events: none;
            `;
            document.body.appendChild(this.container);
        },

        show(message, type = 'info', title = null, duration = 4500, options = {}) {
            this.init();

            const toast = document.createElement('div');
            const id = Utils.uid('toast');
            toast.className = `admin-toast ${type}`;
            toast.id = id;
            toast.style.cssText = `
                background: var(--bg-primary, #fff);
                border: 1px solid var(--border, #e2e8f0);
                border-radius: 12px;
                padding: 0.85rem 1.1rem;
                box-shadow: 0 15px 40px rgba(0,0,0,0.15);
                display: flex; align-items: flex-start; gap: 0.75rem;
                animation: toastSlideIn 0.3s ease-out;
                border-left: 4px solid ${this._colors[type]};
                pointer-events: auto;
                position: relative;
                overflow: hidden;
            `;

            const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
            const titles = { success: 'Berhasil', error: 'Error', warning: 'Peringatan', info: 'Info' };

            let actionHtml = '';
            if (options.action) {
                actionHtml = `<button class="admin-toast-action" style="
                    margin-top: 0.5rem; padding: 0.35rem 0.7rem;
                    background: ${this._colors[type]}; color: #fff;
                    border: none; border-radius: 6px; font-size: 0.75rem;
                    font-weight: 600; cursor: pointer; font-family: inherit;
                ">${Utils.escapeHtml(options.action.label)}</button>`;
            }

            toast.innerHTML = `
                <span style="font-size: 1.25rem; flex-shrink: 0;">${icons[type] || icons.info}</span>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-size: 0.88rem; font-weight: 700; color: var(--text-primary, #0f172a); margin-bottom: 0.15rem;">
                        ${Utils.escapeHtml(title || titles[type])}
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-secondary, #475569); line-height: 1.4;">
                        ${Utils.escapeHtml(message)}
                    </div>
                    ${actionHtml}
                </div>
                <button style="
                    background: transparent; border: none;
                    color: var(--text-muted, #64748b); cursor: pointer;
                    font-size: 1.15rem; padding: 0; width: 20px; height: 20px;
                    display: flex; align-items: center; justify-content: center;
                " data-close>✕</button>
                ${duration > 0 ? `<div style="
                    position: absolute; bottom: 0; left: 0; right: 0;
                    height: 3px; background: ${this._colors[type]};
                    animation: toastProgress ${duration}ms linear;
                    transform-origin: left;
                "></div>` : ''}
            `;

            // Close button
            toast.querySelector('[data-close]').addEventListener('click', () => this.remove(toast));

            // Action button
            if (options.action) {
                toast.querySelector('.admin-toast-action').addEventListener('click', () => {
                    options.action.onClick();
                    this.remove(toast);
                });
            }

            this.container.appendChild(toast);

            // Remove oldest if too many
            const toasts = this.container.querySelectorAll('.admin-toast');
            if (toasts.length > this.maxVisible) {
                this.remove(toasts[0]);
            }

            // Auto-remove
            if (duration > 0) {
                const timer = setTimeout(() => this.remove(toast), duration);
                toast.addEventListener('mouseenter', () => clearTimeout(timer));
                toast.addEventListener('mouseleave', () => {
                    setTimeout(() => this.remove(toast), 1500);
                });
            }

            return toast;
        },

        remove(toast) {
            if (!toast || !toast.parentElement) return;
            toast.style.animation = 'toastSlideOut 0.3s ease-in forwards';
            setTimeout(() => toast.remove(), 300);
        },

        success(msg, title, duration, opts) { return this.show(msg, 'success', title, duration, opts); },
        error(msg, title, duration, opts) { return this.show(msg, 'error', title, duration, opts); },
        warning(msg, title, duration, opts) { return this.show(msg, 'warning', title, duration, opts); },
        info(msg, title, duration, opts) { return this.show(msg, 'info', title, duration, opts); },

        _colors: {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        }
    };

    // Add CSS animations
    const toastStyle = document.createElement('style');
    toastStyle.textContent = `
        @keyframes toastSlideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes toastSlideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        @keyframes toastProgress {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }
    `;
    document.head.appendChild(toastStyle);

    // ================================================
    // 2. 🎭 MODAL SYSTEM (ENHANCED)
    // ================================================
    const Modal = {
        stack: [],

        create(options = {}) {
            const id = Utils.uid('modal');
            const overlay = document.createElement('div');
            overlay.className = 'admin-modal-overlay';
            overlay.id = id;
            overlay.style.cssText = `
                position: fixed; inset: 0;
                background: rgba(15, 23, 42, 0.75);
                backdrop-filter: blur(8px);
                display: flex; align-items: center; justify-content: center;
                z-index: 10002; padding: 2rem;
                animation: modalFadeIn 0.2s;
            `;

            const sizeMap = { sm: '400px', md: '550px', lg: '720px', xl: '900px', full: '95vw' };
            const size = sizeMap[options.size || 'md'];

            overlay.innerHTML = `
                <div class="admin-modal-box" style="
                    background: var(--bg-primary, #fff);
                    border-radius: 20px; max-width: ${size}; width: 100%;
                    max-height: 90vh; overflow: hidden;
                    box-shadow: 0 30px 60px rgba(0,0,0,0.3);
                    animation: modalPop 0.3s; display: flex; flex-direction: column;
                    border: 1px solid var(--border, #e2e8f0);
                ">
                    ${options.header !== false ? `
                        <div style="
                            padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border, #e2e8f0);
                            display: flex; justify-content: space-between; align-items: center;
                            background: ${options.headerGradient ? `linear-gradient(135deg, ${options.headerGradient})` : 'var(--bg-secondary, #f8fafc)'};
                            color: ${options.headerColor || 'var(--text-primary)'};
                        ">
                            <h3 style="font-size: 1.15rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem;">
                                ${options.icon ? `<span>${options.icon}</span>` : ''}
                                ${Utils.escapeHtml(options.title || 'Modal')}
                            </h3>
                            <button data-close style="
                                width: 32px; height: 32px; border-radius: 50%;
                                background: rgba(255,255,255,0.15); border: none;
                                cursor: pointer; font-size: 1.1rem; color: inherit;
                                display: flex; align-items: center; justify-content: center;
                                transition: all 0.2s;
                            ">✕</button>
                        </div>
                    ` : ''}
                    <div style="padding: 1.5rem; overflow-y: auto; flex: 1;">
                        ${options.content || ''}
                    </div>
                    ${options.footer ? `
                        <div style="
                            padding: 1rem 1.5rem; border-top: 1px solid var(--border, #e2e8f0);
                            background: var(--bg-secondary, #f8fafc);
                            display: flex; gap: 0.5rem; justify-content: flex-end;
                        ">
                            ${options.footer}
                        </div>
                    ` : ''}
                </div>
            `;

            document.body.appendChild(overlay);
            document.body.style.overflow = 'hidden';

            // Close handlers
            const close = () => this.close(id);
            overlay.querySelector('[data-close]')?.addEventListener('click', close);
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) close();
            });

            const escHandler = (e) => {
                if (e.key === 'Escape' && this.stack[this.stack.length - 1] === id) {
                    close();
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);

            this.stack.push(id);

            if (options.onOpen) options.onOpen(overlay);

            return { id, overlay, close };
        },

        close(id) {
            const overlay = id ? document.getElementById(id) : document.querySelector('.admin-modal-overlay:last-child');
            if (!overlay) return;

            overlay.style.animation = 'modalFadeOut 0.2s forwards';
            setTimeout(() => {
                overlay.remove();
                this.stack = this.stack.filter(x => x !== id);
                if (this.stack.length === 0) {
                    document.body.style.overflow = '';
                }
            }, 200);
        },

        closeAll() {
            document.querySelectorAll('.admin-modal-overlay').forEach(el => el.remove());
            this.stack = [];
            document.body.style.overflow = '';
        },

        // Confirm dialog (replacement for native confirm)
        confirm(message, options = {}) {
            return new Promise((resolve) => {
                const modal = this.create({
                    title: options.title || 'Konfirmasi',
                    icon: options.icon || '⚠️',
                    size: 'sm',
                    content: `
                        <p style="font-size: 0.95rem; line-height: 1.6; color: var(--text-secondary);">
                            ${Utils.escapeHtml(message)}
                        </p>
                    `,
                    footer: `
                        <button data-action="cancel" style="
                            padding: 0.65rem 1.25rem; background: var(--bg-tertiary, #f1f5f9);
                            color: var(--text-primary); border: 1px solid var(--border);
                            border-radius: 10px; font-family: inherit; font-weight: 600;
                            cursor: pointer; font-size: 0.88rem;
                        ">${Utils.escapeHtml(options.cancelText || 'Batal')}</button>
                        <button data-action="confirm" style="
                            padding: 0.65rem 1.25rem; background: ${options.confirmColor || 'var(--primary, #0a6847)'};
                            color: #fff; border: none; border-radius: 10px;
                            font-family: inherit; font-weight: 600; cursor: pointer;
                            font-size: 0.88rem;
                        ">${Utils.escapeHtml(options.confirmText || 'Ya')}</button>
                    `
                });

                modal.overlay.querySelector('[data-action="cancel"]').addEventListener('click', () => {
                    modal.close();
                    resolve(false);
                });
                modal.overlay.querySelector('[data-action="confirm"]').addEventListener('click', () => {
                    modal.close();
                    resolve(true);
                });
            });
        },

        // Prompt dialog
        prompt(message, options = {}) {
            return new Promise((resolve) => {
                const inputId = Utils.uid('prompt');
                const modal = this.create({
                    title: options.title || 'Input',
                    icon: options.icon || '✏️',
                    size: 'sm',
                    content: `
                        <p style="font-size: 0.9rem; margin-bottom: 1rem; color: var(--text-secondary);">
                            ${Utils.escapeHtml(message)}
                        </p>
                        <input type="${options.type || 'text'}" id="${inputId}" value="${Utils.escapeHtml(options.defaultValue || '')}"
                            placeholder="${Utils.escapeHtml(options.placeholder || '')}"
                            style="
                                width: 100%; padding: 0.75rem 1rem;
                                border: 2px solid var(--border); border-radius: 10px;
                                font-family: inherit; font-size: 0.95rem;
                                background: var(--bg-secondary);
                            ">
                    `,
                    footer: `
                        <button data-action="cancel" style="
                            padding: 0.65rem 1.25rem; background: var(--bg-tertiary);
                            color: var(--text-primary); border: 1px solid var(--border);
                            border-radius: 10px; font-family: inherit; font-weight: 600; cursor: pointer;
                        ">Batal</button>
                        <button data-action="confirm" style="
                            padding: 0.65rem 1.25rem; background: var(--primary, #0a6847);
                            color: #fff; border: none; border-radius: 10px;
                            font-family: inherit; font-weight: 600; cursor: pointer;
                        ">OK</button>
                    `
                });

                const input = modal.overlay.querySelector(`#${inputId}`);
                setTimeout(() => input.focus(), 100);

                const getValue = () => input.value;
                modal.overlay.querySelector('[data-action="cancel"]').addEventListener('click', () => {
                    modal.close();
                    resolve(null);
                });
                modal.overlay.querySelector('[data-action="confirm"]').addEventListener('click', () => {
                    const value = getValue();
                    modal.close();
                    resolve(value);
                });
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        const value = getValue();
                        modal.close();
                        resolve(value);
                    }
                });
            });
        }
    };

    // Modal animations
    const modalStyle = document.createElement('style');
    modalStyle.textContent = `
        @keyframes modalFadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes modalFadeOut { from { opacity: 1; } to { opacity: 0; } }
        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    `;
    document.head.appendChild(modalStyle);

    // ================================================
    // 3. 🎹 COMMAND PALETTE (ENHANCED)
    // ================================================
    const CommandPalette = {
        isOpen: false,
        items: [],
        history: [],
        activeIndex: 0,

        init() {
            if (document.querySelector('.admin-command-palette')) return;
            this.registerDefaultCommands();
            this.loadHistory();
            this.createPalette();
        },

        registerDefaultCommands() {
            this.items = [
                { id: 'dashboard', title: 'Dashboard', desc: 'Kembali ke dashboard', icon: '📊', category: 'Navigasi', shortcut: 'G D', action: () => location.href = 'dashboard.php' },
                { id: 'berita', title: 'Kelola Berita', desc: 'CRUD artikel berita', icon: '📰', category: 'Navigasi', shortcut: 'G B', action: () => location.href = 'berita.php' },
                { id: 'agenda', title: 'Agenda', desc: 'Kelola agenda', icon: '📆', category: 'Navigasi', action: () => location.href = 'agenda.php' },
                { id: 'dosen', title: 'Data Dosen', desc: 'Kelola data dosen', icon: '👨‍🏫', category: 'Navigasi', action: () => location.href = 'dosen.php' },
                { id: 'prodi', title: 'Program Studi', desc: 'Kelola program studi', icon: '🎓', category: 'Navigasi', action: () => location.href = 'program.php' },
                { id: 'kontak', title: 'Pesan Masuk', desc: 'Inbox pengunjung', icon: '✉️', category: 'Navigasi', action: () => location.href = 'kontak.php' },
                { id: 'pengaturan', title: 'Pengaturan', desc: 'Konfigurasi sistem', icon: '⚙️', category: 'Navigasi', action: () => location.href = 'pengaturan.php' },
                { id: 'new-berita', title: 'Tulis Berita Baru', desc: 'Buat artikel baru', icon: '✍️', category: 'Aksi', action: () => location.href = 'berita-form.php' },
                { id: 'new-agenda', title: 'Tambah Agenda', desc: 'Buat agenda baru', icon: '📅', category: 'Aksi', action: () => location.href = 'agenda-form.php' },
                { id: 'darkmode', title: 'Toggle Dark Mode', desc: 'Ganti tema', icon: '🌓', category: 'Aksi', action: () => document.getElementById('themeToggle')?.click() },
                { id: 'system-info', title: 'System Info', desc: 'Lihat info sistem', icon: '💻', category: 'Aksi', action: () => window.showSystemInfo?.() },
                { id: 'print', title: 'Print Halaman', desc: 'Cetak halaman ini', icon: '🖨️', category: 'Aksi', action: () => window.print() },
                { id: 'website', title: 'Buka Website Publik', desc: 'Lihat tampilan publik', icon: '🌐', category: 'Aksi', action: () => window.open(window.location.origin, '_blank') },
                { id: 'logout', title: 'Logout', desc: 'Keluar dari sistem', icon: '🚪', category: 'Aksi', action: async () => {
                    if (await Modal.confirm('Yakin ingin logout?')) location.href = 'logout.php';
                }}
            ];
        },

        loadHistory() {
            this.history = Utils.storage.get('command_history') || [];
        },

        saveToHistory(id) {
            this.history = this.history.filter(x => x !== id);
            this.history.unshift(id);
            if (this.history.length > 5) this.history = this.history.slice(0, 5);
            Utils.storage.set('command_history', this.history, 60 * 24 * 7); // 7 hari
        },

        createPalette() {
            const palette = document.createElement('div');
            palette.className = 'admin-command-palette';
            palette.style.cssText = `
                position: fixed; inset: 0;
                background: rgba(15, 23, 42, 0.7);
                backdrop-filter: blur(8px);
                z-index: 10003; display: none;
                align-items: flex-start; justify-content: center;
                padding-top: 15vh; animation: modalFadeIn 0.2s;
            `;
            palette.innerHTML = `
                <div style="
                    background: var(--bg-primary, #fff);
                    border-radius: 16px; width: 90%; max-width: 600px;
                    box-shadow: 0 30px 80px rgba(0,0,0,0.4);
                    overflow: hidden; animation: modalPop 0.3s;
                    border: 1px solid var(--border);
                ">
                    <div style="
                        display: flex; align-items: center; gap: 0.75rem;
                        padding: 1rem 1.25rem; border-bottom: 1px solid var(--border);
                        background: var(--bg-secondary);
                    ">
                        <span style="font-size: 1.25rem;">🔍</span>
                        <input type="text" class="admin-command-input" placeholder="Ketik perintah atau cari..."
                            autocomplete="off" style="
                                flex: 1; border: none; outline: none; background: transparent;
                                font-size: 1rem; font-family: inherit; color: var(--text-primary);
                            ">
                        <kbd style="
                            background: var(--bg-tertiary); padding: 0.2rem 0.5rem;
                            border-radius: 5px; font-size: 0.7rem; font-family: monospace;
                            color: var(--text-muted);
                        ">ESC</kbd>
                    </div>
                    <div class="admin-command-results" style="
                        max-height: 400px; overflow-y: auto; padding: 0.5rem;
                    "></div>
                    <div style="
                        padding: 0.6rem 1.25rem; border-top: 1px solid var(--border);
                        background: var(--bg-secondary); display: flex; gap: 1rem;
                        font-size: 0.72rem; color: var(--text-muted);
                    ">
                        <span>↑↓ Navigasi</span>
                        <span>↵ Pilih</span>
                        <span>ESC Tutup</span>
                    </div>
                </div>
            `;
            document.body.appendChild(palette);

            const input = palette.querySelector('.admin-command-input');
            const results = palette.querySelector('.admin-command-results');

            input.addEventListener('input', Utils.debounce(() => {
                this.renderResults(input.value, results);
            }, 100));

            input.addEventListener('keydown', (e) => this.handleKeyboard(e, results));

            palette.addEventListener('click', (e) => {
                if (e.target === palette) this.close();
            });
        },

        fuzzyMatch(query, text) {
            query = query.toLowerCase();
            text = text.toLowerCase();
            let qi = 0;
            for (let ti = 0; ti < text.length && qi < query.length; ti++) {
                if (text[ti] === query[qi]) qi++;
            }
            return qi === query.length;
        },

        renderResults(query, container) {
            let items = this.items;

            // Prioritize recent
            const recent = this.history.map(id => items.find(i => i.id === id)).filter(Boolean);

            if (query.trim()) {
                items = items.filter(item =>
                    this.fuzzyMatch(query, item.title) ||
                    this.fuzzyMatch(query, item.desc) ||
                    (item.category && this.fuzzyMatch(query, item.category))
                );
            }

            if (items.length === 0) {
                container.innerHTML = `
                    <div style="padding: 2.5rem 1rem; text-align: center; color: var(--text-muted);">
                        <div style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 0.5rem;">🔍</div>
                        <div>Tidak ada hasil untuk "<strong>${Utils.escapeHtml(query)}</strong>"</div>
                    </div>
                `;
                this.activeIndex = -1;
                return;
            }

            // Group by category
            const grouped = {};
            items.forEach(item => {
                const cat = item.category || 'Lainnya';
                if (!grouped[cat]) grouped[cat] = [];
                grouped[cat].push(item);
            });

            let html = '';

            // Show recent first (if no query)
            if (!query && recent.length > 0) {
                html += `<div style="padding: 0.5rem 0.75rem 0.25rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); font-weight: 700;">🕐 Baru-baru ini</div>`;
                recent.slice(0, 3).forEach(item => {
                    html += this._renderItem(item);
                });
            }

            Object.entries(grouped).forEach(([cat, catItems]) => {
                html += `<div style="padding: 0.5rem 0.75rem 0.25rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); font-weight: 700;">${Utils.escapeHtml(cat)}</div>`;
                catItems.forEach(item => {
                    html += this._renderItem(item);
                });
            });

            container.innerHTML = html;
            this.activeIndex = 0;
            this._updateActive(container);

            container.querySelectorAll('.admin-command-item').forEach(el => {
                el.addEventListener('click', () => {
                    const id = el.dataset.id;
                    const item = this.items.find(i => i.id === id);
                    if (item) {
                        this.saveToHistory(id);
                        item.action();
                        this.close();
                    }
                });
                el.addEventListener('mouseenter', () => {
                    container.querySelectorAll('.admin-command-item').forEach(x => x.classList.remove('active'));
                    el.classList.add('active');
                });
            });
        },

        _renderItem(item) {
            return `
                <div class="admin-command-item" data-id="${item.id}" style="
                    display: flex; align-items: center; gap: 0.85rem;
                    padding: 0.7rem 0.85rem; border-radius: 8px; cursor: pointer;
                    transition: background 0.15s; margin-bottom: 0.15rem;
                ">
                    <span style="font-size: 1.15rem; width: 26px; text-align: center;">${item.icon}</span>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-primary);">
                            ${Utils.escapeHtml(item.title)}
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            ${Utils.escapeHtml(item.desc)}
                        </div>
                    </div>
                    ${item.shortcut ? `<kbd style="background: var(--bg-tertiary); padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.68rem; font-family: monospace; color: var(--text-muted);">${Utils.escapeHtml(item.shortcut)}</kbd>` : ''}
                </div>
            `;
        },

        _updateActive(container) {
            const items = container.querySelectorAll('.admin-command-item');
            items.forEach((el, i) => {
                el.classList.toggle('active', i === this.activeIndex);
                if (i === this.activeIndex) {
                    el.style.background = 'var(--bg-secondary)';
                    el.scrollIntoView({ block: 'nearest' });
                } else {
                    el.style.background = 'transparent';
                }
            });
        },

        handleKeyboard(e, container) {
            const items = container.querySelectorAll('.admin-command-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.activeIndex = Math.min(this.activeIndex + 1, items.length - 1);
                this._updateActive(container);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.activeIndex = Math.max(this.activeIndex - 1, 0);
                this._updateActive(container);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                items[this.activeIndex]?.click();
            }
        },

        open() {
            const palette = document.querySelector('.admin-command-palette');
            if (!palette) return;
            palette.style.display = 'flex';
            const input = palette.querySelector('.admin-command-input');
            input.value = '';
            this.renderResults('', palette.querySelector('.admin-command-results'));
            setTimeout(() => input.focus(), 50);
            this.isOpen = true;
        },

        close() {
            const palette = document.querySelector('.admin-command-palette');
            if (!palette) return;
            palette.style.display = 'none';
            this.isOpen = false;
        },

        toggle() {
            this.isOpen ? this.close() : this.open();
        },

        register(item) {
            if (!this.items.find(i => i.id === item.id)) {
                this.items.push(item);
            }
        }
    };

    // ================================================
    // 4. 📁 DRAG & DROP UPLOAD (ENHANCED)
    // ================================================
    const DragDrop = {
        init() {
            Utils.$$('.admin-dropzone').forEach(zone => this.setupZone(zone));
        },

        setupZone(zone) {
            const input = zone.querySelector('input[type="file"]');
            if (!input) return;

            ['dragenter', 'dragover'].forEach(ev => {
                zone.addEventListener(ev, (e) => {
                    e.preventDefault();
                    zone.classList.add('dragover');
                    zone.style.borderColor = 'var(--primary)';
                    zone.style.background = 'rgba(10,104,71,0.05)';
                });
            });

            ['dragleave', 'drop'].forEach(ev => {
                zone.addEventListener(ev, (e) => {
                    e.preventDefault();
                    zone.classList.remove('dragover');
                    zone.style.borderColor = '';
                    zone.style.background = '';
                });
            });

            zone.addEventListener('drop', (e) => {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    this.handleFiles(files, zone);
                }
            });

            zone.addEventListener('click', (e) => {
                if (e.target.tagName !== 'INPUT') input.click();
            });

            input.addEventListener('change', (e) => {
                if (e.target.files.length > 0) this.handleFiles(e.target.files, zone);
            });
        },

        handleFiles(files, zone) {
            const preview = zone.querySelector('.admin-dropzone-preview');
            if (!preview) return;

            preview.innerHTML = '';
            Array.from(files).forEach(file => {
                const item = document.createElement('div');
                item.style.cssText = `
                    display: flex; align-items: center; gap: 0.75rem;
                    padding: 0.75rem; background: var(--bg-secondary);
                    border: 1px solid var(--border); border-radius: 10px;
                    margin-bottom: 0.5rem;
                `;

                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        item.innerHTML = `
                            <img src="${e.target.result}" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 0.88rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    ${Utils.escapeHtml(file.name)}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    ${Utils.formatBytes(file.size)}
                                </div>
                            </div>
                            <span style="color: #10b981;">✓</span>
                        `;
                    };
                    reader.readAsDataURL(file);
                } else {
                    item.innerHTML = `
                        <span style="font-size: 1.75rem;">📄</span>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 0.88rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                ${Utils.escapeHtml(file.name)}
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                ${Utils.formatBytes(file.size)}
                            </div>
                        </div>
                        <span style="color: #10b981;">✓</span>
                    `;
                }

                preview.appendChild(item);
            });
        }
    };

    // ================================================
    // 5. 🔢 COUNTER ANIMATION (ENHANCED)
    // ================================================
    const Counter = {
        init() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.animate(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.3 });

            Utils.$$('[data-count], .count-up').forEach(el => {
                if (!el.dataset.target && el.dataset.count) {
                    el.dataset.target = el.dataset.count;
                }
                observer.observe(el);
            });
        },

        animate(element) {
            const target = parseFloat(element.dataset.target || element.dataset.count || 0);
            const duration = parseInt(element.dataset.duration || 1800);
            const decimals = parseInt(element.dataset.decimals || 0);
            const prefix = element.dataset.prefix || '';
            const suffix = element.dataset.suffix || '';
            const start = performance.now();

            const step = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                const value = eased * target;

                if (decimals > 0) {
                    element.textContent = prefix + value.toFixed(decimals) + suffix;
                } else {
                    element.textContent = prefix + Math.floor(value).toLocaleString('id-ID') + suffix;
                }

                if (progress < 1) requestAnimationFrame(step);
            };

            requestAnimationFrame(step);
        }
    };

    // ================================================
    // 6. ✅ FORM VALIDATION (ENHANCED)
    // ================================================
    const FormValidator = {
        init() {
            Utils.$$('form[data-validate]').forEach(form => this.setupForm(form));

            // Auto-init required fields
            Utils.$$('form input[required], form textarea[required], form select[required]').forEach(field => {
                field.addEventListener('blur', () => this.validateField(field));
                field.addEventListener('input', Utils.debounce(() => {
                    if (field.classList.contains('error')) this.validateField(field);
                }, 300));
            });
        },

        setupForm(form) {
            form.addEventListener('submit', (e) => {
                let isValid = true;
                form.querySelectorAll('[required]').forEach(field => {
                    if (!this.validateField(field)) isValid = false;
                });

                if (!isValid) {
                    e.preventDefault();
                    Toast.error('Mohon lengkapi semua field yang wajib diisi.', 'Validasi Error');
                    const firstError = form.querySelector('.error');
                    firstError?.focus();
                    firstError?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        },

        validateField(field) {
            const value = field.value.trim();
            let valid = true;
            let message = '';

            if (field.hasAttribute('required') && !value) {
                valid = false;
                message = 'Field ini wajib diisi';
            } else if (field.type === 'email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                valid = false;
                message = 'Format email tidak valid';
            } else if (field.minLength && value.length < field.minLength) {
                valid = false;
                message = `Minimal ${field.minLength} karakter`;
            } else if (field.maxLength && value.length > field.maxLength) {
                valid = false;
                message = `Maksimal ${field.maxLength} karakter`;
            } else if (field.pattern && value && !new RegExp(field.pattern).test(value)) {
                valid = false;
                message = 'Format tidak sesuai';
            }

            if (!valid) {
                field.classList.add('error');
                field.style.borderColor = '#ef4444';
                this.showError(field, message);
            } else {
                field.classList.remove('error');
                field.style.borderColor = '';
                this.clearError(field);
            }

            return valid;
        },

        showError(field, message) {
            this.clearError(field);
            const errorEl = document.createElement('div');
            errorEl.className = 'field-error-message';
            errorEl.style.cssText = `
                color: #ef4444; font-size: 0.75rem; margin-top: 0.35rem;
                display: flex; align-items: center; gap: 0.3rem;
                animation: toastSlideIn 0.2s;
            `;
            errorEl.innerHTML = `<span>⚠️</span> ${Utils.escapeHtml(message)}`;
            field.parentElement.appendChild(errorEl);
        },

        clearError(field) {
            const existing = field.parentElement.querySelector('.field-error-message');
            if (existing) existing.remove();
        }
    };

    // ================================================
    // 7. 📊 TABLE ENHANCER (ENHANCED)
    // ================================================
    const TableEnhancer = {
        init() {
            this.setupSortableTables();
            this.setupSearchableTables();
            this.setupSelectableTables();
        },

        setupSortableTables() {
            Utils.$$('.admin-table[data-sortable] th[data-sortable]').forEach(header => {
                header.style.cursor = 'pointer';
                header.style.userSelect = 'none';

                // Add sort icon
                const icon = document.createElement('span');
                icon.style.cssText = 'margin-left: 0.4rem; opacity: 0.3; font-size: 0.8rem;';
                icon.textContent = '↕';
                header.appendChild(icon);

                header.addEventListener('click', () => this.sortTable(header));
            });
        },

        sortTable(header) {
            const table = header.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const index = Array.from(header.parentElement.children).indexOf(header);
            const isAsc = header.classList.contains('asc');

            // Reset other headers
            header.parentElement.querySelectorAll('th').forEach(th => {
                th.classList.remove('asc', 'desc');
                const icon = th.querySelector('span');
                if (icon) icon.textContent = '↕';
            });

            rows.sort((a, b) => {
                const aText = a.children[index]?.textContent.trim() || '';
                const bText = b.children[index]?.textContent.trim() || '';
                const aNum = parseFloat(aText.replace(/[^\d.-]/g, ''));
                const bNum = parseFloat(bText.replace(/[^\d.-]/g, ''));

                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return isAsc ? bNum - aNum : aNum - bNum;
                }
                return isAsc ? bText.localeCompare(aText) : aText.localeCompare(bText);
            });

            rows.forEach(row => tbody.appendChild(row));
            header.classList.add(isAsc ? 'desc' : 'asc');
            const icon = header.querySelector('span');
            if (icon) icon.textContent = isAsc ? '↓' : '↑';
        },

        setupSearchableTables() {
            Utils.$$('.admin-table[data-searchable]').forEach(table => {
                if (table.parentElement.querySelector('.table-search')) return;

                const searchInput = document.createElement('input');
                searchInput.type = 'text';
                searchInput.className = 'table-search';
                searchInput.placeholder = '🔍 Cari di tabel...';
                searchInput.style.cssText = `
                    width: 100%; padding: 0.7rem 1rem; margin-bottom: 1rem;
                    border: 2px solid var(--border); border-radius: 10px;
                    font-family: inherit; font-size: 0.9rem;
                    background: var(--bg-secondary);
                `;

                searchInput.addEventListener('input', Utils.debounce((e) => {
                    this.filterTable(table, e.target.value);
                }, 200));

                table.parentElement.insertBefore(searchInput, table);
            });
        },

        filterTable(table, query) {
            const rows = table.querySelectorAll('tbody tr');
            const lowerQuery = query.toLowerCase();
            let visible = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const match = text.includes(lowerQuery);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            // Show no-results message
            let msg = table.parentElement.querySelector('.table-no-results');
            if (visible === 0 && query) {
                if (!msg) {
                    msg = document.createElement('div');
                    msg.className = 'table-no-results';
                    msg.style.cssText = `
                        padding: 2rem; text-align: center; color: var(--text-muted);
                        background: var(--bg-secondary); border-radius: 10px; margin-top: 1rem;
                    `;
                    table.parentElement.appendChild(msg);
                }
                msg.textContent = `Tidak ada hasil untuk "${query}"`;
                msg.style.display = 'block';
            } else if (msg) {
                msg.style.display = 'none';
            }
        },

        setupSelectableTables() {
            Utils.$$('.admin-table[data-selectable]').forEach(table => {
                const selectAll = table.querySelector('th input[type="checkbox"]');
                if (selectAll) {
                    selectAll.addEventListener('change', (e) => {
                        table.querySelectorAll('tbody input[type="checkbox"]').forEach(cb => {
                            cb.checked = e.target.checked;
                        });
                        this.updateBulkActions(table);
                    });
                }

                table.querySelectorAll('tbody input[type="checkbox"]').forEach(cb => {
                    cb.addEventListener('change', () => this.updateBulkActions(table));
                });
            });
        },

        updateBulkActions(table) {
            const selected = table.querySelectorAll('tbody input[type="checkbox"]:checked').length;
            const bulkBar = document.getElementById('bulkBar');
            if (bulkBar) {
                bulkBar.classList.toggle('show', selected > 0);
                const countEl = bulkBar.querySelector('#bulkCount');
                if (countEl) countEl.textContent = selected;
            }
        },

        // Export table to CSV
        exportCSV(table, filename = 'export.csv') {
            const rows = Array.from(table.querySelectorAll('tr'));
            const csv = rows.map(row =>
                Array.from(row.cells).map(cell => {
                    const text = cell.textContent.replace(/"/g, '""').trim();
                    return `"${text}"`;
                }).join(',')
            ).join('\n');

            Utils.downloadFile('\ufeff' + csv, filename, 'text/csv;charset=utf-8');
            Toast.success('Tabel berhasil diexport ke CSV', 'Export Berhasil');
        }
    };

    // ================================================
    // 8. ⌨️ KEYBOARD SHORTCUTS (ENHANCED)
    // ================================================
    const KeyboardShortcuts = {
        shortcuts: [],

        init() {
            this.registerDefaults();
            document.addEventListener('keydown', (e) => this.handle(e));
        },

        registerDefaults() {
            this.register('Ctrl+K', () => CommandPalette.toggle(), 'Buka Command Palette');
            this.register('Escape', () => {
                Modal.closeAll();
                CommandPalette.close();
            }, 'Tutup semua modal');
        },

        register(combo, action, desc = '') {
            this.shortcuts.push({ combo, action, desc });
        },

        handle(e) {
            const combo = [];
            if (e.ctrlKey || e.metaKey) combo.push('Ctrl');
            if (e.shiftKey) combo.push('Shift');
            if (e.altKey) combo.push('Alt');

            const key = e.key.length === 1 ? e.key.toUpperCase() : e.key;
            if (!['Control', 'Shift', 'Alt', 'Meta'].includes(e.key)) {
                combo.push(key);
            }

            const comboStr = combo.join('+');

            this.shortcuts.forEach(s => {
                if (s.combo === comboStr) {
                    e.preventDefault();
                    s.action();
                }
            });
        }
    };

    // ================================================
    // 9. 💾 AUTO-SAVE INDICATOR (ENHANCED)
    // ================================================
    const AutoSave = {
        init() {
            Utils.$$('form[data-autosave]').forEach(form => this.setupForm(form));
        },

        setupForm(form) {
            const key = 'autosave_' + window.location.pathname + '_' + (form.id || 'main');
            const fields = form.querySelectorAll('input:not([type=file]):not([type=hidden]):not([type=password]):not([type=submit]), textarea, select');

            // Load draft
            const draft = Utils.storage.get(key);
            if (draft && !form.dataset.loaded) {
                const loadDraft = async () => {
                    if (await Modal.confirm('Ada draft tersimpan. Muat draft tersebut?', {
                        title: 'Draft Tersimpan',
                        icon: '💾',
                        confirmText: 'Muat Draft',
                        cancelText: 'Abaikan'
                    })) {
                        Object.keys(draft).forEach(name => {
                            const el = form.querySelector(`[name="${name}"]`);
                            if (el) {
                                if (el.type === 'checkbox') el.checked = draft[name];
                                else if (el.type === 'radio') {
                                    if (el.value === draft[name]) el.checked = true;
                                } else el.value = draft[name];
                            }
                        });
                        Toast.success('Draft berhasil dimuat', 'Auto-save');
                    } else {
                        Utils.storage.remove(key);
                    }
                };
                form.dataset.loaded = '1';
                setTimeout(loadDraft, 500);
            }

            // Save on input (debounced)
            const saveDraft = Utils.debounce(() => {
                const data = {};
                fields.forEach(f => {
                    if (!f.name) return;
                    if (f.type === 'checkbox') data[f.name] = f.checked;
                    else if (f.type === 'radio') {
                        if (f.checked) data[f.name] = f.value;
                    } else data[f.name] = f.value;
                });
                Utils.storage.set(key, data, 60 * 24); // 1 hari
                this.showIndicator('saved');
            }, 1000);

            fields.forEach(f => {
                f.addEventListener('input', () => {
                    this.showIndicator('saving');
                    saveDraft();
                });
            });

            // Clear on submit
            form.addEventListener('submit', () => {
                setTimeout(() => Utils.storage.remove(key), 100);
            });
        },

        showIndicator(state) {
            let ind = document.querySelector('.admin-autosave-indicator');
            if (!ind) {
                ind = document.createElement('div');
                ind.className = 'admin-autosave-indicator';
                ind.style.cssText = `
                    position: fixed; bottom: 2rem; right: 2rem;
                    padding: 0.65rem 1.1rem; border-radius: 999px;
                    font-size: 0.82rem; font-weight: 600;
                    z-index: 99; display: flex; align-items: center; gap: 0.5rem;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
                    transition: all 0.3s;
                    border: 1px solid var(--border);
                `;
                document.body.appendChild(ind);
            }

            if (state === 'saving') {
                ind.textContent = '⏳ Menyimpan draft...';
                ind.style.background = '#fef3c7';
                ind.style.color = '#92400e';
                ind.style.borderColor = '#fcd34d';
            } else {
                ind.textContent = '✅ Draft tersimpan';
                ind.style.background = '#dcfce7';
                ind.style.color = '#166534';
                ind.style.borderColor = '#86efac';
                setTimeout(() => {
                    ind.style.opacity = '0';
                    ind.style.transform = 'translateY(10px)';
                    setTimeout(() => {
                        ind.style.opacity = '1';
                        ind.style.transform = '';
                    }, 3000);
                }, 2000);
            }
        }
    };

    // ================================================
    // 10. 📋 CLIPBOARD HELPER (NEW!)
    // ================================================
    const Clipboard = {
        init() {
            Utils.$$('[data-copy]').forEach(el => {
                el.style.cursor = 'pointer';
                el.addEventListener('click', async () => {
                    const text = el.dataset.copy || el.textContent.trim();
                    if (await Utils.copyToClipboard(text)) {
                        Toast.success('Berhasil disalin ke clipboard', 'Clipboard');
                        const original = el.innerHTML;
                        el.innerHTML = '✓ Tersalin';
                        setTimeout(() => el.innerHTML = original, 1500);
                    }
                });
            });
        },

        async copy(text) {
            return await Utils.copyToClipboard(text);
        }
    };

    // ================================================
    // 11. 🔄 LOADING OVERLAY (NEW!)
    // ================================================
    const Loading = {
        overlay: null,

        show(message = 'Memuat...') {
            if (this.overlay) return;
            this.overlay = document.createElement('div');
            this.overlay.className = 'admin-loading-overlay';
            this.overlay.style.cssText = `
                position: fixed; inset: 0;
                background: rgba(15, 23, 42, 0.75);
                backdrop-filter: blur(8px);
                z-index: 10004; display: flex;
                align-items: center; justify-content: center;
                flex-direction: column; gap: 1rem;
                animation: modalFadeIn 0.2s;
            `;
            this.overlay.innerHTML = `
                <div style="
                    width: 60px; height: 60px; border: 4px solid rgba(255,255,255,0.2);
                    border-top-color: #fff; border-radius: 50%;
                    animation: spin 0.8s linear infinite;
                "></div>
                <div style="color: #fff; font-size: 1rem; font-weight: 600;">
                    ${Utils.escapeHtml(message)}
                </div>
            `;
            document.body.appendChild(this.overlay);
            document.body.style.overflow = 'hidden';
        },

        hide() {
            if (!this.overlay) return;
            this.overlay.style.animation = 'modalFadeOut 0.2s forwards';
            setTimeout(() => {
                this.overlay?.remove();
                this.overlay = null;
                document.body.style.overflow = '';
            }, 200);
        }
    };

    // Loading spinner animation
    const loadingStyle = document.createElement('style');
    loadingStyle.textContent = `@keyframes spin { to { transform: rotate(360deg); } }`;
    document.head.appendChild(loadingStyle);

    // ================================================
    // 12. 🎨 THEME MANAGER (NEW!)
    // ================================================
    const ThemeManager = {
        init() {
            // Already handled in footer.php, but expose API
            this.apply();
        },

        apply() {
            const saved = localStorage.getItem('admin_theme');
            const system = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = saved || (system ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        },

        toggle() {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('admin_theme', next);
            Toast.info(`Tema diubah ke ${next === 'dark' ? 'Gelap' : 'Terang'}`, 'Tema');
        },

        isDark() {
            return document.documentElement.getAttribute('data-theme') === 'dark';
        }
    };

    // ================================================
    // 13. 📊 ANALYTICS TRACKER (NEW!)
    // ================================================
    const Analytics = {
        init() {
            this.trackPageView();
            this.trackInteractions();
        },

        trackPageView() {
            const key = 'admin_analytics';
            const data = Utils.storage.get(key) || { pages: {}, actions: 0, sessions: 0 };
            const path = window.location.pathname;
            data.pages[path] = (data.pages[path] || 0) + 1;
            Utils.storage.set(key, data, 60 * 24 * 30); // 30 hari
        },

        trackInteractions() {
            document.addEventListener('click', (e) => {
                if (e.target.closest('button, a')) {
                    const key = 'admin_analytics';
                    const data = Utils.storage.get(key) || { pages: {}, actions: 0 };
                    data.actions = (data.actions || 0) + 1;
                    Utils.storage.set(key, data, 60 * 24 * 30);
                }
            }, { passive: true });
        },

        getData() {
            return Utils.storage.get('admin_analytics') || {};
        }
    };

    // ================================================
    // 14. 🔍 SMART SEARCH (NEW!)
    // ================================================
    const SmartSearch = {
        init() {
            Utils.$$('input[data-smart-search]').forEach(input => {
                const target = input.dataset.smartSearch;
                input.addEventListener('input', Utils.debounce(() => {
                    this.filter(target, input.value);
                }, 200));
            });
        },

        filter(targetSelector, query) {
            const items = Utils.$$(targetSelector);
            const q = query.toLowerCase().trim();
            let visible = 0;

            items.forEach(item => {
                const text = (item.dataset.searchText || item.textContent).toLowerCase();
                const match = !q || text.includes(q);
                item.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            return visible;
        }
    };

    // ================================================
    // 15. 🎯 SCROLL SPY (NEW!)
    // ================================================
    const ScrollSpy = {
        init() {
            Utils.$$('[data-spy]').forEach(nav => {
                this.setup(nav);
            });
        },

        setup(nav) {
            const targets = Utils.$$(nav.dataset.spy);
            if (targets.length === 0) return;

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.id;
                        nav.querySelectorAll('a').forEach(a => {
                            a.classList.toggle('active', a.getAttribute('href') === '#' + id);
                        });
                    }
                });
            }, { rootMargin: '-20% 0px -70% 0px' });

            targets.forEach(t => observer.observe(t));
        }
    };

    // ================================================
    // 16. 📏 PROGRESS TRACKER (NEW!)
    // ================================================
    const ProgressTracker = {
        create(container, steps, currentStep = 0) {
            const html = `
                <div style="display: flex; gap: 0.5rem; margin-bottom: 2rem;">
                    ${steps.map((step, i) => `
                        <div style="flex: 1; display: flex; flex-direction: column; gap: 0.5rem;">
                            <div style="
                                height: 6px; border-radius: 999px;
                                background: ${i <= currentStep ? 'var(--primary)' : 'var(--bg-tertiary)'};
                                transition: background 0.3s;
                            "></div>
                            <div style="
                                font-size: 0.75rem; font-weight: 600;
                                color: ${i <= currentStep ? 'var(--primary)' : 'var(--text-muted)'};
                            ">${i + 1}. ${Utils.escapeHtml(step)}</div>
                        </div>
                    `).join('')}
                </div>
            `;
            const el = typeof container === 'string' ? document.querySelector(container) : container;
            if (el) el.innerHTML = html;
        }
    };

    // ================================================
    // 17. 📜 CONFIRM DANGER ACTIONS (NEW!)
    // ================================================
    const DangerActions = {
        init() {
            Utils.$$('[data-confirm]').forEach(el => {
                el.addEventListener('click', async (e) => {
                    const message = el.dataset.confirm;
                    const confirmed = await Modal.confirm(message, {
                        title: 'Konfirmasi Aksi',
                        icon: '⚠️',
                        confirmText: 'Ya, Lanjutkan',
                        confirmColor: '#dc2626',
                        cancelText: 'Batal'
                    });
                    if (!confirmed) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                });
            });

            // Type-to-confirm for dangerous actions
            Utils.$$('[data-confirm-type]').forEach(el => {
                el.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const required = el.dataset.confirmType;
                    const message = el.dataset.confirmMessage || `Ketik "${required}" untuk melanjutkan`;

                    const result = await Modal.prompt(message, {
                        title: 'Konfirmasi Berbahaya',
                        icon: '⚠️',
                        placeholder: `Ketik: ${required}`
                    });

                    if (result === required) {
                        // Allow the action
                        if (el.tagName === 'A') window.location.href = el.href;
                        else if (el.tagName === 'FORM') el.submit();
                        else if (el.onclick) el.onclick();
                    } else if (result !== null) {
                        Toast.error('Teks tidak cocok. Aksi dibatalkan.', 'Dibatalkan');
                    }
                });
            });
        }
    };

    // ================================================
    // 🎬 INITIALIZE EVERYTHING
    // ================================================
    document.addEventListener('DOMContentLoaded', () => {
        // Core
        CommandPalette.init();
        DragDrop.init();
        Counter.init();
        FormValidator.init();
        TableEnhancer.init();
        KeyboardShortcuts.init();
        AutoSave.init();

        // New features
        Clipboard.init();
        ThemeManager.init();
        Analytics.init();
        SmartSearch.init();
        ScrollSpy.init();
        DangerActions.init();

        // Console branding
        console.log('%c🎓 FKIP UNIMOF Admin Panel', 'color: #0a6847; font-size: 22px; font-weight: bold;');
        console.log('%cSUPER EXTREME ULTIMATE Edition - v2.0.0', 'color: #16a34a; font-size: 13px;');
        console.log('%cTekan Ctrl+K untuk Command Palette', 'color: #64748b; font-size: 11px;');
        console.log('%cModules loaded: 17 (Toast, Modal, CommandPalette, DragDrop, Counter, FormValidator, TableEnhancer, KeyboardShortcuts, AutoSave, Clipboard, Loading, ThemeManager, Analytics, SmartSearch, ScrollSpy, ProgressTracker, DangerActions)', 'color: #64748b; font-size: 10px;');
    });

    // ================================================
    // 🌐 EXPOSE TO WINDOW (GLOBAL API)
    // ================================================
    window.AdminPanel = {
        Utils,
        Toast,
        Modal,
        CommandPalette,
        DragDrop,
        Counter,
        FormValidator,
        TableEnhancer,
        KeyboardShortcuts,
        AutoSave,
        Clipboard,
        Loading,
        ThemeManager,
        Analytics,
        SmartSearch,
        ScrollSpy,
        ProgressTracker,
        DangerActions,
        // Version
        version: '2.0.0'
    };

    // ================================================
    // 🔗 COMPATIBILITY WRAPPERS (for footer.php features)
    // ================================================
    // Expose showToast for compatibility with footer.php
    window.showToast = (title, message, type = 'info') => {
        Toast.show(message, type, title);
    };

    // Expose openCommandPalette for compatibility
    window.openCommandPalette = () => CommandPalette.open();

})();