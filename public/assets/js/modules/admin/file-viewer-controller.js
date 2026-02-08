/**
 * public/assets/js/modules/admin/file-viewer-controller.js
 * Versión Segura (DOM API)
 */

import { ApiService } from '../../core/api-service.js';
import { ToastManager } from '../../core/toast-manager.js';
import { I18nManager } from '../../core/i18n-manager.js'; 

let _container = null;
let _currentFiles = [];
let _activeFileIndex = 0;
let _isHighlightMode = false;

export const FileViewerController = {
    init: () => {
        console.log("FileViewerController: Inicializado (Safe Mode)");
        
        _container = document.querySelector('[data-section="admin-file-viewer"]');
        if (!_container) return;

        // Leer preferencia de resaltado
        const savedPref = localStorage.getItem('viewer_highlight_mode');
        _isHighlightMode = savedPref === 'true';

        const check = document.getElementById('check-highlight-mode');
        if (check) check.checked = _isHighlightMode;

        // Leer parámetros URL
        const urlParams = new URLSearchParams(window.location.search);
        const filesParam = urlParams.get('files');

        if (!filesParam) {
            showError(I18nManager.t('admin.file_viewer.no_files') || 'No se especificaron archivos.');
            return;
        }

        initEvents();
        // Cargar los archivos iniciales
        loadContent(filesParam.split(','));
    }
};

function initEvents() {
    const btnRefresh = _container.querySelector('[data-action="refresh-file"]');
    const btnCopy = _container.querySelector('[data-action="copy-content"]');
    const btnOptions = _container.querySelector('[data-action="toggle-options"]');
    const menuOptions = document.getElementById('viewer-options-menu');
    const btnToggleHighlight = _container.querySelector('[data-action="toggle-highlight-mode"]');

    if (btnOptions && menuOptions) {
        btnOptions.addEventListener('click', (e) => {
            e.stopPropagation();
            menuOptions.classList.toggle('active');
            btnOptions.classList.toggle('active');
        });
        document.addEventListener('click', (e) => {
            if (!menuOptions.contains(e.target) && !btnOptions.contains(e.target)) {
                menuOptions.classList.remove('active');
                btnOptions.classList.remove('active');
            }
        });
    }

    if (btnToggleHighlight) {
        btnToggleHighlight.addEventListener('click', () => {
            _isHighlightMode = !_isHighlightMode;
            const check = document.getElementById('check-highlight-mode');
            if(check) check.checked = _isHighlightMode;
            localStorage.setItem('viewer_highlight_mode', _isHighlightMode);
            renderActiveContent();
        });
    }

    if (btnRefresh) {
        btnRefresh.addEventListener('click', () => {
            if (_currentFiles.length > 0) {
                // Recargar solo los archivos que siguen abiertos
                loadContent(_currentFiles.map(f => f.path));
            }
        });
    }

    if (btnCopy) {
        btnCopy.addEventListener('click', () => {
            const content = _currentFiles[_activeFileIndex]?.content || '';
            if (content) {
                navigator.clipboard.writeText(content)
                    .then(() => ToastManager.show(I18nManager.t('js.core.copied') || 'Copiado', 'info'))
                    .catch(() => ToastManager.show(I18nManager.t('js.core.copy_error') || 'Error al copiar', 'error'));
            }
        });
    }
}

async function loadContent(paths) {
    const loader = document.getElementById('viewer-loading');
    const errorBox = document.getElementById('viewer-error');
    const contentArea = document.getElementById('file-content-container');
    
    // Ocultar contenido previo
    if (contentArea) contentArea.style.opacity = '0';
    if (loader) loader.classList.remove('d-none');
    if (errorBox) errorBox.classList.add('d-none');

    const formData = new FormData();
    formData.append('files', paths.join(','));

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.GetFileContent, formData);

        if (loader) loader.classList.add('d-none');
        if (contentArea) contentArea.style.opacity = '1';

        if (res.success) {
            _currentFiles = res.files;
            // Asegurar que el índice sea válido
            if (_activeFileIndex >= _currentFiles.length) _activeFileIndex = 0;
            renderTabs();
            renderActiveContent();
            
            updateUrlState(); 
        } else {
            showError(res.message);
        }
    } catch (e) {
        console.error(e);
        if (loader) loader.classList.add('d-none');
        showError(I18nManager.t('js.core.connection_error') || 'Error de conexión.');
    }
}

