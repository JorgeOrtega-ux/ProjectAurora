(function() {
    const API_ADMIN = (window.BASE_PATH || '/ProjectAurora/') + 'api/admin_handler.php';
    const targetId = document.getElementById('history-target-id')?.value;
    const historyBody = document.getElementById('full-history-body');

    if (!targetId) return;

    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    }

    async function loadHistoryData() {
        try {
            const res = await fetch(API_ADMIN, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
                body: JSON.stringify({ action: 'get_user_details', target_id: targetId })
            });
            const data = await res.json();
            
            if(data.success) {
                const u = data.user;
                
                // Cargar Header
                document.getElementById('history-username').textContent = u.username;
                document.getElementById('history-email').textContent = u.email;
                const container = document.getElementById('history-avatar-container');
                if (container) container.dataset.role = u.role;

                if(u.avatar) {
                    const img = document.getElementById('history-user-avatar');
                    img.src = (window.BASE_PATH || '/ProjectAurora/') + u.avatar;
                    img.style.display = 'block';
                    document.getElementById('history-user-icon').style.display = 'none';
                }

                // Cargar Tabla
                if (data.history && data.history.length > 0) {
                    renderFullHistory(data.history);
                } else {
                    historyBody.innerHTML = `
                        <tr>
                            <td colspan="6" class="component-table-empty">
                                <span class="material-symbols-rounded component-table-empty-icon">history_toggle_off</span>
                                <p>Este usuario no tiene registros de sanciones previas.</p>
                            </td>
                        </tr>`;
                }
            }
        } catch(e) {
            console.error(e);
            historyBody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#d32f2f; padding:20px;">Error cargando historial.</td></tr>';
        }
    }

    function renderFullHistory(logs) {
        let html = '';
        logs.forEach(log => {
            const start = new Date(log.started_at).toLocaleString();
            const adminName = log.admin_name ? log.admin_name : 'Sistema';
            
            let durationDisplay = '';
            let endDisplay = '';

            if (parseInt(log.duration_days) === -1) {
                durationDisplay = '<span style="color: #d32f2f; font-weight: 600;">Permanente</span>';
                endDisplay = 'Indefinido';
            } else {
                durationDisplay = log.duration_days + ' días';
                if (log.ends_at) {
                    endDisplay = new Date(log.ends_at).toLocaleString();
                } else {
                    endDisplay = '-';
                }
            }

            let liftedDisplay = '<span class="component-badge component-badge--neutral">Cumplida / Expirada</span>';
            
            if (log.lifted_at) {
                const liftedDate = new Date(log.lifted_at).toLocaleString();
                const lifter = log.lifter_name ? log.lifter_name : 'Admin';
                liftedDisplay = `
                    <div style="display:flex; flex-direction:column;">
                        <span style="color:#2e7d32; font-weight:600; font-size:13px; display:flex; align-items:center; gap:4px;">
                            <span class="material-symbols-rounded" style="font-size:16px;">check_circle</span> 
                            Levantada el ${liftedDate}
                        </span>
                        <span style="color:#888; font-size:11px; margin-left:20px;">por ${lifter}</span>
                    </div>
                `;
            } else {
                // Verificar si sigue activa (solo visual)
                const now = new Date();
                const endDate = log.ends_at ? new Date(log.ends_at) : null;
                
                if (parseInt(log.duration_days) === -1) {
                     liftedDisplay = '<span class="component-badge component-badge--danger">Activa (Permanente)</span>';
                } else if (endDate && endDate > now) {
                     liftedDisplay = '<span class="component-badge component-badge--danger">Activa</span>';
                }
            }

            html += `
                <tr class="component-table-row">
                    <td>${start}</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span class="material-symbols-rounded" style="color:#666; font-size:18px;">gavel</span>
                            ${log.reason}
                        </div>
                    </td>
                    <td>${durationDisplay}</td>
                    <td>${endDisplay}</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span class="material-symbols-rounded" style="font-size:16px; color:#666;">security</span>
                            <span style="font-weight:500;">${adminName}</span>
                        </div>
                    </td>
                    <td>${liftedDisplay}</td>
                </tr>`;
        });
        historyBody.innerHTML = html;
    }

    loadHistoryData();
})();