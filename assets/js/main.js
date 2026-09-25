// assets/js/main.js - Animations & Interactions (ULTIMATE VERSION)

(function() {
    'use strict';
    
    // ===== 1. Preloader =====
    window.addEventListener('load', () => {
        const preloader = document.getElementById('preloader');
        if (preloader) {
            setTimeout(() => {
                preloader.classList.add('hidden');
                setTimeout(() => preloader.remove(), 500);
            }, 800);
        }
    });
    
    // ===== 2. Theme Toggle (Dark Mode) =====
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = themeToggle?.querySelector('.theme-icon-premium') || themeToggle?.querySelector('.theme-icon');
    
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    if (themeIcon) themeIcon.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
    
    themeToggle?.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        if (themeIcon) themeIcon.textContent = newTheme === 'dark' ? '☀️' : '🌙';
    });
    
    // ===== 3. Navbar Scroll Effect & Back to Top =====
    const header = document.getElementById('header');
    const backToTop = document.getElementById('backToTop');
    
    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        if (header) {
            if (currentScroll > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        }
        
        if (backToTop) {
            if (currentScroll > 500) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        }
    });
    
    backToTop?.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    
    // ===== 4. Premium Mobile Navigation =====
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    
    navToggle?.addEventListener('click', () => {
        navMenu.classList.toggle('open');
        
        // Animasi hamburger menjadi X
        const spans = navToggle.querySelectorAll('span');
        if (navMenu.classList.contains('open')) {
            spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
            spans[1].style.opacity = '0';
            spans[2].style.transform = 'rotate(-45deg) translate(5px, -5px)';
        } else {
            spans[0].style.transform = 'none';
            spans[1].style.opacity = '1';
            spans[2].style.transform = 'none';
        }
    });
    
    // Mobile dropdown toggle (Accordion style untuk navbar baru)
    document.querySelectorAll('.nav-dropdown-premium').forEach(dropdown => {
        const link = dropdown.querySelector('.nav-link-premium');
        link?.addEventListener('click', (e) => {
            if (window.innerWidth <= 968) {
                e.preventDefault();
                dropdown.classList.toggle('mobile-open');
            }
        });
    });

    // Tutup menu mobile saat klik di luar area header
    document.addEventListener('click', (e) => {
        if (navMenu && navMenu.classList.contains('open')) {
            if (header && !header.contains(e.target)) {
                navMenu.classList.remove('open');
                const spans = navToggle.querySelectorAll('span');
                spans[0].style.transform = 'none';
                spans[1].style.opacity = '1';
                spans[2].style.transform = 'none';
                
                // Reset dropdowns
                document.querySelectorAll('.nav-dropdown-premium').forEach(d => d.classList.remove('mobile-open'));
            }
        }
    });
    
    // ===== 5. Cookie Consent =====
    const cookieConsent = document.getElementById('cookieConsent');
    const acceptCookies = document.getElementById('acceptCookies');
    
    if (!localStorage.getItem('cookies-accepted')) {
        setTimeout(() => {
            cookieConsent?.classList.add('show');
        }, 2000);
    }
    
    acceptCookies?.addEventListener('click', () => {
        localStorage.setItem('cookies-accepted', 'true');
        cookieConsent.classList.remove('show');
    });
    
    // ===== 6. Particles Animation =====
    const particlesContainer = document.getElementById('particles');
    if (particlesContainer) {
        const colors = ['#0a6847', '#16a34a', '#f5a623', '#3b82f6'];
        for (let i = 0; i < 30; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 20 + 's';
            particle.style.animationDuration = (15 + Math.random() * 15) + 's';
            particle.style.background = colors[Math.floor(Math.random() * colors.length)];
            particle.style.width = (2 + Math.random() * 4) + 'px';
            particle.style.height = particle.style.width;
            particlesContainer.appendChild(particle);
        }
    }
    
    // ===== 7. Counter Animation =====
    const animateCounter = (element) => {
        const target = parseInt(element.getAttribute('data-count')) || 0;
        const duration = 2000;
        const steps = 60;
        const stepValue = target / steps;
        let current = 0;
        const interval = setInterval(() => {
            current += stepValue;
            if (current >= target) {
                element.textContent = target.toLocaleString('id-ID');
                clearInterval(interval);
            } else {
                element.textContent = Math.floor(current).toLocaleString('id-ID');
            }
        }, duration / steps);
    };
    
    // ===== 8. Intersection Observer for AOS + Counters =====
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('aos-animate');
                
                if (entry.target.hasAttribute('data-count')) {
                    animateCounter(entry.target);
                }
                
                observer.unobserve(entry.target);
            }
        });
    }, { 
        threshold: 0.15,
        rootMargin: '0px 0px -50px 0px'
    });
    
    document.querySelectorAll('[data-aos], [data-count]').forEach(el => {
        observer.observe(el);
    });
    
    // ===== 9. Smooth Scroll for anchor links =====
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== '#' && document.querySelector(href)) {
                e.preventDefault();
                const target = document.querySelector(href);
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
    
    // ===== 10. Form Validation Helper =====
    const validateForms = () => {
        document.querySelectorAll('form[data-validate]').forEach(form => {
            form.addEventListener('submit', (e) => {
                let isValid = true;
                form.querySelectorAll('[required]').forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#ef4444';
                        isValid = false;
                    } else {
                        field.style.borderColor = '';
                    }
                });
                if (!isValid) {
                    e.preventDefault();
                    alert('Mohon lengkapi semua field yang wajib diisi.');
                }
            });
        });
    };
    validateForms();
    
    // ===== 11. Parallax on Hero (desktop only) =====
    if (window.innerWidth > 968) {
        const hero = document.querySelector('.hero-bg');
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            if (hero && scrolled < window.innerHeight) {
                hero.style.transform = `translateY(${scrolled * 0.3}px)`;
            }
        });
    }
    
    // ===== 12. Lazy Loading Images =====
    if ('loading' in HTMLImageElement.prototype) {
        document.querySelectorAll('img[loading="lazy"]').forEach(img => {
            img.src = img.dataset.src || img.src;
        });
    }
    
    // ===== 13. Console Easter Egg =====
    console.log('%c🎓 FKIP UNIMOF', 'color: #0a6847; font-size: 24px; font-weight: bold;');
    console.log('%cFakultas Keguruan dan Ilmu Pendidikan Universitas Muhammadiyah Maumere', 'color: #16a34a; font-size: 14px;');
    console.log('%c© ' + new Date().getFullYear() + ' - Mencerdaskan bangsa dengan teknologi', 'color: #64748b;');
    
})();