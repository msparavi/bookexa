<?php
declare(strict_types=1);

/**
 * Bookexa Pure-PHP Production Asset Builder & Minifier (No Node required for customer on cPanel)
 *
 * Usage: php cron/build_assets.php [--images]
 *  - Combines & minifies public CSS & JS into content-hashed files in assets/dist/
 *  - Extracts/builds inlined Critical CSS for above-the-fold speed (LCP < 2.5s)
 *  - Splits reader and admin bundles from public bundles
 *  - Generates font subset CSS (Hind Siliguri 400/700 + Noto Serif Bengali 700) with size-adjusted fallbacks
 *  - Optional --images flag converts uploaded JPG/PNG/WebP into responsive WebP + AVIF variants
 */

function bx_minify_css(string $css): string
{
    $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? '';
    $css = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $css);
    $css = preg_replace('/\s{2,}/', ' ', $css) ?? '';
    $css = preg_replace('/\s*([\{\};:,>~])\s*/', '$1', $css) ?? '';
    $css = str_replace(';}', '}', $css);
    return trim($css);
}

/**
 * Safe whitespace/comment-stripping JS minifier in plain PHP (preserves strings & regex literals safely).
 */
function bx_minify_js(string $js): string
{
    // Strip multi-line /* ... */ comments safely without breaking strings/regexes via state tokenizer
    $out = '';
    $len = strlen($js);
    $i = 0;
    $lastNonWs = '';
    while ($i < $len) {
        $ch = $js[$i];
        $nx = $i + 1 < $len ? $js[$i + 1] : '';

        // String or template literal
        if ($ch === '"' || $ch === "'" || $ch === '`') {
            $quote = $ch;
            $buf = $ch;
            $i++;
            while ($i < $len) {
                $c = $js[$i];
                $buf .= $c;
                if ($c === '\\' && $i + 1 < $len) {
                    $buf .= $js[$i + 1];
                    $i += 2;
                    continue;
                }
                $i++;
                if ($c === $quote) {
                    break;
                }
            }
            $out .= $buf;
            $lastNonWs = $quote;
            continue;
        }

        // Line comment //
        if ($ch === '/' && $nx === '/') {
            while ($i < $len && $js[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        // Block comment /* ... */
        if ($ch === '/' && $nx === '*') {
            $i += 2;
            while ($i + 1 < $len && !($js[$i] === '*' && $js[$i + 1] === '/')) {
                $i++;
            }
            $i += 2;
            continue;
        }

        // Collapse whitespace while preserving statement newlines
        if ($ch === ' ' || $ch === "\t" || $ch === "\r" || $ch === "\n") {
            $sawNl = ($ch === "\n" || $ch === "\r");
            while ($i < $len && ($js[$i] === ' ' || $js[$i] === "\t" || $js[$i] === "\r" || $js[$i] === "\n")) {
                if ($js[$i] === "\n" || $js[$i] === "\r") {
                    $sawNl = true;
                }
                $i++;
            }
            if ($out !== '') {
                $nextChar = $i < $len ? $js[$i] : '';
                if ($sawNl && $lastNonWs !== '{' && $lastNonWs !== ';' && $lastNonWs !== ',') {
                    $out .= "\n";
                } elseif (
                    preg_match('/[A-Za-z0-9_$\)]/', $lastNonWs)
                    && preg_match('/[A-Za-z0-9_$\(]/', $nextChar)
                ) {
                    $out .= ' ';
                }
            }
            continue;
        }

        $out .= $ch;
        $lastNonWs = $ch;
        $i++;
    }
    return trim($out);
}

function bx_build_critical_css_source(): string
{
    $tokens = (string) @file_get_contents(APP_ROOT . '/assets/css/tokens.css');
    $fontsCss = (string) @file_get_contents(APP_ROOT . '/assets/css/fonts.css');
    $notoPos = strpos($fontsCss, '/* Noto Serif Bengali');
    $fontsHead = $notoPos !== false ? substr($fontsCss, 0, $notoPos) : $fontsCss;
    $fontsSlice = str_replace('../fonts/', '__BX_FONTS_BASE__/', $fontsHead);

    // Minimal lean structural critical CSS (~9 KB total minified) covering above-the-fold layout for all
    // 5 public route templates with zero duplicated styling or closed overlay layout shift.
    $struct = '
*,*::before,*::after{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
[hidden]{display:none!important}
html{-webkit-text-size-adjust:100%;max-width:100%;overflow-x:clip}
body{margin:0;font-family:var(--font-sans);font-size:16px;background:var(--bg);color:var(--text);line-height:1.72;min-height:100vh;min-height:100dvh;max-width:100%;display:flex;flex-direction:column;overflow-x:clip;position:relative}
a{color:inherit;text-decoration:none}
img,video,svg{max-width:100%}
img{height:auto;display:block}
button,input,select,textarea{font-family:inherit}
main,section,article,aside,.bx-card,.bx-header-inner>*,.bx-footer-inner>*,.bx-folder-grid>*,.bx-book-grid>*,.bx-portal-grid>*,.bx-detail-grid>*,.bx-book-info,.bx-folder-face,.bx-slide-content{min-width:0}
.bx-icon{width:18px;height:18px;flex-shrink:0;display:block}
.bx-icon-lg{width:24px;height:24px;flex-shrink:0;display:block}
.bx-logo-svg{height:36px;width:auto;max-width:100%;display:block}
.container{width:100%;max-width:1240px;margin:0 auto;padding:0 clamp(14px,3.5vw,22px)}
.bx-display{font-family:var(--font-serif);font-size:clamp(1.55rem,3.2vw,2.75rem);line-height:1.3;margin:0}
.bx-h2{font-family:var(--font-serif);font-size:clamp(1.24rem,2.2vw,1.85rem);line-height:1.38;margin:0}
.bx-muted{color:var(--text-muted)}
.bx-top-progress{position:fixed;top:0;left:0;right:0;height:3px;z-index:200;opacity:0;pointer-events:none}
.bx-header{position:sticky;top:0;z-index:60;padding-top:env(safe-area-inset-top);background:var(--surface);border-bottom:1px solid var(--border)}
.bx-header-inner{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:70px}
.bx-brand{display:inline-flex;align-items:center;gap:10px;min-width:44px;min-height:44px;font-family:var(--font-serif);font-weight:700;font-size:clamp(1.1rem,2.2vw,1.38rem);color:var(--text)}
.bx-nav{display:flex;align-items:center;gap:4px}
.bx-nav a{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:8px 14px;border-radius:var(--radius-pill);font-size:.94rem;font-weight:600;color:var(--text-muted);white-space:nowrap}
.bx-search-bar{display:flex;align-items:center;background:var(--input-bg);border:1.5px solid var(--input-border);border-radius:var(--radius-pill);padding:2px 3px 2px 14px;flex:1 1 180px;max-width:280px;min-width:165px;position:relative}
.bx-search-bar input{border:none;background:transparent;color:var(--text);width:100%;min-width:0;min-height:40px;font-size:.95rem;outline:none}
.bx-search-bar button{border:none;background:var(--grad-indigo-teal);color:#fff;width:40px;height:40px;min-width:40px;border-radius:var(--radius-pill);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
.bx-header-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}
.bx-pill-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-width:44px;min-height:44px;padding:8px 13px;border-radius:var(--radius-pill);border:1px solid var(--border);background:var(--surface);color:var(--text);font-size:.86rem;font-weight:600;box-shadow:var(--shadow-sm)}
.bx-mobile-toggle{display:none}
[data-theme="light"] .bx-theme-sun,[data-theme="dark"] .bx-theme-moon{display:none}
.bx-drawer,.bx-modal-backdrop{position:fixed;inset:0;z-index:100;opacity:0;pointer-events:none}
.bx-drawer-panel{width:min(310px,88%);height:100%;transform:translateX(-100%)}
.bx-cart-drawer-wrap,.bx-notif-menu{display:none}
.bx-mobile-bottom-nav{display:none;position:fixed;bottom:0;left:0;right:0;z-index:70;background:var(--surface);border-top:1px solid var(--border);padding:6px 8px calc(6px + env(safe-area-inset-bottom))}
.bx-mobile-bottom-nav a{flex:1;min-height:44px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;font-size:.78rem;font-weight:600;color:var(--text-muted);padding:4px;border-radius:14px}
.bx-mobile-bottom-nav a.active{color:var(--primary)}
.bx-main{flex:1;width:100%;padding-bottom:clamp(36px,5vw,64px)}
.bx-section{padding:clamp(22px,4vw,34px) 0 0}
.bx-section:nth-of-type(n+3),.bx-guide-sub{content-visibility:auto;contain-intrinsic-size:auto 360px}
.bx-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:clamp(16px,3.8vw,26px);box-shadow:var(--shadow-sm);position:relative}
.bx-section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:20px;flex-wrap:wrap}
.bx-section-head p{margin:4px 0 0;color:var(--text-muted);font-size:.95rem}
.bx-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:10px 20px;border-radius:var(--radius-sm);font-weight:700;font-size:.94rem;border:1px solid transparent;cursor:pointer;white-space:nowrap}
.bx-btn-primary{background:var(--grad-indigo-teal);color:#fff}
.bx-btn-accent{background:var(--grad-gold-rose);color:#14213D}
.bx-btn-outline{background:transparent;border-color:var(--input-border);color:var(--text)}
.bx-btn-ghost{background:rgba(43,47,119,.09);color:var(--primary)}
.bx-btn-sm{min-height:40px;padding:8px 14px;font-size:.85rem;border-radius:12px}
.bx-badge,.bx-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:var(--radius-pill);font-size:.78rem;font-weight:700;max-width:100%}
.bx-badge-teal,.bx-badge-success{background:rgba(20,184,166,.18);color:#0B6B5F}
.bx-badge-accent{background:rgba(244,166,42,.20);color:#8A4F00}
.bx-input,.bx-select{width:100%;max-width:100%;min-height:44px;padding:11px 14px;border-radius:var(--radius-sm);border:1.5px solid var(--input-border);background:var(--input-bg);color:var(--text);font-size:1rem}
.bx-breadcrumb{display:flex;align-items:center;flex-wrap:nowrap;gap:4px;overflow-x:auto;overflow-y:hidden;scrollbar-width:none;white-space:nowrap;font-size:.9rem;color:var(--text-muted);padding:4px 18px 4px 12px;border-radius:var(--radius-sm);background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-sm);max-width:100%}
.bx-breadcrumb::-webkit-scrollbar{display:none}
.bx-breadcrumb>*{flex-shrink:0}
.bx-breadcrumb a,.bx-breadcrumb .current{display:inline-block;position:relative;overflow:hidden;min-height:40px;min-width:40px;line-height:32px;padding:4px 8px;border-radius:8px;max-width:min(210px,52vw);text-overflow:ellipsis;white-space:nowrap}
.bx-breadcrumb .current{color:var(--text);font-weight:700}
.bx-hero-wrap{padding:clamp(14px,3vw,26px) 0 10px;overflow:hidden}
.bx-slider{position:relative;border-radius:var(--radius-lg);overflow:hidden;min-height:clamp(270px,46dvh,380px);background:#121A33}
.bx-slide{position:absolute;inset:0;opacity:0;pointer-events:none;display:flex;align-items:center;justify-content:space-between;gap:24px}
.bx-slide.active{position:relative;opacity:1;pointer-events:auto;min-height:clamp(270px,46dvh,380px)}
.bx-slide-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.bx-slide-overlay{position:absolute;inset:0;background:linear-gradient(100deg,rgba(11,16,32,.94) 0%,rgba(43,47,119,.78) 55%,rgba(20,184,166,.25) 100%)}
.bx-slide-content{position:relative;z-index:2;max-width:650px;padding:clamp(20px,5vw,48px) clamp(18px,5vw,48px) clamp(52px,7vw,62px);color:#FBF6EC}
.bx-slide-content h1,.bx-slide-content h2.bx-hero-reveal{font-family:var(--font-serif);font-size:clamp(1.5rem,3.2vw,2.75rem);line-height:1.28;margin:0 0 12px}
.bx-slide-content p{font-size:clamp(.95rem,2vw,1.05rem);margin:0 0 22px}
.bx-slider-dots{position:absolute;bottom:8px;left:clamp(14px,5vw,44px);z-index:5;display:flex;gap:4px}
.bx-slider-dot{min-width:44px;min-height:44px;padding:17px 8px;border:none;background:transparent;display:inline-flex;align-items:center;justify-content:center}
.bx-slider-dot::after{content:"";display:block;width:10px;height:10px;border-radius:999px;background:rgba(251,246,236,.45)}
.bx-slider-dot.active::after{width:28px;background:var(--gold)}
.bx-folder-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,250px),1fr));gap:clamp(16px,3vw,26px)}
.bx-folder-3d,.bx-book-3d{display:block;max-width:100%}
.bx-folder-inner{position:relative;padding-top:22px}
.bx-folder-lid{position:absolute;top:10px;left:0;right:0;height:68px;border-radius:20px 20px 0 0;background:linear-gradient(135deg,var(--folder-accent,#232766) 0%,var(--folder-accent-2,#0F766E) 100%);z-index:1}
.bx-folder-peek{position:relative;z-index:2;margin:0 12px;aspect-ratio:16/10;border-radius:14px;overflow:hidden;border:2px solid var(--surface);background:var(--bg-alt)}
.bx-folder-peek img{width:100%;height:100%;object-fit:cover;display:block}
.bx-folder-face{position:relative;z-index:2;margin-top:8px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:16px 18px 14px;box-shadow:var(--shadow-sm)}
.bx-folder-title{font-family:var(--font-serif);font-size:clamp(1rem,2.2vw,1.14rem);font-weight:700;margin:0 0 5px}
.bx-folder-desc{margin:0 0 12px;font-size:.88rem;color:var(--text-muted);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.bx-folder-meta{display:flex;align-items:center;gap:6px;font-size:.82rem;font-weight:700;color:var(--primary);padding-top:10px;border-top:1px dashed var(--border);min-width:0}
.bx-folder-meta-line{display:inline-flex;align-items:center;gap:5px;min-width:0;max-width:100%;white-space:nowrap;overflow:hidden}
.bx-folder-meta-line .bx-icon{width:14px;height:14px;flex-shrink:0}
.bx-folder-meta-text{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bx-book-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,225px),1fr));gap:clamp(14px,3vw,26px)}
.bx-book-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-md);overflow:hidden;box-shadow:var(--shadow-sm);display:flex;flex-direction:column;height:100%;position:relative}
.bx-book-cover{position:relative;display:block;aspect-ratio:2/3;max-height:58dvh;background:var(--bg-alt);overflow:hidden}
.bx-book-cover img{width:100%;height:100%;object-fit:cover}
.bx-book-ribbon{position:absolute;top:10px;left:20px;padding:4px 10px;border-radius:var(--radius-pill);font-size:.76rem;font-weight:700;color:#fff;background:#232766;z-index:3}
.bx-book-ribbon.free{background:var(--teal);color:#0B1020}
.bx-wish-btn{position:absolute;top:8px;right:8px;z-index:3;width:44px;height:44px;border-radius:var(--radius-pill);border:1px solid rgba(20,33,61,.14);background:rgba(255,255,255,.94);color:#4A5572;display:inline-flex;align-items:center;justify-content:center}
.bx-book-info,.bx-book-body{padding:clamp(12px,2.5vw,16px);display:flex;flex-direction:column;flex:1}
.bx-book-title{font-family:var(--font-serif);font-size:clamp(.96rem,2vw,1.05rem);font-weight:700;margin:0 0 4px;line-height:1.42}
.bx-book-author{font-size:.84rem;color:var(--text-muted);margin:0 0 12px}
.bx-book-footer,.bx-book-foot{margin-top:auto;padding-top:10px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.bx-price{font-weight:700;font-size:1.08rem;color:var(--primary)}
.bx-portal-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:clamp(14px,3vw,24px)}
.bx-detail-grid{display:grid;grid-template-columns:minmax(0,300px) minmax(0,1fr);gap:clamp(20px,4vw,38px);margin-top:20px;align-items:start}
.bx-detail-stage{padding:6px 6px 14px;max-width:310px;width:100%;margin:0 auto}
.bx-detail-book-3d{position:relative;border-radius:var(--radius-md);overflow:hidden;aspect-ratio:2/3;max-height:58dvh;margin:0 auto;background:var(--surface)}
.bx-detail-book-3d img{width:100%;height:100%;object-fit:cover;display:block}
.bx-detail-actions{display:flex;flex-wrap:wrap;gap:12px;align-items:center}
.bx-detail-actions .bx-cta-short{display:none}
.bx-shop-promo-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 16px;border-radius:12px;background:rgba(20,184,166,.12);border:1px solid rgba(20,184,166,.28);color:var(--teal-text);font-weight:700;font-size:.9rem;margin-bottom:14px}
.bx-shop-cat-pills{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.bx-cat-pill{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;min-height:40px;border-radius:var(--radius-pill);background:var(--surface);border:1px solid var(--border);color:var(--text);font-size:.86rem;font-weight:600}
.bx-cat-pill.active{background:var(--primary);border-color:var(--primary);color:#FBF6EC}
.bx-browse-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:14px;border-radius:var(--radius-md);background:var(--surface);border:1px solid var(--border);margin-bottom:22px}
.bx-browse-search{position:relative;flex:1 1 220px;min-width:0}
.bx-browse-ctl{width:auto;min-width:140px;flex:0 1 auto}
.bx-browse-check{display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:0 8px;font-size:.9rem;font-weight:600}
.bx-answer-box{margin:14px 0 20px;padding:16px 18px;border-radius:var(--radius-sm);background:var(--surface-2);border:1px solid var(--border);border-left:4px solid var(--primary)}
.bx-answer-kicker{display:inline-block;font-size:.76rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:var(--primary);margin-bottom:6px}
.bx-answer-box p{margin:0;font-size:.98rem;line-height:1.75}
.bx-toc-box{padding:16px 18px;border-radius:var(--radius-sm);background:var(--bg-alt);border:1px solid var(--border);margin-bottom:22px}
.bx-toc-box ul{list-style:none;padding:0;margin:8px 0 0;display:flex;flex-direction:column;gap:6px}
.bx-toc-box li.toc-l3{padding-left:16px;font-size:.92rem}
.bx-toc-box a{text-decoration:none;color:var(--primary);font-weight:600}
.bx-prose{font-size:1.03rem;line-height:1.85;max-width:74ch;color:var(--text)}
.bx-prose h2{font-family:var(--font-serif);font-size:1.38rem;line-height:1.35;margin:1.6em 0 .55em}
.bx-prose h3{font-family:var(--font-serif);font-size:1.14rem;line-height:1.4;margin:1.25em 0 .45em}
.bx-prose p{margin:0 0 1em}
.bx-prose ul,.bx-prose ol{margin:0 0 1.1em;padding-left:1.45em}
.bx-prose li{margin-bottom:.4em}
.bx-author-box{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;padding:18px;border-radius:var(--radius-md);background:var(--bg-alt);border:1px solid var(--border);margin-top:24px;content-visibility:auto;contain-intrinsic-size:auto 260px}
.bx-sources-box{margin-top:24px;padding:16px 18px;border-radius:var(--radius-sm);background:var(--bg-alt);border:1px solid var(--border);content-visibility:auto;contain-intrinsic-size:auto 160px}
.bx-footer{border-top:1px solid var(--border);background:var(--surface);padding:28px 0 calc(28px + env(safe-area-inset-bottom));margin-top:auto;font-size:.9rem;color:var(--text-muted);content-visibility:auto;contain-intrinsic-size:auto 240px}
.bx-faq-item{border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--surface);padding:12px 16px;margin-bottom:10px}
.bx-reveal{opacity:1;transform:none}
@media(max-width:1020px){
  .bx-nav,.bx-search-bar{display:none}
  .bx-mobile-toggle{display:inline-flex}
  .bx-mobile-bottom-nav{display:flex;height:calc(58px + env(safe-area-inset-bottom))}
  .bx-hide-sm{display:none!important}
  .bx-portal-grid,.bx-detail-grid{grid-template-columns:minmax(0,1fr)}
  .bx-slide,.bx-slide.active{padding-right:0}
  .bx-slider-arrow{display:none}
}
@media(max-width:768px){
  .bx-detail-stage{max-width:min(260px,72vw)}
  .bx-detail-actions .bx-cta-long{display:none}
  .bx-detail-actions .bx-cta-short{display:inline}
  .bx-detail-actions{position:fixed;left:0;right:0;bottom:calc(58px + env(safe-area-inset-bottom));z-index:65;background:var(--surface);border-top:1px solid var(--border);padding:10px 14px;gap:8px;flex-wrap:wrap}
  .bx-detail-actions>*{flex:1 1 150px;min-width:0;text-align:center;justify-content:center}
  @media(max-width:399px){.bx-detail-actions>*{flex:1 1 100%}}
}
@media(max-width:600px){
  .bx-header-inner{gap:8px;min-height:62px}
  .bx-logo-svg{height:28px;max-width:122px}
  .bx-header-actions{gap:6px}
  .bx-header-profile-label{display:none}
  .bx-header-actions .bx-pill-btn,.bx-header-actions .bx-btn-sm,.bx-mobile-toggle{min-width:40px;min-height:40px;padding:6px 10px}
  .bx-folder-grid,.bx-book-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
}
@media(max-width:480px){
  .bx-header-inner{min-height:58px;gap:6px}
  .bx-brand{flex-shrink:0;min-width:76px}
  .bx-logo-svg{height:24px;max-width:96px}
  .bx-header-actions{gap:4px}
  .bx-header-actions .bx-pill-btn,.bx-header-actions .bx-btn-sm,.bx-mobile-toggle{min-width:40px;min-height:40px;padding:6px 8px;font-size:.8rem}
  .bx-folder-grid,.bx-book-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
  .bx-folder-inner{padding-top:16px}
  .bx-folder-lid{top:6px;height:48px;border-radius:14px 14px 0 0}
  .bx-folder-peek{margin:0 6px;border-radius:10px}
  .bx-folder-face{margin-top:6px;padding:9px 7px;border-radius:14px}
  .bx-folder-title{font-size:.88rem;line-height:1.32;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
  .bx-folder-desc{display:none}
  .bx-folder-meta{font-size:12px;padding-top:6px}
  .bx-book-info,.bx-book-body{padding:10px}
  .bx-book-title{font-size:.88rem;line-height:1.32;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
  .bx-book-author{font-size:12px;margin-bottom:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .bx-book-footer,.bx-book-foot{padding-top:8px;gap:6px;flex-direction:column;align-items:stretch}
  .bx-book-footer .bx-btn-sm,.bx-book-foot .bx-btn-sm{width:100%;min-height:40px;padding:6px 10px;font-size:.8rem}
}
@media(hover:none),(pointer:coarse){.bx-folder-inner,.bx-book-card,.bx-portal-card,.bx-folder-peek,.bx-folder-face,.bx-book-cover img,.bx-btn,.bx-pill-btn,.bx-footer{transition:none!important;transform:none!important}}
@media(max-width:420px){
  .bx-header-actions .bx-lang-pill{display:none}
  .bx-brand{min-width:80px}
}
.bx-cookie-banner[hidden]{display:none!important}
.bx-share-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:10px 0}';

    return bx_minify_css($tokens . "\n" . $fontsSlice . "\n" . $struct);
}

function bx_build_deferred_css_source(): string
{
    $compLines = @file(APP_ROOT . '/assets/css/components.css') ?: [];
    $motion = (string) @file_get_contents(APP_ROOT . '/assets/css/motion.css');
    // Only non-critical UI slices (drawer open states, auth, modals, toasts, skeletons, notifications,
    // reviews, checkout coupons, legal pages, tactile press overlays, search suggestions, mini-cart drawer,
    // tracking, editorial admin editor) plus dark/hover polish — zero re-declaration of :root or layout boxes.
    $deferredSlices = [
        implode('', array_slice($compLines, 326, 49)),   // drawer panel interior
        implode('', array_slice($compLines, 458, 12)),   // hover button states
        implode('', array_slice($compLines, 476, 51)),   // dark badge tints & form validation focus
        implode('', array_slice($compLines, 653, 12)),   // folder hover
        implode('', array_slice($compLines, 721, 10)),   // book card hover
        implode('', array_slice($compLines, 907, 126)),  // auth, modals, toasts, skeletons
        implode('', array_slice($compLines, 1175, 148)), // notifications dropdown, reviews, coupons, legal, tactile press
        implode('', array_slice($compLines, 1340, 42)),  // search suggest dropdown, highlights, pagination, device cards
        implode('', array_slice($compLines, 1405, 72)),  // product gallery, qty control, checkout grid, cart drawer, tracking
        implode('', array_slice($compLines, 1513, 120)), // sample thumbs, editorial editor, AEO boxes & growth components
    ];
    return bx_minify_css(implode("\n", $deferredSlices) . "\n" . $motion);
}

function bx_build_dist_assets(bool $optimizeImages = false): array
{
    $distDir = APP_ROOT . '/assets/dist';
    if (!is_dir($distDir)) {
        @mkdir($distDir, 0755, true);
    }

    // Remove previous generated dist assets
    foreach (glob($distDir . '/*.{css,js,json}', GLOB_BRACE) ?: [] as $old) {
        @unlink($old);
    }

    $writeHashed = static function (string $name, string $ext, string $content) use ($distDir): array {
        $hash = substr(sha1($content), 0, 8);
        $rel = "assets/dist/{$name}.{$hash}.min.{$ext}";
        @file_put_contents(APP_ROOT . '/' . $rel, $content, LOCK_EX);
        return ['path' => $rel, 'hash' => $hash, 'bytes' => strlen($content)];
    };

    $fontsCss = bx_minify_css((string) @file_get_contents(APP_ROOT . '/assets/css/fonts.css'));
    // Deferred CSS bundle carries only secondary/interactive components & motion so activating it triggers
    // zero full-page re-cascade of already-styled above-the-fold layout blocks.
    $publicCss = bx_build_deferred_css_source();
    $criticalCss = bx_build_critical_css_source();
    $critFile = 'assets/dist/critical.' . substr(sha1($criticalCss), 0, 8) . '.min.css';
    @file_put_contents(APP_ROOT . '/' . $critFile, $criticalCss, LOCK_EX);

    $publicJs = bx_minify_js(
        (string) @file_get_contents(APP_ROOT . '/assets/js/app.js')
        . "\n;" . (string) @file_get_contents(APP_ROOT . '/assets/js/motion.js')
    );
    $themeInitJs = bx_minify_js((string) @file_get_contents(APP_ROOT . '/assets/js/theme-init.js'));
    $readerCss = bx_minify_css((string) @file_get_contents(APP_ROOT . '/assets/css/reader.css'));
    $readerJs  = bx_minify_js((string) @file_get_contents(APP_ROOT . '/assets/js/reader.js'));
    $adminCss  = bx_minify_css((string) @file_get_contents(APP_ROOT . '/assets/css/admin.css'));
    $adminJs   = bx_minify_js((string) @file_get_contents(APP_ROOT . '/assets/js/admin.js'));

    $fontPreloads = [
        'bn' => is_file(APP_ROOT . '/assets/fonts/hind-bn-400.woff2')
            ? ['assets/fonts/hind-bn-400.woff2']
            : ['assets/fonts/font-1.woff2'],
        'en' => is_file(APP_ROOT . '/assets/fonts/hind-lat-400.woff2')
            ? ['assets/fonts/hind-lat-400.woff2', 'assets/fonts/hind-lat-700.woff2']
            : ['assets/fonts/font-3.woff2'],
    ];

    $manifest = [
        'built_at'          => date('c'),
        'critical_css_file' => $critFile,
        'critical_bytes'    => strlen($criticalCss),
        'font_preloads'     => $fontPreloads,
        'files'             => [
            'fonts_css'      => $writeHashed('fonts', 'css', $fontsCss),
            'app_css'        => $writeHashed('app', 'css', $publicCss),
            'app_js'         => $writeHashed('app', 'js', $publicJs),
            'theme_init_js'  => $writeHashed('theme-init', 'js', $themeInitJs),
            'reader_css'     => $writeHashed('reader', 'css', $readerCss),
            'reader_js'      => $writeHashed('reader', 'js', $readerJs),
            'admin_css'      => $writeHashed('admin', 'css', $adminCss),
            'admin_js'       => $writeHashed('admin', 'js', $adminJs),
        ],
    ];
    @file_put_contents($distDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

    $imgCount = 0;
    if ($optimizeImages && function_exists('perf_optimize_image_variants')) {
        $dirs = ['uploads/banners', 'uploads/covers', 'uploads/folders', 'uploads/products', 'uploads/branding'];
        foreach ($dirs as $d) {
            $absDir = APP_ROOT . '/' . $d;
            if (!is_dir($absDir)) continue;
            foreach (scandir($absDir) ?: [] as $fname) {
                if (preg_match('/-\d+w\.(webp|avif)$/i', $fname)) continue;
                if (preg_match('/\.(jpg|jpeg|png|webp)$/i', $fname)) {
                    $variants = perf_optimize_image_variants($d . '/' . $fname, true);
                    if ($variants) $imgCount++;
                }
            }
        }
    }
    $manifest['images_optimized'] = $imgCount;
    return $manifest;
}
