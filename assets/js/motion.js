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

    // Delegated route progress & folder open transition (zero startup querySelectorAll walk over every <a>)
    document.addEventListener('click', (e) => {
        const folderCard = e.target.closest('.bx-folder-3d[data-folder-nav]');
        if (folderCard) {
            const href = folderCard.getAttribute('href');
            if (!href || href.startsWith('#') || e.metaKey || e.ctrlKey || reducedMotion) return;
            e.preventDefault();
            sessionStorage.setItem('bx_nav_dir', 'deeper');
            folderCard.classList.add('is-opening');
            if (progBar) progBar.classList.add('active');
            if (mainEl) mainEl.classList.add('is-leaving-deeper');
            setTimeout(() => { window.location.href = href; }, 230);
            return;
        }
        const link = e.target.closest('a[href]');
        if (!link) return;
        const href = link.getAttribute('href') || '';
        if (e.metaKey || e.ctrlKey || e.shiftKey || href === '' || href.startsWith('#') || link.getAttribute('target') === '_blank') {
            return;
        }
        if (link.hasAttribute('data-breadcrumb-back')) {
            sessionStorage.setItem('bx_nav_dir', 'back');
        }
        if (progBar && !reducedMotion) {
            progBar.classList.add('active');
        }
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

    // 4. Defer non-essential animations, ripples, parallax, counters & lazy fades until idle
    const runIdleEffects = () => {
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
                }, { passive: true });
            });
        }

        document.querySelectorAll('.bx-wish-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                btn.classList.remove('is-popping');
                requestAnimationFrame(() => btn.classList.add('is-popping'));
            });
        });

        // 5. Button Ripple Micro-interaction (desktop/fine pointer only, preserves instant mobile INP)
        if (!reducedMotion && canHover) {
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('.bx-btn, .bx-pill-btn:not(.bx-notif-btn), .bx-wish-btn, .bx-kebab-btn, .bx-search-bar button, .bx-chart-range-btn');
                if (!btn || btn.disabled) return;
                requestAnimationFrame(() => {
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
            });
        }

        // 6. Hero Floating 3D Objects Parallax (hover desktop only to avoid mobile wakeups)
        const floatObjs = Array.from(document.querySelectorAll('.bx-float-obj'));
        if (floatObjs.length && !reducedMotion && canHover) {
            const applyParallax = (nx, ny) => {
                floatObjs.forEach((el, idx) => {
                    const depth = (idx + 1) * 10;
                    el.style.setProperty('--px', (nx * depth).toFixed(1) + 'px');
                    el.style.setProperty('--py', (ny * depth).toFixed(1) + 'px');
                    el.style.setProperty('--ry', ((nx * 14) + (idx === 1 ? 10 : -12)).toFixed(1) + 'deg');
                    el.style.setProperty('--rx', ((-ny * 10) + 5).toFixed(1) + 'deg');
                });
            };
            window.addEventListener('mousemove', (e) => {
                const nx = (e.clientX / window.innerWidth) - 0.5;
                const ny = (e.clientY / window.innerHeight) - 0.5;
                requestAnimationFrame(() => applyParallax(nx, ny));
            }, { passive: true });
        }

        // 7. Animated Counters via IntersectionObserver
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
                const dur = 650;
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

        // 8. Smooth Lazy Image Fade-in (only for unloaded offscreen lazy images; never hide complete/eager images)
        document.querySelectorAll('img[loading="lazy"]').forEach((img) => {
            if (img.complete && img.naturalWidth > 0) return;
            img.setAttribute('data-fade-img', '1');
            img.addEventListener('load', () => img.classList.add('is-loaded'), { once: true });
            img.addEventListener('error', () => img.classList.add('is-loaded'), { once: true });
        });
    };

    if ('requestIdleCallback' in window) {
        window.addEventListener('load', () => {
            setTimeout(() => window.requestIdleCallback(runIdleEffects, { timeout: 1500 }), 400);
        }, { once: true });
    } else {
        window.addEventListener('load', () => setTimeout(runIdleEffects, 400), { once: true });
    }
})();
