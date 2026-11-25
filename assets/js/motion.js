/**
 * Bookexa Motion, 3D Glare, Parallax, Counters & First-Visit Loader (Vanilla JS)
 */
(function () {
    'use strict';

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    // 1. First-Visit Branded Book Opening Loader
    const loader = document.getElementById('bx-first-loader');
    if (loader) {
        if (reducedMotion || localStorage.getItem('bookexa_welcomed') === '1') {
            loader.remove();
        } else {
            localStorage.setItem('bookexa_welcomed', '1');
            setTimeout(() => {
                loader.classList.add('is-leaving');
                setTimeout(() => loader.remove(), 460);
            }, 680);
        }
    }

    // 2. Route Transition Direction (Deeper vs Reverse Back) & Top Progress Bar
    const mainEl = document.querySelector('.bx-main');
    const progBar = document.getElementById('bx-top-progress');
    if (mainEl && !reducedMotion) {
        const dir = sessionStorage.getItem('bx_nav_dir');
        sessionStorage.removeItem('bx_nav_dir');
        if (dir === 'back') mainEl.classList.add('enter-back');
        else if (dir === 'deeper') mainEl.classList.add('enter-deeper');
    }

    document.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', (e) => {
            const href = link.getAttribute('href') || '';
            if (
                e.metaKey || e.ctrlKey || e.shiftKey ||
                href === '' || href.startsWith('#') ||
                link.getAttribute('target') === '_blank' ||
                link.hasAttribute('data-folder-nav')
            ) {
                return;
            }
            if (link.hasAttribute('data-breadcrumb-back')) {
                sessionStorage.setItem('bx_nav_dir', 'back');
            }
            if (progBar && !reducedMotion) {
                progBar.classList.add('active');
            }
        });
    });

    // Folder "open & expand" transition on deeper navigation
    document.querySelectorAll('.bx-folder-3d[data-folder-nav]').forEach((card) => {
        card.addEventListener('click', (e) => {
            const href = card.getAttribute('href');
            if (!href || href.startsWith('#') || e.metaKey || e.ctrlKey || reducedMotion) return;
            e.preventDefault();
            sessionStorage.setItem('bx_nav_dir', 'deeper');
            card.classList.add('is-opening');
            if (progBar) progBar.classList.add('active');
            if (mainEl) mainEl.classList.add('is-leaving-deeper');
            setTimeout(() => { window.location.href = href; }, 230);
        });
    });

    // 3. Sticky Shrinking Header on Scroll
    const header = document.querySelector('.bx-header');
    if (header) {
        let ticking = false;
        window.addEventListener('scroll', () => {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(() => {
                header.classList.toggle('is-scrolled', window.scrollY > 28);
                ticking = false;
            });
        }, { passive: true });
    }

    // 4. Cursor Glare on 3D Cards + Wish Heart Pop (Hover devices only)
    if (!reducedMotion && canHover) {
        document.querySelectorAll('.bx-folder-face, .bx-book-card, .bx-portal-card').forEach((surface) => {
            if (!surface.querySelector('.bx-glare')) {
                const glare = document.createElement('span');
                glare.className = 'bx-glare';
                surface.appendChild(glare);
            }
            surface.addEventListener('mousemove', (e) => {
                const r = surface.getBoundingClientRect();
                const gx = ((e.clientX - r.left) / r.width) * 100;
                const gy = ((e.clientY - r.top) / r.height) * 100;
                surface.style.setProperty('--gx', gx.toFixed(1) + '%');
                surface.style.setProperty('--gy', gy.toFixed(1) + '%');
            });
        });
    }

    document.querySelectorAll('.bx-wish-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            btn.classList.remove('is-popping');
            void btn.offsetWidth;
            btn.classList.add('is-popping');
        });
    });

    // 5. Button Ripple Micro-interaction
    if (!reducedMotion) {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.bx-btn');
            if (!btn) return;
            const rect = btn.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const ripple = document.createElement('span');
            ripple.className = 'bx-ripple';
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            btn.appendChild(ripple);
            setTimeout(() => ripple.remove(), 580);
        });
    }

    // 6. Hero Floating 3D Objects Parallax (Mouse + Mobile DeviceOrientation)
    const floatObjs = Array.from(document.querySelectorAll('.bx-float-obj'));
    if (floatObjs.length && !reducedMotion) {
        const applyParallax = (nx, ny) => {
            floatObjs.forEach((el, idx) => {
                const depth = (idx + 1) * 10;
                el.style.setProperty('--px', (nx * depth).toFixed(1) + 'px');
                el.style.setProperty('--py', (ny * depth).toFixed(1) + 'px');
                el.style.setProperty('--ry', ((nx * 14) + (idx === 1 ? 10 : -12)).toFixed(1) + 'deg');
                el.style.setProperty('--rx', ((-ny * 10) + 5).toFixed(1) + 'deg');
            });
        };

        if (canHover) {
            window.addEventListener('mousemove', (e) => {
                const nx = (e.clientX / window.innerWidth) - 0.5;
                const ny = (e.clientY / window.innerHeight) - 0.5;
                requestAnimationFrame(() => applyParallax(nx, ny));
            }, { passive: true });
        }

        window.addEventListener('deviceorientation', (e) => {
            if (e.gamma === null || e.beta === null) return;
            const nx = Math.max(-0.5, Math.min(0.5, e.gamma / 45));
            const ny = Math.max(-0.5, Math.min(0.5, (e.beta - 40) / 45));
            requestAnimationFrame(() => applyParallax(nx, ny));
        }, { passive: true });
    }

    // 7. Staggered Children & Animated Counters via IntersectionObserver
    document.querySelectorAll('[data-stagger]').forEach((grid) => {
        Array.from(grid.children).forEach((child, idx) => {
            child.style.setProperty('--stagger-i', String(Math.min(idx, 8)));
        });
    });

    const counters = document.querySelectorAll('[data-counter]');
    if (counters.length) {
        const isBn = document.documentElement.lang === 'bn';
        const toBn = (n) => isBn
            ? String(n).replace(/[0-9]/g, (d) => '০১২৩৪৫৬৭৮৯'[d])
            : String(n);

        const animateCounter = (el) => {
            const target = parseInt(el.getAttribute('data-counter') || '0', 10);
            const suffix = el.getAttribute('data-counter-suffix') || '';
            if (reducedMotion || target <= 0) {
                el.textContent = toBn(target) + suffix;
                return;
            }
            const start = performance.now();
            const dur = 850;
            const step = (now) => {
                const p = Math.min(1, (now - start) / dur);
                const eased = 1 - Math.pow(1 - p, 3);
                el.textContent = toBn(Math.round(target * eased)) + suffix;
                if (p < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        };

        if ('IntersectionObserver' in window) {
            const cObs = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        animateCounter(entry.target);
                        cObs.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.25 });
            counters.forEach((c) => cObs.observe(c));
        } else {
            counters.forEach(animateCounter);
        }
    }

    // 8. Smooth Lazy Image Fade-in
    document.querySelectorAll('img[loading="lazy"]').forEach((img) => {
        img.setAttribute('data-fade-img', '1');
        if (img.complete) {
            img.classList.add('is-loaded');
        } else {
            img.addEventListener('load', () => img.classList.add('is-loaded'), { once: true });
            img.addEventListener('error', () => img.classList.add('is-loaded'), { once: true });
        }
    });
})();
