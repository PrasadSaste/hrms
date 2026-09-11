/**
 * Small, dependency-free interactions for the HRMS shell:
 * the sidebar drawer, dropdown menus, dialogs, flash dismissal and the
 * live clock on the attendance punch card.
 */

import { initToaster, showConfirmToast, showToast } from './toast';

const onReady = (fn) =>
    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', fn)
        : fn();

function closeAllMenus(except = null) {
    document.querySelectorAll('[data-menu]').forEach((menu) => {
        if (menu !== except) menu.classList.add('hidden');
    });
}

function initMenus() {
    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        const menu = document.querySelector(
            `[data-menu="${button.dataset.menuToggle}"]`
        );
        if (!menu) return;

        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = menu.classList.contains('hidden');
            closeAllMenus(menu);
            menu.classList.toggle('hidden', !willOpen);
            button.setAttribute('aria-expanded', String(willOpen));
        });
    });

    document.addEventListener('click', () => closeAllMenus());
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeAllMenus();
    });
}

function initSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const overlay = document.querySelector('[data-sidebar-overlay]');
    if (!sidebar) return;

    const setOpen = (open) => {
        sidebar.classList.toggle('-translate-x-full', !open);
        overlay?.classList.toggle('hidden', !open);
    };

    document
        .querySelectorAll('[data-sidebar-toggle]')
        .forEach((button) =>
            button.addEventListener('click', () =>
                setOpen(sidebar.classList.contains('-translate-x-full'))
            )
        );

    overlay?.addEventListener('click', () => setOpen(false));

    // Collapsible nav groups remember their state per browser.
    document.querySelectorAll('[data-nav-group]').forEach((group) => {
        const key = `hrms.nav.${group.dataset.navGroup}`;
        const panel = group.querySelector('[data-nav-panel]');
        const toggle = group.querySelector('[data-nav-toggle]');
        const chevron = group.querySelector('[data-nav-chevron]');
        if (!panel || !toggle) return;

        const stored = localStorage.getItem(key);
        const startOpen = stored === null ? group.dataset.navOpen === 'true' : stored === '1';
        panel.classList.toggle('hidden', !startOpen);
        chevron?.classList.toggle('rotate-90', startOpen);

        toggle.addEventListener('click', () => {
            const open = panel.classList.toggle('hidden') === false;
            chevron?.classList.toggle('rotate-90', open);
            localStorage.setItem(key, open ? '1' : '0');
        });
    });
}

function initDialogs() {
    const setDialog = (name, open) => {
        const dialog = document.querySelector(`[data-dialog="${name}"]`);
        if (!dialog) return;
        dialog.classList.toggle('hidden', !open);
        document.body.classList.toggle('overflow-hidden', open);
        if (open) dialog.querySelector('input, select, textarea')?.focus();
    };

    document.querySelectorAll('[data-dialog-open]').forEach((button) =>
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const dialog = document.querySelector(
                `[data-dialog="${button.dataset.dialogOpen}"]`
            );
            // Buttons may carry data-fill-* attributes to prefill the form.
            Object.entries(button.dataset).forEach(([key, value]) => {
                if (!key.startsWith('fill')) return;
                const field = key.slice(4).replace(/^./, (c) => c.toLowerCase());
                const input = dialog?.querySelector(`[name="${field}"]`);
                if (input) input.value = value;
            });
            if (button.dataset.action && dialog) {
                dialog.querySelector('form')?.setAttribute('action', button.dataset.action);
            }
            setDialog(button.dataset.dialogOpen, true);
        })
    );

    document.querySelectorAll('[data-dialog-close]').forEach((button) =>
        button.addEventListener('click', (event) => {
            event.preventDefault();
            setDialog(button.dataset.dialogClose, false);
        })
    );

    // A click on the backdrop deliberately does nothing. These dialogs hold
    // half-finished forms, and losing one to a stray click beside the panel is
    // worse than the extra press it costs to close on purpose. Cancel, the
    // close button and Escape are the ways out.
    document.querySelectorAll('[data-dialog]').forEach((dialog) => {
        // A dialog rendered open (a form that came back with errors) still
        // needs the page behind it held still.
        if (!dialog.classList.contains('hidden')) {
            document.body.classList.add('overflow-hidden');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document
            .querySelectorAll('[data-dialog]:not(.hidden)')
            .forEach((dialog) => setDialog(dialog.dataset.dialog, false));
    });
}

