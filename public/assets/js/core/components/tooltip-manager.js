const TooltipManager = {
    init: () => {
        if (!window.Popper) {
            return;
        }

        let popperInstance = null;
        let activeTrigger = null;
        let currentTooltipEl = null;

        const createTooltipElement = () => {
            const el = document.createElement('div');
            el.id = 'global-tooltip';
            el.classList.add('tooltip');
            el.setAttribute('role', 'tooltip');
            return el;
        };

        const showTooltip = (trigger) => {
            if (window.innerWidth <= 725) return;

            if (currentTooltipEl) {
                removeTooltipFromDOM();
            }

            const text = trigger.dataset.tooltip;
            const shortcut = trigger.dataset.shortcut;
            
            if (!text) return;

            activeTrigger = trigger;

            currentTooltipEl = createTooltipElement();

            if (shortcut) {
                currentTooltipEl.classList.add('tooltip--with-shortcut');
                currentTooltipEl.innerHTML = `
                    <span class="tooltip-text">${text}</span>
                    <span class="tooltip-shortcut"><kbd>${shortcut}</kbd></span>
                `;
            } else {
                currentTooltipEl.classList.remove('tooltip--with-shortcut');
                currentTooltipEl.innerHTML = `<span class="tooltip-text">${text}</span>`;
            }

            document.body.appendChild(currentTooltipEl);
            
            currentTooltipEl.style.display = 'block';

            popperInstance = Popper.createPopper(trigger, currentTooltipEl, {
                placement: 'auto',
                modifiers: [
                    {
                        name: 'offset',
                        options: {
                            offset: [0, 8],
                        },
                    },
                    {
                        name: 'preventOverflow',
                        options: {
                            padding: 8,
                        },
                    },
                ],
            });
            
            currentTooltipEl.setAttribute('data-show', '');
        };

        const removeTooltipFromDOM = () => {
            if (popperInstance) {
                popperInstance.destroy();
                popperInstance = null;
            }
            
            if (currentTooltipEl) {
                currentTooltipEl.remove();
                currentTooltipEl = null;
            }
            
            activeTrigger = null;
        };

        const eventsShow = ['mouseenter', 'focus'];
        const eventsHide = ['mouseleave', 'blur'];

        eventsShow.forEach(event => {
            document.body.addEventListener(event, (e) => {
                const trigger = e.target.closest('[data-tooltip]');
                if (trigger) {
                    showTooltip(trigger);
                }
            }, true); 
        });

        eventsHide.forEach(event => {
            document.body.addEventListener(event, (e) => {
                const trigger = e.target.closest('[data-tooltip]');
                if (trigger && trigger === activeTrigger) {
                    removeTooltipFromDOM();
                }
            }, true);
        });
    }
};

export { TooltipManager };