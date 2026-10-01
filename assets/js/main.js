/**
 * ============================================================================
 * FKIP UNIMOF - MAIN.JS (EXTREME MULTIMATE VERSION)
 * ----------------------------------------------------------------------------
 * Global animations, interactions, utilities, dan performance optimizations
 * untuk seluruh website FKIP UNIMOF.
 *
 * @version     3.0.0-multimate
 * @author      FKIP UNIMOF Dev Team
 * @updated     2026-09-27
 * ============================================================================
 */

(function () {
    'use strict';

    // =====================================================================
    // 0. GLOBAL CONFIGURATION
    // =====================================================================
    const CONFIG = {
        theme: {
            key: 'fkip_theme_v2',
            default: 'light',
        },
        cookies: {
            key: 'fkip_cookies_accepted',
            delay: 2000,
        },
        scroll: {
            headerThreshold: 50,
            backToTopThreshold: 500,
            smoothOffset: 100, // offset untuk sticky header
        },
        particles: {
            count: 30,
            colors: ['#0a6847', '#16a34a', '#f5a623', '#3b82f6'],
            minSize: 2,
            maxSize: 6,
        },
        counter: {
            duration: 2000,
            easing: (t) => 1 - Math.pow(1 - t, 3), // easeOutCubic
        },
        observer: {
            threshold: 0.15,
            rootMargin: '0px 0px -50px 0px',
        },
    };

    // =====================================================================
    // 1. UTILITY FUNCTIONS
    // =====================================================================
    const Utils = {
        $: (selector, context = document) => context.querySelector(selector),
        $$: (selector, context = document) => Array.from(context.querySelectorAll(selector)),

        debounce(fn, wait = 100) {
            let timeout;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => fn.apply(this, args), wait);
            };
        },

        throttle(fn, limit = 100) {
            let inThrottle = false;
            return function (...args) {
                if (!inThrottle) {
                    fn.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => (inThrottle = false), limit);
                }
            };
        },

        prefersReducedMotion() {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        },

        prefersDarkMode() {
            return window.matchMedia('(prefers-color-scheme: dark)').matches;
        },

        isMobile() {
            return window.innerWidth <= 968;
        },

        isOnline() {
            return navigator.onLine;
        },

        formatNumber(num) {
            return new Intl.NumberFormat('id-ID').format(num);
        },

        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },
    };

    // =====================================================================
    // 2. TOAST NOTIFICATION SYSTEM (GLOBAL)
    // =====================================================================
    const Toast = {
        _container: null,

        _getContainer() {
            if (!this._container) {
                this._container = Utils.$('#toastContainer');
                if (!this._container) {
                    this._container = document.createElement('div');
                    this._container.id = 'toastContainer';
                    this._container.style.cssText =
                        'position:fixed;bottom:2rem;right:2rem;z-index:99999;display:flex;flex-direction:column;gap:0.5rem;max-width:90%;pointer-events:none;';
                    document.body.appendChild(this._container);
                }
            }
            return this._container;
        },

        show(message, options = {}) {
            const { type = 'info', duration = 3500, icon = 'ℹ️' } = options;
            const container = this._getContainer();

            const toast = document.createElement('div');
            toast.style.cssText = `
                background: var(--bg-primary, #fff);
                border: 1px solid var(--border, #e5e7eb);
                border-left: 4px solid ${this._getColor(type)};
                border-radius: 12px;
                padding: 0.9rem 1.25rem;
                box-shadow: 0 10px 30px rgba(0,0,0,0.12);
                display: flex;
                align-items: center;
                gap: 0.75rem;
                font-size: 0.9rem;
                font-weight: 600;
                color: var(--text-primary, #0f172a);
                transform: translateX(150%);
                transition: transform 0.4s cubic-bezier(0.4,0,0.2,1);
                pointer-events: auto;
                min-width: 260px;
            `;

            const icons = { success: '✓', error: '✕', warning: '⚠️', info: 'ℹ️' };
            toast.innerHTML = `
                <span style="font-size:1.15rem;">${icon || icons[type] || icons.info}</span>
                <span style="flex:1;">${Utils.escapeHtml(message)}</span>
            `;

            container.appendChild(toast);

            requestAnimationFrame(() => {
                toast.style.transform = 'translateX(0)';
            });

            setTimeout(() => {
                toast.style.transform = 'translateX(150%)';
                setTimeout(() => toast.remove(), 400);
            }, duration);
        },

        _getColor(type) {
            const colors = {
                success: '#10b981',
                error: '#ef4444',
                warning: '#f59e0b',
                info: '#3b82f6',
            };
            return colors[type] || colors.info;
        },

        success(msg) { this.show(msg, { type: 'success' }); },
        error(msg)   { this.show(msg, { type: 'error' }); },
        warning(msg) { this.show(msg, { type: 'warning' }); },
        info(msg)    { this.show(msg, { type: 'info' }); },
    };

    // Expose globally
    window.FKIPToast = Toast;

    // =====================================================================
    // 3. PRELOADER
    // =====================================================================
    const Preloader = {
        init() {
            window.addEventListener('load', () => {
                const preloader = Utils.$('#preloader');
                if (!preloader) return;
                setTimeout(() => {
                    preloader.classList.add('hidden');
                    setTimeout(() => preloader.remove(), 500);
                    document.body.classList.add('page-loaded');
                }, 800);
            });
        },
    };

    // =====================================================================
    // 4. THEME TOGGLE (DARK MODE) — Dengan system preference detection
    // =====================================================================
    const ThemeManager = {
        init() {
            const toggle = Utils.$('#themeToggle');
            const icon = toggle?.querySelector('.theme-icon-premium') || toggle?.querySelector('.theme-icon');

            // Load theme: localStorage > system preference > default
            const saved = localStorage.getItem(CONFIG.theme.key);
            const initialTheme = saved || (Utils.prefersDarkMode() ? 'dark' : CONFIG.theme.default);

            this._apply(initialTheme, icon);

            // Toggle on click
            toggle?.addEventListener('click', () => {
                const current = document.documentElement.getAttribute('data-theme') || 'light';
                const next = current === 'dark' ? 'light' : 'dark';
                localStorage.setItem(CONFIG.theme.key, next);
                this._apply(next, icon);
                Toast.info(`Mode ${next === 'dark' ? 'gelap' : 'terang'} diaktifkan`, { icon: next === 'dark' ? '🌙' : '☀️' });
            });

            // Listen to system preference changes
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (!localStorage.getItem(CONFIG.theme.key)) {
                    this._apply(e.matches ? 'dark' : 'light', icon);
                }
            });
        },

        _apply(theme, icon) {
            document.documentElement.setAttribute('data-theme', theme);
            if (icon) icon.textContent = theme === 'dark' ? '☀️' : '🌙';
        },
    };

    // =====================================================================
    // 5. NAVBAR SCROLL EFFECT + SCROLL PROGRESS INDICATOR
    // =====================================================================
    const ScrollEffects = {
        _progressBar: null,

        init() {
            const header = Utils.$('#header');
            const backToTop = Utils.$('#backToTop');

            // Create scroll progress bar
            this._progressBar = document.createElement('div');
            this._progressBar.id = 'scrollProgress';
            this._progressBar.style.cssText =
                'position:fixed;top:0;left:0;height:3px;background:linear-gradient(90deg,#0a6847,#f5a623);width:0%;z-index:9999;transition:width 0.1s ease-out;pointer-events:none;';
            document.body.appendChild(this._progressBar);

            const onScroll = Utils.throttle(() => {
                const currentScroll = window.pageYOffset;
                const docHeight = document.documentElement.scrollHeight - window.innerHeight;
                const progress = docHeight > 0 ? (currentScroll / docHeight) * 100 : 0;

                if (this._progressBar) this._progressBar.style.width = progress + '%';

                if (header) {
                    header.classList.toggle('scrolled', currentScroll > CONFIG.scroll.headerThreshold);
                }
                if (backToTop) {
                    backToTop.classList.toggle('visible', currentScroll > CONFIG.scroll.backToTopThreshold);
                }
            }, 16);

            window.addEventListener('scroll', onScroll, { passive: true });

            backToTop?.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        },
    };

    // =====================================================================
    // 6. PREMIUM MOBILE NAVIGATION
    // =====================================================================
    const MobileNav = {
        init() {
            const navToggle = Utils.$('#navToggle');
            const navMenu = Utils.$('#navMenu');
            const header = Utils.$('#header');

            if (!navToggle || !navMenu) return;

            const spans = navToggle.querySelectorAll('span');

            const closeMenu = () => {
                navMenu.classList.remove('open');
                if (spans[0]) spans[0].style.transform = 'none';
                if (spans[1]) spans[1].style.opacity = '1';
                if (spans[2]) spans[2].style.transform = 'none';
                document.body.style.overflow = '';
                Utils.$$('.nav-dropdown-premium').forEach((d) => d.classList.remove('mobile-open'));
            };

            navToggle.addEventListener('click', () => {
                const isOpen = navMenu.classList.toggle('open');
                if (isOpen) {
                    if (spans[0]) spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
                    if (spans[1]) spans[1].style.opacity = '0';
                    if (spans[2]) spans[2].style.transform = 'rotate(-45deg) translate(5px, -5px)';
                    document.body.style.overflow = 'hidden';
                } else {
                    closeMenu();
                }
            });

            // Mobile dropdown accordion
            Utils.$$('.nav-dropdown-premium').forEach((dropdown) => {
                const link = dropdown.querySelector('.nav-link-premium');
                link?.addEventListener('click', (e) => {
                    if (Utils.isMobile()) {
                        e.preventDefault();
                        dropdown.classList.toggle('mobile-open');
                    }
                });
            });

            // Close when clicking outside
            document.addEventListener('click', (e) => {
                if (navMenu.classList.contains('open') && header && !header.contains(e.target)) {
                    closeMenu();
                }
            });

            // Close on Escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && navMenu.classList.contains('open')) closeMenu();
            });
        },
    };

    // =====================================================================
    // 7. COOKIE CONSENT
    // =====================================================================
    const CookieConsent = {
        init() {
            const consent = Utils.$('#cookieConsent');
            const accept = Utils.$('#acceptCookies');
            const decline = Utils.$('#declineCookies');

            if (!consent) return;
            if (localStorage.getItem(CONFIG.cookies.key)) return;

            setTimeout(() => consent.classList.add('show'), CONFIG.cookies.delay);

            accept?.addEventListener('click', () => {
                localStorage.setItem(CONFIG.cookies.key, 'true');
                consent.classList.remove('show');
                Toast.success('Preferensi cookie disimpan');
            });

            decline?.addEventListener('click', () => {
                localStorage.setItem(CONFIG.cookies.key, 'declined');
                consent.classList.remove('show');
            });
        },
    };

    // =====================================================================
    // 8. PARTICLES ANIMATION (dengan reduced-motion respect)
    // =====================================================================
    const Particles = {
        init() {
            if (Utils.prefersReducedMotion()) return;

            const container = Utils.$('#particles');
            if (!container) return;

            for (let i = 0; i < CONFIG.particles.count; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                const size =
                    CONFIG.particles.minSize +
                    Math.random() * (CONFIG.particles.maxSize - CONFIG.particles.minSize);
                particle.style.cssText = `
                    left: ${Math.random() * 100}%;
                    animation-delay: ${Math.random() * 20}s;
                    animation-duration: ${15 + Math.random() * 15}s;
                    background: ${CONFIG.particles.colors[Math.floor(Math.random() * CONFIG.particles.colors.length)]};
                    width: ${size}px;
                    height: ${size}px;
                `;
                container.appendChild(particle);
            }
        },
    };

    // =====================================================================
    // 9. COUNTER ANIMATION (dengan requestAnimationFrame — lebih smooth)
    // =====================================================================
    const Counter = {
        animate(element) {
            const target = parseInt(element.getAttribute('data-count')) || 0;
            if (target === 0) {
                element.textContent = '0';
                return;
            }
            if (Utils.prefersReducedMotion()) {
                element.textContent = Utils.formatNumber(target);
                return;
            }

            const duration = CONFIG.counter.duration;
            const easing = CONFIG.counter.easing;
            const start = performance.now();

            const step = (now) => {
                const elapsed = now - start;
                const progress = Math.min(elapsed / duration, 1);
                const value = Math.floor(easing(progress) * target);
                element.textContent = Utils.formatNumber(value);
                if (progress < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        },
    };

    // =====================================================================
    // 10. INTERSECTION OBSERVER (AOS + Counters)
    // =====================================================================
    const ScrollObserver = {
        init() {
            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('aos-animate');
                            if (entry.target.hasAttribute('data-count')) {
                                Counter.animate(entry.target);
                            }
                            observer.unobserve(entry.target);
                        }
                    });
                },
                {
                    threshold: CONFIG.observer.threshold,
                    rootMargin: CONFIG.observer.rootMargin,
                }
            );

            Utils.$$('[data-aos], [data-count]').forEach((el) => observer.observe(el));
        },
    };

    // =====================================================================
    // 11. SMOOTH SCROLL (dengan offset untuk sticky header)
    // =====================================================================
    const SmoothScroll = {
        init() {
            document.addEventListener('click', (e) => {
                const anchor = e.target.closest('a[href^="#"]');
                if (!anchor) return;
                const href = anchor.getAttribute('href');
                if (!href || href === '#') return;

                const target = document.querySelector(href);
                if (!target) return;

                e.preventDefault();
                const headerHeight = Utils.$('#header')?.offsetHeight || 0;
                const top = target.getBoundingClientRect().top + window.pageYOffset - headerHeight - CONFIG.scroll.smoothOffset + 20;
                window.scrollTo({ top, behavior: 'smooth' });
            });
        },
    };

    // =====================================================================
    // 12. FORM VALIDATION (dengan toast feedback)
    // =====================================================================
    const FormValidator = {
        init() {
            Utils.$$('form[data-validate]').forEach((form) => {
                // Real-time validation
                form.querySelectorAll('[required]').forEach((field) => {
                    field.addEventListener('blur', () => this._validateField(field));
                    field.addEventListener('input', Utils.debounce(() => this._validateField(field), 300));
                });

                form.addEventListener('submit', (e) => {
                    let isValid = true;
                    form.querySelectorAll('[required]').forEach((field) => {
                        if (!this._validateField(field)) isValid = false;
                    });

                    // Email validation
                    form.querySelectorAll('input[type="email"]').forEach((field) => {
                        if (field.value && !this._isValidEmail(field.value)) {
                            field.style.borderColor = '#ef4444';
                            isValid = false;
                        }
                    });

                    if (!isValid) {
                        e.preventDefault();
                        Toast.error('Mohon lengkapi semua field dengan benar', { icon: '⚠️' });
                        const firstError = form.querySelector('[style*="border-color: rgb(239, 68, 68)"]');
                        firstError?.focus();
                    }
                });
            });
        },

        _validateField(field) {
            const value = field.value.trim();
            if (!value) {
                field.style.borderColor = '#ef4444';
                return false;
            }
            field.style.borderColor = '';
            return true;
        },

        _isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },
    };

    // =====================================================================
    // 13. PARALLAX HERO (desktop only + respect reduced-motion)
    // =====================================================================
    const Parallax = {
        init() {
            if (Utils.isMobile() || Utils.prefersReducedMotion()) return;

            const hero = Utils.$('.hero-bg, .about-hero-extreme::before, .detail-hero');
            if (!hero) return;

            const onScroll = Utils.throttle(() => {
                const scrolled = window.pageYOffset;
                if (scrolled < window.innerHeight) {
                    hero.style.transform = `translateY(${scrolled * 0.3}px)`;
                }
            }, 16);

            window.addEventListener('scroll', onScroll, { passive: true });
        },
    };

    // =====================================================================
    // 14. LAZY LOADING IMAGES (dengan network-aware)
    // =====================================================================
    const LazyLoad = {
        init() {
            if ('loading' in HTMLImageElement.prototype) {
                Utils.$$('img[loading="lazy"]').forEach((img) => {
                    if (img.dataset.src) img.src = img.dataset.src;
                });
            } else {
                // Fallback untuk browser lama
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) img.src = img.dataset.src;
                            observer.unobserve(img);
                        }
                    });
                });
                Utils.$$('img[data-src]').forEach((img) => observer.observe(img));
            }
        },
    };

    // =====================================================================
    // 15. UNIVERSAL IMAGE LIGHTBOX
    // =====================================================================
    const Lightbox = {
        _overlay: null,

        init() {
            Utils.$$('img[data-lightbox], .gallery-item-extreme img, .galeri-item-extreme img').forEach((img) => {
                img.style.cursor = 'zoom-in';
                img.addEventListener('click', () => this.open(img.src, img.alt));
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this._overlay) this.close();
            });
        },

        open(src, alt = '') {
            if (this._overlay) this.close();
            this._overlay = document.createElement('div');
            this._overlay.style.cssText = `
                position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:99998;
                display:flex;align-items:center;justify-content:center;cursor:zoom-out;
                animation:lbFadeIn 0.3s ease;padding:2rem;
            `;
            const img = document.createElement('img');
            img.src = src;
            img.alt = alt;
            img.style.cssText = 'max-width:90vw;max-height:90vh;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,0.5);';
            this._overlay.appendChild(img);
            this._overlay.addEventListener('click', () => this.close());
            document.body.appendChild(this._overlay);
            document.body.style.overflow = 'hidden';

            // Inject keyframes jika belum ada
            if (!Utils.$('#lb-styles')) {
                const style = document.createElement('style');
                style.id = 'lb-styles';
                style.textContent = '@keyframes lbFadeIn{from{opacity:0}to{opacity:1}}';
                document.head.appendChild(style);
            }
        },

        close() {
            if (this._overlay) {
                this._overlay.remove();
                this._overlay = null;
                document.body.style.overflow = '';
            }
        },
    };

    // =====================================================================
    // 16. GLOBAL KEYBOARD SHORTCUTS
    // =====================================================================
    const KeyboardShortcuts = {
        init() {
            document.addEventListener('keydown', (e) => {
                // Skip jika user sedang mengetik
                const tag = document.activeElement?.tagName;
                const isTyping = ['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || document.activeElement?.isContentEditable;

                if (e.key === 'Escape') {
                    // Tutup semua modal
                    Utils.$$('.modal-overlay.show').forEach((m) => m.classList.remove('show'));
                    Utils.$$('.modal-overlay-ext.show').forEach((m) => m.classList.remove('show'));
                    Lightbox.close();
                    document.body.style.overflow = '';
                }

                if (isTyping) return;

                // "/" untuk focus ke search
                if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                    const searchInput =
                        Utils.$('#searchInput') ||
                        Utils.$('#jurnalSearch') ||
                        Utils.$('#prestasiSearch') ||
                        Utils.$('#faqSearch') ||
                        Utils.$('#mitraSearch');
                    if (searchInput) {
                        e.preventDefault();
                        searchInput.focus();
                        Toast.info('Mode pencarian aktif', { icon: '🔍', duration: 1500 });
                    }
                }

                // "T" untuk scroll to top
                if (e.key.toLowerCase() === 't' && !e.ctrlKey && !e.metaKey) {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }

                // "D" untuk toggle dark mode
                if (e.key.toLowerCase() === 'd' && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
                    Utils.$('#themeToggle')?.click();
                }

                // "H" untuk scroll ke home / hero
                if (e.key.toLowerCase() === 'h' && !e.ctrlKey && !e.metaKey) {
                    const hero = Utils.$('#hero, .hero-extreme, .about-hero-extreme');
                    if (hero) hero.scrollIntoView({ behavior: 'smooth' });
                }

                // "?" untuk tampilkan help
                if (e.key === '?' || (e.shiftKey && e.key === '/')) {
                    Toast.info('Shortcuts: / (search) • T (top) • D (dark) • H (home) • Esc (close)', { duration: 5000 });
                }
            });
        },
    };

    // =====================================================================
    // 17. SMART PREFETCH (prefetch link on hover untuk navigasi lebih cepat)
    // =====================================================================
    const SmartPrefetch = {
        _prefetched: new Set(),

        init() {
            if (Utils.isMobile()) return; // Skip di mobile untuk hemat data
            if (!Utils.isOnline()) return;

            Utils.$$('a[href]').forEach((link) => {
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('http') || href.startsWith('mailto:') || href.startsWith('tel:')) return;
                if (href.endsWith('.pdf') || href.endsWith('.jpg') || href.endsWith('.png')) return;

                const prefetch = () => {
                    if (this._prefetched.has(href)) return;
                    this._prefetched.add(href);
                    const l = document.createElement('link');
                    l.rel = 'prefetch';
                    l.href = href;
                    document.head.appendChild(l);
                };

                link.addEventListener('mouseenter', prefetch, { once: true });
                link.addEventListener('focus', prefetch, { once: true });
            });
        },
    };

    // =====================================================================
    // 18. ONLINE / OFFLINE INDICATOR
    // =====================================================================
    const NetworkStatus = {
        init() {
            window.addEventListener('online', () => {
                Toast.success('Koneksi internet tersambung kembali', { icon: '📶' });
            });

            window.addEventListener('offline', () => {
                Toast.error('Koneksi internet terputus', { icon: '📡', duration: 5000 });
            });
        },
    };

    // =====================================================================
    // 19. PERFORMANCE MONITORING (Web Vitals)
    // =====================================================================
    const PerformanceMonitor = {
        init() {
            if (!('performance' in window)) return;

            // Report LCP
            try {
                new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    const last = entries[entries.length - 1];
                    if (last && last.startTime > 4000) {
                        console.warn('⚠️ [Performance] LCP lambat:', last.startTime.toFixed(0), 'ms');
                    }
                }).observe({ type: 'largest-contentful-paint', buffered: true });
            } catch (e) {}

            // Report CLS
            try {
                let clsValue = 0;
                new PerformanceObserver((list) => {
                    for (const entry of list.getEntries()) {
                        if (!entry.hadRecentInput) clsValue += entry.value;
                    }
                    if (clsValue > 0.25) {
                        console.warn('⚠️ [Performance] CLS tinggi:', clsValue.toFixed(3));
                    }
                }).observe({ type: 'layout-shift', buffered: true });
            } catch (e) {}
        },
    };

    // =====================================================================
    // 20. VIEW TRANSITIONS API (halaman transisi lebih smooth)
    // =====================================================================
    const ViewTransitions = {
        init() {
            if (!document.startViewTransition) return;

            Utils.$$('a[href]').forEach((link) => {
                const href = link.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('http') || href.startsWith('mailto:') || href.startsWith('javascript:')) return;

                link.addEventListener('click', (e) => {
                    // Skip jika modifier key ditekan
                    if (e.ctrlKey || e.metaKey || e.shiftKey) return;
                    e.preventDefault();
                    document.startViewTransition(() => {
                        window.location.href = href;
                    });
                });
            });
        },
    };

    // =====================================================================
    // 21. CONSOLE EASTER EGG (Enhanced)
    // =====================================================================
    const ConsoleBranding = {
        init() {
            const styles = {
                title: 'color:#0a6847;font-size:24px;font-weight:bold;text-shadow:1px 1px 2px rgba(0,0,0,0.1);',
                subtitle: 'color:#16a34a;font-size:14px;',
                info: 'color:#64748b;font-size:11px;',
                tip: 'color:#f59e0b;font-size:11px;font-style:italic;',
            };
            console.log('%c🎓 FKIP UNIMOF', styles.title);
            console.log('%cFakultas Keguruan dan Ilmu Pendidikan', styles.subtitle);
            console.log('%cUniversitas Muhammadiyah Maumere', styles.subtitle);
            console.log('%c© ' + new Date().getFullYear() + ' — Mencerdaskan bangsa dengan teknologi', styles.info);
            console.log('%c💡 Tip: Tekan "?" di halaman manapun untuk melihat keyboard shortcuts', styles.tip);
        },
    };

    // =====================================================================
    // INITIALIZATION
    // =====================================================================
    const modules = [
        Preloader,
        ThemeManager,
        ScrollEffects,
        MobileNav,
        CookieConsent,
        Particles,
        ScrollObserver,
        SmoothScroll,
        FormValidator,
        Parallax,
        LazyLoad,
        Lightbox,
        KeyboardShortcuts,
        SmartPrefetch,
        NetworkStatus,
        PerformanceMonitor,
        ViewTransitions,
        ConsoleBranding,
    ];

    // Initialize semua module dengan error handling
    modules.forEach((mod) => {
        try {
            if (typeof mod.init === 'function') mod.init();
        } catch (error) {
            console.error(`❌ [FKIP] Error initializing ${mod.constructor?.name || 'module'}:`, error);
        }
    });

    // =====================================================================
    // EXPOSE GLOBAL API
    // =====================================================================
    window.FKIP = {
        Toast,
        Utils,
        Lightbox,
        CONFIG,
        version: '3.0.0-multimate',
    };

})();