/**
 * Tabbed sections. Panels are plain elements marked with data-tab-panel, so a
 * page without JavaScript simply shows every section stacked.
 *
 * The chosen tab is remembered per page. If validation failed, the first
 * section carrying an error wins over the remembered one, so an error is never
 * hidden behind a tab the person is not looking at.
 */
function initTabs() {
    document.querySelectorAll('[data-tabs]').forEach((group) => {
        const key = `hrms.tab.${location.pathname}.${group.dataset.tabs}`;
        const buttons = [...group.querySelectorAll('[data-tab-button]')];
        const panels = [...group.querySelectorAll('[data-tab-panel]')];
        if (!buttons.length || !panels.length) return;

        const panelFor = (name) => panels.find((p) => p.dataset.tabPanel === name);

        const show = (name, remember = true) => {
            if (!panelFor(name)) return;
            panels.forEach((p) => {
                p.hidden = p.dataset.tabPanel !== name;
            });
            buttons.forEach((b) =>
                b.setAttribute(
                    'aria-selected',
                    String(b.dataset.tabButton === name)
                )
            );
            if (remember) {
                try {
                    localStorage.setItem(key, name);
                } catch {
                    /* private browsing; the tab simply is not remembered */
                }
            }
        };

        // Flag any section that failed validation.
        const failing = [];
        buttons.forEach((button) => {
            const panel = panelFor(button.dataset.tabButton);
            const hasError = panel?.querySelector('.form-error') != null;
            button.querySelector('[data-tab-badge]')?.classList.toggle('hidden', !hasError);
            if (hasError) failing.push(button.dataset.tabButton);
        });

        buttons.forEach((button) => {
            button.addEventListener('click', () => show(button.dataset.tabButton));
        });

        // Left and right arrows move between tabs.
        group.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            const current = buttons.findIndex(
                (b) => b.getAttribute('aria-selected') === 'true'
            );
            if (current < 0) return;
            const step = event.key === 'ArrowRight' ? 1 : -1;
            const next = buttons[(current + step + buttons.length) % buttons.length];
            next.focus();
            show(next.dataset.tabButton);
        });

        let stored = null;
        try {
            stored = localStorage.getItem(key);
        } catch {
            /* ignore */
        }

        show(failing[0] ?? stored ?? group.dataset.tabsDefault, false);

        /*
         * A required field on a hidden panel cannot be focused, so the browser
         * refuses to submit and shows nothing at all. The browser fires invalid
         * for every failing control in document order; we act on the first one
         * only, so the person is taken to the earliest problem rather than the
         * last.
         */
        let handling = false;

        group.closest('form')?.addEventListener(
            'invalid',
            (event) => {
                if (handling) return;
                handling = true;
                // Release the latch once this round of validation is over.
                setTimeout(() => {
                    handling = false;
                }, 0);

                const panel = event.target.closest('[data-tab-panel]');
                if (!panel) return;
                if (panel.hidden) show(panel.dataset.tabPanel);
                // Focus once the panel is on screen so the message anchors to it.
                requestAnimationFrame(() => event.target.focus());
            },
            true
        );
    });
}

/**
 * Roles screen: a per-module "All" button, and a live granted-of-total count
 * so you can see at a glance what a role covers.
 */
function initPermissionGroups() {
    document.querySelectorAll('[data-permission-toggle]').forEach((button) => {
        const card = button.closest('.card');
        if (!card) return;

        const boxes = [...card.querySelectorAll('input[type="checkbox"]:not(:disabled)')];
        const count = card.querySelector('[data-permission-count]');
        if (!boxes.length) return;

        const sync = () => {
            const on = boxes.filter((b) => b.checked).length;
            if (count) count.textContent = `${on}/${boxes.length}`;
            button.textContent = on === boxes.length ? 'None' : 'All';
        };

        button.addEventListener('click', () => {
            const turnOn = boxes.some((b) => !b.checked);
            boxes.forEach((b) => {
                b.checked = turnOn;
            });
            sync();
        });

        boxes.forEach((b) => b.addEventListener('change', sync));
        sync();
    });
}

/**
 * The slab table on a salary component.
 *
 * Professional tax is a table rather than a percentage, and how many bands a
 * state has is the state's business, so the rows are added and removed here.
 * The names are re-indexed after every change: a gap in slabs[0], slabs[2]
 * would be sent as an object rather than a list.
 */
