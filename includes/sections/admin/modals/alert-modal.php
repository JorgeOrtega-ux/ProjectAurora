<div class="component-modal-overlay" id="modal-system-alert" style="display: none;">
    <div class="component-modal" style="width: 500px;">
        <div class="component-modal-header">
            <span class="component-modal-title">Emitir Alerta Global</span>
            <div class="component-modal-close" data-action="close-modal">
                <span class="material-symbols-rounded">close</span>
            </div>
        </div>
        
        <div class="component-modal-body">
            <div class="form-group mb-3">
                <label class="form-label">Tipo de Alerta</label>
                <select id="alert-type-selector" class="form-input">
                    <option value="performance">Problemas de Rendimiento</option>
                    <option value="maintenance">Mantenimiento</option>
                    <option value="policy">Actualización de Políticas</option>
                </select>
            </div>

            <div id="block-performance" class="alert-config-block">
                <div class="form-group">
                    <label class="form-label">Mensaje</label>
                    <textarea id="perf-message" class="form-input" rows="3">Estamos experimentando problemas de rendimiento. Estamos trabajando para resolverlo lo antes posible.</textarea>
                </div>
            </div>

            <div id="block-maintenance" class="alert-config-block" style="display:none;">
                <div class="form-group mb-2">
                    <label class="form-label">Tipo de Mantenimiento</label>
                    <div class="radio-group">
                        <label><input type="radio" name="maint_type" value="scheduled" checked> Programado</label>
                        <label><input type="radio" name="maint_type" value="emergency"> Emergencia</label>
                    </div>
                </div>
                
                <div id="maint-scheduled-options">
                    <div class="form-group mb-2">
                        <label class="form-label">Inicio Programado</label>
                        <input type="datetime-local" id="maint-start-time" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Duración Estimada (Minutos)</label>
                        <input type="number" id="maint-duration" class="form-input" value="60">
                    </div>
                </div>

                <div id="maint-emergency-options" style="display:none;">
                     <div class="alert-warning-box mb-2">
                        <span class="material-symbols-rounded">warning</span>
                        Se notificará a los usuarios que el sistema podría cerrarse inminentemente.
                     </div>
                     <div class="form-group">
                        <label class="form-label">Hora de corte</label>
                        <input type="time" id="maint-emergency-time" class="form-input">
                    </div>
                </div>
            </div>

            <div id="block-policy" class="alert-config-block" style="display:none;">
                <div class="form-group mb-2">
                    <label class="form-label">Estado</label>
                    <div class="radio-group">
                        <label><input type="radio" name="policy_status" value="future" checked> Futura</label>
                        <label><input type="radio" name="policy_status" value="done"> Realizada</label>
                    </div>
                </div>

                <div class="form-group mb-2">
                    <label class="form-label">Documento</label>
                    <select id="policy-doc-type" class="form-input">
                        <option value="terms">Términos y Condiciones</option>
                        <option value="privacy">Política de Privacidad</option>
                        <option value="cookies">Cookies</option>
                    </select>
                </div>

                <div id="policy-future-options">
                    <div class="form-group mb-2">
                        <label class="form-label">Entra en vigor</label>
                        <input type="date" id="policy-effective-date" class="form-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Enlace a Documentación</label>
                    <input type="url" id="policy-link" class="form-input" placeholder="https://...">
                </div>
            </div>
        </div>

        <div class="component-modal-footer">
            <button class="component-button danger" id="btn-deactivate-alert" style="margin-right: auto; display: none;">Apagar Alerta Actual</button>
            <button class="component-button secondary" data-action="close-modal">Cancelar</button>
            <button class="component-button primary" id="btn-emit-alert">Emitir Alerta</button>
        </div>
    </div>
</div>