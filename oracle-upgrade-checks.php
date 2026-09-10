<?php
/**
 * Plugin Name: Oracle Upgrade Checks
 * Description: Verificações Oracle AutoUpgrade com upload de relatório e painel admin. Use [oracle_upgrade_checks] em posts/páginas.
 * Version:     4.2.0
 * Author:      DBA Focus
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OUC_VERSION', '4.2.0' );
define( 'OUC_DATA_FILE', WP_CONTENT_DIR . '/oracle-checks-data.json' );

/* ─── ENQUEUE ASSETS ─── */
function ouc_enqueue_assets() {
    wp_enqueue_style( 'ouc-style', plugin_dir_url( __FILE__ ) . 'assets/oracle-checks.css', [], OUC_VERSION );
    // JS is all inline in the shortcode — no external JS file needed
}
add_action( 'wp_enqueue_scripts', 'ouc_enqueue_assets' );

/* ─── SHORTCODE ─── */
function ouc_render_shortcode( $atts ) {
    $atts = shortcode_atts(['severity'=>'','stage'=>''], $atts, 'oracle_upgrade_checks');
    $ajax_url = admin_url('admin-ajax.php');
    ob_start(); ?>
    <div class="ouc-wrap" id="ouc-app"
         data-preset-severity="<?php echo esc_attr(strtoupper($atts['severity'])); ?>"
         data-preset-stage="<?php echo esc_attr(strtoupper($atts['stage'])); ?>"
         data-ajax-url="<?php echo esc_url($ajax_url); ?>">

        <!-- ── INTRO ── -->
        <div class="ouc-intro">
            <div class="ouc-intro-icon">🔍</div>
            <div class="ouc-intro-text">
                <p style="margin:0 0 10px;">O <strong>Oracle AutoUpgrade</strong> gera um relatório detalhado durante a fase de <em>Analyze</em>, listando todos os checks que precisam de atenção antes de realizar um upgrade ou aplicar um patch. Esta ferramenta cruza esse relatório com a base de conhecimento de verificações, destacando cada item encontrado e mostrando em quais bancos e PDBs ele ocorreu.</p>
                <div class="ouc-intro-cols">
                    <div>
                        <strong class="ouc-intro-label">📄 Tipos de relatório suportados</strong>
                        <div class="ouc-intro-filetype">
                            <code>check_upgrade.log</code>
                            <span>— gerado no pré-upgrade (non-CDB ou CDB)</span>
                        </div>
                        <div class="ouc-intro-filetype">
                            <code>check_patching.log</code>
                            <span>— gerado no pré-patching (suporta múltiplos PDBs)</span>
                        </div>
                    </div>
                    <div>
                        <strong class="ouc-intro-label">⚙️ Como usar</strong>
                        <ol class="ouc-intro-steps">
                            <li>Execute: <code>java -jar autoupgrade.jar -config config.cfg -mode analyze</code></li>
                            <li>Localize o relatório em: <code>.../prechecks/&lt;db&gt;_preupgrade.log</code></li>
                            <li>Faça upload acima e os checks serão destacados em verde</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── REPORT UPLOAD ── -->
        <div class="ouc-upload-panel">
            <div class="ouc-upload-header">
                <span>📋 Analisar Relatório AutoUpgrade</span>
                <button class="ouc-toggle-upload" id="ouc-toggle-btn">Carregar relatório ▼</button>
            </div>
            <div class="ouc-upload-body" id="ouc-upload-body" style="display:none">
                <p class="ouc-upload-hint">Selecione um relatório gerado pelo AutoUpgrade (<code>check_upgrade.log</code> ou <code>check_patching.log</code>). Os checks encontrados serão destacados na lista abaixo.</p>
                <div class="ouc-upload-row">
                    <input type="file" id="ouc-file-input" accept=".log,.txt">
                    <button class="ouc-btn ouc-btn-clear" id="ouc-clear-btn" style="display:none">✕ Limpar relatório</button>
                </div>
                <div id="ouc-report-summary" style="display:none"></div>
            </div>
        </div>

        <!-- ── TOOLBAR ── -->
        <div class="ouc-toolbar">
            <div class="ouc-search-wrap">
                <svg class="ouc-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" id="ouc-search" placeholder="Pesquisar por nome, descrição ou ação…">
            </div>
            <select id="ouc-sev">
                <option value="">Todas as Severidades</option>
                <option value="ERROR">🔴 Erro</option>
                <option value="WARNING">🟡 Aviso</option>
                <option value="RECOMMEND">🟣 Recomendação</option>
                <option value="INFO">🔵 Informação</option>
            </select>
            <select id="ouc-stage">
                <option value="">Todas as Etapas</option>
                <option value="PRE">PRÉ-Upgrade</option>
                <option value="POST">PÓS-Upgrade</option>
                <option value="VALIDATION">Validação</option>
            </select>
            <select id="ouc-fix">
                <option value="">Tipo de Correção</option>
                <option value="AUTO">Automática</option>
                <option value="MANUAL">Manual</option>
                <option value="BOTH">Ambas</option>
            </select>
            <select id="ouc-cat">
                <option value="">⬆️📦 Upgrade + Patching</option>
                <option value="UPGRADE">⬆️ Somente Upgrade</option>
                <option value="PATCHING">📦 Somente Patching</option>
            </select>
            <select id="ouc-report-filter" style="display:none">
                <option value="">Todos os itens</option>
                <option value="found">✅ Encontrados no relatório</option>
                <option value="notfound">— Não encontrados</option>
            </select>
        </div>

        <!-- ── UNKNOWN CHECKS ALERT ── -->
        <div id="ouc-unknown-alert" style="display:none;margin-bottom:14px;padding:14px 16px;background:#fff7ed;border:2px solid #fb923c;border-radius:8px;font-size:13px;">
            <div style="font-weight:700;color:#c2410c;margin-bottom:8px;">🆕 Checks não cadastrados encontrados no relatório</div>
            <div style="color:#7c2d12;margin-bottom:10px;font-size:12px;">Os itens abaixo foram encontrados no seu relatório mas <strong>não estão na lista de verificações</strong>. Isso pode indicar checks novos ou alterados pelo Oracle. Aviso enviado ao administrador.</div>
            <div id="ouc-unknown-list" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
        </div>

        <!-- ── LEGEND ── -->
        <div class="ouc-legend">
            <span class="ouc-legend-title">Legenda:</span>
            <span class="ouc-leg-item"><span class="ouc-dot ouc-dot--error"></span>Erro</span>
            <span class="ouc-leg-item"><span class="ouc-dot ouc-dot--warning"></span>Aviso</span>
            <span class="ouc-leg-item"><span class="ouc-dot ouc-dot--recommend"></span>Recomendação</span>
            <span class="ouc-leg-item"><span class="ouc-dot ouc-dot--info"></span>Informação</span>
            <span class="ouc-legend-sep"></span>
            <span class="ouc-leg-item"><strong>⬆️ Upgrade</strong></span>
            <span class="ouc-leg-item"><strong>📦 Patching</strong></span>
        </div>

        <!-- ── STATS ── -->
        <div class="ouc-statsbar">
            Exibindo <strong id="ouc-cnt-vis">0</strong> de <strong id="ouc-cnt-total">0</strong> verificações &nbsp;|&nbsp;
            <span class="ouc-s-err">● <span id="ouc-cnt-err">0</span> Erros</span>
            <span class="ouc-s-warn">● <span id="ouc-cnt-warn">0</span> Avisos</span>
            <span class="ouc-s-rec">● <span id="ouc-cnt-rec">0</span> Rec.</span>
            <span class="ouc-s-inf">● <span id="ouc-cnt-inf">0</span> Info</span>
            <span id="ouc-report-badge" style="display:none" class="ouc-report-badge">
                📋 <span id="ouc-report-found-count">0</span> encontrados no relatório
            </span>
        </div>

        <!-- ── RESULTS ── -->
        <div id="ouc-results"></div>

    </div><!-- /.ouc-wrap -->

    <script>
    /* Oracle Upgrade Checks — Inline Parser
     * Placed AFTER all HTML elements so getElementById always finds them.
     * Parser runs in this script; rendering is delegated to oracle-checks.js
     * via window._oucApplyReport().
     */
    (function() {
        function parseLogText(text, filename) {
            var FOUND = {}, META = { filename: filename, dbs: [], type: '', generated: '' };
            text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
            META.type = /AutoUpgrade Patching/i.test(text) ? 'Patching' : 'Upgrade';
            var gm = text.match(/Report generated by AutoUpgrade[^\n]+on\s+([\d\-: ]+)/);
            if (gm) META.generated = gm[1].trim();

            var lines = text.split('\n');
            var db = '?', name = null, sev = null, stage = null, buf = [], st = 'idle';

            function tok(l) { var t = l.trim().split(/\s+/); return t[0]||''; }
            function save() {
                if (name && sev) {
                    if (!FOUND[name]) FOUND[name] = [];
                    FOUND[name].push({ db:db, sev:sev, stage:stage, detail:buf.join(' ').replace(/\s+/g,' ').trim().substring(0,300) });
                }
                name=null; sev=null; stage=null; buf=[]; st='idle';
            }

            for (var i=0; i<lines.length; i++) {
                var ln = lines[i], tr = ln.trim();

                if (/Database Name:/.test(tr))   db = tr.replace(/.*Database Name:\s*/,'').trim();
                if (/Container Name:/.test(tr)) {
                    var cn = tr.replace(/.*Container Name:\s*/,'').trim();
                    if (cn && cn !== 'Not Applicable in Pre-12.1 database')
                        db = db.split('/')[0]+'/'+cn;
                }
                if (db!=='?' && META.dbs.indexOf(db)===-1) META.dbs.push(db);

                if (ln.indexOf('CheckName')>=0 && ln.indexOf('FixUp Available')>=0) { save(); st='cn_hdr'; continue; }
                if (st==='cn_hdr') { var t=tok(ln); if(t && /^[A-Z][A-Z0-9_]{2,}$/.test(t)){name=t; st='got_cn';} continue; }
                if (st==='got_cn' && ln.indexOf('Severity')>=0 && ln.indexOf('Stage')>=0) { st='sev_hdr'; continue; }
                if (st==='sev_hdr') {
                    var p=tr.split(/\s+/);
                    if(p.length>=2 && /^(ERROR|WARNING|RECOMMEND|INFO)$/.test(p[0])){ sev=p[0]; stage=p[1]; st='collect'; buf=[]; }
                    continue;
                }
                if (st==='collect' && tr) buf.push(tr);
            }
            save(); // commit last

            window._oucReportFound = FOUND;
            window._oucReportMeta  = META;
            if (typeof window._oucApplyReport === 'function')
                window._oucApplyReport(FOUND, META, filename);
            else
                oucApplyReport(FOUND, META, filename); // fallback: call local function
        }

        /* Wire events — elements exist because this script is AFTER the HTML */
        var fileInput = document.getElementById('ouc-file-input');
        var clearBtn  = document.getElementById('ouc-clear-btn');
        var toggleBtn = document.getElementById('ouc-toggle-btn');
        var uploadBody= document.getElementById('ouc-upload-body');

        if (toggleBtn && uploadBody) {
            toggleBtn.addEventListener('click', function() {
                var hidden = uploadBody.style.display === 'none';
                uploadBody.style.display = hidden ? '' : 'none';
                toggleBtn.textContent = hidden ? 'Fechar ▲' : 'Carregar relatório ▼';
            });
        }

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                var file = this.files[0];
                if (!file) return;
                var r = new FileReader();
                r.onload = function(e) { parseLogText(e.target.result, file.name); };
                r.readAsText(file);
            });
        }

        if (clearBtn && fileInput) {
            clearBtn.addEventListener('click', function() {
                window._oucReportFound = {};
                var s=document.getElementById('ouc-report-summary'); if(s) s.style.display='none';
                clearBtn.style.display = 'none';
                var rf=document.getElementById('ouc-report-filter'); if(rf) rf.style.display='none';
                var rb=document.getElementById('ouc-report-badge');  if(rb) rb.style.display='none';
                fileInput.value = '';
                oucApplyReport({}, {dbs:[]}, '');
            });
        }

        /* ── ALL RENDERING CODE INLINE ──────────────────────────────────────
         * oracle-checks.js may be cached by WP/CDN. Everything needed to
         * render cards and respond to the report upload lives here instead.
         * ─────────────────────────────────────────────────────────────────── */
        var OUC_DATA = [];
        var OUC_AJAX_URL = '';

        var SEV_LBL   = {ERROR:'Erro',WARNING:'Aviso',RECOMMEND:'Recomendação',INFO:'Informação'};
        var SEV_CLS   = {ERROR:'error',WARNING:'warning',RECOMMEND:'recommend',INFO:'info'};
        var STAGE_LBL = {PRE:'PRÉ',POST:'PÓS',VALIDATION:'Validação',PRECHECKS:'PRÉ',POSTCHECKS:'PÓS'};
        var STAGE_CLS = {PRE:'stage-pre',POST:'stage-post',VALIDATION:'stage-val',PRECHECKS:'stage-pre',POSTCHECKS:'stage-post'};
        var CAT_LBL   = {UPGRADE:'⬆️ Upgrade',PATCHING:'📦 Patching'};
        var CAT_CLS   = {UPGRADE:'upgrade',PATCHING:'patching'};

        function oEsc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
        function oFix(f){ return f==='AUTO'?'fix-auto':f==='MANUAL'?'fix-manual':f==='BOTH'?'fix-both':'fix-manual'; }
        function oFixL(f){ return f==='AUTO'?'Automático':f==='MANUAL'?'Manual':f==='BOTH'?'Ambos':(f||'—'); }
        function oGet(id){ return document.getElementById(id); }
        function oVal(id){ var e=oGet(id); return e?e.value:''; }

        /* Apply report result — updates UI then re-renders */
        function oucApplyReport(found, meta, filename) {
            window._oucReportFound   = found || {};
            window._oucReportMeta    = meta  || {};
            window._oucReportFilename= filename || '';
            var cnt = Object.keys(window._oucReportFound).length;
            var sumEl = oGet('ouc-report-summary');
            if (sumEl) {
                if (filename) {
                    var dbList = (meta.dbs||[]).slice(0,6).join(', ') +
                        ((meta.dbs||[]).length>6 ? '… (+'+ ((meta.dbs||[]).length-6) +')' : '');
                    sumEl.innerHTML =
                        '<strong>✅ Relatório carregado: '+oEsc(filename)+'</strong>' +
                        (meta.type ? ' — Tipo: '+oEsc(meta.type) : '') +
                        (meta.generated ? ' · Gerado em '+oEsc(meta.generated) : '') +
                        '<br><span class="ouc-report-db">Bancos/PDBs: <span>'+oEsc(dbList||'—')+'</span></span>' +
                        '<br><span class="ouc-report-db"><strong>'+cnt+' verificações encontradas</strong> no relatório.</span>';
                    sumEl.style.display='';
                    if (clearBtn) clearBtn.style.display='';
                } else {
                    sumEl.style.display='none';
                    if (clearBtn) clearBtn.style.display='none';
                }
            }
            var rf=oGet('ouc-report-filter'), rb=oGet('ouc-report-badge'), rc=oGet('ouc-report-found-count');
            if (rf) rf.style.display = cnt>0?'':'none';
            if (rb) rb.style.display = cnt>0?'':'none';
            if (rc) rc.textContent = cnt;

            /* ── Detect unknown checks ── */
            oucCheckUnknown(found, meta, filename);

            oucRender();
        }
        window._oucApplyReport = oucApplyReport;

        /* Detect checks in the report that are not in OUC_DATA */
        function oucCheckUnknown(found, meta, filename) {
            var alertEl = oGet('ouc-unknown-alert');
            var listEl  = oGet('ouc-unknown-list');
            if (!alertEl || !listEl) return;

            if (!found || !filename) {
                alertEl.style.display = 'none';
                listEl.innerHTML = '';
                return;
            }

            // Build a set of all known check names
            var known = {};
            OUC_DATA.forEach(function(r){ known[r.name] = true; });

            // Find checks in the report that are NOT in OUC_DATA
            var unknown = Object.keys(found).filter(function(name){
                return !known[name];
            });

            if (unknown.length === 0) {
                alertEl.style.display = 'none';
                listEl.innerHTML = '';
                return;
            }

            // Show alert
            listEl.innerHTML = unknown.map(function(name){
                return '<span style="display:inline-block;background:#fff;border:1.5px solid #fb923c;color:#c2410c;'+
                    'border-radius:5px;padding:3px 10px;font-size:12px;font-family:monospace;font-weight:700;">'+
                    '🆕 '+oEsc(name)+'</span>';
            }).join('');
            alertEl.style.display = '';

            // Send to server for logging + email
            if (OUC_AJAX_URL) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', OUC_AJAX_URL);
                xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
                xhr.send('action=ouc_log_unknown'+
                    '&filename='+encodeURIComponent(filename||'')+
                    '&checks='+encodeURIComponent(JSON.stringify(unknown))+
                    '&dbs='+encodeURIComponent(JSON.stringify((meta.dbs||[]).slice(0,10)))+
                    '&type='+encodeURIComponent(meta.type||''));
            }
        }

        /* Build a single card */
        function oucBuildCard(item) {
            var FOUND = window._oucReportFound || {};
            var sv  = (item.sev||'').toUpperCase();
            var sc  = SEV_CLS[sv]||'info';
            var st  = (item.stage||'').toUpperCase();
            var ft  = (item.fix||'').toUpperCase();
            var cat = (item.cat||'').toUpperCase();
            var found = FOUND[item.name];

            var div = document.createElement('div');
            div.className = 'ouc-card ouc-sev--'+sc+(found?' ouc-in-report':'');

            var badges = '<span class="ouc-badge ouc-badge--'+sc+'">'+(SEV_LBL[sv]||sv)+'</span>';
            if (st)  badges += '<span class="ouc-badge ouc-badge--'+(STAGE_CLS[st]||'')+'">'+(STAGE_LBL[st]||st)+'</span>';
            if (ft)  badges += '<span class="ouc-badge ouc-badge--'+oFix(ft)+'">'+oFixL(ft)+'</span>';
            if (cat) badges += '<span class="ouc-badge ouc-badge--'+(CAT_CLS[cat]||'')+'">'+oEsc(CAT_LBL[cat]||cat)+'</span>';
            if (found) badges += '<span class="ouc-found-tag">✅ No seu relatório ('+found.length+'x)</span>';

            var rep = '';
            if (found) {
                var isHeaderNoise = function(d) {
                    return !d || d.indexOf('Component Current') >= 0 ||
                           d.indexOf('CID       Version') >= 0 ||
                           d.indexOf('Oracle-Maintained User Name') >= 0 ||
                           d.indexOf('Tablespace Name') >= 0 ||
                           d.indexOf('Parameter -----') >= 0;
                };
                var total = found.length;
                var pageSize = 10;
                var uid = 'pdb-'+Math.random().toString(36).substr(2,6);

                // Build all pages as hidden divs
                var pages = '';
                for (var pi = 0; pi < Math.ceil(total/pageSize); pi++) {
                    var items = found.slice(pi*pageSize, (pi+1)*pageSize).map(function(f){
                        var detail = (!isHeaderNoise(f.detail)) ? f.detail.substring(0,200) : '';
                        return '<div style="padding:5px 10px;background:#f0fdf4;border-radius:4px;font-size:12px;border-left:3px solid #86efac;line-height:1.5;word-break:break-word;margin-bottom:4px;">'+
                            '<strong style="color:#15803d;">'+oEsc(f.db)+'</strong>'+
                            (detail ? '<br><span style="color:#374151;font-size:11px;">'+oEsc(detail)+'</span>' : '')+
                            '</div>';
                    }).join('');
                    pages += '<div id="'+uid+'-p'+pi+'" style="display:'+(pi===0?'block':'none')+'">'+items+'</div>';
                }

                var nav = '';
                if (total > pageSize) {
                    nav = '<div style="display:flex;align-items:center;gap:8px;margin-top:8px;font-size:12px;" onclick="event.stopPropagation()">'+
                        '<button onclick="event.stopPropagation();oucPage(\''+uid+'\','+total+','+pageSize+',-1)" id="'+uid+'-prev" style="padding:3px 10px;border:1px solid #86efac;border-radius:4px;background:#f0fdf4;color:#15803d;cursor:pointer;font-size:12px;">◀</button>'+
                        '<span id="'+uid+'-info" style="color:#374151;">1 – '+Math.min(pageSize,total)+' de '+total+'</span>'+
                        '<button onclick="event.stopPropagation();oucPage(\''+uid+'\','+total+','+pageSize+',1)" id="'+uid+'-next" style="padding:3px 10px;border:1px solid #86efac;border-radius:4px;background:#f0fdf4;color:#15803d;cursor:pointer;font-size:12px;">▶</button>'+
                        '</div>';
                }

                rep = '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px 12px;margin-top:10px;font-size:12px;" onclick="event.stopPropagation()">'+
                    '<strong style="color:#15803d;display:block;margin-bottom:8px;">📋 Encontrado no relatório ('+total+'x):</strong>'+
                    '<div id="'+uid+'">'+pages+'</div>'+
                    nav+
                    '</div>';
            }

            div.innerHTML =
                '<div class="ouc-card-header">'+
                    '<span class="ouc-check-name">'+oEsc(item.name)+'</span>'+
                    '<div class="ouc-badges">'+badges+'</div>'+
                    '<svg class="ouc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>'+
                '</div>'+
                '<div class="ouc-card-body">'+
                    (item.desc   ?'<div class="ouc-section-label">Descrição</div><div class="ouc-section-content">'+oEsc(item.desc)+'</div>':'')+
                    (item.action ?'<div class="ouc-section-label">Ação de Correção</div><div class="ouc-section-content">'+oEsc(item.action)+'</div>':'')+
                    rep+
                '</div>';
            div.addEventListener('click', function(){ div.classList.toggle('ouc-open'); });
            return div;
        }

        function oucBuildSecHdr(cat, count) {
            var d = document.createElement('div');
            d.className = 'ouc-section-header '+(cat==='UPGRADE'?'upgrade':'patching');
            d.innerHTML = (cat==='UPGRADE'
                ?'<span>⬆️ Parte 1 — Verificações de Upgrade Oracle</span>'
                :'<span>📦 Parte 2 — Verificações de Patching Oracle</span>')+
                '<span class="ouc-section-count">'+count+' verificações</span>';
            return d;
        }

        function oucUpdateStats(vis) {
            function s(id,v){ var e=oGet(id); if(e) e.textContent=v; }
            s('ouc-cnt-vis',vis.length); s('ouc-cnt-total',OUC_DATA.length);
            s('ouc-cnt-err', vis.filter(function(r){return r.sev==='ERROR';}).length);
            s('ouc-cnt-warn',vis.filter(function(r){return r.sev==='WARNING';}).length);
            s('ouc-cnt-rec', vis.filter(function(r){return r.sev==='RECOMMEND';}).length);
            s('ouc-cnt-inf', vis.filter(function(r){return r.sev==='INFO';}).length);
        }

        function oucRender() {
            var FOUND = window._oucReportFound || {};
            var q     = oVal('ouc-search').toLowerCase().trim();
            var sev   = oVal('ouc-sev');
            var stage = oVal('ouc-stage');
            var fix   = oVal('ouc-fix');
            var cat   = oVal('ouc-cat');
            var rfVal = oVal('ouc-report-filter');

            var filtered = OUC_DATA.filter(function(r) {
                if (sev  && r.sev   !== sev)   return false;
                if (fix  && r.fix   !== fix)    return false;
                if (cat  && r.cat   !== cat)    return false;
                // Stage: data has PRE/POST but log has PRECHECKS/POSTCHECKS — match both
                if (stage) {
                    var rs = (r.stage||'').toUpperCase();
                    if (rs !== stage && rs.indexOf(stage) !== 0) return false;
                }
                if (rfVal==='found'    && !FOUND[r.name]) return false;
                if (rfVal==='notfound' &&  FOUND[r.name]) return false;
                if (q) return [r.name,r.desc,r.action].join(' ').toLowerCase().indexOf(q) !== -1;
                return true;
            });

            var container = oGet('ouc-results');
            if (!container) return;
            container.innerHTML = '';

            if (filtered.length === 0) {
                container.innerHTML = '<div class="ouc-empty"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg><div>Nenhuma verificação encontrada.</div></div>';
                oucUpdateStats(filtered); return;
            }

            var frag   = document.createDocumentFragment();
            var upItms = filtered.filter(function(r){return r.cat==='UPGRADE';});
            var ptItms = filtered.filter(function(r){return r.cat==='PATCHING';});
            var showH  = upItms.length>0 && ptItms.length>0;

            if (upItms.length>0){ if(showH) frag.appendChild(oucBuildSecHdr('UPGRADE',upItms.length)); upItms.forEach(function(r){frag.appendChild(oucBuildCard(r));}); }
            if (ptItms.length>0){ if(showH) frag.appendChild(oucBuildSecHdr('PATCHING',ptItms.length)); ptItms.forEach(function(r){frag.appendChild(oucBuildCard(r));}); }

            container.appendChild(frag);
            oucUpdateStats(filtered);
        }
        window.oucRender = oucRender;

        /* Pagination for PDB list inside cards */
        window.oucPage = function(uid, total, pageSize, dir) {
            var container = document.getElementById(uid);
            if (!container) return;
            var pages = container.children;
            var current = 0;
            for (var i = 0; i < pages.length; i++) {
                if (pages[i].style.display !== 'none') { current = i; break; }
            }
            var next = current + dir;
            if (next < 0 || next >= pages.length) return;
            pages[current].style.display = 'none';
            pages[next].style.display = 'block';
            var from = next * pageSize + 1;
            var to   = Math.min((next + 1) * pageSize, total);
            var info = document.getElementById(uid + '-info');
            if (info) info.textContent = from + ' – ' + to + ' de ' + total;
        };

        /* Load data via WP AJAX then render */
        function oucLoadData() {
            if (!OUC_AJAX_URL) { oucRender(); return; }
            var xhr = new XMLHttpRequest();
            xhr.open('POST', OUC_AJAX_URL);
            xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
            xhr.onload = function() {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success && Array.isArray(res.data)) {
                        OUC_DATA = res.data;
                        // If a report was already loaded before data arrived, re-apply it
                        if (window._oucReportFound && Object.keys(window._oucReportFound).length > 0) {
                            oucApplyReport(window._oucReportFound, window._oucReportMeta||{}, window._oucReportFilename||'');
                        } else {
                            oucRender();
                        }
                    }
                } catch(e){ console.error('OUC load error',e); }
            };
            xhr.onerror = function(){ oucRender(); };
            xhr.send('action=ouc_get_data');
        }

        /* Wire filters */
        ['ouc-search','ouc-sev','ouc-stage','ouc-fix','ouc-cat','ouc-report-filter'].forEach(function(id){
            var el=oGet(id);
            if(el){ el.addEventListener('input',oucRender); el.addEventListener('change',oucRender); }
        });

        /* Preset filters from shortcode attributes */
        var app = oGet('ouc-app');
        if (app) {
            var ps = app.getAttribute('data-preset-severity');
            var pt = app.getAttribute('data-preset-stage');
            if (ps && oGet('ouc-sev'))   oGet('ouc-sev').value   = ps;
            if (pt && oGet('ouc-stage')) oGet('ouc-stage').value = pt;
            // Read ajax_url from data attribute (bypasses wp_localize_script cache issue)
            OUC_AJAX_URL = app.getAttribute('data-ajax-url') || OUC_AJAX_URL;
        }

        oucLoadData();

    })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'oracle_upgrade_checks', 'ouc_render_shortcode' );

