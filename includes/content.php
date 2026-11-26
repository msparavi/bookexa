<?php
declare(strict_types=1);

/**
 * Bookexa Editorial Content Engine:
 * - Safe Built-In Rich Text Sanitizer & Auto-TOC (h2/h3)
 * - Bangla-aware word count, reading time, excerpts & spelling-variant search normalization
 * - Live & Server-Side Content Quality Checklist + Duplicate Content Blocker
 * - RSS 2.0 & Atom 1.0 Feeds
 * - Internal Link / Orphan Page Analyzer (< 3 incoming links)
 */

/**
 * Strip zero-width joiners/spaces and compose decomposed Bangla nukta forms.
 */
function content_strip_zero_width(string $text): string
{
    $clean = str_replace(
        ["\u{200C}", "\u{200D}", "\u{200B}", "\u{FEFF}", "\u{00AD}"],
        '',
        $text
    );
    return str_replace(
        ["\u{09A1}\u{09BC}", "\u{09A2}\u{09BC}", "\u{09AF}\u{09BC}"],
        ['ড়', 'ঢ়', 'য়'],
        $clean
    );
}

/**
 * Normalize common Bangla spelling variants for search queries and return bounded variant list.
 */
function content_bangla_search_variants(string $query): array
{
    $base = trim(preg_replace('/\s+/u', ' ', content_strip_zero_width($query)) ?? '');
    if ($base === '') {
        return [];
    }

    $variants = [$base];

    // Direct composed/decomposed forms + high-frequency academic/Bangla spelling equivalences
    $variants[] = str_replace(['ড়', 'ঢ়', 'য়'], ["\u{09A1}\u{09BC}", "\u{09A2}\u{09BC}", "\u{09AF}\u{09BC}"], $base);

    $commonMap = [
        'পরিক্ষা'   => 'পরীক্ষা',
        'পরীক্ষা'   => 'পরিক্ষা',
        'অধ্যায়'    => 'অধ্যায়',
        'অধ্যায়'    => 'অধ্যায়',
        'রসায়ন'    => 'রসায়ন',
        'রসায়ন'    => 'রসায়ন',
        'বিশ্ববিদ্যালয়' => 'বিশ্ববিদ্যালয়',
        'বিশ্ববিদ্যালয়' => 'বিশ্ববিদ্যালয়',
        'ব্যবসায়'  => 'ব্যবসায়',
        'ব্যবসায়'  => 'ব্যবসায়',
        'বিসিএস'    => 'BCS',
        'bcs'       => 'বিসিএস',
        'এসএসসি'    => 'SSC',
        'ssc'       => 'এসএসসি',
        'এইচএসসি'  => 'HSC',
        'hsc'       => 'এইচএসসি',
        'মেডিকেল'   => 'Medical',
        'medical'   => 'মেডিকেল',
        'বাংলা'     => 'Bangla',
        'bangla'    => 'বাংলা',
        'ইংরেজি'    => 'English',
        'english'   => 'ইংরেজি',
        'গণিত'      => 'Math',
        'math'      => 'গণিত',
        'পদার্থবিজ্ঞান' => 'Physics',
        'physics'   => 'পদার্থবিজ্ঞান',
        'জীববিজ্ঞান' => 'Biology',
        'biology'   => 'জীববিজ্ঞান',
        'গাইড'      => 'Guide',
        'guide'     => 'গাইড',
        'নোট'       => 'নোটস',
        'নোটস'     => 'নোট',
    ];
    $low = mb_strtolower($base, 'UTF-8');
    $swapped = strtr($low, $commonMap);
    if ($swapped !== '' && $swapped !== $low) {
        $variants[] = $swapped;
    }

    // Vowel length / sibilant spelling variants (ঈ<->ই, ী<->ি, ূ<->ু, ঙ<->ং)
    $alt1 = str_replace(['ী', 'ূ', 'ঙ'], ['ি', 'ু', 'ং'], $base);
    $alt2 = str_replace(['ি', 'ু', 'ং'], ['ী', 'ূ', 'ঙ'], $base);
    $alt3 = str_replace(['য়', 'য'], ['য', 'য়'], $base);
    foreach ([$alt1, $alt2, $alt3] as $a) {
        if ($a !== '' && $a !== $base) {
            $variants[] = $a;
        }
    }

    $uniq = [];
    foreach ($variants as $v) {
        $v = trim($v);
        if ($v !== '' && !in_array($v, $uniq, true)) {
            $uniq[] = $v;
            if (count($uniq) >= 6) {
                break;
            }
        }
    }
    return $uniq;
}

