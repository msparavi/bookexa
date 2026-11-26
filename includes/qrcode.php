<?php
declare(strict_types=1);

/**
 * Pure-PHP, dependency-free QR Code SVG generator (Byte mode, ECC Level L, Versions 1-6).
 * Keeps printable order invoices 100% self-contained without third-party CDNs or inline scripts.
 */
function qr_gf_mul(int $a, int $b): int
{
    $p = 0;
    for ($i = 0; $i < 8; $i++) {
        if ($b & 1) {
            $p ^= $a;
        }
        $hi = $a & 0x80;
        $a = ($a << 1) & 0xFF;
        if ($hi) {
            $a ^= 0x1D;
        }
        $b >>= 1;
    }
    return $p;
}

function qr_rs_divisor(int $degree): array
{
    $rs = array_fill(0, $degree, 0);
    $rs[$degree - 1] = 1;
    $root = 1;
    for ($i = 0; $i < $degree; $i++) {
        for ($j = 0; $j < $degree; $j++) {
            $rs[$j] = qr_gf_mul($rs[$j], $root);
            if ($j + 1 < $degree) {
                $rs[$j] ^= $rs[$j + 1];
            }
        }
        $root = qr_gf_mul($root, 0x02);
    }
    return $rs;
}

function qr_rs_compute(array $data, int $eccLen): array
{
    $div = qr_rs_divisor($eccLen);
    $res = array_fill(0, $eccLen, 0);
    foreach ($data as $byte) {
        $factor = $byte ^ array_shift($res);
        $res[] = 0;
        for ($i = 0; $i < $eccLen; $i++) {
            $res[$i] ^= qr_gf_mul($div[$i], $factor);
        }
    }
    return $res;
}

function qr_place_finder(array &$mat, array &$fn, int $r, int $c, int $sz): void
{
    for ($dy = -1; $dy <= 7; $dy++) {
        for ($dx = -1; $dx <= 7; $dx++) {
            $yy = $r + $dy;
            $xx = $c + $dx;
            if ($yy < 0 || $yy >= $sz || $xx < 0 || $xx >= $sz) {
                continue;
            }
            $dist = max(abs($dy - 3), abs($dx - 3));
            $val = ($dist === 2 || $dist === 4) ? 0 : 1;
            if ($dy === -1 || $dy === 7 || $dx === -1 || $dx === 7) {
                $val = 0;
            }
            $mat[$yy][$xx] = $val;
            $fn[$yy][$xx] = true;
        }
    }
}

function qr_place_alignment(array &$mat, array &$fn, int $r, int $c): void
{
    for ($dy = -2; $dy <= 2; $dy++) {
        for ($dx = -2; $dx <= 2; $dx++) {
            $dist = max(abs($dy), abs($dx));
            $mat[$r + $dy][$c + $dx] = ($dist === 1) ? 0 : 1;
            $fn[$r + $dy][$c + $dx] = true;
        }
    }
}

function qr_draw_format(array &$mat, array &$fn, int $sz, int $maskId): void
{
    // ECC Level L (01) + maskId (0..7), BCH encoded format bits
    $data = (1 << 3) | $maskId;
    $rem = $data;
    for ($i = 0; $i < 10; $i++) {
        $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
    }
    $bits = (($data << 10) | $rem) ^ 0x5412;

    for ($i = 0; $i <= 5; $i++) {
        $mat[8][$i] = ($bits >> $i) & 1;
        $fn[8][$i] = true;
    }
    $mat[8][7] = ($bits >> 6) & 1;
    $fn[8][7] = true;
    $mat[8][8] = ($bits >> 7) & 1;
    $fn[8][8] = true;
    $mat[7][8] = ($bits >> 8) & 1;
    $fn[7][8] = true;
    for ($i = 9; $i < 15; $i++) {
        $mat[14 - $i][8] = ($bits >> $i) & 1;
        $fn[14 - $i][8] = true;
    }

    for ($i = 0; $i < 8; $i++) {
        $mat[$sz - 1 - $i][8] = ($bits >> $i) & 1;
        $fn[$sz - 1 - $i][8] = true;
    }
    for ($i = 8; $i < 15; $i++) {
        $mat[8][$sz - 15 + $i] = ($bits >> $i) & 1;
        $fn[8][$sz - 15 + $i] = true;
    }
    $mat[$sz - 8][8] = 1;
    $fn[$sz - 8][8] = true;
}

