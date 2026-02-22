// public/assets/js/components/calendar-controller.js
import { Toast } from './toast-controller.js';

export class CalendarController {
    constructor() {
        this.currentDate = new Date();
        this.selectedDate = new Date();
        this.activeInputTarget = null;
        this.activeDisplayTarget = null;
        this.activeModuleId = null;

        this.init();
    }

    init() {
        // --- EVENTO CAPTURADO ---
        document.body.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-calendar-trigger="true"]');
            if (trigger) {
                this.openCalendar(trigger);
            }
        }, true); 

        // --- EVENTOS NORMALES ---
        document.body.addEventListener('click', (e) => {
            // 1. Navegación de meses
            const prevBtn = e.target.closest('[data-cal-action="prev-month"]');
            if (prevBtn) {
                e.preventDefault();
                e.stopPropagation(); 
                if (prevBtn.disabled) return; // Bloqueo de seguridad si está deshabilitado
                
                this.currentDate.setMonth(this.currentDate.getMonth() - 1);
                this.renderCalendar();
                return;
            }

            const nextBtn = e.target.closest('[data-cal-action="next-month"]');
            if (nextBtn) {
                e.preventDefault();
                e.stopPropagation(); 
                this.currentDate.setMonth(this.currentDate.getMonth() + 1);
                this.renderCalendar();
                return;
            }

            // 2. Selección de un día
            const dayBtn = e.target.closest('.calendar-day:not(.disabled)');
            if (dayBtn) {
                e.preventDefault();
                e.stopPropagation(); 
                                     
                const day = parseInt(dayBtn.dataset.day);
                const month = parseInt(dayBtn.dataset.month);
                const year = parseInt(dayBtn.dataset.year);
                
                this.selectedDate.setFullYear(year);
                this.selectedDate.setMonth(month);
                this.selectedDate.setDate(day);
                
                this.renderCalendar(); 
                return;
            }

            // 3. Aceptar (Aplicar fecha y hora)
            const applyBtn = e.target.closest('[data-action="apply-calendar"]');
            if (applyBtn) {
                e.preventDefault();
                this.applySelection();
                return;
            }

            // 4. Cancelar / Cerrar
            const closeBtn = e.target.closest('[data-action="close-calendar"]');
            if (closeBtn) {
                e.preventDefault();
                this.closeCalendar();
                return;
            }
        });

        // 5. Validar inputs de tiempo
        document.body.addEventListener('change', (e) => {
            if (e.target.id === 'cal-hour') {
                let v = parseInt(e.target.value);
                if (isNaN(v) || v < 0) v = 0;
                if (v > 23) v = 23;
                e.target.value = String(v).padStart(2, '0');
            }
            if (e.target.id === 'cal-minute' || e.target.id === 'cal-second') {
                let v = parseInt(e.target.value);
                if (isNaN(v) || v < 0) v = 0;
                if (v > 59) v = 59;
                e.target.value = String(v).padStart(2, '0');
            }
        });
    }

    openCalendar(trigger) {
        this.activeInputTarget = trigger.dataset.inputTarget;
        this.activeDisplayTarget = trigger.dataset.displayTarget;
        this.activeModuleId = trigger.dataset.target;

        const input = document.getElementById(this.activeInputTarget);
        if (input && input.value) {
            let val = input.value.replace(' ', 'T');
            const d = new Date(val);
            if (!isNaN(d.getTime())) {
                this.selectedDate = new Date(d);
                this.currentDate = new Date(d);
            } else {
                this.selectedDate = new Date();
                this.currentDate = new Date();
            }
        } else {
            this.selectedDate = new Date();
            this.currentDate = new Date();
        }

        const hourInput = document.getElementById('cal-hour');
        const minInput = document.getElementById('cal-minute');
        const secInput = document.getElementById('cal-second');

        if (hourInput) hourInput.value = String(this.selectedDate.getHours()).padStart(2, '0');
        if (minInput) minInput.value = String(this.selectedDate.getMinutes()).padStart(2, '0');
        if (secInput) secInput.value = String(this.selectedDate.getSeconds()).padStart(2, '0');

        this.renderCalendar();
    }

    renderCalendar() {
        const grid = document.getElementById('calendar-days-grid');
        const title = document.getElementById('calendar-title-display');
        if (!grid || !title) return;

        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();

        const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        title.textContent = `${monthNames[month]} ${year}`;

        grid.innerHTML = '';

        const firstDayOfMonth = new Date(year, month, 1).getDay(); 
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();

        const today = new Date();
        const todayDateOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());

        // LÓGICA: Deshabilitar el botón de "Mes Anterior" si estamos en el mes/año actual
        if (this.activeModuleId) {
            const module = document.getElementById(this.activeModuleId);
            if (module) {
                const prevBtn = module.querySelector('[data-cal-action="prev-month"]');
                if (prevBtn) {
                    if (year < today.getFullYear() || (year === today.getFullYear() && month <= today.getMonth())) {
                        prevBtn.disabled = true;
                        prevBtn.style.opacity = '0.3';
                        prevBtn.style.cursor = 'not-allowed';
                    } else {
                        prevBtn.disabled = false;
                        prevBtn.style.opacity = '1';
                        prevBtn.style.cursor = 'pointer';
                    }
                }
            }
        }
        
        // Rellenar días del mes anterior
        for (let i = firstDayOfMonth - 1; i >= 0; i--) {
            const dayNum = daysInPrevMonth - i;
            const classes = ['other-month'];
            
            // Evaluar si es un día pasado
            const cellDate = new Date(year, month - 1, dayNum);
            if (cellDate < todayDateOnly) classes.push('disabled');

            grid.appendChild(this.createDayElement(dayNum, month - 1, year, classes));
        }

        // Rellenar días del mes actual
        for (let i = 1; i <= daysInMonth; i++) {
            const classes = [];
            const cellDate = new Date(year, month, i);
            
            if (year === today.getFullYear() && month === today.getMonth() && i === today.getDate()) {
                classes.push('today');
            }
            
            if (year === this.selectedDate.getFullYear() && month === this.selectedDate.getMonth() && i === this.selectedDate.getDate()) {
                classes.push('selected');
            }

            // Evaluar si es un día pasado
            if (cellDate < todayDateOnly) classes.push('disabled');

            grid.appendChild(this.createDayElement(i, month, year, classes));
        }

        // Rellenar días del mes siguiente
        const totalCellsRendered = firstDayOfMonth + daysInMonth;
        const remainingCells = 42 - totalCellsRendered;
        for (let i = 1; i <= remainingCells; i++) {
            const classes = ['other-month'];
            
            // Evaluar si es un día pasado (raro para el mes siguiente, pero protege contra cambios de año)
            const cellDate = new Date(year, month + 1, i);
            if (cellDate < todayDateOnly) classes.push('disabled');

            grid.appendChild(this.createDayElement(i, month + 1, year, classes));
        }
    }

    createDayElement(day, month, year, extraClasses = []) {
        let d = new Date(year, month, day);
        let finalDay = d.getDate();
        let finalMonth = d.getMonth();
        let finalYear = d.getFullYear();

        const btn = document.createElement('button');
        btn.className = `calendar-day ${extraClasses.join(' ')}`;
        btn.textContent = finalDay;
        btn.dataset.day = finalDay;
        btn.dataset.month = finalMonth;
        btn.dataset.year = finalYear;
        return btn;
    }

    applySelection() {
        const hourInput = document.getElementById('cal-hour');
        const minInput = document.getElementById('cal-minute');
        const secInput = document.getElementById('cal-second');

        if (hourInput) this.selectedDate.setHours(parseInt(hourInput.value) || 0);
        if (minInput) this.selectedDate.setMinutes(parseInt(minInput.value) || 0);
        if (secInput) this.selectedDate.setSeconds(parseInt(secInput.value) || 0);

        // VALIDACIÓN FRONTEND: Bloquear si la hora seleccionada está en el pasado
        const now = new Date();
        if (this.selectedDate <= now) {
            Toast.show('La fecha y hora de suspensión deben ser en el futuro.', 'error');
            return; // Abortar cierre, obligar al admin a corregir
        }

        const input = document.getElementById(this.activeInputTarget);
        const display = document.getElementById(this.activeDisplayTarget);

        if (input) {
            input.value = this.formatValueDate(this.selectedDate);
            const event = new Event('change', { bubbles: true });
            input.dispatchEvent(event);
        }

        if (display) {
            display.textContent = this.formatDisplayDate(this.selectedDate);
        }

        this.closeCalendar();
    }

    closeCalendar() {
        if (this.activeModuleId) {
            const module = document.getElementById(this.activeModuleId);
            if (module) {
                module.classList.add('disabled');
                const panel = module.querySelector('.component-module-panel');
                if (panel) panel.style.transform = '';
            }
        }
    }

    formatValueDate(d) {
        const pad = (n) => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    }

    formatDisplayDate(d) {
        const pad = (n) => String(n).padStart(2, '0');
        const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return `${pad(d.getDate())} ${monthNames[d.getMonth()]} ${d.getFullYear()}, ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    }
}