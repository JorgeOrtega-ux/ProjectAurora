<?php
// includes/modules/moduleCalendar.php

// Permite definir un ID dinámico antes de hacer el include, por defecto será 'moduleCalendarDefault'
$calendarModuleId = $calendarModuleId ?? 'moduleCalendarDefault';
?>
<div class="component-module component-module--display-overlay component-module--size-m component-module--dropdown-selector disabled" data-module="<?= htmlspecialchars($calendarModuleId) ?>" id="<?= htmlspecialchars($calendarModuleId) ?>">
    <div class="component-module-panel">
        <div class="pill-container"><div class="drag-handle"></div></div>
        <div class="component-module-panel-body component-module-panel-body--padded">
            <div class="calendar-header">
                <button type="button" class="component-button component-button--square-40" data-cal-action="prev-month"><span class="material-symbols-rounded">chevron_left</span></button>
                <div class="calendar-title" id="calendar-title-display" style="font-weight: 600;">Mes Año</div>
                <button type="button" class="component-button component-button--square-40" data-cal-action="next-month"><span class="material-symbols-rounded">chevron_right</span></button>
            </div>
            <div class="calendar-weekdays">
                <span>Do</span><span>Lu</span><span>Ma</span><span>Mi</span><span>Ju</span><span>Vi</span><span>Sa</span>
            </div>
            <div class="calendar-grid" id="calendar-days-grid"></div>
            <hr class="component-divider" style="margin: 16px 0;">
            <div class="calendar-time-picker">
                <div class="time-col">
                    <label>Hora</label>
                    <input type="number" min="0" max="23" id="cal-hour" class="time-input" value="12">
                </div>
                <span class="time-separator">:</span>
                <div class="time-col">
                    <label>Min</label>
                    <input type="number" min="0" max="59" id="cal-minute" class="time-input" value="00">
                </div>
                <span class="time-separator">:</span>
                <div class="time-col">
                    <label>Seg</label>
                    <input type="number" min="0" max="59" id="cal-second" class="time-input" value="00">
                </div>
            </div>
        </div>
        <div style="padding: 16px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 8px;">
            <button type="button" class="component-button" data-action="close-calendar">Cancelar</button>
            <button type="button" class="component-button primary" data-action="apply-calendar">Aceptar</button>
        </div>
    </div>
</div>