function render_qr_svg(string $text, int $pxSize = 132): string
{
    $bytes = array_values(unpack('C*', $text) ?: []);
    $len = count($bytes);

    // Version table for ECC-L (byte capacity, data codewords, ecc per block, blocks, alignment center)
    $versions = [
        1 => ['cap' => 17,  'data' => 19,  'ecc' => 7,  'blocks' => 1, 'align' => null],
        2 => ['cap' => 32,  'data' => 34,  'ecc' => 10, 'blocks' => 1, 'align' => 18],
        3 => ['cap' => 53,  'data' => 55,  'ecc' => 15, 'blocks' => 1, 'align' => 22],
        4 => ['cap' => 78,  'data' => 80,  'ecc' => 20, 'blocks' => 1, 'align' => 26],
        5 => ['cap' => 106, 'data' => 108, 'ecc' => 26, 'blocks' => 1, 'align' => 30],
        6 => ['cap' => 134, 'data' => 136, 'ecc' => 18, 'blocks' => 2, 'align' => 34],
    ];

    $ver = 6;
    if ($len > 134) {
        $bytes = array_slice($bytes, 0, 134);
        $len = 134;
    }
    foreach ($versions as $v => $spec) {
        if ($len <= $spec['cap']) {
            $ver = $v;
            break;
        }
    }

    $spec = $versions[$ver];
    $sz = $ver * 4 + 17;

    // Bit buffer: Mode (0100), Byte count (8 bits), Data bytes, Terminator, Pad codewords
    $bits = [];
    $pushBits = static function (int $val, int $count) use (&$bits): void {
        for ($i = $count - 1; $i >= 0; $i--) {
            $bits[] = ($val >> $i) & 1;
        }
    };
    $pushBits(0b0100, 4);
    $pushBits($len, 8);
    foreach ($bytes as $b) {
        $pushBits($b, 8);
    }
    $maxBits = $spec['data'] * 8;
    $term = min(4, $maxBits - count($bits));
    for ($i = 0; $i < $term; $i++) {
        $bits[] = 0;
    }
    while (count($bits) % 8 !== 0) {
        $bits[] = 0;
    }
    $padBytes = [0xEC, 0x11];
    $pi = 0;
    while (count($bits) < $maxBits) {
        $pushBits($padBytes[$pi % 2], 8);
        $pi++;
    }

    $dataCodewords = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $cw = 0;
        for ($j = 0; $j < 8; $j++) {
            $cw = ($cw << 1) | $bits[$i + $j];
        }
        $dataCodewords[] = $cw;
    }

    // Split into RS blocks & interleave
    $numBlocks = $spec['blocks'];
    $blockLen  = (int) ($spec['data'] / $numBlocks);
    $dataBlocks = [];
    $eccBlocks  = [];
    for ($b = 0; $b < $numBlocks; $b++) {
        $blk = array_slice($dataCodewords, $b * $blockLen, $blockLen);
        $dataBlocks[] = $blk;
        $eccBlocks[]  = qr_rs_compute($blk, $spec['ecc']);
    }

    $streamBits = [];
    for ($i = 0; $i < $blockLen; $i++) {
        for ($b = 0; $b < $numBlocks; $b++) {
            $cw = $dataBlocks[$b][$i];
            for ($k = 7; $k >= 0; $k--) {
                $streamBits[] = ($cw >> $k) & 1;
            }
        }
    }
    for ($i = 0; $i < $spec['ecc']; $i++) {
        for ($b = 0; $b < $numBlocks; $b++) {
            $cw = $eccBlocks[$b][$i];
            for ($k = 7; $k >= 0; $k--) {
                $streamBits[] = ($cw >> $k) & 1;
            }
        }
    }

    // Remainder zero bits for versions 2-6
    $remBits = ($ver >= 2 && $ver <= 6) ? 7 : 0;
    for ($i = 0; $i < $remBits; $i++) {
        $streamBits[] = 0;
    }

    // Build base function matrix
    $baseMat = array_fill(0, $sz, array_fill(0, $sz, 0));
    $isFn    = array_fill(0, $sz, array_fill(0, $sz, false));

    qr_place_finder($baseMat, $isFn, 0, 0, $sz);
    qr_place_finder($baseMat, $isFn, 0, $sz - 7, $sz);
    qr_place_finder($baseMat, $isFn, $sz - 7, 0, $sz);

    if ($spec['align'] !== null) {
        qr_place_alignment($baseMat, $isFn, $spec['align'], $spec['align']);
    }

    for ($i = 8; $i < $sz - 8; $i++) {
        $v = ($i % 2 === 0) ? 1 : 0;
        if (!$isFn[6][$i]) {
            $baseMat[6][$i] = $v;
            $isFn[6][$i] = true;
        }
        if (!$isFn[$i][6]) {
            $baseMat[$i][6] = $v;
            $isFn[$i][6] = true;
        }
    }

    // Reserve format area
    qr_draw_format($baseMat, $isFn, $sz, 0);

    // Place data bits in zig-zag
    $bitIdx = 0;
    $bitTot = count($streamBits);
    for ($right = $sz - 1; $right >= 1; $right -= 2) {
        if ($right === 6) {
            $right = 5;
        }
        for ($vert = 0; $vert < $sz; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $x = $right - $j;
                $upward = ((($right + 1) & 2) === 0);
                $y = $upward ? ($sz - 1 - $vert) : $vert;
                if (!$isFn[$y][$x]) {
                    $baseMat[$y][$x] = ($bitIdx < $bitTot) ? $streamBits[$bitIdx++] : 0;
                }
            }
        }
    }

    // Evaluate all 8 masks and choose lowest dark-balance/run penalty (fast & clean)
    $bestMask = 0;
    $bestScore = PHP_INT_MAX;
    $bestMat = $baseMat;

    for ($m = 0; $m < 8; $m++) {
        $cand = $baseMat;
        $dark = 0;
        for ($y = 0; $y < $sz; $y++) {
            for ($x = 0; $x < $sz; $x++) {
                if (!$isFn[$y][$x]) {
                    $inv = match ($m) {
                        0 => (($y + $x) % 2 === 0),
                        1 => ($y % 2 === 0),
                        2 => ($x % 3 === 0),
                        3 => (($y + $x) % 3 === 0),
                        4 => (((int) ($y / 2) + (int) ($x / 3)) % 2 === 0),
                        5 => ((($y * $x) % 2 + ($y * $x) % 3) === 0),
                        6 => ((((($y * $x) % 2) + (($y * $x) % 3)) % 2) === 0),
                        7 => ((((($y + $x) % 2) + (($y * $x) % 3)) % 2) === 0),
                    };
                    if ($inv) {
                        $cand[$y][$x] ^= 1;
                    }
                }
                if ($cand[$y][$x] === 1) {
                    $dark++;
                }
            }
        }
        $dummyFn = $isFn;
        qr_draw_format($cand, $dummyFn, $sz, $m);

        // Penalty 1 (adjacent runs) + Penalty 4 (balance)
        $score = (int) (abs(($dark * 100 / ($sz * $sz)) - 50) * 2);
        for ($y = 0; $y < $sz; $y++) {
            $runH = 1;
            $runV = 1;
            for ($x = 1; $x < $sz; $x++) {
                if ($cand[$y][$x] === $cand[$y][$x - 1]) {
                    $runH++;
                    if ($runH === 5) {
                        $score += 3;
                    } elseif ($runH > 5) {
                        $score++;
                    }
                } else {
                    $runH = 1;
                }
                if ($cand[$x][$y] === $cand[$x - 1][$y]) {
                    $runV++;
                    if ($runV === 5) {
                        $score += 3;
                    } elseif ($runV > 5) {
                        $score++;
                    }
                } else {
                    $runV = 1;
                }
            }
        }
        if ($score < $bestScore) {
            $bestScore = $score;
            $bestMask  = $m;
            $bestMat   = $cand;
        }
    }

    // Build compact SVG path with quiet zone = 2 modules
    $pad = 2;
    $vb = $sz + ($pad * 2);
    $d = [];
    for ($y = 0; $y < $sz; $y++) {
        for ($x = 0; $x < $sz; $x++) {
            if ($bestMat[$y][$x] === 1) {
                $px = $x + $pad;
                $py = $y + $pad;
                $d[] = "M{$px},{$py}h1v1h-1z";
            }
        }
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $vb . ' ' . $vb . '" width="' . $pxSize . '" height="' . $pxSize . '" role="img" aria-label="Order QR Code" shape-rendering="crispEdges" style="background:#ffffff;border-radius:8px;border:1px solid #d1d5db;">'
        . '<rect width="' . $vb . '" height="' . $vb . '" fill="#ffffff"/>'
        . '<path d="' . implode('', $d) . '" fill="#111827"/>'
        . '</svg>';
}