function initSlabTable() {
    const wrapper = document.querySelector('[data-slabs]');
    if (!wrapper) return;

    const rows = wrapper.querySelector('[data-slab-rows]');
    const add = wrapper.querySelector('[data-slab-add]');

    const renumber = () => {
        rows.querySelectorAll('[data-slab-row]').forEach((row, index) => {
            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(/slabs\[\d*\]/, `slabs[${index}]`);
            });
        });
    };

    add?.addEventListener('click', () => {
        const first = rows.querySelector('[data-slab-row]');
        if (!first) return;

        const copy = first.cloneNode(true);
        copy.querySelectorAll('input').forEach((input) => (input.value = ''));
        rows.appendChild(copy);
        renumber();
    });

    rows?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-slab-remove]');
        if (!button) return;

        // The last row stays, emptied: with none at all there is nothing to
        // clone when somebody wants one back.
        const all = rows.querySelectorAll('[data-slab-row]');
        if (all.length === 1) {
            all[0].querySelectorAll('input').forEach((input) => (input.value = ''));

            return;
        }

        button.closest('[data-slab-row]').remove();
        renumber();
    });
}

/**
 * Settings: the mail server fields.
 *
 * A host and a password are only meaningful when messages are actually being
 * sent through a server, so the block is hidden for the other choices rather
 * than sitting there asking for details nobody will use. The server ignores
 * them for those choices either way, so this is tidiness, not enforcement.
 */
function initMailTransport() {
    const choice = document.querySelector('[data-mail-transport]');
    const fields = document.querySelector('[data-mail-smtp]');
    if (!choice || !fields) return;

    const sync = () => fields.classList.toggle('hidden', choice.value !== 'smtp');

    choice.addEventListener('change', sync);
    sync();
}

/**
 * Settings: the theme picker.
 *
 * Three colours and a background choice drive the whole interface, so the
 * miniature layout beside them is repainted as they move — the ramps, the
 * navigation column, the icons and the buttons all at once. The arithmetic
 * below is the same mix BrandPalette does on the server; if one changes, the
 * other has to.
 */