/**
 * Word count across Bangla & English text cleanly ignoring tags & zero-width artifacts.
 */
function content_word_count(string $htmlOrText): int
{
    $plain = content_strip_zero_width(strip_tags(html_entity_decode($htmlOrText, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? '');
    if ($plain === '') {
        return 0;
    }
    $words = preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY);
    return is_array($words) ? count($words) : 0;
}

/**
 * Realistic Bangla + English reading time in minutes (~180 wpm).
 */
function content_reading_time(string $htmlOrText): int
{
    $w = content_word_count($htmlOrText);
    return max(1, (int) ceil($w / 180));
}

/**
 * Bangla-aware word-boundary excerpt truncation.
 */
function content_excerpt(string $htmlOrText, int $maxChars = 155): string
{
    $plain = content_strip_zero_width(strip_tags(html_entity_decode($htmlOrText, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? '');
    if ($plain === '' || mb_strlen($plain, 'UTF-8') <= $maxChars) {
        return $plain;
    }
    $slice = mb_substr($plain, 0, max(10, $maxChars - 1), 'UTF-8');
    $sp = mb_strrpos($slice, ' ', 0, 'UTF-8');
    if ($sp !== false && $sp >= (int) floor($maxChars * 0.55)) {
        $slice = mb_substr($slice, 0, $sp, 'UTF-8');
    }
    $cleanedSlice = preg_replace('/[\s,;:\-|—–।]+$/u', '', $slice) ?? $slice;
    return $cleanedSlice . '…';
}

/**
 * Compute canonical SHA-256 content hash for exact-duplicate blocking.
 */
function content_normalized_hash(string $bnHtml, string $enHtml): string
{
    $norm = static function (string $s): string {
        $t = mb_strtolower(content_strip_zero_width(strip_tags(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $t) ?? '');
    };
    $b = $norm($bnHtml);
    $e = $norm($enHtml);
    return hash('sha256', $b . '|' . $e);
}

/**
 * Allowlist rich-HTML sanitizer (zero CDN / zero scripts / safe URLs & images).
 */
function content_sanitize_rich_html(string $raw): string
{
    $raw = trim(content_strip_zero_width($raw));
    if ($raw === '') {
        return '';
    }

    // If plain text without block HTML tags was entered, wrap paragraphs safely
    if (!preg_match('/<(p|h2|h3|h4|ul|ol|blockquote|table|pre)\b/i', $raw)) {
        $paras = preg_split('/\R{2,}/u', $raw) ?: [$raw];
        $wrapped = [];
        foreach ($paras as $p) {
            $t = trim($p);
            if ($t !== '') {
                $wrapped[] = '<p>' . nl2br(htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
            }
        }
        return implode("\n", $wrapped);
    }

    if (!class_exists('DOMDocument')) {
        return strip_tags($raw, '<p><br><h2><h3><h4><ul><ol><li><strong><b><em><i><u><blockquote><pre><code><a><img><table><thead><tbody><tr><th><td><figure><figcaption><hr>');
    }

    $allowedTags = [
        'p', 'br', 'h2', 'h3', 'h4', 'ul', 'ol', 'li',
        'strong', 'b', 'em', 'i', 'u', 'blockquote', 'pre', 'code',
        'a', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'figure', 'figcaption', 'hr',
    ];
    $dropCompletely = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'textarea', 'select'];

    $dom = new DOMDocument('1.0', 'UTF-8');
    $prevErr = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="bx-root">' . $raw . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prevErr);

    $root = $dom->getElementById('bx-root');
    if (!$root) {
        return strip_tags($raw, '<p><br><h2><h3><h4><ul><ol><li><strong><b><em><i><a>');
    }

    $cleanNode = static function (DOMNode $node) use (&$cleanNode, $dom, $allowedTags, $dropCompletely): string {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return htmlspecialchars($node->nodeValue ?? '', ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }
        /** @var DOMElement $node */
        $tag = strtolower($node->tagName);
        if (in_array($tag, $dropCompletely, true)) {
            return '';
        }

        // Convert stray h1 inside rich body -> h2 so page keeps strictly one H1
        if ($tag === 'h1') {
            $tag = 'h2';
        }

        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $cleanNode($child);
        }

        if (!in_array($tag, $allowedTags, true)) {
            return $inner;
        }

        if ($tag === 'br' || $tag === 'hr') {
            return "<{$tag}>";
        }

        $attrs = [];
        if ($tag === 'a') {
            $href = trim($node->getAttribute('href'));
            $isSafeHref = str_starts_with($href, '/')
                || str_starts_with($href, '#')
                || (preg_match('#^(https?://|mailto:)#i', $href) === 1);
            if (!$isSafeHref || str_starts_with($href, '//') || str_contains($href, '\\')) {
                return $inner;
            }
            $attrs['href'] = $href;
            if (preg_match('#^https?://#i', $href) && (!defined('BASE_URL') || !str_starts_with($href, BASE_URL))) {
                $attrs['target'] = '_blank';
                $attrs['rel'] = 'noopener noreferrer';
            }
            $titleAttr = trim($node->getAttribute('title'));
            if ($titleAttr !== '') {
                $attrs['title'] = $titleAttr;
            }
        } elseif ($tag === 'img') {
            $src = trim($node->getAttribute('src'));
            if ($src === '' || (!str_starts_with($src, '/') && !preg_match('#^https?://#i', $src))) {
                return '';
            }
            $attrs['src'] = $src;
            $attrs['alt'] = trim($node->getAttribute('alt'));
            $attrs['loading'] = 'lazy';
            $w = (int) $node->getAttribute('width');
            $h = (int) $node->getAttribute('height');
            if ($w > 0) $attrs['width'] = (string) $w;
            if ($h > 0) $attrs['height'] = (string) $h;
            $attrStr = '';
            foreach ($attrs as $ak => $av) {
                $attrStr .= ' ' . $ak . '="' . htmlspecialchars($av, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
            return "<img{$attrStr}>";
        } elseif (in_array($tag, ['h2', 'h3', 'h4'], true)) {
            $idAttr = preg_replace('/[^a-zA-Z0-9_-]/', '', $node->getAttribute('id')) ?? '';
            if ($idAttr !== '') {
                $attrs['id'] = $idAttr;
            }
        }

        $attrStr = '';
        foreach ($attrs as $ak => $av) {
            $attrStr .= ' ' . $ak . '="' . htmlspecialchars($av, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        return "<{$tag}{$attrStr}>{$inner}</{$tag}>";
    };

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $cleanNode($child);
    }
    return trim($out);
}

/**
 * Extract auto-TOC from h2/h3 headings and attach anchor IDs safely.
 */
function content_extract_headings_and_toc(string $sanitizedHtml): array
{
    if ($sanitizedHtml === '') {
        return ['html' => '', 'toc' => []];
    }
    $toc = [];
    $idx = 0;
    $updated = preg_replace_callback(
        '#<(h[23])([^>]*)>(.*?)</\1>#is',
        static function (array $m) use (&$toc, &$idx): string {
            $idx++;
            $tag = strtolower($m[1]);
            $level = $tag === 'h2' ? 2 : 3;
            $attrs = $m[2];
            $inner = $m[3];
            $plainText = trim(strip_tags(html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($plainText === '') {
                return $m[0];
            }
            $id = 'sec-' . $idx;
            if (preg_match('/\bid="([^"]+)"/i', $attrs, $im)) {
                $id = $im[1];
            } else {
                $attrs .= ' id="' . e($id) . '"';
            }
            $toc[] = ['id' => $id, 'level' => $level, 'text' => $plainText];
            return "<{$tag}{$attrs}>{$inner} <a class=\"bx-heading-anchor\" href=\"#" . e($id) . '" aria-label="Permalink">#</a>' . "</{$tag}>";
        },
        $sanitizedHtml
    );

    return ['html' => $updated ?? $sanitizedHtml, 'toc' => $toc];
}

/**
 * Analyze editorial content quality (headings, alt text, words, readability, internal links, duplicates).
 */
function content_quality_analyze(array $input, ?int $excludeGuideId = null): array
{
    $title   = trim((string) ($input['title'] ?? ''));
    $desc    = trim((string) ($input['description'] ?? ''));
    $bodyBn  = (string) ($input['body_bn'] ?? '');
    $bodyEn  = (string) ($input['body_en'] ?? '');
    $relCnt  = (int) ($input['related_count'] ?? 0);

    $titleLen = mb_strlen($title, 'UTF-8');
    $descLen  = mb_strlen($desc, 'UTF-8');

    $combinedHtml = trim($bodyBn . "\n" . $bodyEn);
    $wordsBn = content_word_count($bodyBn);
    $wordsEn = content_word_count($bodyEn);
    $primaryWords = max($wordsBn, $wordsEn);

    // Check single H1 (editor must NOT inject extra <h1> inside rich body)
    $bodyHasH1 = preg_match('/<h1\b/i', $combinedHtml) === 1;

    // Heading sequence check
    preg_match_all('/<h([2-6])\b/i', $bodyBn !== '' ? $bodyBn : $bodyEn, $hm);
    $levels = array_map('intval', $hm[1] ?? []);
    $hasH2 = in_array(2, $levels, true);
    $headingJump = false;
    $prevLv = 2;
    foreach ($levels as $lv) {
        if ($lv > $prevLv + 1) {
            $headingJump = true;
            break;
        }
        $prevLv = $lv;
    }

    // Images alt check
    preg_match_all('/<img\b[^>]*>/i', $combinedHtml, $imgs);
    $missingAlt = 0;
    foreach ($imgs[0] ?? [] as $imgTag) {
        if (!preg_match('/\balt="[^"]+"/i', $imgTag)) {
            $missingAlt++;
        }
    }

    // Internal links count (body links + explicitly linked books/folders)
    preg_match_all('/<a\b[^>]*href="([^"]+)"/i', $combinedHtml, $links);
    $internalLinks = $relCnt;
    foreach ($links[1] ?? [] as $href) {
        if (str_starts_with($href, '/') || (defined('BASE_URL') && str_starts_with($href, BASE_URL))) {
            $internalLinks++;
        }
    }

    // Readability (paragraphs & avg sentence length)
    $primaryHtml = $bodyBn !== '' ? $bodyBn : $bodyEn;
    $plain = content_strip_zero_width(strip_tags($primaryHtml));
    $sentences = preg_split('/[।.!?]+/u', trim($plain), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $avgWordsPerSentence = count($sentences) > 0 ? round($primaryWords / count($sentences), 1) : $primaryWords;
    $readabilityOk = $primaryWords >= 60 && $avgWordsPerSentence <= 30;

    // Duplicate check against existing guides
    $hash = content_normalized_hash($bodyBn, $bodyEn);
    $isDuplicate = false;
    if ($primaryWords > 10) {
        $dup = $excludeGuideId
            ? db_one('SELECT id, title_en FROM guides WHERE content_hash = ? AND id != ? LIMIT 1', [$hash, $excludeGuideId])
            : db_one('SELECT id, title_en FROM guides WHERE content_hash = ? LIMIT 1', [$hash]);
        $isDuplicate = $dup !== null;
    }

    return [
        'title_len'            => $titleLen,
        'title_ok'             => $titleLen >= 25 && $titleLen <= 75,
        'desc_len'             => $descLen,
        'desc_ok'              => $descLen >= 60 && $descLen <= 165,
        'single_h1_ok'         => !$bodyHasH1,
        'heading_structure_ok' => $hasH2 && !$headingJump,
        'images_alt_ok'        => $missingAlt === 0,
        'word_count'           => $primaryWords,
        'word_count_ok'        => $primaryWords >= 150,
        'readability_ok'       => $readabilityOk,
        'avg_sentence_words'   => $avgWordsPerSentence,
        'internal_links'       => $internalLinks,
        'internal_links_ok'    => $internalLinks >= 2,
        'is_duplicate'         => $isDuplicate,
        'content_hash'         => $hash,
    ];
}

/**
 * Parse comma-separated tag list cleanly into trimmed unique strings.
 */
function content_parse_tags(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }
    $out = [];
    foreach (explode(',', $raw) as $part) {
        $t = trim($part);
        if ($t !== '' && !in_array($t, $out, true)) {
            $out[] = $t;
        }
    }
    return $out;
}

/**
 * Parse comma-separated integer IDs cleanly.
 */
function content_parse_id_list(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }
    $ids = [];
    foreach (explode(',', $raw) as $part) {
        $n = (int) trim($part);
        if ($n > 0 && !in_array($n, $ids, true)) {
            $ids[] = $n;
        }
    }
    return $ids;
}

/**
 * Decode FAQs JSON field safely into uniform bilingual rows.
 */
function content_parse_faqs_json(?string $raw): array
{
    if ($raw === null || trim($raw) === '') {
        return [];
    }
    $dec = json_decode($raw, true);
    if (!is_array($dec)) {
        return [];
    }
    $out = [];
    foreach ($dec as $row) {
        if (!is_array($row)) continue;
        $qBn = trim((string) ($row['q_bn'] ?? ''));
        $aBn = trim((string) ($row['a_bn'] ?? ''));
        $qEn = trim((string) ($row['q_en'] ?? ''));
        $aEn = trim((string) ($row['a_en'] ?? ''));
        $url = trim((string) ($row['url'] ?? ''));
        if (($qBn !== '' && $aBn !== '') || ($qEn !== '' && $aEn !== '')) {
            $out[] = ['q_bn' => $qBn, 'a_bn' => $aBn, 'q_en' => $qEn, 'a_en' => $aEn, 'url' => $url];
        }
    }
    return $out;
}

/**
 * Serialize submitted FAQ arrays (faq_q_bn[], faq_a_bn[], faq_q_en[], faq_a_en[], faq_url[]) to JSON.
 */
function content_serialize_faqs_post(array $post): ?string
{
    $qBn = $post['faq_q_bn'] ?? [];
    $aBn = $post['faq_a_bn'] ?? [];
    $qEn = $post['faq_q_en'] ?? [];
    $aEn = $post['faq_a_en'] ?? [];
    $uArr = $post['faq_url'] ?? [];
    if (!is_array($qBn)) {
        return null;
    }
    $rows = [];
    foreach ($qBn as $i => $qb) {
        $qb = trim((string) $qb);
        $ab = trim((string) ($aBn[$i] ?? ''));
        $qe = trim((string) ($qEn[$i] ?? ''));
        $ae = trim((string) ($aEn[$i] ?? ''));
        $u  = trim((string) ($uArr[$i] ?? ''));
        if ($u !== '' && !str_starts_with($u, '/') && !preg_match('#^https?://#i', $u)) {
            $u = '';
        }
        if (($qb !== '' && $ab !== '') || ($qe !== '' && $ae !== '')) {
            $rows[] = ['q_bn' => $qb, 'a_bn' => $ab, 'q_en' => $qe, 'a_en' => $ae, 'url' => $u];
        }
    }
    return $rows ? json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
}

/**
 * Scheduled publishing SQL clause for guides.
 */
function content_guides_published_sql(string $alias = ''): string
{
    $p = $alias !== '' ? ($alias . '.') : '';
    return "({$p}is_active = 1 AND ({$p}publish_at IS NULL OR {$p}publish_at <= NOW()))";
}

/**
 * Build RSS 2.0 XML feed for published guides (never outputs protected book content).
 */
function content_build_rss_feed(string $lang): string
{
    $siteName = site_setting('site_name', 'Bookexa');
    $selfUrl = absolute_url_for_lang('/feed.xml', $lang);
    $homeUrl = absolute_url_for_lang('/', $lang);
    $desc = $lang === 'bn'
        ? 'বিসিএস, এসএসসি, এইচএসসি, মেডিকেল ও বিশ্ববিদ্যালয় ভর্তি প্রস্তুতির সর্বশেষ স্টাডি গাইড।'
        : 'Latest Bangla & English exam preparation guides, syllabus tips, and study strategies.';

    $guides = db_all(
        'SELECT slug, title_bn, title_en, summary_bn, summary_en, category_bn, category_en, author_name, publish_at, created_at, updated_at
         FROM guides WHERE ' . content_guides_published_sql() . ' ORDER BY COALESCE(publish_at, created_at) DESC LIMIT 30'
    );

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n"
        . "  <channel>\n"
        . '    <title>' . htmlspecialchars($siteName . ($lang === 'bn' ? ' — স্টাডি গাইড' : ' — Study Guides'), ENT_XML1, 'UTF-8') . "</title>\n"
        . '    <link>' . htmlspecialchars($homeUrl, ENT_XML1, 'UTF-8') . "</link>\n"
        . '    <description>' . htmlspecialchars($desc, ENT_XML1, 'UTF-8') . "</description>\n"
        . '    <language>' . ($lang === 'bn' ? 'bn-BD' : 'en-BD') . "</language>\n"
        . '    <atom:link href="' . htmlspecialchars($selfUrl, ENT_XML1, 'UTF-8') . "\" rel=\"self\" type=\"application/rss+xml\"/>\n";

    foreach ($guides as $g) {
        $gTitle = trim((string) ($g['title_' . $lang] ?: $g['title_en']));
        $gSum   = trim((string) ($g['summary_' . $lang] ?: $g['summary_en']));
        $gLink  = absolute_url_for_lang('/guides/' . rawurlencode((string) $g['slug']), $lang);
        $ts     = strtotime((string) ($g['publish_at'] ?: $g['created_at'])) ?: time();
        $xml .= "    <item>\n"
            . '      <title>' . htmlspecialchars($gTitle, ENT_XML1, 'UTF-8') . "</title>\n"
            . '      <link>' . htmlspecialchars($gLink, ENT_XML1, 'UTF-8') . "</link>\n"
            . '      <guid isPermaLink="true">' . htmlspecialchars($gLink, ENT_XML1, 'UTF-8') . "</guid>\n"
            . '      <pubDate>' . gmdate(DATE_RSS, $ts) . "</pubDate>\n"
            . '      <description>' . htmlspecialchars($gSum, ENT_XML1, 'UTF-8') . "</description>\n";
        if (!empty($g['category_' . $lang])) {
            $xml .= '      <category>' . htmlspecialchars((string) $g['category_' . $lang], ENT_XML1, 'UTF-8') . "</category>\n";
        }
        $xml .= "    </item>\n";
    }
    $xml .= "  </channel>\n</rss>";
    return $xml;
}

/**
 * Build Atom 1.0 XML feed for published guides.
 */
function content_build_atom_feed(string $lang): string
{
    $siteName = site_setting('site_name', 'Bookexa');
    $selfUrl = absolute_url_for_lang('/atom.xml', $lang);
    $homeUrl = absolute_url_for_lang('/', $lang);
    $guides = db_all(
        'SELECT slug, title_bn, title_en, summary_bn, summary_en, author_name, publish_at, created_at, updated_at
         FROM guides WHERE ' . content_guides_published_sql() . ' ORDER BY COALESCE(publish_at, created_at) DESC LIMIT 30'
    );
    $updTs = $guides ? (strtotime((string) ($guides[0]['updated_at'] ?? 'now')) ?: time()) : time();

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n"
        . '  <title>' . htmlspecialchars($siteName . ' Guides', ENT_XML1, 'UTF-8') . "</title>\n"
        . '  <link href="' . htmlspecialchars($homeUrl, ENT_XML1, 'UTF-8') . "\"/>\n"
        . '  <link href="' . htmlspecialchars($selfUrl, ENT_XML1, 'UTF-8') . "\" rel=\"self\"/>\n"
        . '  <updated>' . gmdate(DATE_ATOM, $updTs) . "</updated>\n"
        . '  <id>' . htmlspecialchars($selfUrl, ENT_XML1, 'UTF-8') . "</id>\n";

    foreach ($guides as $g) {
        $gTitle = trim((string) ($g['title_' . $lang] ?: $g['title_en']));
        $gSum   = trim((string) ($g['summary_' . $lang] ?: $g['summary_en']));
        $gLink  = absolute_url_for_lang('/guides/' . rawurlencode((string) $g['slug']), $lang);
        $ts     = strtotime((string) ($g['updated_at'] ?: $g['created_at'])) ?: time();
        $xml .= "  <entry>\n"
            . '    <title>' . htmlspecialchars($gTitle, ENT_XML1, 'UTF-8') . "</title>\n"
            . '    <link href="' . htmlspecialchars($gLink, ENT_XML1, 'UTF-8') . "\"/>\n"
            . '    <id>' . htmlspecialchars($gLink, ENT_XML1, 'UTF-8') . "</id>\n"
            . '    <updated>' . gmdate(DATE_ATOM, $ts) . "</updated>\n"
            . '    <author><name>' . htmlspecialchars((string) ($g['author_name'] ?: $siteName), ENT_XML1, 'UTF-8') . "</name></author>\n"
            . '    <summary>' . htmlspecialchars($gSum, ENT_XML1, 'UTF-8') . "</summary>\n"
            . "  </entry>\n";
    }
    $xml .= '</feed>';
    return $xml;
}

/**
 * Scan active pages & internal links across guides, hubs, folders, items, nav & footer
 * to detect orphan pages with fewer than 3 incoming internal links.
 */
function content_orphan_pages_report(): array
{
    $pages = []; // path => ['type' => ..., 'title' => ..., 'incoming' => 0]
    $register = static function (string $path, string $type, string $title) use (&$pages): void {
        $norm = '/' . ltrim($path, '/');
        if (!isset($pages[$norm])) {
            $pages[$norm] = ['path' => $norm, 'type' => $type, 'title' => $title, 'incoming' => 0];
        }
    };

    $pubF = published_window_sql();
    $folders = db_all("SELECT id, parent_id, slug, name_en FROM folders WHERE is_active = 1 AND {$pubF}");
    $folderById = [];
    foreach ($folders as $f) {
        $folderById[(int) $f['id']] = $f;
    }
    $folderPathMap = [];
    foreach ($folders as $f) {
        $fid = (int) $f['id'];
        $p = folder_path_string($fid);
        if ($p !== '') {
            $route = '/pdf/' . $p;
            $folderPathMap[$fid] = $route;
            $register($route, 'Folder', (string) $f['name_en']);
        }
    }

    $items = db_all('SELECT id, folder_id, slug, title_en FROM items WHERE is_active = 1 AND ' . published_window_sql());
    $itemPathMap = [];
    foreach ($items as $it) {
        $r = '/book/' . rawurlencode((string) $it['slug']);
        $itemPathMap[(int) $it['id']] = $r;
        $register($r, 'Book', (string) $it['title_en']);
    }

    $guides = db_all('SELECT id, slug, title_en, body_bn, body_en, related_item_ids, related_folder_ids, category_slug FROM guides WHERE ' . content_guides_published_sql());
    $guidePathMap = [];
    foreach ($guides as $g) {
        $r = '/guides/' . rawurlencode((string) $g['slug']);
        $guidePathMap[(int) $g['id']] = $r;
        $register($r, 'Guide', (string) $g['title_en']);
    }

    $hubs = db_all('SELECT id, slug, title_en, intro_bn, intro_en, guide_id, featured_item_ids, featured_folder_ids FROM hubs WHERE is_active = 1');
    foreach ($hubs as $h) {
        $r = '/hub/' . rawurlencode((string) $h['slug']);
        $register($r, 'Hub', (string) $h['title_en']);
    }

    $addHit = static function (string $targetPath) use (&$pages): void {
        $clean = (string) parse_url($targetPath, PHP_URL_PATH);
        if (defined('BASE_PATH') && BASE_PATH !== '' && str_starts_with($clean, BASE_PATH)) {
            $clean = substr($clean, strlen(BASE_PATH));
        }
        $clean = '/' . ltrim($clean, '/');
        if (str_starts_with($clean, '/en/')) {
            $clean = '/' . ltrim(substr($clean, 4), '/');
        }
        if (isset($pages[$clean])) {
            $pages[$clean]['incoming']++;
        }
    };

    // 1. Structural links: Home + /pdf root link top-level folders, parent folders link children, folders link items
    foreach ($folders as $f) {
        $fid = (int) $f['id'];
        $fRoute = $folderPathMap[$fid] ?? '';
        if ($fRoute === '') continue;
        if ($f['parent_id'] === null) {
            $addHit($fRoute); // from /pdf
        } else {
            $addHit($fRoute); // from parent folder
            $parentRoute = $folderPathMap[(int) $f['parent_id']] ?? '';
            if ($parentRoute !== '') {
                $addHit($parentRoute); // breadcrumb back to parent
            }
        }
    }
    foreach ($items as $it) {
        $iRoute = $itemPathMap[(int) $it['id']] ?? '';
        if ($iRoute !== '' && !empty($it['folder_id']) && isset($folderPathMap[(int) $it['folder_id']])) {
            $addHit($iRoute); // listed inside its parent folder
            $addHit($folderPathMap[(int) $it['folder_id']]); // breadcrumb back to folder
        }
    }

    // 2. Scan Guide body HTML + explicit related IDs + category index link
    foreach ($guides as $g) {
        $gRoute = $guidePathMap[(int) $g['id']];
        $addHit($gRoute); // /guides index always links published guides
        if (!empty($g['category_slug'])) {
            $addHit($gRoute); // category page links guide
        }
        foreach (content_parse_id_list($g['related_item_ids'] ?? null) as $rid) {
            if (isset($itemPathMap[$rid])) $addHit($itemPathMap[$rid]);
        }
        foreach (content_parse_id_list($g['related_folder_ids'] ?? null) as $rfid) {
            if (isset($folderPathMap[$rfid])) $addHit($folderPathMap[$rfid]);
        }
        if (preg_match_all('/href="([^"]+)"/i', ($g['body_bn'] ?? '') . "\n" . ($g['body_en'] ?? ''), $am)) {
            foreach ($am[1] as $href) {
                $addHit($href);
            }
        }
    }

    // 3. Scan Hub links & Footer links
    foreach ($hubs as $h) {
        $addHit('/hub/' . rawurlencode((string) $h['slug'])); // linked from global footer / hubs
        if (!empty($h['guide_id']) && isset($guidePathMap[(int) $h['guide_id']])) {
            $addHit($guidePathMap[(int) $h['guide_id']]);
        }
        foreach (content_parse_id_list($h['featured_item_ids'] ?? null) as $rid) {
            if (isset($itemPathMap[$rid])) $addHit($itemPathMap[$rid]);
        }
        foreach (content_parse_id_list($h['featured_folder_ids'] ?? null) as $rfid) {
            if (isset($folderPathMap[$rfid])) $addHit($folderPathMap[$rfid]);
        }
    }

    $orphans = [];
    foreach ($pages as $p) {
        if ($p['incoming'] < 3) {
            $orphans[] = $p;
        }
    }
    usort($orphans, static fn(array $a, array $b): int => $a['incoming'] <=> $b['incoming']);

    return [
        'total_pages'   => count($pages),
        'orphan_count'  => count($orphans),
        'orphans'       => $orphans,
        'all'           => array_values($pages),
    ];
}
