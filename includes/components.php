<?php
declare(strict_types=1);

function bookexa_folder_color_pair(string $slug, string $accent): array
{
    $pairs = [
        'academic'            => ['#232766', '#0F766E'],
        'job'                 => ['#8B3A2B', '#C26A1B'],
        'admission'           => ['#1B4D89', '#0E7490'],
        'religious-knowledge' => ['#14532D', '#0F766E'],
        'arts-and-culture'    => ['#5B2C6F', '#A82849'],
        'drawing'             => ['#9A3412', '#B45309'],
        'ssc-class-9-10'      => ['#1E4E5A', '#0F766E'],
        'bangla'              => ['#9E3B25', '#B8294A'],
    ];
    if (isset($pairs[$slug])) {
        return $pairs[$slug];
    }
    return [$accent !== '' ? $accent : '#232766', '#0F766E'];
}

function render_folder_card(array $folder, string $href, array $options = []): string
{
    [$accent1, $accent2] = bookexa_folder_color_pair((string) ($folder['slug'] ?? ''), (string) ($folder['accent_color'] ?? ''));
    $accent  = e($accent1);
    $accent2 = e($accent2);
    $thumb = !empty($folder['thumbnail']) ? asset($folder['thumbnail']) : asset('uploads/folders/academic.svg');
    $title = bi($folder, 'name');
    $desc  = bi($folder, 'description');
    $hlQ   = (string) ($options['highlightQuery'] ?? '');
    $titleHtml = $hlQ !== '' ? highlight_search_text($title, $hlQ) : e($title);
    $subCount = (int) ($folder['subfolder_count'] ?? 0);
    $itemCount = (int) ($folder['item_count'] ?? 0);

    ob_start(); ?>
    <a href="<?= e($href) ?>" class="bx-folder-3d bx-reveal" data-folder-nav style="--folder-accent: <?= $accent ?>; --folder-accent-2: <?= $accent2 ?>;">
        <div class="bx-folder-inner">
            <div class="bx-folder-lid"></div>
            <div class="bx-folder-peek">
                <img src="<?= e($thumb) ?>" alt="<?= e($title) ?>" width="480" height="300" loading="lazy" decoding="async">
            </div>
            <div class="bx-folder-face">
                <h3 class="bx-folder-title"><?= $titleHtml ?></h3>
                <?php if ($desc !== ''): ?>
                    <p class="bx-folder-desc"><?= $hlQ !== '' ? highlight_search_text($desc, $hlQ) : e($desc) ?></p>
                <?php endif; ?>
                <div class="bx-folder-meta">
                    <span><?= icon('folder') ?> <?= e(num_localize($subCount)) ?> <?= e(t('folders_count')) ?></span>
                    <span><?= e(num_localize($itemCount)) ?> <?= e(t('books_count')) ?></span>
                </div>
            </div>
        </div>
    </a>
    <?php
    return ob_get_clean();
}

function render_book_card(array $item, array $options = []): string
{
    $isFree = (bool) $item['is_free'] || (float) $item['price'] <= 0;
    $cover = !empty($item['cover']) ? asset($item['cover']) : asset('uploads/covers/cover-bangla.svg');
    $title = bi($item, 'title');
    $author = bi($item, 'author');
    $hlQ = (string) ($options['highlightQuery'] ?? '');
    $titleHtml = $hlQ !== '' ? highlight_search_text($title, $hlQ) : e($title);
    $authorHtml = $hlQ !== '' ? highlight_search_text($author, $hlQ) : e($author);
    $itemId = (int) $item['id'];
    $detailUrl = url('/book/' . rawurlencode($item['slug']));
    $user = current_user();
    $isWished = $user ? user_has_wishlisted((int) $user['id'], $itemId) : false;
    $ratingInfo = item_rating_summary($itemId);
    $wishLabel = current_lang() === 'bn' ? 'পছন্দের তালিকায় রাখুন' : 'Toggle wishlist';

    ob_start(); ?>
    <div class="bx-book-3d bx-reveal">
        <article class="bx-book-card">
            <a href="<?= e($detailUrl) ?>" class="bx-book-cover">
                <img src="<?= e($cover) ?>" alt="<?= e($title) ?>" width="400" height="600" loading="lazy" decoding="async">
                <span class="bx-book-ribbon <?= $isFree ? 'free' : '' ?>">
                    <?= e($isFree ? t('free_badge') : format_bdt((float) $item['price'])) ?>
                </span>
            </a>
            <button type="button"
                    class="bx-wish-btn<?= $isWished ? ' active' : '' ?>"
                    data-wish-btn
                    data-item-id="<?= $itemId ?>"
                    aria-pressed="<?= $isWished ? 'true' : 'false' ?>"
                    aria-label="<?= e($wishLabel) ?>"><?= icon('heart') ?></button>
            <div class="bx-book-info">
                <a href="<?= e($detailUrl) ?>">
                    <h3 class="bx-book-title"><?= $titleHtml ?></h3>
                </a>
                <?php if ($author !== '' || $ratingInfo['count'] > 0): ?>
                    <p class="bx-book-author">
                        <?= $authorHtml ?>
                        <?php if ($ratingInfo['count'] > 0): ?>
                            <span class="bx-card-rating">★ <?= e(num_localize((string) $ratingInfo['avg'])) ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <div class="bx-book-footer">
                    <div class="bx-price">
                        <?= e($isFree ? t('free_badge') : format_bdt((float) $item['price'])) ?>
                        <?php if (!$isFree && !empty($item['original_price']) && (float) $item['original_price'] > (float) $item['price']): ?>
                            <span class="bx-price-old"><?= e(format_bdt((float) $item['original_price'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <a class="bx-btn bx-btn-primary bx-btn-sm" href="<?= e($detailUrl) ?>">
                        <?= e(t('view_details')) ?>
                    </a>
                </div>
            </div>
        </article>
    </div>
    <?php
    return ob_get_clean();
}
