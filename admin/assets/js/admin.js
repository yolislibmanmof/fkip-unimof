// =====================================================
// FKIP UNIMOF - Admin Panel EXTREME SCRIPTS
// Advanced Interactions & Features
// =====================================================

(function() {
    'use strict';
    
    // ===== 1. Toast Notification System =====
    const Toast = {
        container: null,
        
        init() {
            this.container = document.createElement('div');
            this.container.className = 'admin-toast-container';
            document.body.appendChild(this.container);
        },
        
        show(message, type = 'success', title = null, duration = 5000) {
            if (!this.container) this.init();
            
            const toast = document.createElement('div');
            toast.className = `admin-toast ${type} admin-animate-slide-right`;
            
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
                info: 'Info'
            };
            
            toast.innerHTML = `
                <span class="admin-toast-icon">${icons[type] || icons.info}</span>
                <div class="admin-toast-content">
                    <div class="admin-toast-title">${title || titles[type]}</div>
                    <div class="admin-toast-message">${message}</div>
                </div>
                <button class="admin-toast-close">&times;</button>
            `;
            
            toast.querySelector('.admin-toast-close').addEventListener('click', () => {
                this.remove(toast);
            });
            
            this.container.appendChild(toast);
            
            if (duration > 0) {
                setTimeout(() => this.remove(toast), duration);
            }
            
            return toast;
        },
        
        remove(toast) {
            toast.style.animation = 'adminFadeIn 0.3s reverse';
            setTimeout(() => toast.remove(), 300);
        },
        
        success(message, title = null, duration = 5000) {
            return this.show(message, 'success', title, duration);
        },
        
        error(message, title = null, duration = 5000) {
            return this.show(message, 'error', title, duration);
        },
        
        warning(message, title = null, duration = 5000) {
            return this.show(message, 'warning', title, duration);
        },
        
        info(message, title = null, duration = 5000) {
            return this.show(message, 'info', title, duration);
        }
    };
    
    // ===== 2. Modal System =====
    const Modal = {
        currentModal: null,
        
        create(html) {
            const overlay = document.createElement('div');
            overlay.className = 'admin-modal-overlay';
            overlay.innerHTML = html;
            document.body.appendChild(overlay);
            
            // Trigger animation
            setTimeout(() => overlay.classList.add('active'), 10);
            
            this.currentModal = overlay;
            
            // Close on overlay click
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    this.close();
                }
            });
            
            // Close on ESC
            const closeOnEsc = (e) => {
                if (e.key === 'Escape') {
                    this.close();
                    document.removeEventListener('keydown', closeOnEsc);
                }
            };
            document.addEventListener('keydown', closeOnEsc);
            
            return overlay;
        },
        
        close() {
            if (!this.currentModal) return;
            
            this.currentModal.classList.remove('active');
            setTimeout(() => {
                this.currentModal.remove();
                this.currentModal = null;
            }, 300);
        }
    };
    
    // ===== 3. Command Palette (Ctrl+K) =====
    const CommandPalette = {
        isOpen: false,
        items: [],
        
        init() {
            this.registerDefaultCommands();
            this.createPalette();
            this.setupKeyboardShortcut();
        },
        
        registerDefaultCommands() {
            this.items = [
                { id: 'dashboard', title: 'Dashboard', desc: 'Kembali ke dashboard', icon: '📊', shortcut: 'G D', action: () => window.location.href = 'dashboard.php' },
                { id: 'berita', title: 'Kelola Berita', desc: 'CRUD artikel berita', icon: '📰', shortcut: 'G B', action: () => window.location.href = 'berita.php' },
                { id: 'dosen', title: 'Data Dosen', desc: 'Kelola data dosen', icon: '👨‍🏫', shortcut: 'G F', action: () => window.location.href = 'dosen.php' },
                { id: 'prodi', title: 'Program Studi', desc: 'Kelola program studi', icon: '🎓', shortcut: 'G P', action: () => window.location.href = 'program.php' },
                { id: 'pengaturan', title: 'Pengaturan', desc: 'Konfigurasi sistem', icon: '⚙️', shortcut: 'G S', action: () => window.location.href = 'pengaturan.php' },
                { id: 'darkmode', title: 'Toggle Dark Mode', desc: 'Ganti tema terang/gelap', icon: '🌓', shortcut: 'D', action: () => document.getElementById('themeToggle')?.click() },
                { id: 'logout', title: 'Logout', desc: 'Keluar dari sistem', icon: '🚪', shortcut: 'Q', action: () => { if(confirm('Yakin ingin logout?')) window.location.href = 'logout.php'; } }
            ];
        },
        
        createPalette() {
            const palette = document.createElement('div');
            palette.className = 'admin-command-palette';
            palette.innerHTML = `
                <div class="admin-command-box">
                    <input type="text" class="admin-command-input" placeholder="Ketik perintah atau cari menu...">
                    <div class="admin-command-results"></div>
                </div>
            `;
            document.body.appendChild(palette);
            
            const input = palette.querySelector('.admin-command-input');
            const results = palette.querySelector('.admin-command-results');
            
            input.addEventListener('input', (e) => {
                this.filterAndRender(e.target.value, results);
            });
            
            input.addEventListener('keydown', (e) => {
                this.handleNavigation(e, results);
            });
            
            palette.addEventListener('click', (e) => {
                if (e.target === palette) {
                    this.close();
                }
            });
        },
        
        filterAndRender(query, container) {
            const filtered = this.items.filter(item => 
                item.title.toLowerCase().includes(query.toLowerCase()) ||
                item.desc.toLowerCase().includes(query.toLowerCase())
            );
            
            if (filtered.length === 0) {
                container.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--admin-text-muted);">Tidak ada hasil</div>';
                return;
            }
            
            container.innerHTML = filtered.map((item, index) => `
                <div class="admin-command-item ${index === 0 ? 'active' : ''}" data-index="${index}" data-id="${item.id}">
                    <div class="admin-command-item-icon">${item.icon}</div>
                    <div class="admin-command-item-text">
                        <div class="admin-command-item-title">${item.title}</div>
                        <div class="admin-command-item-desc">${item.desc}</div>
                    </div>
                    <span class="admin-command-shortcut">${item.shortcut}</span>
                </div>
            `).join('');
            
            container.querySelectorAll('.admin-command-item').forEach(item => {
                item.addEventListener('click', () => {
                    const cmd = filtered.find(i => i.id === item.dataset.id);
                    if (cmd) {
                        cmd.action();
                        this.close();
                    }
                });
            });
        },
        
        handleNavigation(e, container) {
            const items = container.querySelectorAll('.admin-command-item');
            const active = container.querySelector('.admin-command-item.active');
            let index = active ? parseInt(active.dataset.index) : -1;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (index < items.length - 1) {
                    items[index]?.classList.remove('active');
                    items[index + 1]?.classList.add('active');
                    items[index + 1]?.scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (index > 0) {
                    items[index]?.classList.remove('active');
                    items[index - 1]?.classList.add('active');
                    items[index - 1]?.scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                active?.click();
            }
        },
        
        setupKeyboardShortcut() {
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    this.open();
                }
            });
        },
        
        open() {
            const palette = document.querySelector('.admin-command-palette');
            palette.classList.add('active');
            palette.querySelector('.admin-command-input').value = '';
            palette.querySelector('.admin-command-input').focus();
            this.isOpen = true;
        },
        
        close() {
            const palette = document.querySelector('.admin-command-palette');
            palette.classList.remove('active');
            this.isOpen = false;
        }
    };
    
    // ===== 4. Drag and Drop Upload =====
    const DragDrop = {
        init() {
            document.querySelectorAll('.admin-dropzone').forEach(zone => {
                this.setupZone(zone);
            });
        },
        
        setupZone(zone) {
            const input = zone.querySelector('input[type="file"]');
            
            ['dragenter', 'dragover'].forEach(event => {
                zone.addEventListener(event, (e) => {
                    e.preventDefault();
                    zone.classList.add('dragover');
                });
            });
            
            ['dragleave', 'drop'].forEach(event => {
                zone.addEventListener(event, (e) => {
                    e.preventDefault();
                    zone.classList.remove('dragover');
                });
            });
            
            zone.addEventListener('drop', (e) => {
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    this.handleFiles(files, zone);
                }
            });
            
            zone.addEventListener('click', () => input.click());
            
            input.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    this.handleFiles(e.target.files, zone);
                }
            });
        },
        
        handleFiles(files, zone) {
            const preview = zone.querySelector('.admin-dropzone-preview');
            if (preview) {
                const file = files[0];
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview" style="max-height: 200px; border-radius: var(--admin-radius);">
                        <p style="margin-top: 1rem; font-weight: 600;">${file.name}</p>
                        <p style="font-size: 0.85rem; color: var(--admin-text-muted);">${this.formatFileSize(file.size)}</p>
                    `;
                };
                reader.readAsDataURL(file);
            }
        },
        
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    };
    
    // ===== 5. Counter Animation =====
    const Counter = {
        init() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.animate(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });
            
            document.querySelectorAll('[data-count]').forEach(el => {
                observer.observe(el);
            });
        },
        
        animate(element) {
            const target = parseInt(element.getAttribute('data-count')) || 0;
            const duration = 2000;
            const start = performance.now();
            
            const step = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                element.textContent = Math.floor(eased * target).toLocaleString('id-ID');
                
                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            };
            
            requestAnimationFrame(step);
        }
    };
    
    // ===== 6. Form Validation =====
    const FormValidator = {
        init() {
            document.querySelectorAll('form[data-validate]').forEach(form => {
                this.setupForm(form);
            });
        },
        
        setupForm(form) {
            form.addEventListener('submit', (e) => {
                let isValid = true;
                
                form.querySelectorAll('[required]').forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('error');
                        isValid = false;
                    } else {
                        field.classList.remove('error');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    Toast.error('Mohon lengkapi semua field yang wajib diisi.', 'Validasi Error');
                }
            });
            
            // Real-time validation
            form.querySelectorAll('[required]').forEach(field => {
                field.addEventListener('blur', () => {
                    if (!field.value.trim()) {
                        field.classList.add('error');
                    } else {
                        field.classList.remove('error');
                    }
                });
            });
        }
    };
    
    // ===== 7. Table Enhancements =====
    const TableEnhancer = {
        init() {
            this.setupSortableTables();
            this.setupSearchableTables();
        },
        
        setupSortableTables() {
            document.querySelectorAll('.admin-table[data-sortable]').forEach(table => {
                const headers = table.querySelectorAll('th[data-sortable]');
                headers.forEach(header => {
                    header.style.cursor = 'pointer';
                    header.addEventListener('click', () => this.sortTable(table, header));
                });
            });
        },
        
        sortTable(table, header) {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const index = Array.from(header.parentElement.children).indexOf(header);
            const isAsc = header.classList.contains('asc');
            
            rows.sort((a, b) => {
                const aText = a.children[index].textContent.trim();
                const bText = b.children[index].textContent.trim();
                return isAsc ? aText.localeCompare(bText) : bText.localeCompare(aText);
            });
            
            rows.forEach(row => tbody.appendChild(row));
            header.classList.toggle('asc');
        },
        
        setupSearchableTables() {
            document.querySelectorAll('.admin-table[data-searchable]').forEach(table => {
                const searchInput = document.createElement('input');
                searchInput.type = 'text';
                searchInput.className = 'admin-form-input';
                searchInput.placeholder = 'Cari data...';
                searchInput.style.marginBottom = '1rem';
                
                searchInput.addEventListener('input', (e) => {
                    this.filterTable(table, e.target.value);
                });
                
                table.parentElement.insertBefore(searchInput, table);
            });
        },
        
        filterTable(table, query) {
            const rows = table.querySelectorAll('tbody tr');
            const lowerQuery = query.toLowerCase();
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(lowerQuery) ? '' : 'none';
            });
        }
    };
    
    // ===== 8. Keyboard Shortcuts =====
    const KeyboardShortcuts = {
        init() {
            document.addEventListener('keydown', (e) => {
                // Ctrl/Cmd + K: Command Palette
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    CommandPalette.open();
                }
                
                // Escape: Close modals/palette
                if (e.key === 'Escape') {
                    Modal.close();
                    CommandPalette.close();
                }
                
                // Ctrl/Cmd + S: Save form
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    const form = document.querySelector('form');
                    if (form) {
                        e.preventDefault();
                        form.dispatchEvent(new Event('submit'));
                    }
                }
            });
        }
    };
    
    // ===== 9. Auto-save Indicator =====
    const AutoSave = {
        init() {
            let timeout;
            document.querySelectorAll('form input, form textarea, form select').forEach(field => {
                field.addEventListener('input', () => {
                    clearTimeout(timeout);
                    this.showSaving();
                    timeout = setTimeout(() => this.showSaved(), 1000);
                });
            });
        },
        
        showSaving() {
            let indicator = document.querySelector('.admin-autosave-indicator');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.className = 'admin-autosave-indicator';
                indicator.style.cssText = 'position: fixed; bottom: 1rem; right: 1rem; padding: 0.5rem 1rem; background: var(--admin-bg-secondary); border: 1px solid var(--admin-border); border-radius: var(--admin-radius); font-size: 0.85rem; color: var(--admin-text-muted); z-index: 100;';
                document.body.appendChild(indicator);
            }
            indicator.textContent = '💾 Menyimpan...';
            indicator.style.background = '#fef3c7';
            indicator.style.color = '#92400e';
        },
        
        showSaved() {
            const indicator = document.querySelector('.admin-autosave-indicator');
            if (indicator) {
                indicator.textContent = '✅ Tersimpan';
                indicator.style.background = '#dcfce7';
                indicator.style.color = '#166534';
                setTimeout(() => indicator.remove(), 2000);
            }
        }
    };
    
    // ===== 10. Initialize Everything =====
    document.addEventListener('DOMContentLoaded', () => {
        Toast.init();
        CommandPalette.init();
        DragDrop.init();
        Counter.init();
        FormValidator.init();
        TableEnhancer.init();
        KeyboardShortcuts.init();
        AutoSave.init();
        
        // Console branding
        console.log('%c FKIP UNIMOF Admin Panel', 'color: #0a6847; font-size: 24px; font-weight: bold;');
        console.log('%cEXTREME MULTIMATE Edition - Sistem siap!', 'color: #16a34a; font-size: 14px;');
        console.log('%cTekan Ctrl+K untuk Command Palette', 'color: #64748b; font-size: 12px;');
    });
    
    // Expose to window for global access
    window.AdminPanel = {
        Toast,
        Modal,
        CommandPalette,
        DragDrop,
        Counter,
        FormValidator,
        TableEnhancer
    };
    
})();