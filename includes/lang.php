<?php
declare(strict_types=1);

/**
 * Bookexa Bilingual Language System (Bangla default + English toggle)
 */
function get_default_language_strings(): array
{
    return [
        'site_name'            => ['bn' => 'Bookexa', 'en' => 'Bookexa'],
        'tagline'              => ['bn' => 'নিরাপদ ডিজিটাল বই ও একাডেমিক পিডিএফ ভুবন', 'en' => 'Secure Digital Books & Study PDF Platform'],
        'meta_default_desc'    => ['bn' => 'Bookexa-তে একাডেমিক, চাকরি, ভর্তি ও সৃজনশীল ডিজিটাল বই ও পিডিএফ পড়ুন নিরাপদ রিডারে।', 'en' => 'Explore academic, job prep, admission, and creative PDFs & books on Bookexa.'],
        'nav_home'             => ['bn' => 'হোম', 'en' => 'Home'],
        'nav_pdfs'             => ['bn' => 'পিডিএফ সংগ্রহ', 'en' => 'PDFs'],
        'nav_guides'           => ['bn' => 'স্টাডি গাইড', 'en' => 'Guides'],
        'nav_authors'          => ['bn' => 'লেখকবৃন্দ', 'en' => 'Authors'],
        'nav_shop'             => ['bn' => 'শপিং', 'en' => 'Shop'],
        'nav_about'            => ['bn' => 'আমাদের সম্পর্কে', 'en' => 'About'],
        'nav_contact'          => ['bn' => 'যোগাযোগ', 'en' => 'Contact'],
        'nav_library'          => ['bn' => 'আমার লাইব্রেরি', 'en' => 'My Library'],
        'nav_search'           => ['bn' => 'খুঁজুন', 'en' => 'Search'],
        'nav_login'            => ['bn' => 'লগইন', 'en' => 'Login'],
        'nav_logout'           => ['bn' => 'লগআউট', 'en' => 'Logout'],
        'search_placeholder'   => ['bn' => 'বই, লেখক বা ফোল্ডার খুঁজুন...', 'en' => 'Search books, authors, or folders...'],
        'lang_toggle_label'    => ['bn' => 'EN', 'en' => 'বাং'],

        // Homepage
        'hero_badge'           => ['bn' => 'ডিজিটাল রিডিং • ওয়াটারমার্ক সুরক্ষিত', 'en' => 'Digital Reading • Watermark Protected'],
        'portal_pdf_title'     => ['bn' => 'PDF / ই-বুক', 'en' => 'PDF / e-Book'],
        'portal_pdf_desc'      => ['bn' => 'একাডেমিক নোট, চাকরি, ভর্তি প্রস্তুতি ও ড্রয়িং পিডিএফ ব্রাউজ করুন এবং নিরাপদ রিডারে পড়ুন।', 'en' => 'Browse academic notes, job prep, admission & drawing PDFs in our secure viewer.'],
        'portal_pdf_cta'       => ['bn' => 'ফোল্ডার ব্রাউজ করুন', 'en' => 'Browse Folders'],
        'portal_shop_title'    => ['bn' => 'শপিং', 'en' => 'Book Shop'],
        'portal_shop_desc'     => ['bn' => 'প্রিন্টেড বই, নোটবুক ও স্টাডি কিট সংগ্রহ করুন — দ্রুত ক্যাশ অন ডেলিভারি ও অনলাইন পেমেন্ট সুবিধা।', 'en' => 'Order printed books, notebooks & study kits with fast cash on delivery or mobile payment.'],
        'portal_shop_cta'      => ['bn' => 'শপ দেখুন', 'en' => 'Browse Shop'],
        'featured_folders'     => ['bn' => 'জনপ্রিয় বিষয় ও ফোল্ডার', 'en' => 'Featured Folders'],
        'featured_folders_sub' => ['bn' => 'যেকোনো ফোল্ডার খুলে ভেতরের অধ্যায় ও পিডিএফ দেখুন', 'en' => 'Open any folder to explore nested topics and PDFs'],
        'newest_pdfs'          => ['bn' => 'সর্বশেষ যুক্ত পিডিএফ', 'en' => 'Newest PDFs'],
        'newest_pdfs_sub'      => ['bn' => 'সম্প্রতি প্রকাশিত ডিজিটাল বই ও হ্যান্ডনোট', 'en' => 'Recently published digital editions and study notes'],
        'view_all'             => ['bn' => 'সবগুলো দেখুন', 'en' => 'View All'],
        'about_short_title'    => ['bn' => 'কেন Bookexa ব্যবহার করবেন?', 'en' => 'Why Read with Bookexa?'],
        'about_short_desc'     => ['bn' => 'বাংলাদেশের শিক্ষার্থী ও পাঠকদের জন্য তৈরি একটি আধুনিক ডিজিটাল প্ল্যাটফর্ম—যেখানে সাজানো ফোল্ডার, সহজ বিকাশ/নগদ পেমেন্ট এবং ওয়াটারমার্ক সুরক্ষিত ইন-ব্রাউজার রিডার একসাথে পাওয়া যায়।', 'en' => 'A modern digital reading platform built for Bangladesh—combining organized nested folders, simple bKash/Nagad verification, and a watermarked in-browser reader.'],
        'learn_more'           => ['bn' => 'আরও জানুন', 'en' => 'Learn More'],

        // Folders & PDFs
        'browse_folders_title' => ['bn' => 'সকল পিডিএফ ফোল্ডার', 'en' => 'All PDF Folders'],
        'subfolders'           => ['bn' => 'সাব-ফোল্ডারসমূহ', 'en' => 'Subfolders'],
        'pdfs_in_folder'       => ['bn' => 'এই ফোল্ডারের পিডিএফসমূহ', 'en' => 'PDFs in this Folder'],
        'filter_placeholder'   => ['bn' => 'এই ফোল্ডারে খুঁজুন...', 'en' => 'Filter inside this folder...'],
        'empty_folder_title'   => ['bn' => 'এখনো কোনো কনটেন্ট নেই', 'en' => 'Nothing Here Yet'],
        'empty_folder_desc'    => ['bn' => 'এই ফোল্ডারে এখনো কোনো সাব-ফোল্ডার বা পিডিএফ যোগ করা হয়নি।', 'en' => 'No subfolders or PDFs have been added to this folder yet.'],
        'back_to_all_folders'  => ['bn' => 'সব ফোল্ডারে ফিরে যান', 'en' => 'Back to All Folders'],
        'folders_count'        => ['bn' => 'টি ফোল্ডার', 'en' => 'folders'],
        'books_count'          => ['bn' => 'টি পিডিএফ', 'en' => 'PDFs'],

        // PDF Detail
        'free_badge'           => ['bn' => 'ফ্রি', 'en' => 'Free'],
        'author_label'         => ['bn' => 'লেখক / প্রকাশক', 'en' => 'Author'],
        'pages_label'          => ['bn' => 'পৃষ্ঠা', 'en' => 'Pages'],
        'sample_label'         => ['bn' => 'ফ্রি স্যাম্পল', 'en' => 'Free Sample'],
        'first_n_pages'        => ['bn' => 'প্রথম %s পৃষ্ঠা', 'en' => 'First %s pages'],
        'read_sample_btn'      => ['bn' => 'ফ্রি স্যাম্পল পড়ুন', 'en' => 'Read Free Sample'],
        'read_now_btn'         => ['bn' => 'এখনই পড়ুন', 'en' => 'Read Now'],
        'buy_now_btn'          => ['bn' => 'কিনুন ও আনলক করুন', 'en' => 'Buy Now'],
        'security_note'        => ['bn' => 'নিরাপদ ইন-ব্রাউজার রিডার • ডাউনলোড ছাড়া সরাসরি পড়া • ব্যক্তিগত ওয়াটারমার্ক', 'en' => 'Secure in-browser reader • No raw file download • Personal watermark'],
        'related_pdfs'         => ['bn' => 'একই ফোল্ডারের আরও বই', 'en' => 'More from this Folder'],
        'view_details'         => ['bn' => 'বিস্তারিত', 'en' => 'Details'],

        // Shop, Search, About, Contact, 404
        'shop_title'           => ['bn' => 'Bookexa শপ', 'en' => 'Bookexa Shop'],
        'shop_sub'             => ['bn' => 'প্রিন্টেড বই, একাডেমিক গাইড ও স্টাডি কিট অর্ডার করুন সারা বাংলাদেশে হোম ডেলিভারিতে।', 'en' => 'Order printed books, academic guides, and study kits with fast home delivery across Bangladesh.'],
        'shop_badge'           => ['bn' => 'প্রিন্টেড শপ', 'en' => 'Book Store'],
        'nav_cart'             => ['bn' => 'কার্ট', 'en' => 'Cart'],
        'nav_track_order'      => ['bn' => 'অর্ডার ট্র্যাক', 'en' => 'Track Order'],
        'search_title'         => ['bn' => 'অনুসন্ধান', 'en' => 'Search'],
        'search_empty'         => ['bn' => 'আপনার খোঁজা শব্দে কোনো ফোল্ডার বা পিডিএফ পাওয়া যায়নি।', 'en' => 'No matching folders or PDFs were found for your query.'],
        'about_title'          => ['bn' => 'Bookexa সম্পর্কে', 'en' => 'About Bookexa'],
        'contact_title'        => ['bn' => 'যোগাযোগ করুন', 'en' => 'Contact Us'],
        'contact_sub'          => ['bn' => 'যেকোনো প্রশ্ন, পেমেন্ট সহায়তা বা বই প্রকাশের জন্য আমাদের বার্তা পাঠান।', 'en' => 'Send us a message for order support, questions, or publishing inquiries.'],
        'send_message'         => ['bn' => 'বার্তা পাঠান', 'en' => 'Send Message'],
        'not_found_title'      => ['bn' => 'পাতাটি পাওয়া যায়নি (৪০৪)', 'en' => 'Page Not Found (404)'],
        'not_found_desc'       => ['bn' => 'আপনি যে ঠিকানাটি খুঁজছেন তা সরানো হয়েছে অথবা ভুল হতে পারে।', 'en' => 'The page you are looking for may have been moved or does not exist.'],
        'go_home'              => ['bn' => 'হোমে ফিরে যান', 'en' => 'Return Home'],
        'footer_rights'        => ['bn' => 'সর্বস্বত্ব সংরক্ষিত', 'en' => 'All rights reserved'],
        'nav_wishlist'         => ['bn' => 'পছন্দের তালিকা', 'en' => 'Wishlist'],
        'nav_notifications'    => ['bn' => 'বিজ্ঞপ্তি', 'en' => 'Notifications'],
        'reviews_title'        => ['bn' => 'পাঠকদের রিভিউ ও রেটিং', 'en' => 'Reader Reviews & Ratings'],
        'legal_terms'          => ['bn' => 'শর্তাবলি', 'en' => 'Terms'],
        'legal_privacy'        => ['bn' => 'গোপনীয়তা', 'en' => 'Privacy'],
        'legal_refund'         => ['bn' => 'রিফান্ড নীতি', 'en' => 'Refund Policy'],
        'legal_copyright'      => ['bn' => 'কপিরাইট ও ওয়াটারমার্ক', 'en' => 'Copyright & Watermark'],
        'legal_editorial'      => ['bn' => 'সম্পাদকীয় নীতিমালা', 'en' => 'Editorial Policy'],
        'legal_review_policy'  => ['bn' => 'বই পর্যালোচনা নীতি', 'en' => 'How We Review Books'],
        'legal_corrections'    => ['bn' => 'সংশোধনী নীতি', 'en' => 'Corrections Policy'],
        'nav_faq'              => ['bn' => 'প্রশ্নোত্তর (FAQ)', 'en' => 'FAQ'],
        'share_book'           => ['bn' => 'শেয়ার করুন', 'en' => 'Share'],
    ];
}

