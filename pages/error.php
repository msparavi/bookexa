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
        'bn' => ['পাতাটি পাওয়া যায়নি (৪০৪)', 'আপনি যে ঠিকানাটি খুঁজছেন তা সরানো হয়েছে অথবা অস্তিত্ব নেই।'],
        'en' => ['Page Not Found (404)', 'The page you are looking for may have been moved or does not exist.'],
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
$illus = $code === 404 ? '404' : 'error';
?>
<div class="container" style="padding-top:42px;padding-bottom:32px;max-width:740px;">
    <div class="bx-empty-state">
        <?= function_exists('illustration_svg') ? illustration_svg($illus) : '' ?>
        <h1 class="bx-display" style="font-size:clamp(1.45rem,3vw,2rem);margin-bottom:10px;"><?= e($title) ?></h1>
        <p class="bx-muted" style="margin:0 0 22px;"><?= e($desc) ?></p>
        <?php if ($code !== 503): ?>
            <div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;">
                <a href="<?= e(url('/')) ?>" class="bx-btn bx-btn-primary"><?= e(function_exists('t') ? t('go_home') : 'Home') ?></a>
                <a href="<?= e(url('/pdf')) ?>" class="bx-btn bx-btn-outline"><?= e(function_exists('t') ? t('nav_pdfs') : 'Browse PDFs') ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>