function initThemeColors() {
    const roles = Array.from(document.querySelectorAll('[data-theme-role]'));
    if (!roles.length) return;

    const preview = document.querySelector('[data-theme-preview]');
    const tokens = { primary: 'brand', secondary: 'accent', tertiary: 'tertiary' };

    const steps = {
        50: -0.95, 100: -0.89, 200: -0.77, 300: -0.6, 400: -0.36,
        500: -0.16, 600: 0, 700: 0.14, 800: 0.28, 900: 0.42,
    };

    const toRgb = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));
    const clamp = (n) => Math.max(0, Math.min(255, Math.round(n)));
    const toHex = (rgb) => '#' + rgb.map((c) => clamp(c).toString(16).padStart(2, '0')).join('');
    const mix = (from, to, amount) => {
        const a = toRgb(from);
        const b = toRgb(to);
        return toHex(a.map((c, i) => c + (b[i] - c) * amount));
    };

    /** Black or white, whichever stays readable. Relative luminance, per WCAG. */
    const foregroundOn = (hex) => {
        const channel = (c) => {
            const v = c / 255;
            return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
        };
        const [r, g, b] = toRgb(hex);
        const luminance = 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
        return luminance > 0.45 ? '#0f172a' : '#ffffff';
    };

    /**
     * The same derivation `BrandPalette::ink()` does on the server: the brand
     * colour walked darker until small text set in it clears 4.5:1 against the
     * page background. Mirrored here so the live preview and the saved page
     * cannot disagree about what a link will look like.
     */
    const TEXT_CONTRAST = 4.5;

    const luminance = (hex) => {
        const channel = (c) => {
            const v = c / 255;
            return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
        };
        const [r, g, b] = toRgb(hex);
        return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
    };

    const contrast = (a, b) => {
        const [one, two] = [luminance(a), luminance(b)];
        return (Math.max(one, two) + 0.05) / (Math.min(one, two) + 0.05);
    };

    const inkFor = (hex) => {
        const against = '#f1f5f9';
        if (contrast(hex, against) >= TEXT_CONTRAST) return hex.toLowerCase();

        const rgb = toRgb(hex);
        for (let step = 1; step <= 20; step++) {
            const candidate = toHex(rgb.map((c) => c + (0 - c) * (step * 0.05)));
            if (contrast(candidate, against) >= TEXT_CONTRAST) return candidate;
        }
        return '#0f172a';
    };

    const rampFor = (hex) => {
        const rgb = toRgb(hex);
        const ramp = {};
        Object.entries(steps).forEach(([weight, amount]) => {
            const target = amount < 0 ? 255 : 0;
            ramp[weight] = toHex(rgb.map((c) => c + (target - c) * Math.abs(amount)));
        });
        return ramp;
    };

    const currentHex = (role) => {
        const input = document.querySelector(`[data-theme-role="${role}"] [data-theme-color]`);
        return input && /^#[0-9a-fA-F]{6}$/.test(input.value) ? input.value : null;
    };

    const currentSurface = () =>
        document.querySelector('[data-surface-choice]:checked')?.value || 'light';

    /** Mirrors BrandPalette::surfaceVariables(). */
    const surfaceVars = (surface, ramps) => {
        const primary = ramps.primary;
        const secondary = ramps.secondary;

        if (surface === 'tinted') {
            return {
                '--surface-app': secondary[50],
                '--surface-nav': '#ffffff',
                '--surface-nav-border': secondary[100],
                '--surface-nav-text': '#334155',
                '--surface-nav-muted': '#94a3b8',
                '--surface-nav-icon': secondary[500],
                '--surface-nav-hover': secondary[50],
                '--surface-nav-active': primary[50],
                '--surface-nav-active-text': primary[700],
                '--surface-nav-active-icon': primary[600],
                '--surface-topbar': '#ffffff',
            };
        }

        if (surface === 'dark') {
            return {
                '--surface-app': '#f1f5f9',
                '--surface-nav': mix(secondary[900], '#020617', 0.72),
                '--surface-nav-border': mix(secondary[900], '#020617', 0.5),
                '--surface-nav-text': '#cbd5e1',
                '--surface-nav-muted': '#64748b',
                '--surface-nav-icon': '#94a3b8',
                '--surface-nav-hover': mix(secondary[800], '#020617', 0.55),
                '--surface-nav-active': primary[600],
                '--surface-nav-active-text': foregroundOn(primary[600]),
                '--surface-nav-active-icon': foregroundOn(primary[600]),
                '--surface-topbar': '#ffffff',
            };
        }

        return {
            '--surface-app': '#f1f5f9',
            '--surface-nav': '#ffffff',
            '--surface-nav-border': '#e2e8f0',
            '--surface-nav-text': '#475569',
            '--surface-nav-muted': '#94a3b8',
            '--surface-nav-icon': '#94a3b8',
            '--surface-nav-hover': '#f1f5f9',
            '--surface-nav-active': primary[50],
            '--surface-nav-active-text': primary[700],
            '--surface-nav-active-icon': primary[600],
            '--surface-topbar': '#ffffff',
        };
    };

    const paint = () => {
        const ramps = {};

        roles.forEach((group) => {
            const role = group.dataset.themeRole;
            const hex = currentHex(role);
            if (!hex) return;

            const ramp = rampFor(hex);
            ramps[role] = ramp;

            Object.entries(ramp).forEach(([weight, shade]) => {
                group.querySelector(`[data-theme-swatch="${weight}"]`)?.style
                    .setProperty('background-color', shade);
                // The page itself follows too, so the real buttons and the
                // preview never disagree about what was just chosen.
                document.documentElement.style.setProperty(`--color-${tokens[role]}-${weight}`, shade);
                preview?.style.setProperty(`--color-${tokens[role]}-${weight}`, shade);
            });

            const foreground = foregroundOn(hex);
            document.documentElement.style.setProperty(`--color-${tokens[role]}-foreground`, foreground);
            preview?.style.setProperty(`--color-${tokens[role]}-foreground`, foreground);

            const ink = inkFor(hex);
            document.documentElement.style.setProperty(`--color-${tokens[role]}-ink`, ink);
            preview?.style.setProperty(`--color-${tokens[role]}-ink`, ink);
        });

        if (!ramps.primary || !ramps.secondary) return;

        // Only the preview takes the surface, so choosing Dark does not black
        // out the settings screen you are still working on.
        Object.entries(surfaceVars(currentSurface(), ramps)).forEach(([name, value]) =>
            preview?.style.setProperty(name, value)
        );
    };

    roles.forEach((group) => {
        const picker = group.querySelector('[data-theme-color]');
        const text = group.querySelector('[data-theme-color-text]');

        picker?.addEventListener('input', () => {
            if (text) text.value = picker.value.toUpperCase();
            paint();
        });

        text?.addEventListener('input', () => {
            const value = text.value.trim();
            if (!/^#[0-9a-fA-F]{6}$/.test(value)) return;
            if (picker) picker.value = value;
            paint();
        });
    });

    document.querySelectorAll('[data-surface-choice]').forEach((choice) =>
        choice.addEventListener('change', () => {
            document.querySelectorAll('[data-surface-option]').forEach((option) => {
                const selected = option.querySelector('[data-surface-choice]')?.checked;
                option.classList.toggle('border-brand-500', !!selected);
                option.classList.toggle('bg-brand-50', !!selected);
                option.classList.toggle('border-slate-200', !selected);
            });
            paint();
        })
    );

    paint();
}

