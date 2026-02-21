// public/assets/js/components/calendar-controller.js

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
        // --- EVENTO CAPTURADO (Crucial para esquivar la detención de propagación de MainController al abrir) ---
        document.body.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-calendar-trigger="true"]');
            if (trigger) {
                // Al detectar el trigger, abrimos internamente el calendario y sus datos.
                this.openCalendar(trigger);
            }
        }, true); // <-- El true significa fase de captura, se ejecuta primero.

        // --- EVENTOS NORMALES (Navegación y botones internos del calendario) ---
        document.body.addEventListener('click', (e) => {
            // 1. Navegación de meses
            const prevBtn = e.target.closest('[data-cal-action="prev-month"]');
            if (prevBtn) {
                e.preventDefault();
                e.stopPropagation(); // FIX: Prevenir que MainController detecte un clic fuera al repintar el DOM
                this.currentDate.setMonth(this.currentDate.getMonth() - 1);
                this.renderCalendar();
                return;
            }

            const nextBtn = e.target.closest('[data-cal-action="next-month"]');
            if (nextBtn) {
                e.preventDefault();
                e.stopPropagation(); // FIX: Prevenir que MainController cierre el módulo
                this.currentDate.setMonth(this.currentDate.getMonth() + 1);
                this.renderCalendar();
                return;
            }

            // 2. Selección de un día
            const dayBtn = e.target.closest('.calendar-day:not(.disabled)');
            if (dayBtn) {
                e.preventDefault();
                e.stopPropagation(); // FIX CRÍTICO: Detener la propagación. Al hacer renderCalendar, 
                                     // este botón se destruye. Si el evento llega a document, MainController 
                                     // creerá que el clic fue fuera del panel y cerrará el calendario prematuramente.
                                     
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
                // Aquí no detenemos propagación porque queremos que cierre normalmente
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

        // 5. Validar inputs de tiempo (No permitir valores imposibles)
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

        // Generamos la cuadricula de días para el instante que se abre
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

        const firstDayOfMonth = new Date(year, month, 1).getDay(); // 0 = Domingo
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();

        const today = new Date();
        
        // Rellenar días del mes anterior
        for (let i = firstDayOfMonth - 1; i >= 0; i--) {
            const dayNum = daysInPrevMonth - i;
            grid.appendChild(this.createDayElement(dayNum, month - 1, year, ['other-month']));
        }

        // Rellenar días del mes actual
        for (let i = 1; i <= daysInMonth; i++) {
            const classes = [];
            
            // Marca el día actual en la vida real
            if (year === today.getFullYear() && month === today.getMonth() && i === today.getDate()) {
                classes.push('today');
            }
            
            // Marca el día que el usuario tiene seleccionado
            if (year === this.selectedDate.getFullYear() && month === this.selectedDate.getMonth() && i === this.selectedDate.getDate()) {
                classes.push('selected');
            }

            grid.appendChild(this.createDayElement(i, month, year, classes));
        }

        // Rellenar días del mes siguiente para completar la cuadrícula (42 celdas = 6 filas)
        const totalCellsRendered = firstDayOfMonth + daysInMonth;
        const remainingCells = 42 - totalCellsRendered;
        for (let i = 1; i <= remainingCells; i++) {
            grid.appendChild(this.createDayElement(i, month + 1, year, ['other-month']));
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

        const input = document.getElementById(this.activeInputTarget);
        const display = document.getElementById(this.activeDisplayTarget);

        if (input) {
            input.value = this.formatValueDate(this.selectedDate);
            // Disparar evento change por si otro script lo escucha
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
        // Formato exacto requerido por MySQL y el backend actual
        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    }

    formatDisplayDate(d) {
        const pad = (n) => String(n).padStart(2, '0');
        const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        return `${pad(d.getDate())} ${monthNames[d.getMonth()]} ${d.getFullYear()}, ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    }
}