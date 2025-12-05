// public/assets/js/modules/admin/admin-diagnostics.js

import { ApiService } from '../../services/api-service.js';
import { AlertManager } from '../../ui/alert-manager.js';

export function init() {
    console.log('Admin Diagnostics Module Initialized');
    
    // Cargar estado inicial de Redis
    loadRedisStatus();

    // Event Listeners
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
        const response = await ApiService.post('admin_handler.php', {
            action: 'get_redis_status'
        });

        if (response.success && response.connected) {
            // Conexión exitosa
            indicator.innerHTML = `
                <span class="status-dot dot-green"></span> 
                <span style="font-weight: 600; color: #2e7d32;">Conectado y Operativo</span>
            `;
            
            // Mostrar contenido, ocultar error
            contentArea.style.display = 'block';
            errorBox.style.display = 'none';

            // Renderizar claves
            const keys = response.keys || [];
            countSpan.textContent = keys.length;

            if (keys.length > 0) {
                btnClear.style.display = 'inline-flex';
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
                        previewHtml = `<div class="code-block">${previewText}</div>`;
                    }

                    html += `
                        <div class="redis-item">
                            <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600;">
                                <span style="color:#333;">${escapeHtml(item.key)}</span>
                                <span class="component-badge component-badge--neutral">${item.count} msgs</span>
                            </div>
                            ${previewHtml}
                        </div>
                    `;
                });
                listContainer.innerHTML = html;
            } else {
                btnClear.style.display = 'none';
                listContainer.innerHTML = `
                    <p style="text-align:center; color:#999; font-size:13px; padding:20px;">
                        El buffer está vacío. Todos los mensajes han sido procesados a MySQL.
                    </p>
                `;
            }

        } else {
            // Redis no conectado o error controlado
            throw new Error(response.msg || 'Error desconocido de Redis');
        }

    } catch (error) {
        console.error('Redis status error:', error);
        indicator.innerHTML = `
            <span class="status-dot dot-red"></span> 
            <span style="font-weight: 600; color: #c62828;">Error de Conexión</span>
        `;
        contentArea.style.display = 'none';
        errorBox.style.display = 'block';
        document.getElementById('redis-error-msg').textContent = error.message;
    }
}

/**
 * Confirmar y ejecutar limpieza de Redis
 */
async function confirmClearRedis() {
    if (!confirm('¿Seguro que quieres eliminar TODOS los mensajes en memoria (Redis)? Esta acción no se puede deshacer.')) {
        return;
    }

    try {
        const response = await ApiService.post('admin_handler.php', {
            action: 'clear_redis'
        });

        if (response.success) {
            AlertManager.show('success', `Memoria limpiada: ${response.count} colas eliminadas.`);
            loadRedisStatus(); // Recargar la vista
        } else {
            AlertManager.show('error', response.message || 'Error al limpiar Redis');
        }
    } catch (error) {
        AlertManager.show('error', 'Error de red al intentar limpiar Redis.');
    }
}

/**
 * Ejecutar prueba del puente Socket
 */
async function testBridgeConnection() {
    const btn = document.getElementById('btn-test-bridge');
    const resultContainer = document.getElementById('bridge-result-container');
    const resultBox = document.getElementById('bridge-result-box');
    const title = document.getElementById('bridge-result-title');
    const msg = document.getElementById('bridge-result-msg');
    const hint = document.getElementById('bridge-result-hint');

    // Estado Loading
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded loader-spin">sync</span> Probando...';
    resultContainer.style.display = 'none';

    try {
        const response = await ApiService.post('admin_handler.php', {
            action: 'test_bridge'
        });

        resultContainer.style.display = 'block';
        
        if (response.success) {
            resultBox.style.backgroundColor = '#e8f5e9';
            resultBox.style.borderColor = '#c8e6c9';
            resultBox.style.color = '#2e7d32';
            title.textContent = 'Señal Enviada Correctamente';
            msg.textContent = response.message; // Mensaje del servidor
            hint.textContent = '💡 Si tienes el chat abierto en otra pestaña, deberías ver una notificación global ahora mismo.';
            
            AlertManager.show('success', 'Prueba de puente exitosa');
        } else {
            throw new Error(response.message);
        }

    } catch (error) {
        resultContainer.style.display = 'block';
        resultBox.style.backgroundColor = '#ffebee';
        resultBox.style.borderColor = '#ffcdd2';
        resultBox.style.color = '#c62828';
        title.textContent = 'Error de Conexión';
        msg.textContent = error.message || 'No se pudo conectar al socket.';
        hint.textContent = '💡 Verifica que socket-connect.py esté ejecutándose en el puerto 8081.';
        
        AlertManager.show('error', 'Falló la prueba del puente');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

/**
 * Utilidad para escapar HTML y prevenir inyección en la vista previa
 */
function escapeHtml(text) {
    if (!text) return text;
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}