/**
 * Attendance punching with a location check.
 *
 * The device position is collected before the form is submitted. Each way the
 * request can fail gets its own message, because "location unavailable" is not
 * something a person can act on, whereas "you blocked location for this site"
 * is. The server enforces the same rule, so turning JavaScript off does not
 * bypass it.
 */
function initPunchForms() {
    const forms = document.querySelectorAll('[data-punch-form]');
    if (!forms.length) return;

    const notice = (message, detail) => {
        document.querySelectorAll('[data-punch-error]').forEach((n) => n.remove());

        const box = document.createElement('div');
        box.dataset.punchError = '';
        box.setAttribute('role', 'alert');
        box.className =
            'mt-3 flex items-start gap-3 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-600/20 ring-inset';
        box.innerHTML =
            '<svg class="mt-0.5 size-5 shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">' +
            '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>' +
            '<div><p class="font-semibold"></p><p class="mt-0.5"></p></div>';
        box.querySelectorAll('p')[0].textContent = message;
        box.querySelectorAll('p')[1].textContent = detail;

        const anchor = document.querySelector('[data-punch-anchor]') || forms[0].closest('.card') || forms[0];
        anchor.appendChild(box);
        box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    };

    const explain = (error) => {
        switch (error?.code) {
            case 1: // PERMISSION_DENIED
                return [
                    'Location access is blocked',
                    'Attendance needs your location. Allow it for this site in your browser settings, then try again. In most browsers the control is the icon at the left of the address bar.',
                ];
            case 2: // POSITION_UNAVAILABLE
                return [
                    'Your location could not be determined',
                    'Your device could not get a position fix. Move somewhere with a clearer signal, or turn location services on for your device, then try again.',
                ];
            case 3: // TIMEOUT
                return [
                    'Finding your location took too long',
                    'Your device did not report a position in time. Please try again.',
                ];
            default:
                return [
                    'Location is not available',
                    'This browser did not provide a location. Attendance can only be recorded from a page served over HTTPS, so check the address begins with https:// and try again.',
                ];
        }
    };

    const busy = (form, on) => {
        const button = form.querySelector('[data-punch-button]');
        if (!button) return;
        button.disabled = on;
        form.querySelector('[data-punch-idle]').hidden = on;
        form.querySelector('[data-punch-busy]').hidden = !on;
    };

    forms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            // A second pass, once the coordinates are in place, submits for real.
            if (form.dataset.punchReady === '1') return;

            event.preventDefault();
            document.querySelectorAll('[data-punch-error]').forEach((n) => n.remove());

            // A property can exist and still be unusable, so check for the
            // method itself rather than the key.
            const geo = navigator.geolocation;

            if (!geo || typeof geo.getCurrentPosition !== 'function' || !window.isSecureContext) {
                notice(...explain(null));
                return;
            }

            busy(form, true);

            try {
                geo.getCurrentPosition(
                    (position) => {
                        form.querySelector('[data-punch-latitude]').value = position.coords.latitude;
                        form.querySelector('[data-punch-longitude]').value = position.coords.longitude;
                        form.querySelector('[data-punch-accuracy]').value = Math.round(
                            position.coords.accuracy ?? 0
                        );
                        form.dataset.punchReady = '1';
                        form.submit();
                    },
                    (error) => {
                        busy(form, false);
                        notice(...explain(error));
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 }
                );
            } catch {
                // Never leave the button spinning with nothing explained.
                busy(form, false);
                notice(...explain(null));
            }
        });
    });
}

/**
 * Dismissing an inline alert.
 *
 * These used to be flashed messages and timed out after eight seconds. Flashed
 * messages are toasts now, and what is left carrying this class is page content
 * — the warning that somebody still holds a laptop, sitting above the button
 * that would relieve them. Content does not expire, so only the close button
 * remains.
 */
function initFlash() {
    document.querySelectorAll('[data-flash]').forEach((flash) => {
        flash
            .querySelector('[data-flash-dismiss]')
            ?.addEventListener('click', () => flash.remove());
    });
}

/**
 * Show the time in the application's timezone rather than the browser's, so the
 * running clock agrees with the check-in and check-out times we record.
 */
