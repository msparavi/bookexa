(function () {
    'use strict';

    function stripHtml(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html || '';
        return (tmp.textContent || tmp.innerText || '').replace(/[\u200B-\u200D\uFEFF]/g, '').trim();
    }

    function countWords(text) {
        var clean = stripHtml(text).replace(/\s+/g, ' ').trim();
        return clean === '' ? 0 : clean.split(/\s+/).length;
    }

    // 1. Built-in CSP-safe Rich Text Editor (contenteditable <-> textarea sync)
    function initRichEditors() {
        var textareas = document.querySelectorAll('textarea[data-rich-editor]');
        textareas.forEach(function (ta) {
            if (ta.dataset.richReady === '1') return;
            ta.dataset.richReady = '1';

            var wrap = document.createElement('div');
            wrap.className = 'bx-editor-shell';

            var bar = document.createElement('div');
            bar.className = 'bx-editor-toolbar';

            var buttons = [
                { label: 'H2', cmd: 'formatBlock', val: 'H2', title: 'Heading 2' },
                { label: 'H3', cmd: 'formatBlock', val: 'H3', title: 'Heading 3' },
                { label: 'P',  cmd: 'formatBlock', val: 'P',  title: 'Paragraph' },
                { label: 'B',  cmd: 'bold',        val: null, title: 'Bold' },
                { label: 'I',  cmd: 'italic',      val: null, title: 'Italic' },
                { label: '• List', cmd: 'insertUnorderedList', val: null, title: 'Bullet List' },
                { label: '1. List', cmd: 'insertOrderedList',  val: null, title: 'Numbered List' },
                { label: '❝ Quote', cmd: 'formatBlock', val: 'BLOCKQUOTE', title: 'Blockquote' },
                { label: '🔗 Link', cmd: '_link', val: null, title: 'Insert Link' },
                { label: '🖼 Image', cmd: '_img', val: null, title: 'Insert Image with Alt' },
                { label: '</> HTML', cmd: '_source', val: null, title: 'Toggle HTML Source' }
            ];

            var area = document.createElement('div');
            area.className = 'bx-editor-body bx-prose';
            area.contentEditable = 'true';
            area.innerHTML = ta.value || '<p><br></p>';

            var sourceMode = false;
            ta.style.display = 'none';
            ta.parentNode.insertBefore(wrap, ta);
            wrap.appendChild(bar);
            wrap.appendChild(area);
            wrap.appendChild(ta);

            function syncToTextarea() {
                if (!sourceMode) {
                    ta.value = area.innerHTML;
                    ta.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }

            buttons.forEach(function (btnDef) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'bx-btn bx-btn-ghost bx-btn-sm bx-ed-btn';
                btn.textContent = btnDef.label;
                btn.title = btnDef.title;
                btn.addEventListener('click', function () {
                    if (btnDef.cmd === '_source') {
                        sourceMode = !sourceMode;
                        if (sourceMode) {
                            ta.value = area.innerHTML;
                            area.style.display = 'none';
                            ta.style.display = 'block';
                            btn.classList.add('bx-btn-primary');
                        } else {
                            area.innerHTML = ta.value || '<p><br></p>';
                            ta.style.display = 'none';
                            area.style.display = 'block';
                            btn.classList.remove('bx-btn-primary');
                        }
                        return;
                    }
                    if (sourceMode) return;
                    area.focus();
                    if (btnDef.cmd === '_link') {
                        var href = window.prompt('Link URL (e.g. /pdf or /guides/...):', '/pdf');
                        if (href) {
                            document.execCommand('createLink', false, href.trim());
                        }
                    } else if (btnDef.cmd === '_img') {
                        var src = window.prompt('Image URL (e.g. /uploads/covers/...):', '/uploads/covers/');
                        if (src) {
                            var alt = window.prompt('Required Image Alt Text (Bangla or English):', '');
                            var imgHtml = '<img src="' + src.replace(/"/g, '&quot;') + '" alt="' + (alt || '').replace(/"/g, '&quot;') + '">';
                            document.execCommand('insertHTML', false, imgHtml);
                        }
                    } else {
                        document.execCommand(btnDef.cmd, false, btnDef.val);
                    }
                    syncToTextarea();
                });
                bar.appendChild(btn);
            });

            area.addEventListener('input', syncToTextarea);
            area.addEventListener('blur', syncToTextarea);
            var form = ta.closest('form');
            if (form) {
                form.addEventListener('submit', syncToTextarea);
            }
        });
    }

    // 2. Live Editorial Content Quality Checklist
    function initQualityChecklist() {
        var box = document.querySelector('[data-quality-checklist]');
        if (!box) return;

        var form = box.closest('form') || document.body;
        var titleEl = form.querySelector('[name="seo_title_bn"], [name="title_bn"]') || form.querySelector('[name="title_bn"]');
        var titleBnEl = form.querySelector('[name="title_bn"]');
        var seoTitleBnEl = form.querySelector('[name="seo_title_bn"]');
        var sumBnEl = form.querySelector('[name="summary_bn"]');
        var seoDescBnEl = form.querySelector('[name="seo_description_bn"]');
        var bodyBnEl = form.querySelector('[name="body_bn"]');
        var bodyEnEl = form.querySelector('[name="body_en"]');
        var relItemsEl = form.querySelector('[name="related_item_ids"]');
        var relFoldersEl = form.querySelector('[name="related_folder_ids"]');

        function evaluate() {
            var effTitle = ((seoTitleBnEl && seoTitleBnEl.value) || (titleBnEl && titleBnEl.value) || (titleEl && titleEl.value) || '').trim();
            var effDesc  = ((seoDescBnEl && seoDescBnEl.value) || (sumBnEl && sumBnEl.value) || '').trim();
            var htmlBn   = bodyBnEl ? bodyBnEl.value : '';
            var htmlEn   = bodyEnEl ? bodyEnEl.value : '';
            var primaryHtml = htmlBn.trim() !== '' ? htmlBn : htmlEn;

            var tLen = effTitle.length;
            var dLen = effDesc.length;
            var words = Math.max(countWords(htmlBn), countWords(htmlEn));
            var hasH1 = /<h1\b/i.test(primaryHtml);
            var hasH2 = /<h2\b/i.test(primaryHtml);

            var tmp = document.createElement('div');
            tmp.innerHTML = primaryHtml;
            var imgs = tmp.querySelectorAll('img');
            var missingAlt = 0;
            imgs.forEach(function (im) { if (!im.getAttribute('alt') || im.getAttribute('alt').trim() === '') missingAlt++; });

            var linkCount = 0;
            tmp.querySelectorAll('a[href]').forEach(function (a) {
                var h = a.getAttribute('href') || '';
                if (h.startsWith('/')) linkCount++;
            });
            if (relItemsEl) relItemsEl.value.split(',').forEach(function (s) { if (s.trim() !== '') linkCount++; });
            if (relFoldersEl) relFoldersEl.value.split(',').forEach(function (s) { if (s.trim() !== '') linkCount++; });

            var plain = stripHtml(primaryHtml);
            var sentences = plain.split(/[।.!?]+/).filter(function (s) { return s.trim().length > 0; });
            var avgSent = sentences.length > 0 ? Math.round((words / sentences.length) * 10) / 10 : words;

            var checks = [
                { ok: tLen >= 25 && tLen <= 75, text: 'Title length (' + tLen + ' chars — target 30–60)' },
                { ok: dLen >= 60 && dLen <= 165, text: 'Meta/Excerpt length (' + dLen + ' chars — target 70–155)' },
                { ok: !hasH1, text: 'Zero <h1> inside body (template keeps strictly 1 H1)' },
                { ok: hasH2, text: 'Heading hierarchy uses <h2> / <h3> sections' },
                { ok: missingAlt === 0, text: 'All embedded images have alt text (' + missingAlt + ' missing)' },
                { ok: words >= 150, text: 'Word count: ' + words + ' words (recommended >= 150)' },
                { ok: words >= 60 && avgSent <= 30, text: 'Readability: ~' + avgSent + ' words/sentence' },
                { ok: linkCount >= 2, text: 'Internal links + related items: ' + linkCount + ' (target >= 2)' }
            ];

            box.innerHTML = '';
            checks.forEach(function (ck) {
                var li = document.createElement('li');
                li.className = ck.ok ? 'is-ok' : 'is-warn';
                li.textContent = (ck.ok ? '✓ ' : '! ') + ck.text;
                box.appendChild(li);
            });
        }

        form.addEventListener('input', evaluate);
        form.addEventListener('change', evaluate);
        evaluate();
    }

    // 3. Repeatable FAQ Rows (Folders, Books, Hubs)
    function initFaqRepeaters() {
        document.querySelectorAll('[data-faq-wrap]').forEach(function (wrap) {
            var list = wrap.querySelector('[data-faq-list]');
            var addBtn = wrap.querySelector('[data-faq-add]');
            if (!list || !addBtn) return;

            function bindRemove(btn) {
                btn.addEventListener('click', function () {
                    var item = btn.closest('[data-faq-row]');
                    if (item) item.remove();
                });
            }

            list.querySelectorAll('[data-faq-remove]').forEach(bindRemove);

            addBtn.addEventListener('click', function () {
                var row = document.createElement('div');
                row.className = 'bx-faq-edit-row';
                row.setAttribute('data-faq-row', '1');
                row.innerHTML =
                    '<div class="bx-grid-2" style="gap:8px;margin-bottom:6px;">' +
                    '<input type="text" name="faq_q_bn[]" class="bx-input" placeholder="প্রশ্ন (বাংলা)">' +
                    '<input type="text" name="faq_q_en[]" class="bx-input" placeholder="Question (English)">' +
                    '</div>' +
                    '<div class="bx-grid-2" style="gap:8px;margin-bottom:6px;">' +
                    '<textarea name="faq_a_bn[]" rows="2" class="bx-input" placeholder="উত্তর (বাংলা)"></textarea>' +
                    '<textarea name="faq_a_en[]" rows="2" class="bx-input" placeholder="Answer (English)"></textarea>' +
                    '</div>' +
                    '<input type="text" name="faq_url[]" class="bx-input" style="margin-bottom:6px;" placeholder="Optional link path (e.g. /faq or /guides/...)">' +
                    '<button type="button" class="bx-btn bx-btn-ghost bx-btn-sm" data-faq-remove style="color:var(--rose-text);">Remove FAQ</button>';
                list.appendChild(row);
                bindRemove(row.querySelector('[data-faq-remove]'));
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initRichEditors();
        initQualityChecklist();
        initFaqRepeaters();
    });
})();