function renderTabs() {
    const container = document.getElementById('file-viewer-tabs');
    if (!container) return;
    container.innerHTML = '';

    _currentFiles.forEach((file, index) => {
        const isActive = (index === _activeFileIndex);
        const tab = document.createElement('div');
        tab.className = `viewer-tab ${isActive ? 'active' : ''}`;
        
        let iconName = 'description';
        if (file.filename.endsWith('.log')) iconName = 'text_snippet';
        else if (file.filename.endsWith('.php')) iconName = 'php';
        else if (file.filename.endsWith('.js')) iconName = 'javascript';
        else if (file.filename.endsWith('.sql')) iconName = 'database';
        
        const icon = document.createElement('span');
        icon.className = 'material-symbols-rounded tab-icon';
        icon.textContent = iconName;
        tab.appendChild(icon);

        const label = document.createElement('span');
        label.className = 'tab-label';
        label.textContent = file.filename; // [SEGURIDAD]
        tab.appendChild(label);

        const closeBtn = document.createElement('span');
        closeBtn.className = 'material-symbols-rounded tab-close';
        closeBtn.title = I18nManager.t('admin.file_viewer.close_file') || 'Cerrar archivo';
        closeBtn.textContent = 'close';
        
        // Click en el botón de cerrar
        closeBtn.onclick = (e) => {
            e.stopPropagation();
            closeFile(index);
        };
        tab.appendChild(closeBtn);
        
        // Click en la pestaña
        tab.onclick = () => {
            _activeFileIndex = index;
            renderTabs();
            renderActiveContent();
        };

        container.appendChild(tab);
    });
}

function closeFile(indexToRemove) {
    _currentFiles.splice(indexToRemove, 1);

    if (_currentFiles.length === 0) {
        _activeFileIndex = -1;
    } else {
        if (indexToRemove === _activeFileIndex) {
            _activeFileIndex = Math.max(0, indexToRemove - 1);
        } else if (indexToRemove < _activeFileIndex) {
            _activeFileIndex--;
        }
    }

    renderTabs();
    renderActiveContent();
    updateUrlState();
}

function updateUrlState() {
    const url = new URL(window.location);
    
    if (_currentFiles.length > 0) {
        const paths = _currentFiles.map(f => f.path).join(',');
        url.searchParams.set('files', paths);
    } else {
        url.searchParams.delete('files');
    }

    window.history.replaceState({}, '', url);
}

function renderActiveContent() {
    const container = document.getElementById('file-content-container');
    if (!container) return;

    container.innerHTML = '';
    container.removeAttribute('style');
    container.style.flex = '1';
    
    // CASO: No quedan archivos
    if (_activeFileIndex === -1 || !_currentFiles[_activeFileIndex]) {
        container.innerHTML = `
            <div class="state-empty" style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--text-secondary);">
                <span class="material-symbols-rounded" style="font-size: 48px; opacity: 0.5; margin-bottom: 16px;">folder_off</span>
                <p>${I18nManager.t('admin.file_viewer.no_files_open') || 'No hay archivos abiertos.'}</p>
            </div>`;
        return;
    }

    const file = _currentFiles[_activeFileIndex];
    
    if (file.error) {
        const errDiv = document.createElement('div');
        errDiv.className = 'state-error';
        errDiv.style.textAlign = 'left';
        errDiv.textContent = `${I18nManager.t('js.core.error') || 'Error'}: ${file.error}`;
        container.appendChild(errDiv);
        return;
    }

    const rawContent = file.content;
    const warningMsg = I18nManager.t('admin.file_viewer.truncated', [file.size]) || `Archivo truncado (${file.size})`;
    
    if (file.is_truncated) {
        const warnDiv = document.createElement('div');
        warnDiv.className = 'component-message component-message--warning mb-0';
        warnDiv.style.margin = '16px';
        warnDiv.textContent = warningMsg;
        container.appendChild(warnDiv);
    }

    const syntaxDiv = document.createElement('div');
    syntaxDiv.className = 'syntax-container';

    if (_isHighlightMode) {
        const ext = file.filename.split('.').pop().toLowerCase();
        let safeCode = escapeHtml(rawContent);
        let coloredCode = '';

        if (ext === 'log') {
            coloredCode = highlightLogs(safeCode);
        } else if (ext === 'sql') {
            coloredCode = highlightSql(safeCode);
        } else if (['php', 'js', 'json', 'css'].includes(ext)) {
            coloredCode = highlightCode(safeCode);
        } else {
            coloredCode = safeCode;
        }
        
        // Aquí insertamos el código coloreado. Como el coloreado inserta spans HTML,
        // usamos innerHTML, PERO el contenido 'rawContent' ha sido saneado previamente
        // por 'escapeHtml' antes de ser procesado por los highlighters.
        syntaxDiv.innerHTML = coloredCode; 
    } else {
        // Modo plano: puro texto seguro
        syntaxDiv.textContent = rawContent; 
    }
    
    container.appendChild(syntaxDiv);
}

