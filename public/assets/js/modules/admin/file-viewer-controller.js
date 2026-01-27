/**
 * public/assets/js/modules/admin/file-viewer-controller.js
 */

import { ApiService } from '../../core/api-service.js';
import { Toast } from '../../core/toast-manager.js';

let _container = null;
let _currentFiles = [];
let _activeFileIndex = 0;

export const FileViewerController = {
    init: () => {
        console.log("FileViewerController: Inicializado");
        
        _container = document.querySelector('[data-section="admin-file-viewer"]');
        if (!_container) return;

        // Leer parámetros de la URL (?files=a.log,b.log)
        const urlParams = new URLSearchParams(window.location.search);
        const filesParam = urlParams.get('files');

        if (!filesParam) {
            showError('No se especificaron archivos para visualizar.');
            return;
        }

        const filesToLoad = filesParam.split(',');
        
        initEvents();
        loadContent(filesToLoad);
    }
};

function initEvents() {
    const btnRefresh = _container.querySelector('[data-action="refresh-file"]');
    const btnCopy = _container.querySelector('[data-action="copy-content"]');

    if (btnRefresh) {
        btnRefresh.addEventListener('click', () => {
            if (_currentFiles.length > 0) {
                const paths = _currentFiles.map(f => f.path);
                loadContent(paths);
            }
        });
    }

    if (btnCopy) {
        btnCopy.addEventListener('click', () => {
            const content = _currentFiles[_activeFileIndex]?.content || '';
            if (content) {
                navigator.clipboard.writeText(content).then(() => {
                    Toast.show('Contenido copiado', 'info');
                }).catch(() => Toast.show('Error al copiar', 'error'));
            }
        });
    }
}

async function loadContent(paths) {
    const loader = document.getElementById('viewer-loading');
    const errorBox = document.getElementById('viewer-error');
    const contentArea = document.getElementById('file-content-container');
    const tabsContainer = document.getElementById('file-viewer-tabs');

    if (loader) loader.classList.remove('d-none');
    if (errorBox) errorBox.classList.add('d-none');
    if (contentArea) contentArea.style.opacity = '0.5';

    const formData = new FormData();
    formData.append('files', paths.join(','));

    try {
        const res = await ApiService.post(ApiService.Routes.Admin.GetFileContent, formData);

        if (loader) loader.classList.add('d-none');
        if (contentArea) contentArea.style.opacity = '1';

        if (res.success) {
            _currentFiles = res.files;
            
            // Si el índice activo está fuera de rango, resetear
            if (_activeFileIndex >= _currentFiles.length) _activeFileIndex = 0;

            renderTabs(tabsContainer);
            renderActiveContent();
        } else {
            showError(res.message);
        }

    } catch (e) {
        console.error(e);
        if (loader) loader.classList.add('d-none');
        showError('Error de conexión al cargar archivos.');
    }
}

function renderTabs(container) {
    if (!container) return;
    container.innerHTML = '';

    _currentFiles.forEach((file, index) => {
        const isActive = (index === _activeFileIndex);
        const tab = document.createElement('div');
        tab.className = `viewer-tab ${isActive ? 'active' : ''}`;
        
        let icon = 'description';
        if (file.filename.endsWith('.log')) icon = 'text_snippet';
        
        tab.innerHTML = `
            <span class="material-symbols-rounded">${icon}</span>
            <span>${file.filename}</span>
        `;
        
        tab.onclick = () => {
            _activeFileIndex = index;
            renderTabs(container); // Re-render para actualizar clases
            renderActiveContent();
        };

        container.appendChild(tab);
    });
}

function renderActiveContent() {
    const container = document.getElementById('file-content-container');
    if (!container || !_currentFiles[_activeFileIndex]) return;

    const file = _currentFiles[_activeFileIndex];
    let html = '';

    if (file.error) {
        html = `<div class="state-error" style="text-align:left;">Error: ${file.error}</div>`;
    } else {
        const contentEscaped = escapeHtml(file.content);
        const truncateWarning = file.is_truncated 
            ? `<div class="component-message component-message--warning mb-0" style="border-radius: 4px;">Archivo truncado por tamaño (${file.size}). Mostrando el final.</div>` 
            : '';
            
        html = `${truncateWarning}${contentEscaped}`;
    }

    container.innerHTML = html;
    container.scrollTop = container.scrollHeight; // Scroll al final
}

function showError(msg) {
    const errorBox = document.getElementById('viewer-error');
    if (errorBox) {
        errorBox.textContent = msg;
        errorBox.classList.remove('d-none');
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}