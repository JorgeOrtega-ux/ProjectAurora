<?php
// includes/sections/studio/content.php
?>
<div class="studio-module-wrapper">
    
    <div class="studio-module-top">
        <h2 class="component-toolbar-title"><?php echo $i18n->t('studio.title_content'); ?></h2>
        
        <div class="header-button" data-tooltip="Subir videos">
            <span class="material-symbols-rounded">upload</span>
        </div>
    </div>

    <div class="studio-module-bottom">
        <div class="component-table-wrapper component-table-scrollable">
            <table class="component-table">
                <thead class="component-table-sticky-header">
                    <tr>
                        <th style="min-width: 300px; padding-left: 24px;">Video</th>
                        <th>Visibilidad</th>
                        <th>Restricciones</th>
                        <th>Fecha</th>
                        <th class="text-right">Vistas</th>
                        <th class="text-right">Comentarios</th>
                        <th class="text-right" style="padding-right: 24px;">"Me gusta" (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7">
                            <div class="state-empty" style="padding: 60px 0;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-secondary); opacity: 0.6;">
                                    <span class="material-symbols-rounded" style="font-size: 40px; margin-bottom: 12px;">movie</span>
                                    <span style="font-size: 15px;">No se ha encontrado contenido.</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>