// === MOTOR DE RESALTADO (IMPORTANTE: escapeHtml primero) ===

function safeHighlight(code, grammar) {
    const placeholders = [];
    let processed = code;

    grammar.extraction.forEach(rule => {
        processed = processed.replace(rule.regex, (match) => {
            const placeholder = `___TOKEN_${placeholders.length}___`;
            placeholders.push({
                placeholder: placeholder,
                content: `<span class="${rule.class}">${match}</span>`
            });
            return placeholder;
        });
    });

    grammar.keywords.forEach(rule => {
        processed = processed.replace(rule.regex, `<span class="${rule.class}">$1</span>`);
    });

    placeholders.forEach(item => {
        processed = processed.replace(item.placeholder, item.content);
    });

    return processed;
}

function highlightLogs(code) {
    return code
        .replace(/(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})/g, '<span class="token-date">$1</span>')
        .replace(/(\[.*?\])/g, (match) => {
            if (match.includes('ERROR') || match.includes('CRITICAL')) return `<span class="token-error">${match}</span>`;
            if (match.includes('WARNING')) return `<span class="token-bracket" style="color:var(--color-toast-warning);">${match}</span>`;
            return `<span class="token-bracket">${match}</span>`;
        })
        .replace(/([a-zA-Z]:\\[\w\\.-]+|\/[\w\/.-]+\.\w+)/g, '<span class="token-string">$1</span>')
        .replace(/(Undefined variable|Uncaught Exception|Fatal Error|Call to a member function)/g, '<span class="token-error" style="font-weight:bold;">$1</span>');
}

function highlightSql(code) {
    const sqlGrammar = {
        extraction: [
            { regex: /(--.*)|(#.*)/g, class: 'token-comment' },
            { regex: /(\/\*[\s\S]*?\*\/)/g, class: 'token-comment' },
            { regex: /(['"`])(.*?)\1/g, class: 'token-string' }
        ],
        keywords: [
            { regex: /\b(\d+)\b/g, class: 'token-number' },
            { regex: /\b(SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|AND|OR|LIMIT|ORDER BY|GROUP BY|LEFT JOIN|INNER JOIN|CREATE TABLE|DROP TABLE|ALTER TABLE|VALUES|SET|IS NULL|NOT NULL|PRIMARY KEY|AUTO_INCREMENT|DEFAULT|INTO|IF EXISTS)\b/gi, class: 'token-keyword' },
            { regex: /\b(INT|VARCHAR|TEXT|DATETIME|TIMESTAMP|TINYINT|ENUM|JSON)\b/gi, class: 'token-logic' }
        ]
    };
    return safeHighlight(code, sqlGrammar);
}

function highlightCode(code) {
    const genericGrammar = {
        extraction: [
            { regex: /(\/\/.*)|(\/\*[\s\S]*?\*\/)/g, class: 'token-comment' },
            { regex: /(['"`])(.*?)\1/g, class: 'token-string' }
        ],
        keywords: [
            { regex: /\b(\d+)\b/g, class: 'token-number' },
            { regex: /\b(function|return|if|else|while|for|foreach|class|public|private|protected|const|var|let|async|await|switch|case|break)\b/g, class: 'token-keyword' },
            { regex: /\b(true|false|null|new|echo|print|include|require)\b/g, class: 'token-logic' },
            { regex: /(\$[a-zA-Z_][\w]*)/g, class: 'token-attr' }
        ]
    };
    return safeHighlight(code, genericGrammar);
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function showError(msg) {
    const errorBox = document.getElementById('viewer-error');
    if (errorBox) {
        errorBox.textContent = msg;
        errorBox.classList.remove('d-none');
    }
}