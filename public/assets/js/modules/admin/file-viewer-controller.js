/**
 * public/assets/js/modules/admin/file-viewer-controller.js
 * Versión: Full Height + Theme Aware + Safe Highlighting
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';

let _container = null;
let _currentFiles = [];
let _activeFileIndex = 0;
let _isHighlightMode = false;

export const FileViewerController = {
    init: () => {
        console.log("FileViewerController: Inicializado");
        
        _container = document.querySelector('[data-section="admin-file-viewer"]');
        if (!_container) return;

        // Leer preferencia
        const savedPref = localStorage.getItem('viewer_highlight_mode');
        _isHighlightMode = savedPref === 'true';

        const check = document.getElementById('check-highlight-mode');
        if (check) check.checked = _isHighlightMode;

        // Leer parámetros URL
        const urlParams = new URLSearchParams(window.location.search);
        const filesParam = urlParams.get('files');

        if (!filesParam) {
            showError('No se especificaron archivos.');
            return;
        }

        initEvents();
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
                loadContent(_currentFiles.map(f => f.path));
            }
        });
    }

    if (btnCopy) {
        btnCopy.addEventListener('click', () => {
            const content = _currentFiles[_activeFileIndex]?.content || '';
            if (content) {
                navigator.clipboard.writeText(content)
                    .then(() => Toast.show('Copiado', 'info'))
                    .catch(() => Toast.show('Error al copiar', 'error'));
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
            if (_activeFileIndex >= _currentFiles.length) _activeFileIndex = 0;
            renderTabs();
            renderActiveContent();
        } else {
            showError(res.message);
        }
    } catch (e) {
        console.error(e);
        if (loader) loader.classList.add('d-none');
        showError('Error de conexión.');
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
        
        let icon = 'description';
        if (file.filename.endsWith('.log')) icon = 'text_snippet';
        else if (file.filename.endsWith('.php')) icon = 'php';
        else if (file.filename.endsWith('.js')) icon = 'javascript';
        else if (file.filename.endsWith('.sql')) icon = 'database';
        
        tab.innerHTML = `<span class="material-symbols-rounded">${icon}</span><span>${file.filename}</span>`;
        tab.onclick = () => {
            _activeFileIndex = index;
            renderTabs();
            renderActiveContent();
        };
        container.appendChild(tab);
    });
}

function renderActiveContent() {
    const container = document.getElementById('file-content-container');
    if (!container || !_currentFiles[_activeFileIndex]) return;

    const file = _currentFiles[_activeFileIndex];
    
    // Resetear estilos inline que puedan haber quedado
    container.removeAttribute('style');
    // Forzamos flex:1 para el layout full height
    container.style.flex = '1';
    
    if (file.error) {
        container.innerHTML = `<div class="state-error" style="text-align:left;">Error: ${file.error}</div>`;
        return;
    }

    const rawContent = file.content;
    const warning = file.is_truncated ? `<div class="component-message component-message--warning mb-0" style="margin:16px;">Archivo truncado (${file.size})</div>` : '';

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

        // Renderizar con syntax-container (El CSS maneja el fondo y el color)
        container.innerHTML = `${warning}<div class="syntax-container">${coloredCode}</div>`;
        
    } else {
        // Modo texto plano: Usamos syntax-container pero sin coloreado regex, 
        // para aprovechar el layout y colores de fondo del tema.
        let safeCode = escapeHtml(rawContent);
        container.innerHTML = `${warning}<div class="syntax-container">${safeCode}</div>`;
    }
}

// === MOTOR DE RESALTADO MEJORADO ===

/**
 * Sistema de protección de tokens:
 * Extrae cadenas y comentarios primero para evitar que otras regex 
 * rompan el HTML generado dentro de ellos.
 */
function safeHighlight(code, grammar) {
    const placeholders = [];
    let processed = code;

    // 1. Extraer Cadenas y Comentarios (guardar como placeholders)
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

    // 2. Resaltar Palabras Clave y Números (en el texto restante)
    grammar.keywords.forEach(rule => {
        processed = processed.replace(rule.regex, `<span class="${rule.class}">$1</span>`);
    });

    // 3. Restaurar placeholders
    placeholders.forEach(item => {
        processed = processed.replace(item.placeholder, item.content);
    });

    return processed;
}

function highlightLogs(code) {
    // Corrección: Añadido [\w\\.-] para permitir guiones y puntos en carpetas y archivos
    return code
        // Fechas: [2026-01-01 10:00:00]
        .replace(/(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})/g, '<span class="token-date">$1</span>')
        // Etiquetas: [INFO], [ERROR], [USER:1]
        .replace(/(\[.*?\])/g, (match) => {
            if (match.includes('ERROR') || match.includes('CRITICAL')) return `<span class="token-error">${match}</span>`;
            if (match.includes('WARNING')) return `<span class="token-bracket" style="color:var(--color-toast-warning);">${match}</span>`;
            return `<span class="token-bracket">${match}</span>`;
        })
        // Rutas de archivo (Windows y Unix)
        .replace(/([a-zA-Z]:\\[\w\\.-]+|\/[\w\/.-]+\.\w+)/g, '<span class="token-string">$1</span>')
        // Errores comunes de PHP
        .replace(/(Undefined variable|Uncaught Exception|Fatal Error|Call to a member function)/g, '<span class="token-error" style="font-weight:bold;">$1</span>');
}

function highlightSql(code) {
    const sqlGrammar = {
        extraction: [
            // Comentarios SQL (-- comentario) y (# comentario)
            { regex: /(--.*)|(#.*)/g, class: 'token-comment' },
            // Comentarios bloque /* ... */
            { regex: /(\/\*[\s\S]*?\*\/)/g, class: 'token-comment' },
            // Strings 'texto' y "texto" y `backticks`
            { regex: /(['"`])(.*?)\1/g, class: 'token-string' }
        ],
        keywords: [
            // Números
            { regex: /\b(\d+)\b/g, class: 'token-number' },
            // Palabras clave SQL
            { regex: /\b(SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|AND|OR|LIMIT|ORDER BY|GROUP BY|LEFT JOIN|INNER JOIN|CREATE TABLE|DROP TABLE|ALTER TABLE|VALUES|SET|IS NULL|NOT NULL|PRIMARY KEY|AUTO_INCREMENT|DEFAULT|INTO|IF EXISTS)\b/gi, class: 'token-keyword' },
            // Tipos de datos
            { regex: /\b(INT|VARCHAR|TEXT|DATETIME|TIMESTAMP|TINYINT|ENUM|JSON)\b/gi, class: 'token-logic' }
        ]
    };
    return safeHighlight(code, sqlGrammar);
}

function highlightCode(code) {
    const genericGrammar = {
        extraction: [
            // Comentarios // y /* */
            { regex: /(\/\/.*)|(\/\*[\s\S]*?\*\/)/g, class: 'token-comment' },
            // Strings
            { regex: /(['"`])(.*?)\1/g, class: 'token-string' }
        ],
        keywords: [
            // Números
            { regex: /\b(\d+)\b/g, class: 'token-number' },
            // Keywords PHP/JS
            { regex: /\b(function|return|if|else|while|for|foreach|class|public|private|protected|const|var|let|async|await|switch|case|break)\b/g, class: 'token-keyword' },
            // Lógica y valores
            { regex: /\b(true|false|null|new|echo|print|include|require)\b/g, class: 'token-logic' },
            // Variables PHP ($variable)
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