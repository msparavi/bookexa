<?php
declare(strict_types=1);
$siteName = site_setting('site_name', 'Bookexa');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Mobile QA &amp; Overflow Auditor — <?= e($siteName) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 20px; background: #0B1020; color: #F6F2E8; line-height: 1.5; }
        h1 { margin: 0 0 8px; font-size: 1.35rem; }
        .bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; background: #1A2447; padding: 14px; border-radius: 14px; margin: 14px 0 18px; }
        label { display: inline-flex; align-items: center; gap: 6px; font-size: 0.9rem; font-weight: 600; }
        select, button { padding: 8px 14px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.18); background: #121A33; color: #F6F2E8; font-weight: 600; cursor: pointer; min-height: 40px; }
        button.primary { background: linear-gradient(135deg, #2B2F77, #14B8A6); border: none; color: #FBF6EC; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 18px; }
        .stat { background: #121A33; padding: 12px 14px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); }
        .stat strong { display: block; font-size: 1.4rem; color: #14B8A6; }
        .stat.bad strong { color: #F47296; }
        table { width: 100%; border-collapse: collapse; font-size: 0.86rem; background: #121A33; border-radius: 12px; overflow: hidden; }
        th, td { padding: 9px 10px; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left; vertical-align: top; }
        th { background: #1A2447; }
        .ok { color: #2DD4BF; font-weight: 700; }
        .fail { color: #F47296; font-weight: 700; }
        #sandbox-frame { position: fixed; left: -9999px; top: 0; border: none; visibility: hidden; background: #fff; }
        code { font-size: 0.8rem; color: #F4A62A; }
    </style>
</head>
<body>
<h1>Bookexa Mobile QA &amp; Overflow Auditor (Admin Only)</h1>
<p style="margin:0;color:#B6C0DC;font-size:0.9rem;">
    Audits viewport overflow, elements &gt; viewport, text &lt; 12px, tap targets &lt; 40px, and fixed header / bottom-nav overlap across Bookexa routes.
</p>

<div class="bar">
    <label>Lang:
        <select id="qa-lang">
            <option value="bn">Bangla (bn)</option>
            <option value="en">English (en)</option>
        </select>
    </label>
    <label>Theme:
        <select id="qa-theme">
            <option value="light">Light</option>
            <option value="dark">Dark</option>
        </select>
    </label>
    <label>Suite:
        <select id="qa-suite">
            <option value="all">All (Public + User + Reader + Admin)</option>
            <option value="public">Public Site</option>
            <option value="user">User &amp; Reader</option>
            <option value="admin">Admin Panel</option>
        </select>
    </label>
    <button type="button" id="btn-run-qa" class="primary">Run Full Mobile Audit</button>
    <span id="qa-status" style="font-size:0.88rem;color:#F4A62A;">Ready</span>
</div>

<div class="summary">
    <div class="stat"><span>Checks Run</span><strong id="s-total">0</strong></div>
    <div class="stat"><span>Passed Cleanly</span><strong id="s-pass">0</strong></div>
    <div class="stat" id="card-ov"><span>Overflow Issues</span><strong id="s-ov">0</strong></div>
    <div class="stat" id="card-txt"><span>Text &lt; 12px</span><strong id="s-txt">0</strong></div>
    <div class="stat" id="card-tap"><span>Tap &lt; 40px</span><strong id="s-tap">0</strong></div>
    <div class="stat" id="card-ovl"><span>Fixed Bar Overlaps</span><strong id="s-ovl">0</strong></div>
</div>

<table>
    <thead>
        <tr>
            <th>Route</th>
            <th>Width</th>
            <th>Overflow</th>
            <th>Text &lt;12px</th>
            <th>Tap &lt;40px</th>
            <th>Header/Bottom Overlap</th>
            <th>Details</th>
        </tr>
    </thead>
    <tbody id="qa-tbody"></tbody>
</table>

<iframe id="sandbox-frame" title="QA Probe"></iframe>

<script id="bx-qa-config" type="application/json"><?= json_encode(['base' => BASE_PATH]) ?></script>
<script src="<?= e(asset('assets/js/overflow_qa.js')) ?>"></script>
</body>
</html>