function initClock() {
    const clocks = document.querySelectorAll('[data-clock]');
    if (!clocks.length) return;

    const timeZone =
        document.querySelector('meta[name="app-timezone"]')?.content || undefined;

    const format = (date) => {
        try {
            return date.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                timeZone,
            });
        } catch {
            // An unknown timezone falls back to the browser's own.
            return date.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        }
    };

    const tick = () => {
        const now = new Date();
        clocks.forEach((clock) => {
            clock.textContent = format(now);
        });
    };

    tick();
    setInterval(tick, 1000);
}

/**
 * Ask before anything that cannot be undone.
 *
 * The browser's own confirm() blocked the page and looked like nothing else in
 * the application; showConfirmToast() does not block, so the click has to be
 * stopped first and the action replayed afterwards.
 *
 * Delegated from the document in the capture phase so it also covers buttons
 * that appear later — inside a dialog, or a row added by a repeater — and so it
 * runs before any handler the element has of its own.
 */
function initConfirm() {
    document.addEventListener(
        'click',
        (event) => {
            const trigger = event.target.closest('[data-confirm]');

            if (!trigger || trigger.dataset.confirmPending === 'yes') {
                return;
            }

            // Never intercept a click inside the toaster itself. Answering a
            // question is not an action that needs confirming, and treating it
            // as one would stop the answer ever arriving.
            if (trigger.closest('[data-toaster]')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            // A second click while the question is on screen is the same
            // question, not another one.
            trigger.dataset.confirmPending = 'yes';

            showConfirmToast(trigger.dataset.confirm, {
                confirmLabel: trigger.dataset.confirmLabel,
                cancelLabel: trigger.dataset.cancelLabel,
            })
                .then((answer) => {
                    delete trigger.dataset.confirmPending;

                    if (!answer) {
                        return;
                    }

                    if (trigger.tagName === 'A' && trigger.href) {
                        window.location.href = trigger.href;
                        return;
                    }

                    const form = trigger.form ?? trigger.closest('form');

                    if (!form) {
                        return;
                    }

                    /*
                     * requestSubmit with the button as the submitter, not
                     * form.submit(): the button's own name, value and
                     * formaction decide what the server is being asked to do,
                     * and form.submit() would throw all three away.
                     */
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(trigger.tagName === 'BUTTON' ? trigger : undefined);
                    } else if (trigger.name) {
                        const carry = document.createElement('input');
                        carry.type = 'hidden';
                        carry.name = trigger.name;
                        carry.value = trigger.value;
                        form.appendChild(carry);
                        form.submit();
                    } else {
                        form.submit();
                    }
                })
                .catch((error) => {
                    delete trigger.dataset.confirmPending;
                    console.error('[hrms] confirmation failed', error);
                });
        },
        true
    );
}

/** Submit a filter form as soon as a select changes. */
/**
 * A shadow under the action bar, but only while it is floating.
 *
 * The bar sticks with or without this — all that is added here is the cue that
 * it is hovering over more form rather than sitting at the end of one. The
 * trick is to watch the bar against a viewport shortened by a pixel: it stops
 * being fully visible exactly when it starts sticking.
 */
function initFormActions() {
    const bars = document.querySelectorAll('[data-form-actions]');

    if (!bars.length || typeof IntersectionObserver !== 'function') {
        return;
    }

    const observer = new IntersectionObserver(
        (entries) =>
            entries.forEach((entry) =>
                entry.target.classList.toggle('is-stuck', entry.intersectionRatio < 1)
            ),
        { threshold: [1], rootMargin: '0px 0px -1px 0px' }
    );

    bars.forEach((bar) => observer.observe(bar));
}

function initAutoSubmit() {
    document.querySelectorAll('[data-auto-submit]').forEach((element) =>
        element.addEventListener('change', () => element.form?.submit())
    );
}

/** Toggle the value input on a salary structure row when it is enabled. */
function initStructureRows() {
    document.querySelectorAll('[data-structure-row]').forEach((row) => {
        const toggle = row.querySelector('[data-structure-enabled]');
        const inputs = row.querySelectorAll('[data-structure-input]');
        if (!toggle) return;

        const sync = () =>
            inputs.forEach((input) => {
                input.disabled = !toggle.checked;
                row.classList.toggle('opacity-50', !toggle.checked);
            });

        toggle.addEventListener('change', sync);
        sync();
    });
}

/**
 * The notification editor: drop placeholders into whichever field was last in
 * use, and keep the preview beside the form in step with what is typed.
 */