function is_bilingual_public_route(string $logicalPath): bool
{
    $p = '/' . ltrim($logicalPath, '/');
    if (in_array($p, ['/', '/pdf', '/shop', '/guides', '/authors', '/faq', '/search', '/about', '/contact', '/terms', '/privacy', '/refund-policy', '/copyright', '/editorial-policy', '/how-we-review', '/corrections-policy', '/feed.xml', '/atom.xml', '/llms.txt', '/llms-full.txt'], true)) {
        return true;
    }
    return str_starts_with($p, '/pdf/')
        || str_starts_with($p, '/book/')
        || str_starts_with($p, '/shop/')
        || str_starts_with($p, '/guides/')
        || str_starts_with($p, '/guide/')
        || str_starts_with($p, '/authors/')
        || str_starts_with($p, '/author/')
        || str_starts_with($p, '/hub/');
}

function handle_language_switch(): void
{
    $cookiePath = BASE_PATH !== '' ? BASE_PATH : '/';
    if (defined('BX_URL_HAS_EN') && BX_URL_HAS_EN && defined('BX_LOGICAL_ROUTE') && is_bilingual_public_route(BX_LOGICAL_ROUTE)) {
        $_SESSION['lang'] = 'en';
        $_SESSION['bx_lang_inited'] = 1;
        setcookie('bx_lang', 'en', [
            'expires'  => time() + 86400 * 365,
            'path'     => $cookiePath,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        return;
    }

    if (isset($_GET['lang']) && in_array($_GET['lang'], ['bn', 'en'], true)) {
        $lang = $_GET['lang'];
        $_SESSION['lang'] = $lang;
        $_SESSION['bx_lang_inited'] = 1;
        setcookie('bx_lang', $lang, [
            'expires'  => time() + 86400 * 365,
            'path'     => $cookiePath,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $redirParam = $_GET['redirect'] ?? $_GET['return'] ?? $_GET['next'] ?? null;
        if (is_string($redirParam) && $redirParam !== '') {
            $safeTarget = safe_internal_path($redirParam, '/');
            header('Location: ' . BASE_PATH . $safeTarget);
            exit;
        }
        return;
    }

    if (defined('BX_LOGICAL_ROUTE') && is_bilingual_public_route(BX_LOGICAL_ROUTE)) {
        $firstVisitDefaultEn = !isset($_SESSION['bx_lang_inited'])
            && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
            && BX_LOGICAL_ROUTE === '/'
            && ($_COOKIE['bx_lang'] ?? '') === 'en';
        $_SESSION['bx_lang_inited'] = 1;
        if ($firstVisitDefaultEn) {
            $qStr = (string) ($_SERVER['QUERY_STRING'] ?? '');
            header('Location: ' . BASE_PATH . '/en' . ($qStr !== '' ? ('?' . $qStr) : ''), true, 302);
            exit;
        }
        $_SESSION['lang'] = 'bn';
        setcookie('bx_lang', 'bn', [
            'expires'  => time() + 86400 * 365,
            'path'     => $cookiePath,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
}

function current_lang(): string
{
    if (defined('BX_URL_HAS_EN') && BX_URL_HAS_EN && defined('BX_LOGICAL_ROUTE') && is_bilingual_public_route(BX_LOGICAL_ROUTE)) {
        return 'en';
    }
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['bn', 'en'], true)) {
        return $_GET['lang'];
    }
    if (defined('BX_LOGICAL_ROUTE') && is_bilingual_public_route(BX_LOGICAL_ROUTE)) {
        return 'bn';
    }
    if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], ['bn', 'en'], true)) {
        return $_SESSION['lang'];
    }
    if (!empty($_COOKIE['bx_lang']) && in_array($_COOKIE['bx_lang'], ['bn', 'en'], true)) {
        $_SESSION['lang'] = $_COOKIE['bx_lang'];
        return $_COOKIE['bx_lang'];
    }
    $def = site_setting('default_language', 'bn');
    return in_array($def, ['bn', 'en'], true) ? $def : 'bn';
}

function t(string $key, ...$args): string
{
    static $dict = null;
    if ($dict === null) {
        $dict = get_default_language_strings();
        try {
            $rows = db_all('SELECT lang_key, text_bn, text_en FROM language_strings');
            foreach ($rows as $r) {
                if (!isset($dict[$r['lang_key']])) {
                    $dict[$r['lang_key']] = ['bn' => $r['text_bn'], 'en' => $r['text_en']];
                }
            }
        } catch (Throwable $e) {
        }
    }
    $lang = current_lang();
    $text = $dict[$key][$lang] ?? $dict[$key]['en'] ?? $key;
    return $args ? vsprintf($text, $args) : $text;
}

function bi(array $row, string $field, string $fallback = ''): string
{
    $lang = current_lang();
    $primary = trim((string) ($row[$field . '_' . $lang] ?? ''));
    if ($primary !== '') {
        return $primary;
    }
    $other = $lang === 'bn' ? 'en' : 'bn';
    $alt = trim((string) ($row[$field . '_' . $other] ?? ''));
    return $alt !== '' ? $alt : $fallback;
}

function num_localize($value): string
{
    $str = (string) $value;
    if (current_lang() !== 'bn') {
        return $str;
    }
    return str_replace(['0','1','2','3','4','5','6','7','8','9'], ['০','১','২','৩','৪','৫','৬','৭','৮','৯'], $str);
}

function format_bdt(float $amount): string
{
    $formatted = ($amount == floor($amount)) ? number_format($amount, 0) : number_format($amount, 2);
    return '৳' . num_localize($formatted);
}
