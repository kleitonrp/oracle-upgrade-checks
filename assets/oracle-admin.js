/* Oracle Checks — Admin JS */
(function($){
'use strict';

var OUC_DATA = [];
var filteredIndexes = [];
var oucDirty = false;   // há alterações em memória ainda não persistidas?

function oucSetDirty(v) {
    oucDirty = v;
    var el = document.getElementById('ouc-save-status');
    if (el) el.textContent = v ? '⚠️ Alterações pendentes' : '';
}

/* ── TABS ── */
window.oucTab = function(name, btn) {
    document.querySelectorAll('.ouc-tab-panel').forEach(function(p){ p.style.display='none'; });
    document.querySelectorAll('.ouc-tab').forEach(function(b){ b.classList.remove('active'); });
    document.getElementById('tab-'+name).style.display='';
    btn.classList.add('active');
    if (name === 'list')    oucLoadList();
    if (name === 'unknown') oucLoadUnknown();
};

/* ── LOAD DATA ── */
function oucLoadData(cb, force) {
    // Nunca sobrescreve alterações ainda não persistidas (isso descartava edições silenciosamente).
    if (oucDirty && !force) { if (cb) cb(); return; }
    $.post(OUC_ADMIN.ajax_url, {action:'ouc_get_data'}, function(res){
        if (res.success) { OUC_DATA = res.data; oucSetDirty(false); if(cb) cb(); }
        else { alert('Erro ao carregar dados: ' + (res.data||'desconhecido')); }
    });
}

/* ── RENDER LIST ── */
function oucLoadList() {
    oucLoadData(function(){
        oucAdminFilter();
        document.getElementById('admin-count').textContent = OUC_DATA.length + ' itens total';
    });
}

window.oucAdminFilter = function() {
    var q   = (document.getElementById('admin-search').value||'').toLowerCase();
    var sev = document.getElementById('admin-filter-sev').value;
    var cat = document.getElementById('admin-filter-cat').value;
    var container = document.getElementById('ouc-admin-list');
    container.innerHTML = '';
    filteredIndexes = [];
    OUC_DATA.forEach(function(r, i){
        if (sev && r.sev !== sev) return;
        if (cat && r.cat !== cat) return;
        if (q && (r.name||'').toLowerCase().indexOf(q) === -1) return;
        filteredIndexes.push(i);
        var sevCls = {ERROR:'bg-err',WARNING:'bg-warn',RECOMMEND:'bg-rec',INFO:'bg-inf'}[r.sev]||'bg-inf';
        var catCls = r.cat === 'UPGRADE' ? 'bg-up' : 'bg-pt';
        var row = document.createElement('div');
        row.className = 'ouc-admin-row';
        row.innerHTML =
            '<span class="ouc-name" title="'+esc(r.name)+'">'+esc(r.name)+'</span>'+
            '<span class="ouc-badge-sm '+sevCls+'">'+esc(r.sev)+'</span>'+
            '<span class="ouc-badge-sm">'+esc(r.stage||'—')+'</span>'+
            '<span>'+esc(r.fix||'—')+'</span>'+
            '<span class="ouc-badge-sm '+catCls+'">'+esc(r.cat||'—')+'</span>'+
            '<span class="ouc-desc" title="'+esc(r.desc||'')+'">'+esc((r.desc||'').substring(0,80))+(r.desc&&r.desc.length>80?'…':'')+'</span>'+
            '<div class="ouc-actions">'+
                '<button class="button" onclick="oucEditItem('+i+')">✏️ Editar</button>'+
                '<button class="button" onclick="oucDeleteItem('+i+')" style="color:#d63638">🗑️ Remover</button>'+
            '</div>';
        container.appendChild(row);
    });
    document.getElementById('admin-count').textContent = filteredIndexes.length + ' de ' + OUC_DATA.length + ' itens';
};

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* ── EDIT ── */
window.oucEditItem = function(i) {
    var r = OUC_DATA[i];
    document.getElementById('edit-index').value = i;
    document.getElementById('f-name').value   = r.name   || '';
    document.getElementById('f-desc').value   = r.desc   || '';
    document.getElementById('f-action').value = r.action || '';
    document.getElementById('f-sev').value    = r.sev    || 'ERROR';
    document.getElementById('f-stage').value  = r.stage  || 'PRE';
    document.getElementById('f-fix').value    = r.fix    || 'MANUAL';
    document.getElementById('f-cat').value    = r.cat    || 'UPGRADE';
    document.getElementById('form-title').textContent = '✏️ Editando: ' + r.name;
    document.getElementById('form-msg').innerHTML = '';
    // switch to add tab
    document.querySelectorAll('.ouc-tab-panel').forEach(function(p){ p.style.display='none'; });
    document.querySelectorAll('.ouc-tab').forEach(function(b){ b.classList.remove('active'); });
    document.getElementById('tab-add').style.display='';
    document.querySelectorAll('.ouc-tab')[1].classList.add('active');
    window.scrollTo(0,0);
};

/* ── DELETE ── */
window.oucDeleteItem = function(i) {
    if (!confirm('Remover "' + OUC_DATA[i].name + '"?')) return;
    OUC_DATA.splice(i, 1);
    oucSetDirty(true);
    oucAdminFilter();
    oucPersist();
};

/* ── SAVE ITEM ── */
window.oucSaveItem = function() {
    var idx  = parseInt(document.getElementById('edit-index').value);
    var name = document.getElementById('f-name').value.trim();
    if (!name) { document.getElementById('form-msg').innerHTML = '<span class="ouc-msg-err">Nome obrigatório.</span>'; return; }
    var item = {
        name:   name,
        desc:   document.getElementById('f-desc').value.trim(),
        action: document.getElementById('f-action').value.trim(),
        sev:    document.getElementById('f-sev').value,
        stage:  document.getElementById('f-stage').value,
        fix:    document.getElementById('f-fix').value,
        cat:    document.getElementById('f-cat').value,
    };
    var isEdit = idx >= 0;
    if (isEdit) OUC_DATA[idx] = item;
    else        OUC_DATA.push(item);

    oucSetDirty(true);
    document.getElementById('form-msg').innerHTML = '<span style="color:#646970;">⏳ Salvando…</span>';

    oucPersist(function(ok, err){
        var msg = document.getElementById('form-msg');
        if (ok) {
            msg.innerHTML = '<span class="ouc-msg-ok">✅ Item ' + (isEdit ? 'atualizado' : 'adicionado') + ' e salvo.</span>';
            oucResetForm();
            oucAdminFilter();
        } else {
            msg.innerHTML = '<span class="ouc-msg-err">❌ Erro ao salvar: ' + esc(err||'desconhecido') + '</span>';
        }
    });
};

/* ── PERSIST (envia OUC_DATA ao servidor) ── */
function oucPersist(cb) {
    var status = document.getElementById('ouc-save-status');
    if (status) status.textContent = '⏳ Salvando…';
    $.post(OUC_ADMIN.ajax_url, {
        action: 'ouc_save_data',
        nonce:  OUC_ADMIN.nonce,
        data:   JSON.stringify(OUC_DATA)
    }, function(res){
        if (res.success) {
            oucSetDirty(false);
            if (status) status.textContent = '✅ ' + res.data;
            if (cb) cb(true);
        } else {
            if (status) status.textContent = '❌ Erro: ' + (res.data||'desconhecido');
            if (cb) cb(false, res.data);
        }
    }).fail(function(xhr){
        var e = 'falha de rede (' + (xhr.status||0) + ')';
        if (status) status.textContent = '❌ Erro: ' + e;
        if (cb) cb(false, e);
    });
}

window.oucResetForm = function() {
    document.getElementById('edit-index').value = '-1';
    document.getElementById('f-name').value   = '';
    document.getElementById('f-desc').value   = '';
    document.getElementById('f-action').value = '';
    document.getElementById('f-sev').value    = 'ERROR';
    document.getElementById('f-stage').value  = 'PRE';
    document.getElementById('f-fix').value    = 'MANUAL';
    document.getElementById('f-cat').value    = 'UPGRADE';
    document.getElementById('form-title').textContent = 'Adicionar Nova Verificação';
};

/* ── SAVE ALL ── */
window.oucAdminSave = function() {
    oucPersist();
};

/* ── IMPORT ── */
window.oucImportJSON = function() {
    var txt = document.getElementById('import-json').value.trim();
    try {
        var arr = JSON.parse(txt);
        if (!Array.isArray(arr)) throw new Error('Deve ser um array JSON');
        OUC_DATA = arr;
        document.getElementById('import-msg').innerHTML = '<span class="ouc-msg-ok">✅ ' + arr.length + ' itens importados. Clique em «Salvar todas as alterações» para persistir.</span>';
        oucSetDirty(true);
    } catch(e) {
        document.getElementById('import-msg').innerHTML = '<span class="ouc-msg-err">❌ JSON inválido: ' + e.message + '</span>';
    }
};

/* ── EXPORT ── */
window.oucExportLoad = function() {
    oucLoadData(function(){   // respeita alterações pendentes (não recarrega por cima)
        var txt = JSON.stringify(OUC_DATA, null, 2);
        var ta = document.getElementById('export-json');
        ta.value = txt;
        ta.style.display = '';
        document.getElementById('export-actions').style.display = '';
    });
};
window.oucCopyExport = function() {
    document.getElementById('export-json').select();
    document.execCommand('copy');
    alert('JSON copiado!');
};
window.oucDownloadExport = function() {
    var blob = new Blob([document.getElementById('export-json').value], {type:'application/json'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'oracle-checks-data.json';
    a.click();
};

/* ── Unknown checks log ── */
window.oucLoadUnknown = function() {
    var container = document.getElementById('ouc-unknown-log');
    if (!container) return;
    container.innerHTML = '<p style="color:#646970;font-size:13px;">Carregando...</p>';
    $.post(OUC_ADMIN.ajax_url, { action: 'ouc_get_unknown_log' }, function(res) {
        if (!res.success || !res.data || res.data.length === 0) {
            container.innerHTML = '<p style="color:#646970;font-size:13px;">Nenhum check desconhecido registrado ainda.</p>';
            return;
        }
        var html = res.data.map(function(entry, idx) {
            var checks = (entry.checks||[]).map(function(c){
                return '<span style="display:inline-block;background:#fff7ed;border:1.5px solid #fb923c;color:#c2410c;border-radius:4px;padding:2px 8px;font-size:11px;font-family:monospace;font-weight:700;margin:2px;">🆕 '+esc(c)+'</span>';
            }).join('');
            var dbs = (entry.dbs||[]).join(', ');
            return '<div style="border:1px solid #fed7aa;border-radius:6px;padding:12px 14px;margin-bottom:10px;background:#fff7ed;">'+
                '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">'+
                    '<div style="font-size:12px;color:#9a3412;">'+
                        '<strong>'+esc(entry.date||'')+'</strong> &nbsp;·&nbsp; '+
                        esc(entry.type||'')+ ' &nbsp;·&nbsp; '+
                        '<span style="font-family:monospace;">'+esc(entry.filename||'')+'</span>'+
                        (entry.ip ? ' &nbsp;·&nbsp; IP: '+esc(entry.ip) : '')+
                    '</div>'+
                    '<button onclick="oucDeleteUnknownEntry('+idx+')" style="background:none;border:none;color:#d63638;cursor:pointer;font-size:14px;padding:0;line-height:1;" title="Remover esta entrada">🗑️</button>'+
                '</div>'+
                '<div style="margin-bottom:6px;">'+checks+'</div>'+
                (dbs ? '<div style="font-size:11px;color:#7c2d12;">Bancos/PDBs: '+esc(dbs)+'</div>' : '')+
            '</div>';
        }).join('');
        container.innerHTML = html;
    }).fail(function(){
        container.innerHTML = '<p style="color:#d63638;font-size:13px;">Erro ao carregar histórico.</p>';
    });
};

window.oucClearUnknownLog = function() {
    if (!confirm('Limpar todo o histórico de checks desconhecidos?')) return;
    $.post(OUC_ADMIN.ajax_url, { action:'ouc_clear_unknown_log', nonce:OUC_ADMIN.nonce }, function(res){
        if (res.success) oucLoadUnknown();
        else alert('Erro: ' + (res.data||'desconhecido'));
    });
};

window.oucDeleteUnknownEntry = function(idx) {
    if (!confirm('Remover esta entrada?')) return;
    $.post(OUC_ADMIN.ajax_url, { action:'ouc_delete_unknown_entry', nonce:OUC_ADMIN.nonce, index:idx }, function(res){
        if (res.success) oucLoadUnknown();
        else alert('Erro: ' + (res.data||'desconhecido'));
    });
};

window.oucSaveSettings = function() {
    var token   = document.getElementById('tg-token').value.trim();
    var chat_id = document.getElementById('tg-chat').value.trim();
    var msg     = document.getElementById('settings-msg');
    $.post(OUC_ADMIN.ajax_url, { action:'ouc_save_settings', nonce:OUC_ADMIN.nonce, token:token, chat_id:chat_id }, function(res){
        msg.innerHTML = res.success
            ? '<span class="ouc-msg-ok">✅ '+res.data+'</span>'
            : '<span class="ouc-msg-err">❌ '+(res.data||'Erro')+'</span>';
    });
};

window.oucTestTelegram = function() {
    var token   = document.getElementById('tg-token').value.trim();
    var chat_id = document.getElementById('tg-chat').value.trim();
    var msg     = document.getElementById('settings-msg');
    msg.innerHTML = '<span style="color:#646970;">Enviando...</span>';
    $.post(OUC_ADMIN.ajax_url, { action:'ouc_test_telegram', nonce:OUC_ADMIN.nonce, token:token, chat_id:chat_id }, function(res){
        msg.innerHTML = res.success
            ? '<span class="ouc-msg-ok">✅ '+res.data+'</span>'
            : '<span class="ouc-msg-err">❌ '+(res.data||'Erro ao enviar')+'</span>';
    });
};

/* ── INIT ── */
$(document).ready(function(){
    oucLoadList();
});

// Import ainda é confirmado em duas etapas — avisa se sair com algo pendente.
window.addEventListener('beforeunload', function(e){
    if (!oucDirty) return;
    e.preventDefault();
    e.returnValue = '';
});

})(jQuery);