function initTemplateEditor() {
    const form = document.querySelector('[data-template-form]');
    if (!form) return;

    const fields = Array.from(form.querySelectorAll('[data-template-field]'));
    const inputs = fields
        .map((field) => (field.matches('input, textarea') ? field : field.querySelector('input, textarea')))
        .filter(Boolean);

    // Insert a placeholder where the cursor was, rather than at the end.
    let lastFocused = inputs[0] || null;
    inputs.forEach((input) => input.addEventListener('focus', () => (lastFocused = input)));

    form.querySelectorAll('[data-placeholder]').forEach((chip) =>
        chip.addEventListener('click', () => {
            const target = lastFocused;
            if (!target) return;

            const token = chip.dataset.placeholder;

            // A field with a rich text editor on top places the token itself.
            if (target.richText) {
                target.richText.insert(token);
                return;
            }

            const start = target.selectionStart ?? target.value.length;
            const end = target.selectionEnd ?? target.value.length;

            target.value = target.value.slice(0, start) + token + target.value.slice(end);
            target.focus();
            target.setSelectionRange(start + token.length, start + token.length);
            target.dispatchEvent(new Event('input', { bubbles: true }));
        })
    );

    const url = form.dataset.previewUrl;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const box = form.querySelector('[data-preview]');
    if (!url || !box) return;

    const targets = {
        subject: box.querySelector('[data-preview-subject]'),
        body: box.querySelector('[data-preview-body]'),
        action: box.querySelector('[data-preview-action]'),
        title: box.querySelector('[data-preview-title]'),
        message: box.querySelector('[data-preview-message]'),
    };

    let pending = null;

    const refresh = async () => {
        const payload = new FormData();
        inputs.forEach((input) => payload.append(input.name, input.value));

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                body: payload,
            });

            if (!response.ok) return;

            const data = await response.json();

            if (targets.subject) targets.subject.textContent = data.subject;
            // The server has already escaped anything that was typed as markup.
            if (targets.body) targets.body.innerHTML = data.html;
            if (targets.action) {
                targets.action.textContent = data.action_label;
                targets.action.hidden = !data.action_label;
            }
            if (targets.title) targets.title.textContent = data.title;
            if (targets.message) targets.message.textContent = data.message;
        } catch {
            // A preview that cannot be fetched is not worth interrupting the
            // editor for; the wording is still saved by the form itself.
        }
    };

    inputs.forEach((input) =>
        input.addEventListener('input', () => {
            window.clearTimeout(pending);
            pending = window.setTimeout(refresh, 350);
        })
    );

    refresh();
}

/**
 * Repeating form rows — the field, task and troubleshooting lists in the help
 * editor. Rows are cloned from a <template>, so the markup lives in the Blade
 * file whether a row came from the database or was added here.
 */
function initRepeaters() {
    document.querySelectorAll('[data-repeater]').forEach((repeater) => {
        const rows = repeater.querySelector('[data-repeater-rows]');
        const template = repeater.querySelector('[data-repeater-template]');
        const add = repeater.querySelector('[data-repeater-add]');
        if (!rows || !template || !add) return;

        // Keep counting from the rows already on the page so a new row never
        // collides with an existing one's index.
        let next = rows.querySelectorAll('[data-repeater-row]').length;

        add.addEventListener('click', () => {
            const html = template.innerHTML.replaceAll('__INDEX__', String(next));
            const holder = document.createElement('div');
            holder.innerHTML = html.trim();
            const row = holder.firstElementChild;
            if (!row) return;

            rows.appendChild(row);
            row.querySelector('input, textarea')?.focus();
            next += 1;
        });

        // One listener for every row, including the ones added later.
        repeater.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-repeater-remove]');
            if (remove && repeater.contains(remove)) {
                remove.closest('[data-repeater-row]')?.remove();
            }
        });
    });
}

/**
 * The working-time counter in the header.
 *
 * The server hands over the seconds worked so far and whether the clock is
 * running; the browser only ticks it forward. A page left open over lunch
 * therefore shows the real figure rather than counting through the break.
 */
function initWorkedTimer() {
    const el = document.querySelector('[data-worked-timer]');
    if (!el) return;

    let seconds = Number.parseInt(el.dataset.workedSeconds || '0', 10);
    if (Number.isNaN(seconds)) seconds = 0;

    const running = el.dataset.workedRunning === '1';

    const render = () => {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        el.textContent = `${hours}h ${String(minutes).padStart(2, '0')}m ${String(secs).padStart(2, '0')}s`;
    };

    render();

    if (!running) return;

    window.setInterval(() => {
        seconds += 1;
        render();
    }, 1000);
}

