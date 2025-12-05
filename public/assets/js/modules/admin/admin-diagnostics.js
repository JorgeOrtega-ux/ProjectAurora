// public/assets/js/modules/admin/admin-diagnostics.js
import { AdminApi } from '../../services/api-service.js';
import { showAlert } from '../../ui/alert-manager.js';

export function init() {
    console.log('Admin Diagnostics Module Initialized');
    
    // 1. Inicializar lógica de acordeones (CON FIX DE INTERFERENCIA)
    setupAccordionBehavior();

    // 2. Cargar estado inicial de Redis
    loadRedisStatus();

    // 3. Event Listeners de Botones
    const btnClear = document.getElementById('btn-clear-redis');
    if (btnClear) {
        btnClear.addEventListener('click', confirmClearRedis);
    }

    const btnTestBridge = document.getElementById('btn-test-bridge');
    if (btnTestBridge) {
        btnTestBridge.addEventListener('click', testBridgeConnection);
    }
}

/**
 * Configura el comportamiento de los acordeones
 * IMPORTANTE: Usa stopPropagation para evitar conflicto con admin-server.js
 */
function setupAccordionBehavior() {
    const container = document.querySelector('.section-content[data-section="admin/diagnostics"]');
    if (!container) return;

    // Usamos 'click' en el contenedor específico de esta sección
    container.addEventListener('click', (e) => {
        // Buscar si el click fue en un header de acordeón
        const header = e.target.closest('[data-action="toggle-accordion"]');
        
        if (header) {
            // [CRÍTICO] Detener propagación para que admin-server.js no reciba el evento
            // y cause un "doble toggle" (abrir y cerrar instantáneamente).
            e.stopPropagation();
            e.preventDefault();

            const currentAccordion = header.closest('.component-accordion');
            if (currentAccordion) {
                // Cerrar otros acordeones abiertos dentro de este contenedor
                const allActive = container.querySelectorAll('.component-accordion.active');
                allActive.forEach(acc => {
                    if (acc !== currentAccordion) {
                        acc.classList.remove('active');
                    }
                });
                // Alternar el actual
                currentAccordion.classList.toggle('active');
            }
        }
    });
}

/**
 * Carga el estado de Redis y la lista de claves desde la API
 */
async function loadRedisStatus() {
    const listContainer = document.getElementById('redis-keys-list');
    const indicator = document.getElementById('redis-status-indicator');
    const countSpan = document.getElementById('redis-count');
    const btnClear = document.getElementById('btn-clear-redis');
    const errorBox = document.getElementById('redis-error-box');
    const contentArea = document.getElementById('redis-content-area');

    try {
        const response = await AdminApi.getRedisStatus();

        if (response.success && response.connected) {
            indicator.innerHTML = `
                <span class="status-dot dot-green"></span> 
                <span style="font-weight: 600; color: #2e7d32;">Conectado y Operativo</span>
            `;
            
            if (contentArea) contentArea.style.display = 'block';
            if (errorBox) errorBox.style.display = 'none';

            const keys = response.keys || [];
            if (countSpan) countSpan.textContent = keys.length;

            if (keys.length > 0) {
                if (btnClear) btnClear.style.display = 'inline-flex';
                let html = '';
                
                keys.forEach(item => {
                    let previewHtml = '';
                    if (item.preview && Array.isArray(item.preview)) {
                        const previewText = item.preview.map(jsonStr => {
                            try {
                                const data = JSON.parse(jsonStr);
                                return `${data.sender_username || '??'}: ${data.message || '[Sin texto]'}`;
                            } catch (e) {
                                return '[Error decodificando mensaje]';
                            }
                        }).join('\n');
                        previewHtml = `<div class="code-block">${escapeHtml(previewText)}</div>`;
                    }

                    html += `
                        <div class="redis-item">
                            <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600; margin-bottom:4px;">
                                <span style="color:#333;">${escapeHtml(item.key)}</span>
                                <span class="component-badge component-badge--neutral">${item.count} msgs</span>
                            </div>
                            ${previewHtml}
                        </div>
                    `;
                });
                if (listContainer) listContainer.innerHTML = html;
            } else {
                if (btnClear) btnClear.style.display = 'none';
                if (listContainer) listContainer.innerHTML = `
                    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:30px; color:#999;">
                        <span class="material-symbols-rounded" style="font-size:32px; margin-bottom:10px; color:#ddd;">inbox</span>
                        <p style="font-size:13px; margin:0;">El buffer está vacío.</p>
                    </div>
                `;
            }

        } else {
            throw new Error(response.msg || 'Error desconocido de Redis');
        }

    } catch (error) {
        console.error('Redis status error:', error);
        if (indicator) indicator.innerHTML = `
            <span class="status-dot dot-red"></span> 
            <span style="font-weight: 600; color: #c62828;">Error de Conexión</span>
        `;
        if (errorBox) {
            errorBox.style.display = 'block';
            const msgEl = document.getElementById('redis-error-msg');
            if (msgEl) msgEl.textContent = error.message;
        }
    }
}

async function confirmClearRedis() {
    if (!confirm('¿Seguro que quieres eliminar TODOS los mensajes en memoria (Redis)? Esta acción no se puede deshacer.')) {
        return;
    }
    try {
        const response = await AdminApi.clearRedis();
        if (response.success) {
            showAlert(`Memoria limpiada: ${response.count} colas eliminadas.`, 'success');
            loadRedisStatus();
        } else {
            showAlert(response.message || 'Error al limpiar Redis', 'error');
        }
    } catch (error) {
        showAlert('Error de red al intentar limpiar Redis.', 'error');
    }
}

async function testBridgeConnection() {
    const btn = document.getElementById('btn-test-bridge');
    const resultContainer = document.getElementById('bridge-result-container');
    const resultBox = document.getElementById('bridge-result-box');
    const title = document.getElementById('bridge-result-title');
    const msg = document.getElementById('bridge-result-msg');
    const hint = document.getElementById('bridge-result-hint');
    const icon = document.getElementById('bridge-icon');

    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded loader-spin">sync</span> Probando...';
    resultContainer.style.display = 'none';

    try {
        const response = await AdminApi.testBridge();
        resultContainer.style.display = 'block';
        
        if (response.success) {
            resultBox.style.backgroundColor = '#e8f5e9';
            resultBox.style.borderColor = '#c8e6c9';
            resultBox.style.color = '#2e7d32';
            if(icon) icon.textContent = 'check_circle';
            
            title.textContent = 'Señal Enviada Correctamente';
            msg.textContent = response.message; 
            hint.textContent = '💡 Deberías ver una notificación global ahora mismo.';
            showAlert('Prueba de puente exitosa', 'success');
        } else {
            throw new Error(response.message);
        }

    } catch (error) {
        resultContainer.style.display = 'block';
        resultBox.style.backgroundColor = '#ffebee';
        resultBox.style.borderColor = '#ffcdd2';
        resultBox.style.color = '#c62828';
        if(icon) icon.textContent = 'error';

        title.textContent = 'Error de Conexión';
        msg.textContent = error.message || 'No se pudo conectar al socket.';
        hint.textContent = '💡 Verifica que socket-connect.py esté ejecutándose en el puerto 8081.';
        showAlert('Falló la prueba del puente', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function escapeHtml(text) {
    if (!text) return text;
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}