/*
 * CKEditor 5 Internal Link plugin for ImpressCMS
 * - Adds a toolbar button that opens an internal link dialog using ImpressCMS backend
 * - Reuses /include/ajax/internallink.php (actions: modules, search) with CSRF token
 */
(function(){
    const ICMS_URL = window.ICMS_URL || '';
    const CSRF = window.ICMS_CSRF_TOKEN || '';
    const ALL_VALUE = '__all__';

    // Lightweight debounce
    function debounce(fn, wait){
        let t; return function(){ clearTimeout(t); t = setTimeout(() => fn.apply(this, arguments), wait); };
    }

    // AJAX helpers
    function getJSON(url){
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.onreadystatechange = function(){
                if (xhr.readyState === 4) {
                    try { resolve(JSON.parse(xhr.responseText)); } catch(e){ reject(e); }
                }
            };
            xhr.onerror = reject;
            xhr.send();
        });
    }

    function fetchModules(){
        const token = CSRF ? '&token=' + encodeURIComponent(CSRF) : '';
        return getJSON(ICMS_URL + '/include/ajax/internallink.php?action=modules' + token)
            .then(data => Array.isArray(data && data.modules) ? data.modules : []);
    }

    function fetchSearch(moduleDir, q, items){
        if (!q || q.length < 3) { return Promise.resolve({ results: [] }); }
        const itemsParam = (moduleDir !== ALL_VALUE && items && items.length) ? '&items=' + encodeURIComponent(items.join(',')) : '';
        const token = CSRF ? '&token=' + encodeURIComponent(CSRF) : '';
        const url = ICMS_URL + '/include/ajax/internallink.php?action=search&module=' + encodeURIComponent(moduleDir) + '&q=' + encodeURIComponent(q) + itemsParam + token;
        return getJSON(url).catch(() => ({ results: [] }));
    }

    // Simple modal dialog implementation
    function createDialog(){
        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:99999;display:flex;align-items:center;justify-content:center;';
        const box = document.createElement('div');
        box.style.cssText = 'width:640px;max-width:95vw;background:#fff;border:1px solid #ccc;border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,.2);';
        box.innerHTML = '
            <div style="padding:10px 14px;border-bottom:1px solid #eee;font-weight:bold;">Internal Link</div>
            <div style="padding:12px;display:grid;gap:10px;grid-template-columns:1fr 1fr;">
                <label style="display:flex;flex-direction:column;gap:6px;grid-column:1 / span 2;">
                    <span>Module</span>
                    <select id="il-mod" style="padding:6px;">
                        <option value="__all__">-- All Modules --</option>
                    </select>
                </label>
                <label style="display:flex;flex-direction:column;gap:6px;">
                    <span>Content Types (optional)</span>
                    <select id="il-items" multiple size="5" style="padding:6px;"></select>
                </label>
                <label style="display:flex;flex-direction:column;gap:6px;">
                    <span>Search</span>
                    <input id="il-q" type="text" placeholder="Type at least 3 characters" style="padding:6px;"/>
                </label>
                <div style="grid-column:1 / span 2;border:1px solid #ccc;height:180px;overflow:auto;background:#fff;" id="il-results"></div>
            </div>
            <div style="padding:10px 14px;border-top:1px solid #eee;text-align:right;">
                <button id="il-cancel" style="margin-right:6px;">Cancel</button>
            </div>';
        overlay.appendChild(box);
        document.body.appendChild(overlay);
        return overlay;
    }

    function openInternalLinkDialog(editor){
        const dlg = createDialog();
        const modSel = dlg.querySelector('#il-mod');
        const itemsSel = dlg.querySelector('#il-items');
        const qInput = dlg.querySelector('#il-q');
        const resultsEl = dlg.querySelector('#il-results');
        const cancelBtn = dlg.querySelector('#il-cancel');

        let modules = [];

        function clearResults(){ resultsEl.innerHTML = '<div style="color:#666;padding:10px;">No results</div>'; }
        function renderResults(list){
            resultsEl.innerHTML = '';
            if (!list || !list.length){ clearResults(); return; }
            const ul = document.createElement('ul');
            ul.style.listStyle = 'none'; ul.style.margin = '0'; ul.style.padding = '0';
            list.forEach(item => {
                const li = document.createElement('li');
                li.style.cssText = 'padding:6px 8px;cursor:pointer;border-bottom:1px solid #eee;';
                li.onmouseover = () => li.style.background = '#e8f4f8';
                li.onmouseout = () => li.style.background = 'transparent';
                const modLabel = item.moduleName || item.module || '';
                li.textContent = modLabel ? (item.title + ' — [' + modLabel + ']') : item.title;
                li.addEventListener('click', () => {
                    insertLink(editor, item);
                    close();
                });
                ul.appendChild(li);
            });
            resultsEl.appendChild(ul);
        }
        function close(){ dlg.remove(); }

        function populateItems(){
            itemsSel.innerHTML = '';
            const mod = modSel.value;
            if (mod === ALL_VALUE){ itemsSel.disabled = true; return; }
            itemsSel.disabled = false;
            const found = modules.find(m => m.dirname === mod);
            if (found && Array.isArray(found.items)){
                found.items.forEach(it => {
                    const opt = document.createElement('option');
                    opt.value = it; opt.textContent = it; itemsSel.appendChild(opt);
                });
            }
        }

        const doSearch = debounce(() => {
            const mod = modSel.value;
            const q = qInput.value || '';
            const items = Array.from(itemsSel.selectedOptions || []).map(o => o.value);
            if (!mod || q.length < 3){ clearResults(); return; }
            fetchSearch(mod, q, items).then(data => {
                renderResults((data && data.results) || []);
            });
        }, 300);

        cancelBtn.addEventListener('click', close);
        modSel.addEventListener('change', () => { populateItems(); doSearch(); });
        itemsSel.addEventListener('change', () => { doSearch(); });
        qInput.addEventListener('keyup', () => { doSearch(); });

        // Load modules and prepare UI
        fetchModules().then(list => {
            modules = list || [];
            // fill modules select (All is already present and default)
            modules.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.dirname; opt.textContent = m.name; modSel.appendChild(opt);
            });
            // default to ALL
            modSel.value = ALL_VALUE;
            populateItems();
            clearResults();
        });

        // Auto focus search box
        setTimeout(() => { qInput.focus(); }, 50);
    }

    function insertLink(editor, item){
        const url = item.url;
        const title = item.title || url;
        const model = editor.model;
        const selection = model.document.selection;

        if (!url) { return; }

        model.change(writer => {
            if (!selection.isCollapsed) {
                // Apply link to selected text
                editor.execute('link', url);
            } else {
                // Insert text with link
                const textNode = writer.createText(title, { linkHref: url });
                model.insertContent(textNode, selection.getFirstPosition());
            }
        });
    }

    // Toolbar DOM injection for CDN build
    function installToolbarButton(editor){
        const t = s => s;
        // Find toolbar element
        const rootEl = editor.ui && editor.ui.view && editor.ui.view.element;
        const toolbarEl = rootEl ? rootEl.querySelector('.ck-toolbar') : null;
        if (!toolbarEl) { return; }

        // Create button with CKEditor classes for consistent look
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ck ck-button ck-off';
        btn.setAttribute('aria-label', t('Internal Link'));
        btn.innerHTML = '<span class="ck ck-button__label">' + t('Internal Link') + '</span>';
        btn.addEventListener('click', () => openInternalLinkDialog(editor));

        // Append at end
        toolbarEl.appendChild(btn);
    }

    // Expose installer for init script
    window.ICMS_CK5_InstallInternalLinkButton = installToolbarButton;
})();

