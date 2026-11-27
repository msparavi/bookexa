<?php
declare(strict_types=1);

function bookexa_logo_svg(string $class = 'bx-logo-svg'): string
{
    return '<svg class="' . e($class) . '" viewBox="0 0 184 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <defs>
        <linearGradient id="bxLgIndigo" x1="2" y1="2" x2="46" y2="46" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#2B2F77"/>
          <stop offset="100%" stop-color="#14B8A6"/>
        </linearGradient>
        <linearGradient id="bxLgGold" x1="26" y1="7" x2="36" y2="21" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#F4A62A"/>
          <stop offset="100%" stop-color="#E56B8A"/>
        </linearGradient>
      </defs>
      <rect x="2" y="2" width="44" height="44" rx="14" fill="url(#bxLgIndigo)"/>
      <path d="M10.5 15.5C15 13.8 19.5 14.8 24 18.5C28.5 14.8 33 13.8 37.5 15.5V34.5C33 32.8 28.5 33.8 24 37.5C19.5 33.8 15 32.8 10.5 34.5V15.5Z" fill="#FBF6EC"/>
      <path d="M24 18.5V37.5" stroke="#2B2F77" stroke-width="2" stroke-linecap="round"/>
      <path d="M28.5 8.5H34.5V20L31.5 17.5L28.5 20V8.5Z" fill="url(#bxLgGold)"/>
      <path d="M14 21H19.5M14 25.5H19.5M28.5 25.5H34M28.5 30H33" stroke="#2B2F77" stroke-width="1.7" stroke-linecap="round" opacity="0.65"/>
      <text x="56" y="32" font-family="Georgia, \'Noto Serif Bengali\', serif" font-size="25" font-weight="700" letter-spacing="-0.4" fill="currentColor">Book<tspan class="bx-logo-exa" fill="#9A5B00">exa</tspan></text>
    </svg>';
}

