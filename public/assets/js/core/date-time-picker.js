/**
 * public/assets/js/core/date-time-picker.js
 * Componente de Calendario y Hora Profesional para Project Aurora
 */

export class DateTimePicker {
    constructor(wrapperId, inputId, options = {}) {
        this.wrapper = document.getElementById(wrapperId);
        this.input = document.getElementById(inputId); // El input hidden
        this.options = {
            enableTime: true,
            minDate: new Date(), // Por defecto no permite pasado
            format: 'YYYY-MM-DDTHH:mm', // Formato para el value del input
            displayFormat: { date: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' },
            ...options
        };

        if (!this.wrapper || !this.input) return;

        // Estado interno
        this.currentDate = new Date(); // Fecha navegada (mes/año visible)
        this.selectedDate = null;      // Fecha seleccionada
        
        this.init();
    }

    init() {
        this.renderDOM();
        this.bindEvents();
        
        // Si el input ya tiene valor, lo cargamos
        if (this.input.value) {
            // Intentar parsear la fecha (soporta ISO strings)
            const d = new Date(this.input.value);
            if (!isNaN(d.getTime())) {
                this.selectedDate = d;
                this.currentDate = new Date(this.selectedDate);
                this.updateTriggerDisplay();
            }
        }
        
        // Render inicial
        if (!this.selectedDate) {
             this.wrapper.querySelector('.trigger-select-text').textContent = "Seleccionar fecha...";
        }
        this.renderCalendar();
    }

    renderDOM() {
        // Estructura del popover
        const popoverHTML = `
            <div class="calendar-popover">
                <div class="calendar-header">
                    <button type="button" class="calendar-nav-btn" data-action="prev-month">
                        <span class="material-symbols-rounded">chevron_left</span>
                    </button>
                    <div class="calendar-current-period">
                        <span class="calendar-month-label"></span>
                        <input type="number" class="calendar-year-input" min="2024" max="2030">
                    </div>
                    <button type="button" class="calendar-nav-btn" data-action="next-month">
                        <span class="material-symbols-rounded">chevron_right</span>
                    </button>
                </div>
                
                <div class="calendar-weekdays">
                    <span>Do</span><span>Lu</span><span>Ma</span><span>Mi</span><span>Ju</span><span>Vi</span><span>Sa</span>
                </div>
                
                <div class="calendar-grid"></div>
                
                ${this.options.enableTime ? `
                <div class="calendar-time-footer">
                    <div class="time-picker-group">
                        <span class="material-symbols-rounded">schedule</span>
                        <input type="number" class="time-input hour" min="0" max="23" placeholder="HH">
                        <span class="time-separator">:</span>
                        <input type="number" class="time-input minute" min="0" max="59" placeholder="MM">
                    </div>
                    <button type="button" class="calendar-confirm-btn">Aplicar</button>
                </div>
                ` : ''}
            </div>
        `;

        // Inyectar el popover dentro del wrapper (que debe tener position: relative)
        this.wrapper.insertAdjacentHTML('beforeend', popoverHTML);

        // Referencias a elementos creados
        this.popover = this.wrapper.querySelector('.calendar-popover');
        this.grid = this.wrapper.querySelector('.calendar-grid');
        this.monthLabel = this.wrapper.querySelector('.calendar-month-label');
        this.yearInput = this.wrapper.querySelector('.calendar-year-input');
        
        if (this.options.enableTime) {
            this.hourInput = this.wrapper.querySelector('.time-input.hour');
            this.minuteInput = this.wrapper.querySelector('.time-input.minute');
            
            // Valores por defecto hora actual o la seleccionada
            const now = this.selectedDate || new Date();
            this.hourInput.value = String(now.getHours()).padStart(2, '0');
            this.minuteInput.value = String(now.getMinutes()).padStart(2, '0');
        }
    }

    renderCalendar() {
        this.grid.innerHTML = '';
        
        // Actualizar Header
        const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
        this.monthLabel.textContent = monthNames[this.currentDate.getMonth()];
        this.yearInput.value = this.currentDate.getFullYear();

        // Calcular días
        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        
        const firstDayOfMonth = new Date(year, month, 1).getDay(); // 0 (Domingo) - 6
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        
        // Preparar fecha mínima (resetear horas para comparar solo días)
        let minDateTimestamp = 0;
        if (this.options.minDate) {
            const m = new Date(this.options.minDate);
            m.setHours(0, 0, 0, 0);
            minDateTimestamp = m.getTime();
        }

        // Rellenar espacios vacíos al inicio
        for (let i = 0; i < firstDayOfMonth; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'calendar-day empty';
            this.grid.appendChild(emptyCell);
        }

        // Crear días
        const today = new Date();
        today.setHours(0,0,0,0);

        for (let day = 1; day <= daysInMonth; day++) {
            const dateCell = document.createElement('div');
            dateCell.className = 'calendar-day';
            dateCell.textContent = day;
            
            // Fecha que representa esta celda
            const cellDate = new Date(year, month, day);
            const cellTimestamp = cellDate.getTime();

            // Estilos: Hoy
            if (cellDate.toDateString() === today.toDateString()) {
                dateCell.classList.add('today');
            }

            // Estilos: Seleccionado
            if (this.selectedDate && cellDate.toDateString() === this.selectedDate.toDateString()) {
                dateCell.classList.add('selected');
            }

            // Lógica de deshabilitado (Días pasados)
            // Usamos 'is-disabled' en lugar de 'disabled' para evitar que CSS globales lo oculten
            if (minDateTimestamp > 0 && cellTimestamp < minDateTimestamp) {
                dateCell.classList.add('is-disabled');
                // IMPORTANTE: No asignamos onclick
            } else {
                dateCell.onclick = () => this.selectDate(day);
            }

            this.grid.appendChild(dateCell);
        }
    }

    bindEvents() {
        const trigger = this.wrapper.querySelector('.trigger-selector');
        
        // Toggle Popover
        trigger.onclick = (e) => {
            e.stopPropagation();
            this.closeAllPopovers(); // Cerrar otros
            this.wrapper.classList.toggle('active');
            this.popover.classList.toggle('active');
        };

        // Navegación Mes
        this.wrapper.querySelector('[data-action="prev-month"]').onclick = (e) => {
            e.stopPropagation();
            this.currentDate.setMonth(this.currentDate.getMonth() - 1);
            this.renderCalendar();
        };

        this.wrapper.querySelector('[data-action="next-month"]').onclick = (e) => {
            e.stopPropagation();
            this.currentDate.setMonth(this.currentDate.getMonth() + 1);
            this.renderCalendar();
        };

        // Cambio Año Input
        this.yearInput.onchange = (e) => {
            this.currentDate.setFullYear(parseInt(e.target.value));
            this.renderCalendar();
        };

        // Confirmar Hora y Cerrar
        if (this.options.enableTime) {
            const btn = this.wrapper.querySelector('.calendar-confirm-btn');
            btn.onclick = (e) => {
                e.stopPropagation();
                this.updateValue();
                this.close();
            };
            
            // Validar inputs de hora
            [this.hourInput, this.minuteInput].forEach(inp => {
                inp.onchange = () => {
                    let val = parseInt(inp.value);
                    if (isNaN(val)) val = 0;
                    if (val < 0) val = 0;
                    const max = inp.classList.contains('hour') ? 23 : 59;
                    if (val > max) val = max;
                    inp.value = String(val).padStart(2, '0');
                    // Actualizar en tiempo real si ya hay fecha seleccionada
                    if (this.selectedDate) this.updateValue();
                };
            });
        }

        // Click fuera para cerrar
        document.addEventListener('click', (e) => {
            if (!this.wrapper.contains(e.target)) {
                this.close();
            }
        });
    }

    selectDate(day) {
        // Establecer fecha seleccionada
        if (!this.selectedDate) this.selectedDate = new Date();
        
        this.selectedDate.setFullYear(this.currentDate.getFullYear());
        this.selectedDate.setMonth(this.currentDate.getMonth());
        this.selectedDate.setDate(day);

        // Si no hay hora, preservar la que tenga o usar 00:00
        if (this.options.enableTime) {
            const h = parseInt(this.hourInput.value) || 0;
            const m = parseInt(this.minuteInput.value) || 0;
            this.selectedDate.setHours(h, m, 0, 0);
        } else {
             this.selectedDate.setHours(0, 0, 0, 0);
        }

        this.updateValue();
        this.renderCalendar(); // Para refrescar la clase .selected
        
        // Si no es selector de hora, cerrar al elegir fecha
        if (!this.options.enableTime) {
            this.close();
        }
    }

    updateValue() {
        if (!this.selectedDate) return;

        // Actualizar horas en el objeto fecha
        if (this.options.enableTime) {
             const h = parseInt(this.hourInput.value);
             const m = parseInt(this.minuteInput.value);
             this.selectedDate.setHours(h, m);
        }

        // Formato ISO para el input hidden: YYYY-MM-DDTHH:mm
        const isoString = this.toLocalISOString(this.selectedDate);
        this.input.value = isoString;
        
        // Disparar evento 'input' para que el controlador lo detecte
        this.input.dispatchEvent(new Event('input'));
        
        // Disparar callback de opciones si existe
        if (typeof this.options.onChange === 'function') {
            this.options.onChange([this.selectedDate], isoString, this);
        }

        this.updateTriggerDisplay();
    }

    updateTriggerDisplay() {
        if (!this.selectedDate) return;
        const textEl = this.wrapper.querySelector('.trigger-select-text');
        
        // Formato legible
        const options = { day: 'numeric', month: 'short', year: 'numeric' };
        if (this.options.enableTime) {
            options.hour = '2-digit';
            options.minute = '2-digit';
        }
        textEl.textContent = this.selectedDate.toLocaleString('es-MX', options);
        textEl.style.color = 'var(--text-primary)';
    }

    toLocalISOString(date) {
        const pad = (n) => n < 10 ? '0' + n : n;
        const d = date.getDate();
        const m = date.getMonth() + 1;
        const y = date.getFullYear();
        const h = date.getHours();
        const min = date.getMinutes();
        
        let str = `${y}-${pad(m)}-${pad(d)}`;
        if (this.options.enableTime) {
            str += `T${pad(h)}:${pad(min)}`;
        }
        return str;
    }

    close() {
        this.wrapper.classList.remove('active');
        this.popover.classList.remove('active');
    }
    
    closeAllPopovers() {
        document.querySelectorAll('.calendar-popover.active').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.date-time-picker-wrapper.active').forEach(el => el.classList.remove('active'));
    }
}