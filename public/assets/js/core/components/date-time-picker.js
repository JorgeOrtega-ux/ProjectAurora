class DateTimePicker {
    
    constructor(wrapperSelector, inputSelector, options = {}) {
        this.wrapper = (typeof wrapperSelector === 'string') 
            ? document.querySelector(wrapperSelector) 
            : wrapperSelector;

        this.input = (typeof inputSelector === 'string') 
            ? document.querySelector(inputSelector) 
            : inputSelector;

        this.options = {
            enableTime: true,
            minDate: new Date(),
            format: 'YYYY-MM-DDTHH:mm',
            displayFormat: { date: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' },
            ...options
        };

        if (!this.wrapper || !this.input) {
            return;
        }

        this.currentDate = new Date(); 
        this.selectedDate = null;
        
        this.popover = null;
        this.grid = null;
        this.monthLabel = null;
        this.yearInput = null;
        this.hourInput = null;
        this.minuteInput = null;

        this.init();
    }

    init() {
        this.renderDOM();
        this.bindEvents();
        
        if (this.input.value) {
            const d = new Date(this.input.value);
            if (!isNaN(d.getTime())) {
                this.selectedDate = d;
                this.currentDate = new Date(this.selectedDate);
                this.updateTriggerDisplay();
            }
        }
        
        if (!this.selectedDate) {
             const triggerText = this.wrapper.querySelector('.trigger-select-text');
             if(triggerText) triggerText.textContent = "Seleccionar fecha...";
        }
        this.renderCalendar();
    }

    renderDOM() {
        const popoverHTML = `
            <div class="component-calendar">
                <div class="component-calendar__header">
                    <button type="button" class="component-calendar__nav-btn" data-action="prev-month">
                        <span class="material-symbols-rounded">chevron_left</span>
                    </button>
                    <div class="component-calendar__period">
                        <span class="component-calendar__month-label"></span>
                        <input type="number" class="component-calendar__year-input" min="2024" max="2030">
                    </div>
                    <button type="button" class="component-calendar__nav-btn" data-action="next-month">
                        <span class="material-symbols-rounded">chevron_right</span>
                    </button>
                </div>
                
                <div class="component-calendar__weekdays">
                    <span>Do</span><span>Lu</span><span>Ma</span><span>Mi</span><span>Ju</span><span>Vi</span><span>Sa</span>
                </div>
                
                <div class="component-calendar__grid"></div>
                
                ${this.options.enableTime ? `
                <div class="component-calendar__footer">
                    <div class="component-timepicker">
                        <span class="material-symbols-rounded">schedule</span>
                        <input type="number" class="component-timepicker__input hour" min="0" max="23" placeholder="HH">
                        <span class="component-timepicker__separator">:</span>
                        <input type="number" class="component-timepicker__input minute" min="0" max="59" placeholder="MM">
                    </div>
                    <button type="button" class="component-calendar__confirm-btn">Aplicar</button>
                </div>
                ` : ''}
            </div>
        `;

        this.wrapper.insertAdjacentHTML('beforeend', popoverHTML);

        this.popover = this.wrapper.querySelector('.component-calendar');
        this.grid = this.wrapper.querySelector('.component-calendar__grid');
        this.monthLabel = this.wrapper.querySelector('.component-calendar__month-label');
        this.yearInput = this.wrapper.querySelector('.component-calendar__year-input');
        
        if (this.options.enableTime) {
            this.hourInput = this.wrapper.querySelector('.component-timepicker__input.hour');
            this.minuteInput = this.wrapper.querySelector('.component-timepicker__input.minute');
            
            const now = this.selectedDate || new Date();
            this.hourInput.value = String(now.getHours()).padStart(2, '0');
            this.minuteInput.value = String(now.getMinutes()).padStart(2, '0');
        }
    }

    renderCalendar() {
        this.grid.innerHTML = '';
        const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
        this.monthLabel.textContent = monthNames[this.currentDate.getMonth()];
        this.yearInput.value = this.currentDate.getFullYear();

        const year = this.currentDate.getFullYear();
        const month = this.currentDate.getMonth();
        const firstDayOfMonth = new Date(year, month, 1).getDay(); 
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        
        let minDateTimestamp = 0;
        if (this.options.minDate) {
            const m = new Date(this.options.minDate);
            m.setHours(0, 0, 0, 0);
            minDateTimestamp = m.getTime();
        }

        for (let i = 0; i < firstDayOfMonth; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.className = 'component-calendar__day is-empty';
            this.grid.appendChild(emptyCell);
        }

        const today = new Date();
        today.setHours(0,0,0,0);

        for (let day = 1; day <= daysInMonth; day++) {
            const dateCell = document.createElement('div');
            dateCell.className = 'component-calendar__day';
            dateCell.textContent = day;
            
            const cellDate = new Date(year, month, day);
            const cellTimestamp = cellDate.getTime();

            if (cellDate.toDateString() === today.toDateString()) {
                dateCell.classList.add('is-today');
            }

            if (this.selectedDate && cellDate.toDateString() === this.selectedDate.toDateString()) {
                dateCell.classList.add('is-selected');
            }

            if (minDateTimestamp > 0 && cellTimestamp < minDateTimestamp) {
                dateCell.classList.add('is-disabled');
            } else {
                dateCell.onclick = () => this.selectDate(day);
            }

            this.grid.appendChild(dateCell);
        }
    }

    bindEvents() {
        const trigger = this.wrapper.querySelector('.trigger-selector');
        
        trigger.onclick = (e) => {
            e.stopPropagation();
            this.closeAllPopovers();
            this.wrapper.classList.toggle('active');
            this.popover.classList.toggle('active');
        };

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

        this.yearInput.onchange = (e) => {
            this.currentDate.setFullYear(parseInt(e.target.value));
            this.renderCalendar();
        };

        if (this.options.enableTime) {
            const btn = this.wrapper.querySelector('.component-calendar__confirm-btn');
            btn.onclick = (e) => {
                e.stopPropagation();
                this.updateValue();
                this.close();
            };
            
            [this.hourInput, this.minuteInput].forEach(inp => {
                inp.onchange = () => {
                    let val = parseInt(inp.value);
                    if (isNaN(val)) val = 0;
                    if (val < 0) val = 0;
                    const max = inp.classList.contains('hour') ? 23 : 59;
                    if (val > max) val = max;
                    inp.value = String(val).padStart(2, '0');
                    if (this.selectedDate) this.updateValue();
                };
            });
        }

        document.addEventListener('click', (e) => {
            if (this.wrapper && !this.wrapper.contains(e.target)) {
                this.close();
            }
        });
    }

    selectDate(day) {
        if (!this.selectedDate) this.selectedDate = new Date();
        
        this.selectedDate.setFullYear(this.currentDate.getFullYear());
        this.selectedDate.setMonth(this.currentDate.getMonth());
        this.selectedDate.setDate(day);

        if (this.options.enableTime) {
            const h = parseInt(this.hourInput.value) || 0;
            const m = parseInt(this.minuteInput.value) || 0;
            this.selectedDate.setHours(h, m, 0, 0);
        } else {
             this.selectedDate.setHours(0, 0, 0, 0);
        }

        this.updateValue();
        this.renderCalendar();
        
        if (!this.options.enableTime) {
            this.close();
        }
    }

    updateValue() {
        if (!this.selectedDate) return;

        if (this.options.enableTime) {
             const h = parseInt(this.hourInput.value);
             const m = parseInt(this.minuteInput.value);
             this.selectedDate.setHours(h, m);
        }

        const isoString = this.toLocalISOString(this.selectedDate);
        this.input.value = isoString;
        
        this.input.dispatchEvent(new Event('input'));
        
        if (typeof this.options.onChange === 'function') {
            this.options.onChange([this.selectedDate], isoString, this);
        }

        this.updateTriggerDisplay();
    }

    updateTriggerDisplay() {
        if (!this.selectedDate) return;
        const textEl = this.wrapper.querySelector('.trigger-select-text');
        
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
        if(this.wrapper) this.wrapper.classList.remove('active');
        if(this.popover) this.popover.classList.remove('active');
    }
    
    closeAllPopovers() {
        document.querySelectorAll('.component-calendar.active').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.component-datepicker-wrapper.active').forEach(el => el.classList.remove('active'));
    }
}

export { DateTimePicker };