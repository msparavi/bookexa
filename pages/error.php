<?php
declare(strict_types=1);
/** @var int $errCode, ?string $errTitle, ?string $errDesc */
$lang = function_exists('current_lang') ? current_lang() : 'en';
$code = $errCode ?? 404;

$defaults = [
    403 => [
        'bn' => ['প্রবেশাধিকার সংরক্ষিত (৪০৩)', 'এই পাতাটি দেখার অনুমতি আপনার নেই।'],
        'en' => ['Access Denied (403)', 'You do not have permission to access this resource.'],
    ],
    404 => [
        'bn' => ['পাতাটি পাওয়া যায়নি (৪০৪)', 'আপনি যে ঠিকানাটি খুঁজছেন তা সরানো হয়েছে অথবা অস্তিত্ব নেই। নিচে সার্চ করে বা জনপ্রিয় বিষয়গুলো থেকে আপনার কাঙ্ক্ষিত বই খুঁজে নিন।'],
        'en' => ['Page Not Found (404)', 'The page you are looking for may have been moved or removed. Search below or explore popular topics.'],
    ],
    410 => [
        'bn' => ['কনটেন্টটি স্থায়ীভাবে সরানো হয়েছে (৪১০)', 'আপনি যে পাতাটি খুঁজছেন তা স্থায়ীভাবে মুছে ফেলা হয়েছে এবং আর পাওয়া যাবে না।'],
        'en' => ['Content Permanently Removed (410)', 'The requested resource is permanently gone and will not return.'],
    ],
    500 => [
        'bn' => ['সার্ভার ত্রুটি (৫০০)', 'দুঃখিত, সাময়িক কারিগরি সমস্যা হয়েছে। অনুগ্রহ করে একটু পর আবার চেষ্টা করুন।'],
        'en' => ['Something Went Wrong (500)', 'An unexpected server error occurred. Our team has logged the issue cleanly.'],
    ],
    503 => [
        'bn' => ['সাইট রক্ষণাবেক্ষণ চলছে (৫০৩)', 'আমরা সিস্টেম আপডেট করছি। খুব শীঘ্রই ফিরে আসছি।'],
        'en' => ['Site Under Maintenance (503)', 'We are performing scheduled updates and will be back shortly.'],
    ],
];

$pair = $defaults[$code][$lang] ?? $defaults[500][$lang];
$title = $errTitle ?? $pair[0];
$desc  = $errDesc ?? $pair[1];
$illus = ($code === 404 || $code === 410) ? '404' : 'error';

$popularFolders = [];
if (in_array($code, [404, 410], true) && function_exists('get_cached_folder_tree')) {
    try {
        $fTree = get_cached_folder_tree();
        foreach (array_slice($fTree['children']['root'] ?? [], 0, 6) as $fid) {
            if (isset($fTree['byId'][(string) $fid])) {
                $popularFolders[] = $fTree['byId'][(string) $fid];
            }
        }
    } catch (Throwable $e) {
    }
}
?>
<div class="container" style="padding-top:42px;padding-bottom:44px;max-width:780px;">
    <div class="bx-empty-state">
        <?= function_exists('illustration_svg') ? illustration_svg($illus) : '' ?>
        <h1 class="bx-display" style="font-size:clamp(1.45rem,3vw,2rem);margin-bottom:10px;"><?= e($title) ?></h1>
        <p class="bx-muted" style="margin:0 0 22px;"><?= e($desc) ?></p>

        <?php if (in_array($code, [404, 410], true)): ?>
            <form method="get" action="<?= e(function_exists('url') ? url('/search') : '/search') ?>" style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;max-width:460px;margin:0 auto 22px;">
                <input type="search" name="q" class="bx-input" style="flex:1 1 220px;min-width:0;" placeholder="<?= e(function_exists('t') ? t('search_placeholder') : 'Search books, authors...') ?>" aria-label="<?= e(function_exists('t') ? t('nav_search') : 'Search') ?>">
                <button type="submit" class="bx-btn bx-btn-primary bx-btn-sm"><?= function_exists('icon') ? icon('search') : '' ?> <?= e(function_exists('t') ? t('nav_search') : 'Search') ?></button>
            </form>
        <?php endif; ?>

        <?php if ($code !== 503): ?>
            <div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;">
                <a href="<?= e(function_exists('url') ? url('/') : '/') ?>" class="bx-btn bx-btn-primary"><?= e(function_exists('t') ? t('go_home') : 'Home') ?></a>
                <a href="<?= e(function_exists('url') ? url('/pdf') : '/pdf') ?>" class="bx-btn bx-btn-outline"><?= e(function_exists('t') ? t('nav_pdfs') : 'Browse PDFs') ?></a>
                <a href="<?= e(function_exists('url') ? url('/guides') : '/guides') ?>" class="bx-btn bx-btn-ghost"><?= e(function_exists('t') ? t('nav_guides') : 'Guides') ?></a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($popularFolders)): ?>
        <section style="margin-top:34px;">
            <h2 class="bx-h2" style="font-size:1.2rem;margin-bottom:14px;text-align:center;">
                <?= $lang === 'bn' ? 'জনপ্রিয় বিষয় ও ফোল্ডারসমূহ' : 'Popular Folders & Topics' ?>
            </h2>
            <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:10px;">
                <?php foreach ($popularFolders as $pf): ?>
                    <a href="<?= e(url('/pdf/' . rawurlencode((string) $pf['slug']))) ?>" class="bx-chip" style="padding:8px 14px;font-weight:600;text-decoration:none;">
                        <?= e(bi($pf, 'name')) ?> (<?= e(num_localize((int) ($pf['item_count'] ?? 0))) ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
