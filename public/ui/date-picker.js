/* Shared Schulnetz date/time field. Uses the locally shipped Flatpickr. */
(() => {
    'use strict';
    if (window.LmzDatePicker) return;
    function mount(root, binding = {}) {
        if (root.datePicker) return root.datePicker;
        const config = JSON.parse(root.dataset.datePicker);
        const source = root.querySelector('[data-date-source]');
        const value = root.querySelector('[data-date-value]');
        const time = root.querySelector('[data-time-value]');
        const field = root.querySelector('[data-date-field]');
        const trigger = root.querySelector('[data-date-trigger]');
        const events = new AbortController();
        const listen = (node, event, handler, options = {}) => node.addEventListener(event, handler, { ...options, signal: events.signal });
        const get = input => binding.get && input.dataset.dateModel ? (binding.get(input.dataset.dateModel) ?? '') : input.value;
        const initial = () => time ? (get(value) ? get(value) + ' ' + (get(time) || '09:00') : '') : get(value);
        // Static wire:ignore portals survive Livewire morphs and remain inside modal focus traps.
        const panelPortal = root.closest('[data-anchor-panel]')?.querySelector(':scope > [data-date-picker-portal]');
        const dialogPortal = root.closest('[role="dialog"]')?.querySelector(':scope > [data-date-picker-portal]');
        const parent = config.inline ? root.querySelector('[data-date-inline]') : (panelPortal || dialogPortal || document.body);
        let fp, year, todayButton, display, syncing = false;
        function publish(dates, formatted, instance) {
            if (syncing) return;
            const values = time ? [dates[0] ? instance.formatDate(dates[0], 'Y-m-d') : '', dates[0] ? instance.formatDate(dates[0], 'H:i') : ''] : [formatted];
            const changes = {};
            let changed = false;
            [value, time].filter(Boolean).forEach((input, i) => {
                if (input.value === values[i]) return;
                changed = true;
                input.value = values[i];
                if (input.dataset.dateModel) changes[input.dataset.dateModel] = values[i];
            });
            if (!changed) return;
            if (Object.keys(changes).length) binding.set?.(changes);
            // Both carriers are updated before events; listeners always see a coherent date/time pair.
            [value, time].filter(Boolean).forEach(input => input.dispatchEvent(new Event('input', { bubbles: true })));
            root.dispatchEvent(new CustomEvent('date-picker:change', { bubbles: true, detail: { value: value.value, time: time?.value } }));
        }
        function position(instance) {
            if (config.inline || !instance.isOpen) return;
            const calendar = instance.calendarContainer, anchor = field.getBoundingClientRect();
            const bottomInset = Math.max(12, parseFloat(getComputedStyle(root).getPropertyValue('--dp-viewport-bottom')) || 12);
            const viewport = { left: 12, top: 12, right: window.innerWidth - 12, bottom: window.innerHeight - bottomInset };
            for (let node = calendar.parentElement; node && node !== document.body; node = node.parentElement) {
                const style = getComputedStyle(node), rect = node.getBoundingClientRect();
                if (/(auto|scroll|hidden|clip)/.test(style.overflowX)) { viewport.left = Math.max(viewport.left, rect.left + 12); viewport.right = Math.min(viewport.right, rect.right - 12); }
                if (/(auto|scroll|hidden|clip)/.test(style.overflowY)) { viewport.top = Math.max(viewport.top, rect.top + 12); viewport.bottom = Math.min(viewport.bottom, rect.bottom - 12); }
                // A fixed modal is not clipped by the page's sidebar/content scroller.
                if (style.position === 'fixed') break;
            }
            calendar.style.maxHeight = Math.max(120, viewport.bottom - viewport.top) + 'px';
            const bounds = calendar.getBoundingClientRect(), offset = calendar.offsetParent;
            const origin = offset && offset !== document.body ? offset.getBoundingClientRect() : { left: -window.scrollX, top: -window.scrollY };
            const left = Math.max(viewport.left, Math.min(anchor.left, viewport.right - bounds.width));
            let top = anchor.bottom + 8;
            if (top + bounds.height > viewport.bottom) top = anchor.top - bounds.height - 8;
            top = Math.max(viewport.top, Math.min(top, viewport.bottom - bounds.height));
            calendar.style.left = (left - origin.left + (offset?.scrollLeft || 0)) + 'px';
            calendar.style.top = (top - origin.top + (offset?.scrollTop || 0)) + 'px';
            calendar.style.right = 'auto';
        }
        function refresh(instance) {
            if (year) {
                const min = Math.min(instance.currentYear - 100, instance.config.minDate?.getFullYear() ?? instance.currentYear);
                const max = Math.max(instance.currentYear + 20, instance.config.maxDate?.getFullYear() ?? instance.currentYear);
                if (!year.querySelector('option[value="' + instance.currentYear + '"]')) {
                    year.replaceChildren();
                    for (let y = min; y <= max; y++) {
                        if (instance.config.minDate && y < instance.config.minDate.getFullYear()) continue;
                        if (instance.config.maxDate && y > instance.config.maxDate.getFullYear()) continue;
                        year.add(new Option(String(y), String(y)));
                    }
                }
                year.value = String(instance.currentYear);
            }
            if (todayButton) todayButton.disabled = !instance.isEnabled(new Date(), true);
        }
        function open() {
            if (config.disabled || config.readonly || source.matches(':disabled')) return;
            fp.open();
            position(fp);
        }
        function finish() {
            fp.close();
            display.focus({ preventScroll: true });
        }
        function enhance(instance) {
            const calendar = instance.calendarContainer;
            calendar.classList.add('lmz-date-calendar');
            calendar.classList.toggle('lmz-date-calendar--combined', !!config.enableTime && !config.timeOnly);
            calendar.id = source.id + '-calendar';
            calendar.setAttribute('aria-label', config.timeOnly ? 'Uhrzeit wählen' : 'Datum wählen');
            calendar.inert = !!config.disabled || !!config.readonly;
            display = instance.altInput || instance.input;
            display.id = source.id + '-display';
            display.className = 'lmz-date-display';
            display.setAttribute('aria-controls', calendar.id);
            display.setAttribute('aria-expanded', String(!!config.inline));
            display.setAttribute('autocomplete', 'off');
            root.querySelector('label')?.setAttribute('for', display.id);
            trigger.setAttribute('aria-controls', calendar.id);
            if (!config.timeOnly) {
                year = document.createElement('select');
                year.className = 'lmz-date-year';
                year.setAttribute('aria-label', 'Jahr');
                instance.currentYearElement.parentElement.classList.add('lmz-date-original-year');
                instance.currentYearElement.parentElement.after(year);
                listen(year, 'change', () => { instance.changeYear(Number(year.value)); refresh(instance); });
                instance.monthsDropdownContainer?.setAttribute('aria-label', 'Monat');
                [instance.prevMonthNav, instance.nextMonthNav].forEach((nav, i) => {
                    nav.tabIndex = 0;
                    nav.setAttribute('role', 'button');
                    nav.setAttribute('aria-label', i ? 'Nächster Monat' : 'Vorheriger Monat');
                    listen(nav, 'keydown', event => { if (['Enter', ' '].includes(event.key)) { event.preventDefault(); nav.click(); } });
                });
            }
            if (instance.timeContainer) {
                const title = document.createElement('div');
                title.className = 'lmz-date-time-title';
                title.textContent = 'Uhrzeit';
                instance.timeContainer.prepend(title);
                [[instance.hourElement, 'Stunde'], [instance.minuteElement, 'Minute']].forEach(([input, label]) => {
                    input.tabIndex = 0;
                    input.setAttribute('aria-label', label);
                    input.parentElement.dataset.timeLabel = label;
                    input.parentElement.querySelectorAll('.arrowUp, .arrowDown').forEach(arrow => {
                        const up = arrow.classList.contains('arrowUp');
                        arrow.setAttribute('role', 'button');
                        arrow.setAttribute('aria-label', label + (up ? ' erhöhen' : ' verringern'));
                        arrow.tabIndex = 0;
                        listen(arrow, 'keydown', event => { if (['Enter', ' '].includes(event.key)) { event.preventDefault(); arrow.click(); } });
                    });
                });
            }
            const footer = document.createElement('div');
            footer.className = 'lmz-date-footer';
            const actions = document.createElement('div');
            actions.className = 'lmz-date-actions';
            footer.append(actions);
            function button(label, handler, container = actions) {
                const node = document.createElement('button');
                node.type = 'button'; node.textContent = label;
                listen(node, 'click', handler); container.append(node); return node;
            }
            if (!config.timeOnly) todayButton = button('Heute', () => {
                const date = new Date(), selected = instance.selectedDates[0];
                if (selected) date.setHours(selected.getHours(), selected.getMinutes());
                instance.setDate(date, true); refresh(instance);
            });
            if (config.enableTime) button('Jetzt', () => {
                const date = new Date(instance.selectedDates[0] || new Date()), now = new Date();
                date.setHours(now.getHours(), now.getMinutes()); instance.setDate(date, true);
            });
            if (!config.required) button('Leeren', () => instance.clear());
            if (!config.inline) button('Fertig', finish, footer).className = 'lmz-date-done';
            calendar.append(footer);
            const keyboard = event => {
                if (event.key === 'Escape' && instance.isOpen && !config.inline) {
                    event.preventDefault(); event.stopPropagation(); finish();
                } else if (event.key === 'Enter') {
                    // Editing a time must not submit the surrounding form or close its anchor dropdown.
                    event.stopPropagation();
                    if (event.target.matches('input')) {
                        event.preventDefault();
                        if (event.target === display) instance.setDate(display.value, true, config.altInput ? config.altFormat : instance.config.dateFormat);
                        if (!config.inline) finish();
                    } else if (event.target.matches('[role="button"]')) {
                        event.preventDefault();
                        event.target.click();
                    }
                }
            };
            listen(calendar, 'keydown', keyboard, { capture: true });
            listen(field, 'keydown', keyboard, { capture: true });
            listen(display, 'click', open);
            listen(display, 'keydown', event => {
                if (event.key === 'ArrowDown' && !instance.isOpen) { event.preventDefault(); open(); }
            });
            refresh(instance);
        }
        value.value = get(value);
        if (time) time.value = get(time);
        fp = window.flatpickr(source, {
            locale: 'de', dateFormat: time ? 'Y-m-d H:i' : config.dateFormat,
            altInput: config.altInput, altFormat: config.altFormat,
            mode: config.mode, enableTime: config.enableTime, noCalendar: config.timeOnly,
            time_24hr: true, minuteIncrement: config.minuteIncrement, defaultHour: 9, ariaDateFormat: 'd.m.Y',
            inline: config.inline, appendTo: parent, position,
            clickOpens: false, allowInput: !config.readonly, closeOnSelect: false, disableMobile: true,
            minDate: config.min || undefined, maxDate: config.max || undefined,
            disable: config.disableWeekends ? [date => [0, 6].includes(date.getDay())] : [],
            defaultDate: initial() || undefined,
            onReady: [(_dates, _text, instance) => enhance(instance)], onChange: [publish],
            onMonthChange: [(_dates, _text, instance) => refresh(instance)],
            onYearChange: [(_dates, _text, instance) => refresh(instance)],
            onValueUpdate: [(_dates, _text, instance) => refresh(instance)],
            onOpen: [instanceState(true)], onClose: [(_dates, _text, instance) => {
                // Flatpickr debounces time changes; flush before the user submits the surrounding form.
                publish(instance.selectedDates, instance.input.value, instance);
                instanceState(false)();
            }],
        });
        function instanceState(isOpen) {
            return () => {
                display?.setAttribute('aria-expanded', String(isOpen || config.inline));
                trigger.setAttribute('aria-expanded', String(isOpen || config.inline));
                field.classList.toggle('is-open', isOpen);
            };
        }
        listen(trigger, 'click', () => fp.isOpen ? finish() : open());
        listen(window, 'resize', () => position(fp));
        listen(document, 'scroll', () => position(fp), { capture: true, passive: true });
        const api = {
            sync() {
                const next = initial();
                value.value = get(value); if (time) time.value = get(time);
                if (fp.input.value === next) return;
                syncing = true;
                if (next) fp.setDate(next, false); else fp.clear(false);
                syncing = false;
            },
            close: () => fp.close(),
            destroy() { events.abort(); fp.destroy(); delete root.datePicker; },
        };
        root.datePicker = api;
        return api;
    }
    window.LmzDatePicker = { mount };
    window.lmzDatePicker = () => ({
        picker: null,
        init() {
            const models = Array.from(this.$el.querySelectorAll('[data-date-model]'), input => input.dataset.dateModel).filter(Boolean);
            this.picker = mount(this.$el, {
                get: model => this.$wire.get(model),
                set: values => {
                    const changes = Object.entries(values);
                    changes.forEach(([model, value], index) => this.$wire.set(model, value, index === changes.length - 1));
                },
            });
            models.forEach(model => this.$watch(() => this.$wire.get(model), () => this.picker?.sync()));
        },
        destroy() { this.picker?.destroy(); },
    });
})();