/* ─── AJAX: GET DATA ─── */
function ouc_ajax_get_data() {
    if ( file_exists( OUC_DATA_FILE ) ) {
        $data = file_get_contents( OUC_DATA_FILE );
        wp_send_json_success( json_decode( $data, true ) );
    } else {
        wp_send_json_error( 'Arquivo de dados não encontrado.' );
    }
}
add_action( 'wp_ajax_ouc_get_data',        'ouc_ajax_get_data' );
add_action( 'wp_ajax_nopriv_ouc_get_data', 'ouc_ajax_get_data' );

/* ─── AJAX: SAVE DATA (admin only) ─── */
function ouc_ajax_save_data() {
    if ( ! current_user_can('manage_options') ) wp_die('Sem permissão');
    check_ajax_referer( 'ouc_nonce', 'nonce' );
    $data = json_decode( stripslashes( $_POST['data'] ?? '' ), true );
    if ( ! is_array( $data ) ) {
        wp_send_json_error( 'JSON inválido' );
    }
    file_put_contents( OUC_DATA_FILE, json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
    wp_send_json_success( count($data) . ' itens salvos.' );
}
add_action( 'wp_ajax_ouc_save_data', 'ouc_ajax_save_data' );

/* ─── AJAX: LOG UNKNOWN CHECKS ─── */
function ouc_ajax_log_unknown() {
    $log_file = WP_CONTENT_DIR . '/oracle-checks-unknown-log.json';
    $filename = sanitize_text_field( $_POST['filename'] ?? '' );
    $checks   = json_decode( stripslashes( $_POST['checks'] ?? '[]' ), true );
    $dbs      = json_decode( stripslashes( $_POST['dbs']    ?? '[]' ), true );
    $type     = sanitize_text_field( $_POST['type'] ?? '' );

    if ( empty($checks) || ! is_array($checks) ) {
        wp_send_json_success('noop');
    }

    // Load existing log
    $log = [];
    if ( file_exists($log_file) ) {
        $log = json_decode( file_get_contents($log_file), true ) ?: [];
    }

    // Add new entry
    $entry = [
        'date'     => current_time('Y-m-d H:i:s'),
        'filename' => $filename,
        'type'     => $type,
        'checks'   => $checks,
        'dbs'      => $dbs,
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    array_unshift( $log, $entry ); // newest first
    $log = array_slice( $log, 0, 200 ); // keep last 200 entries
    file_put_contents( $log_file, json_encode( $log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );

    // Try to send Telegram notification
    $tg_token   = get_option('ouc_telegram_token', '');
    $tg_chat_id = get_option('ouc_telegram_chat_id', '');
    if ( $tg_token && $tg_chat_id ) {
        $checks_text = implode("\n", array_map(function($c){ return "  • " . $c; }, $checks));
        $dbs_text    = implode(', ', array_slice($dbs, 0, 8)) . (count($dbs) > 8 ? '...' : '');
        $msg  = "🆕 Oracle Checks — Novo check desconhecido!\n\n";
        $msg .= "📋 Arquivo: " . $filename . "\n";
        $msg .= "🔧 Tipo: " . $type . "\n";
        $msg .= "📅 Data: " . $entry['date'] . "\n";
        $msg .= "🗄️ Bancos/PDBs: " . $dbs_text . "\n\n";
        $msg .= "Checks desconhecidos:\n" . $checks_text;
        wp_remote_post( "https://api.telegram.org/bot{$tg_token}/sendMessage", [
            'timeout' => 10,
            'body'    => [
                'chat_id' => $tg_chat_id,
                'text'    => $msg,
            ],
        ]);
    }

    // Try to send email to admin
    $admin_email = get_option('admin_email');
    $subject = '[Oracle Checks] 🆕 ' . count($checks) . ' check(s) desconhecido(s) encontrado(s)';
    $body  = "Novos checks não cadastrados foram encontrados em um relatório AutoUpgrade.\n\n";
    $body .= "Data: " . $entry['date'] . "\n";
    $body .= "Arquivo: " . $filename . "\n";
    $body .= "Tipo: " . $type . "\n";
    $body .= "Bancos/PDBs: " . implode(', ', array_slice($dbs, 0, 10)) . "\n\n";
    $body .= "Checks desconhecidos:\n";
    foreach ( $checks as $c ) {
        $body .= "  - " . $c . "\n";
    }
    $body .= "\nAcesse o painel Oracle Checks para ver o histórico completo.";
    wp_mail( $admin_email, $subject, $body );

    $tg_result = 'not_configured';
    $tg_token   = get_option('ouc_telegram_token', '');
    $tg_chat_id = get_option('ouc_telegram_chat_id', '');
    if ( $tg_token && $tg_chat_id ) {
        $tg_result = 'configured_sent';
    }

    wp_send_json_success(['status'=>'logged','tg'=>$tg_result,'token_set'=>!empty($tg_token),'chat_set'=>!empty($tg_chat_id)]);
}
add_action( 'wp_ajax_ouc_log_unknown',        'ouc_ajax_log_unknown' );
add_action( 'wp_ajax_nopriv_ouc_log_unknown', 'ouc_ajax_log_unknown' );

/* ─── AJAX: GET UNKNOWN LOG (admin only) ─── */
function ouc_ajax_get_unknown_log() {
    if ( ! current_user_can('manage_options') ) wp_die('Sem permissão');
    $log_file = WP_CONTENT_DIR . '/oracle-checks-unknown-log.json';
    if ( ! file_exists($log_file) ) {
        wp_send_json_success([]);
    }
    $log = json_decode( file_get_contents($log_file), true ) ?: [];
    wp_send_json_success($log);
}
add_action( 'wp_ajax_ouc_get_unknown_log', 'ouc_ajax_get_unknown_log' );

/* ─── AJAX: SAVE SETTINGS ─── */
function ouc_ajax_save_settings() {
    if ( ! current_user_can('manage_options') ) wp_die('Sem permissão');
    check_ajax_referer( 'ouc_nonce', 'nonce' );
    update_option( 'ouc_telegram_token',   sanitize_text_field( $_POST['token']   ?? '' ) );
    update_option( 'ouc_telegram_chat_id', sanitize_text_field( $_POST['chat_id'] ?? '' ) );
    wp_send_json_success('Configurações salvas.');
}
add_action( 'wp_ajax_ouc_save_settings', 'ouc_ajax_save_settings' );

/* ─── AJAX: TEST TELEGRAM ─── */
function ouc_ajax_test_telegram() {
    if ( ! current_user_can('manage_options') ) wp_die('Sem permissão');
    check_ajax_referer( 'ouc_nonce', 'nonce' );
    $token   = sanitize_text_field( $_POST['token']   ?? '' );
    $chat_id = sanitize_text_field( $_POST['chat_id'] ?? '' );
    if ( ! $token || ! $chat_id ) {
        wp_send_json_error('Token ou Chat ID não informado.');
    }
    $msg = "✅ Oracle Checks — Teste de notificação\n\nConexão com Telegram configurada com sucesso!";
    $res = wp_remote_post( "https://api.telegram.org/bot{$token}/sendMessage", [
        'timeout' => 10,
        'body'    => [ 'chat_id' => $chat_id, 'text' => $msg ],
    ]);
    if ( is_wp_error($res) ) {
        wp_send_json_error('Erro: ' . $res->get_error_message());
    }
    $body = json_decode( wp_remote_retrieve_body($res), true );
    if ( ! empty($body['ok']) ) {
        wp_send_json_success('Mensagem enviada com sucesso!');
    } else {
        wp_send_json_error('Erro do Telegram: ' . ($body['description'] ?? 'desconhecido'));
    }
}
add_action( 'wp_ajax_ouc_test_telegram', 'ouc_ajax_test_telegram' );

/* ─── AJAX: CLEAR UNKNOWN LOG ─── */
function ouc_ajax_clear_unknown_log() {
    if ( ! current_user_can('manage_options') ) wp_die('Sem permissão');
    check_ajax_referer( 'ouc_nonce', 'nonce' );
    $log_file = WP_CONTENT_DIR . '/oracle-checks-unknown-log.json';
    if ( file_exists($log_file) ) unlink($log_file);
    wp_send_json_success('Histórico limpo.');
}
add_action( 'wp_ajax_ouc_clear_unknown_log', 'ouc_ajax_clear_unknown_log' );

/* ─── AJAX: DELETE SINGLE UNKNOWN ENTRY ─── */
function ouc_ajax_delete_unknown_entry() {
    if ( ! current_user_can('manage_options') ) wp_die('Sem permissão');
    check_ajax_referer( 'ouc_nonce', 'nonce' );
    $log_file = WP_CONTENT_DIR . '/oracle-checks-unknown-log.json';
    $idx = intval( $_POST['index'] ?? -1 );
    if ( ! file_exists($log_file) ) wp_send_json_error('Log não encontrado.');
    $log = json_decode( file_get_contents($log_file), true ) ?: [];
    if ( $idx < 0 || $idx >= count($log) ) wp_send_json_error('Índice inválido.');
    array_splice( $log, $idx, 1 );
    file_put_contents( $log_file, json_encode( $log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
    wp_send_json_success('Entrada removida.');
}
add_action( 'wp_ajax_ouc_delete_unknown_entry', 'ouc_ajax_delete_unknown_entry' );

/* ─── ADMIN MENU ─── */
function ouc_admin_menu() {
    add_menu_page(
        'Oracle Checks', 'Oracle Checks',
        'manage_options', 'oracle-checks',
        'ouc_admin_page',
        'dashicons-database', 30
    );
}
add_action( 'admin_menu', 'ouc_admin_menu' );

function ouc_admin_enqueue( $hook ) {
    if ( $hook !== 'toplevel_page_oracle-checks' ) return;
    wp_enqueue_style( 'ouc-admin', plugin_dir_url(__FILE__) . 'assets/oracle-admin.css', [], OUC_VERSION );
    wp_enqueue_script( 'ouc-admin', plugin_dir_url(__FILE__) . 'assets/oracle-admin.js', ['jquery'], time(), true );
    wp_localize_script('ouc-admin', 'OUC_ADMIN', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('ouc_nonce'),
        'data_url' => content_url('oracle-checks-data.json'),
    ]);
}
add_action( 'admin_enqueue_scripts', 'ouc_admin_enqueue' );

/* ─── ADMIN PAGE ─── */
function ouc_admin_page() { ?>
<div class="wrap" id="ouc-admin-wrap">
    <h1>🗄️ Oracle Checks — Gerenciar Verificações</h1>

    <div class="ouc-admin-tabs">
        <button class="ouc-tab active" onclick="oucTab('list',this)">📋 Lista de Itens</button>
        <button class="ouc-tab" onclick="oucTab('add',this)">➕ Adicionar Item</button>
        <button class="ouc-tab" onclick="oucTab('import',this)">📤 Importar JSON</button>
        <button class="ouc-tab" onclick="oucTab('export',this)">📥 Exportar JSON</button>
        <button class="ouc-tab" onclick="oucTab('unknown',this)" id="ouc-tab-unknown">🆕 Checks Desconhecidos <?php
            $log_file = WP_CONTENT_DIR . '/oracle-checks-unknown-log.json';
            $count = file_exists($log_file) ? count(json_decode(file_get_contents($log_file), true) ?: []) : 0;
            if ($count > 0) echo '<span style="background:#fb923c;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:4px;">'.$count.'</span>';
        ?></button>
        <button class="ouc-tab" onclick="oucTab('settings',this)">⚙️ Configurações</button>
    </div>

    <!-- LIST TAB -->
    <div id="tab-list" class="ouc-tab-panel">
        <div class="ouc-admin-toolbar">
            <input type="text" id="admin-search" placeholder="Filtrar por nome…" onkeyup="oucAdminFilter()">
            <select id="admin-filter-sev" onchange="oucAdminFilter()">
                <option value="">Todas as Severidades</option>
                <option value="ERROR">ERROR</option>
                <option value="WARNING">WARNING</option>
                <option value="RECOMMEND">RECOMMEND</option>
                <option value="INFO">INFO</option>
            </select>
            <select id="admin-filter-cat" onchange="oucAdminFilter()">
                <option value="">Todas as Categorias</option>
                <option value="UPGRADE">UPGRADE</option>
                <option value="PATCHING">PATCHING</option>
            </select>
            <span id="admin-count" class="ouc-admin-count"></span>
        </div>
        <div id="ouc-admin-list"></div>
    </div>

    <!-- ADD TAB -->
    <div id="tab-add" class="ouc-tab-panel" style="display:none">
        <div class="ouc-form-card">
            <h2 id="form-title">Adicionar Nova Verificação</h2>
            <input type="hidden" id="edit-index" value="-1">
            <div class="ouc-form-grid">
                <label>Nome do Check *<input type="text" id="f-name" placeholder="EX: MY_NEW_CHECK"></label>
                <label>Severidade *
                    <select id="f-sev">
                        <option value="ERROR">ERROR</option>
                        <option value="WARNING">WARNING</option>
                        <option value="RECOMMEND">RECOMMEND</option>
                        <option value="INFO">INFO</option>
                    </select>
                </label>
                <label>Etapa *
                    <select id="f-stage">
                        <option value="PRE">PRE</option>
                        <option value="POST">POST</option>
                        <option value="VALIDATION">VALIDATION</option>
                    </select>
                </label>
                <label>Correção
                    <select id="f-fix">
                        <option value="MANUAL">MANUAL</option>
                        <option value="AUTO">AUTO</option>
                        <option value="BOTH">BOTH</option>
                    </select>
                </label>
                <label>Categoria *
                    <select id="f-cat">
                        <option value="UPGRADE">UPGRADE</option>
                        <option value="PATCHING">PATCHING</option>
                    </select>
                </label>
            </div>
            <label>Descrição *<textarea id="f-desc" rows="3" placeholder="Descreva o que este check verifica…"></textarea></label>
            <label>Ação de Correção<textarea id="f-action" rows="3" placeholder="Como corrigir o problema…"></textarea></label>
            <div class="ouc-form-actions">
                <button class="button button-primary" onclick="oucSaveItem()">💾 Salvar</button>
                <button class="button" onclick="oucResetForm()">✕ Cancelar</button>
            </div>
            <div id="form-msg" style="margin-top:10px"></div>
        </div>
    </div>

    <!-- IMPORT TAB -->
    <div id="tab-import" class="ouc-tab-panel" style="display:none">
        <div class="ouc-form-card">
            <h2>Importar JSON</h2>
            <p>Cole o conteúdo JSON completo abaixo (array de objetos). <strong>Atenção:</strong> isso substituirá todos os dados existentes.</p>
            <textarea id="import-json" rows="12" placeholder='[{"name":"CHECK_NAME","desc":"Descrição","action":"Ação","sev":"ERROR","stage":"PRE","fix":"MANUAL","cat":"UPGRADE"}]'></textarea>
            <div class="ouc-form-actions">
                <button class="button button-primary" onclick="oucImportJSON()">📤 Importar</button>
            </div>
            <div id="import-msg" style="margin-top:10px"></div>
        </div>
    </div>

    <!-- EXPORT TAB -->
    <div id="tab-export" class="ouc-tab-panel" style="display:none">
        <div class="ouc-form-card">
            <h2>Exportar JSON</h2>
            <p>Clique para carregar os dados atuais e copiá-los ou baixá-los.</p>
            <button class="button button-primary" onclick="oucExportLoad()">📥 Carregar JSON atual</button>
            <textarea id="export-json" rows="14" readonly style="margin-top:12px;display:none"></textarea>
            <div class="ouc-form-actions" id="export-actions" style="display:none">
                <button class="button" onclick="oucCopyExport()">📋 Copiar</button>
                <button class="button" onclick="oucDownloadExport()">⬇️ Baixar .json</button>
            </div>
        </div>
    </div>

    <!-- UNKNOWN CHECKS TAB -->
    <div id="tab-unknown" class="ouc-tab-panel" style="display:none">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
            <h2 style="margin:0;font-size:15px;">🆕 Histórico de Checks Desconhecidos</h2>
            <div style="display:flex;gap:8px;">
                <button class="button" onclick="oucLoadUnknown()">🔄 Atualizar</button>
                <button class="button" style="color:#d63638;" onclick="oucClearUnknownLog()">🗑️ Limpar tudo</button>
            </div>
        </div>
        <p style="font-size:13px;color:#646970;margin:0 0 14px;">Checks encontrados em relatórios AutoUpgrade que não estão cadastrados na lista. Cada entrada representa um upload feito por um usuário.</p>
        <div id="ouc-unknown-log">
            <p style="color:#646970;font-size:13px;">Carregando...</p>
        </div>
    </div>

    <!-- SETTINGS TAB -->
    <div id="tab-settings" class="ouc-tab-panel" style="display:none">
        <div class="ouc-form-card">
            <h2>⚙️ Configurações</h2>
            <h3 style="font-size:14px;margin:0 0 6px;">📱 Notificação via Telegram</h3>
            <p style="font-size:13px;color:#646970;margin:0 0 14px;">Quando um check desconhecido for encontrado, uma mensagem será enviada automaticamente para o seu Telegram.</p>
            <div class="ouc-form-grid">
                <label>Token do Bot
                    <input type="password" id="tg-token" placeholder="1234567890:ABCdef..."
                        value="<?php echo esc_attr(get_option('ouc_telegram_token','')); ?>">
                </label>
                <label>Chat ID
                    <input type="text" id="tg-chat" placeholder="-123456789 ou 123456789"
                        value="<?php echo esc_attr(get_option('ouc_telegram_chat_id','')); ?>">
                </label>
            </div>
            <div class="ouc-form-actions">
                <button class="button button-primary" onclick="oucSaveSettings()">💾 Salvar</button>
                <button class="button" onclick="oucTestTelegram()">📤 Enviar mensagem de teste</button>
            </div>
            <div id="settings-msg" style="margin-top:10px;font-size:13px;"></div>
        </div>
    </div>

    <div id="ouc-admin-save-bar">
        <span id="ouc-save-status"></span>
        <button class="button button-primary button-large" onclick="oucAdminSave()">💾 Salvar todas as alterações</button>
    </div>
</div>
<?php }

/* ─── INSTALL: copy default data ─── */
function ouc_install() {
    $default = plugin_dir_path(__FILE__) . 'assets/oracle-checks-default.json';
    if ( ! file_exists( OUC_DATA_FILE ) && file_exists( $default ) ) {
        copy( $default, OUC_DATA_FILE );
    }
}
register_activation_hook( __FILE__, 'ouc_install' );