/**
 * Start one feature, and keep going if it fails.
 *
 * These all share a bundle, so without this an error in any one of them —
 * a browser missing an API, markup that changed under it — takes down every
 * other one with it. Losing a live counter is a nuisance; losing the menu
 * button and the notification bell along with it leaves the page unusable,
 * and it is the same one-line failure either way.
 */
/**
 * "Use my current location" on the branch form.
 *
 * Filling the coordinates by hand means copying them out of a maps app and
 * hoping the digits survive the trip. Standing at the branch and pressing a
 * button is both easier and more likely to be right.
 */
function initLocationPicker() {
    const buttons = document.querySelectorAll('[data-locate]');
    if (!buttons.length) return;

    const status = document.querySelector('[data-locate-status]');
    const say = (message, tone) => {
        if (!status) return;
        status.textContent = message;
        status.className = `mt-3 text-sm ${tone === 'error' ? 'text-rose-600' : 'text-slate-500'}`;
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const geo = navigator.geolocation;

            if (!geo || typeof geo.getCurrentPosition !== 'function' || !window.isSecureContext) {
                say('This browser will only share a location over HTTPS. Type the coordinates in instead.', 'error');
                return;
            }

            button.disabled = true;
            say('Finding your location…');

            geo.getCurrentPosition(
                (position) => {
                    const latitude = document.getElementById(button.dataset.locateLatitude);
                    const longitude = document.getElementById(button.dataset.locateLongitude);

                    // Seven decimal places is about a centimetre — far finer
                    // than any phone reports, and what the column stores.
                    if (latitude) latitude.value = position.coords.latitude.toFixed(7);
                    if (longitude) longitude.value = position.coords.longitude.toFixed(7);

                    button.disabled = false;
                    say(`Filled in from your device, accurate to about ${Math.round(position.coords.accuracy)} m. Save the branch to keep it.`);
                },
                (error) => {
                    button.disabled = false;
                    say(
                        error?.code === 1
                            ? 'Location access is blocked for this site. Allow it in your browser, or type the coordinates in.'
                            : 'Your device could not report a position. Try again, or type the coordinates in.',
                        'error',
                    );
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
            );
        });
    });
}

/**
 * "Preview with sample values" on the letter wording screen.
 *
 * The words on screen are sent rather than the ones last saved, so somebody can
 * try a paragraph out before committing to it.
 */
function initLetterPreview() {
    const button = document.querySelector('[data-letter-preview]');
    if (!button) return;

    const card = document.querySelector('[data-letter-preview-card]');
    const subject = document.querySelector('[data-letter-preview-subject]');
    const body = document.querySelector('[data-letter-preview-body]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    button.addEventListener('click', async () => {
        button.disabled = true;

        try {
            const response = await fetch(button.dataset.previewUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    subject: document.getElementById('subject')?.value ?? '',
                    body: document.getElementById('body')?.value ?? '',
                }),
            });

            if (!response.ok) throw new Error(`Preview failed: ${response.status}`);

            const data = await response.json();
            subject.textContent = data.subject;
            body.innerHTML = data.html;
            card.hidden = false;
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (error) {
            // The only fetch in the shell, and the only place a failure had
            // nowhere to go but the console.
            console.error('[hrms] letter preview failed', error);
            showToast('The preview could not be built. Check your connection and try again.', 'error');
        } finally {
            button.disabled = false;
        }
    });
}

function start(name, fn) {
    try {
        fn();
    } catch (error) {
        console.error(`[hrms] ${name} failed to start`, error);
    }
}

onReady(() => {
    // The toaster comes first: it is how everything below reports a failure,
    // and how the server's own messages reach the screen at all.
    start('toaster', initToaster);

    // The navigation next: whatever else breaks, people can still move
    // around the application.
    start('sidebar', initSidebar);
    start('menus', initMenus);
    start('dialogs', initDialogs);

    start('tabs', initTabs);
    start('permission groups', initPermissionGroups);
    start('theme colours', initThemeColors);
    start('mail transport', initMailTransport);
    start('slab table', initSlabTable);
    start('punch forms', initPunchForms);
    start('flash', initFlash);
    start('clock', initClock);
    start('confirm', initConfirm);
    start('form actions', initFormActions);
    start('auto submit', initAutoSubmit);
    start('structure rows', initStructureRows);
    start('template editor', initTemplateEditor);
    start('repeaters', initRepeaters);
    start('worked timer', initWorkedTimer);
    start('location picker', initLocationPicker);
    start('letter preview', initLetterPreview);
});