function illustration_svg(string $type): string
{
    if ($type === '404') {
        return '<svg class="bx-illus" viewBox="0 0 260 180" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <defs>
            <linearGradient id="il404a" x1="30" y1="20" x2="230" y2="160" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#2B2F77" stop-opacity="0.16"/>
              <stop offset="100%" stop-color="#14B8A6" stop-opacity="0.18"/>
            </linearGradient>
            <linearGradient id="il404b" x1="90" y1="40" x2="170" y2="135" gradientUnits="userSpaceOnUse">
              <stop offset="0%" stop-color="#2B2F77"/>
              <stop offset="100%" stop-color="#14B8A6"/>
            </linearGradient>
          </defs>
          <ellipse cx="130" cy="154" rx="88" ry="12" fill="url(#il404a)"/>
          <circle cx="52" cy="48" r="14" fill="#F4A62A" fill-opacity="0.24"/>
          <circle cx="212" cy="54" r="18" fill="#E56B8A" fill-opacity="0.22"/>
          <path d="M68 58C88 48 110 52 130 68C150 52 172 48 192 58V134C172 124 150 128 130 144C110 128 88 124 68 134V58Z" fill="url(#il404b)" stroke="rgba(182,192,220,0.35)" stroke-width="1.5"/>
          <path d="M76 64C93 56 112 60 130 74C148 60 167 56 184 64V127C167 119 148 123 130 137C112 123 93 119 76 127V64Z" fill="#FBF6EC"/>
          <path d="M130 74V137" stroke="#2B2F77" stroke-width="2.5" stroke-linecap="round"/>
          <circle cx="130" cy="96" r="18" fill="#F4A62A" fill-opacity="0.22"/>
          <text x="130" y="103" text-anchor="middle" font-size="22" font-weight="700" fill="#2B2F77" font-family="sans-serif">?</text>
          <path d="M156 36H172V62L164 55L156 62V36Z" fill="#E56B8A"/>
        </svg>';
    }

    if ($type === 'error') {
        return '<svg class="bx-illus" viewBox="0 0 260 180" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <ellipse cx="130" cy="154" rx="84" ry="11" fill="#E56B8A" fill-opacity="0.16"/>
          <rect x="64" y="42" width="132" height="96" rx="22" fill="#2B2F77" stroke="rgba(182,192,220,0.35)" stroke-width="1.5"/>
          <rect x="74" y="52" width="112" height="76" rx="14" fill="#FBF6EC"/>
          <circle cx="130" cy="86" r="20" fill="#E56B8A" fill-opacity="0.2"/>
          <path d="M130 76V90M130 96V98" stroke="#E56B8A" stroke-width="3.5" stroke-linecap="round"/>
          <rect x="98" y="114" width="64" height="6" rx="3" fill="#14B8A6" fill-opacity="0.5"/>
        </svg>';
    }

    if ($type === 'shop') {
        return '<svg class="bx-illus bx-hero-illus" viewBox="0 0 220 150" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect x="28" y="34" width="110" height="92" rx="18" fill="#FBF6EC" fill-opacity="0.95"/>
          <path d="M52 34V26C52 18 62 14 83 14C104 14 114 18 114 26V34" stroke="#F4A62A" stroke-width="4" stroke-linecap="round"/>
          <rect x="48" y="54" width="70" height="52" rx="10" fill="#0F766E"/>
          <rect x="116" y="44" width="76" height="84" rx="14" fill="#F4A62A"/>
          <circle cx="154" cy="84" r="18" fill="#14213D" fill-opacity="0.16"/>
          <path d="M144 84H164M154 74V94" stroke="#14213D" stroke-width="3" stroke-linecap="round"/>
        </svg>';
    }

    if ($type === 'guides') {
        return '<svg class="bx-illus bx-hero-illus" viewBox="0 0 220 150" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect x="24" y="24" width="172" height="106" rx="20" fill="#FBF6EC" fill-opacity="0.96"/>
          <path d="M110 34V120" stroke="#2B2F77" stroke-width="2.5" stroke-dasharray="4 4"/>
          <rect x="42" y="42" width="52" height="8" rx="4" fill="#2B2F77"/>
          <rect x="42" y="58" width="46" height="6" rx="3" fill="#14B8A6"/>
          <rect x="42" y="72" width="50" height="6" rx="3" fill="#4A5572" fill-opacity="0.5"/>
          <circle cx="152" cy="70" r="22" fill="#F4A62A" fill-opacity="0.25"/>
          <path d="M144 78L152 54L160 78L152 72L144 78Z" fill="#2B2F77"/>
        </svg>';
    }

    if ($type === 'contact') {
        return '<svg class="bx-illus bx-hero-illus" viewBox="0 0 220 150" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect x="26" y="28" width="168" height="96" rx="20" fill="#FBF6EC" fill-opacity="0.96"/>
          <path d="M40 42L110 86L180 42" stroke="#0F766E" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="174" cy="36" r="18" fill="#14B8A6"/>
          <path d="M167 36L172 41L181 31" stroke="#0B1020" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>';
    }

    if ($type === 'faq') {
        return '<svg class="bx-illus bx-hero-illus" viewBox="0 0 220 150" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect x="26" y="22" width="120" height="82" rx="20" fill="#FBF6EC" fill-opacity="0.96"/>
          <rect x="92" y="54" width="104" height="72" rx="18" fill="#F4A62A"/>
          <circle cx="78" cy="62" r="22" fill="#2B2F77" fill-opacity="0.14"/>
          <text x="78" y="71" text-anchor="middle" font-size="28" font-weight="700" fill="#2B2F77" font-family="sans-serif">?</text>
          <path d="M114 82H172M114 98H156" stroke="#14213D" stroke-width="3.5" stroke-linecap="round"/>
        </svg>';
    }

    // Default: empty folder / shelf illustration
    return '<svg class="bx-illus" viewBox="0 0 260 180" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <defs>
        <linearGradient id="ilEmpA" x1="60" y1="40" x2="200" y2="140" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#2B2F77"/>
          <stop offset="100%" stop-color="#14B8A6"/>
        </linearGradient>
      </defs>
      <ellipse cx="130" cy="154" rx="88" ry="12" fill="#14B8A6" fill-opacity="0.14"/>
      <path d="M58 54C58 46.268 64.268 40 72 40H108L122 54H188C195.732 54 202 60.268 202 68V132C202 139.732 195.732 146 188 146H72C64.268 146 58 139.732 58 132V54Z" fill="#2B2F77" stroke="rgba(182,192,220,0.35)" stroke-width="1.5"/>
      <rect x="78" y="48" width="104" height="44" rx="10" fill="#FBF6EC" transform="rotate(-3 78 48)"/>
      <rect x="86" y="54" width="96" height="40" rx="10" fill="#F4A62A" fill-opacity="0.85" transform="rotate(3 86 54)"/>
      <rect x="58" y="70" width="144" height="76" rx="18" fill="url(#ilEmpA)"/>
      <circle cx="130" cy="108" r="16" fill="#FBF6EC" fill-opacity="0.2"/>
      <path d="M124 108H136M130 102V114" stroke="#FBF6EC" stroke-width="2.6" stroke-linecap="round"/>
      <circle cx="208" cy="46" r="10" fill="#E56B8A" fill-opacity="0.4"/>
    </svg>';
}
