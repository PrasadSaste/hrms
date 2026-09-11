/**
 * The toaster: every message the application says out loud, and every question
 * it asks before doing something irreversible.
 *
 * Deliberately dependency-free and about two hundred lines. A toast library
 * would be a bigger download than the whole of the rest of this file, and the
 * behaviour that actually matters here — a confirmation that a keyboard can
 * answer, a stack that survives somebody clicking Delete five times — is the
 * part a library would not get right for us anyway.
 *
 * Two entry points:
 *
 *   showToast('Saved.', 'success')
 *   if (await showConfirmToast('Delete this?')) { ... }
 *
 * Both are also on `window.hrms`, so a Blade view can call them without being
 * part of the bundle.
 */

const MAX_VISIBLE = 4;
const DEFAULT_DURATION = 6000;
const ERROR_DURATION = 10000;

/** message+type seen within this long is a repeat, not a second toast. */
const DEDUPE_WINDOW = 4000;

const TYPES = {
    success: { icon: '✓', label: 'Success' },
    error: { icon: '!', label: 'Error' },
    warning: { icon: '!', label: 'Warning' },
    info: { icon: 'i', label: 'Information' },
};

/** @type {{ id: number, key: string, node: HTMLElement, timer: ?number, count: number, at: number, isConfirm: boolean, settle: ?function }[]} */
const live = [];

/** Only ever one question on screen: a pile of them cannot be answered. */
let pendingConfirm = null;

let sequence = 0;
let container = null;

function reducedMotion() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

/**
 * The region toasts live in.
 *
 * `aria-live="polite"` on the region announces ordinary toasts without cutting
 * across whatever a screen reader is already saying; an error carries
 * `role="alert"` on the toast itself so it does interrupt.
 */
function toaster() {
    if (container && document.body.contains(container)) {
        return container;
    }

    container = document.querySelector('[data-toaster]');

    if (!container) {
        container = document.createElement('div');
        container.setAttribute('data-toaster', '');
        document.body.appendChild(container);
    }

    container.className = 'hrms-toaster';
    container.setAttribute('role', 'region');
    container.setAttribute('aria-label', 'Notifications');
    container.setAttribute('aria-live', 'polite');
    container.setAttribute('aria-relevant', 'additions text');

    return container;
}

function remove(entry) {
    if (entry.timer) {
        clearTimeout(entry.timer);
        entry.timer = null;
    }

    // Belt and braces: a question taken off the screen by any route at all is
    // answered "no" rather than leaving its caller waiting for ever.
    if (entry.isConfirm && entry.settle) {
        const settle = entry.settle;
        entry.settle = null;
        settle(false);
    }

    const index = live.indexOf(entry);
    if (index !== -1) {
        live.splice(index, 1);
    }

    if (!entry.node.isConnected) {
        return;
    }

    if (reducedMotion()) {
        entry.node.remove();
        return;
    }

    entry.node.classList.add('is-leaving');
    entry.node.addEventListener('animationend', () => entry.node.remove(), { once: true });
    // Belt and braces: if the animation never fires the node still goes.
    setTimeout(() => entry.node.remove(), 400);
}

function startTimer(entry, duration) {
    if (!duration) {
        return;
    }

    entry.timer = setTimeout(() => remove(entry), duration);
}

/**
 * Show a message.
 *
 * @param {string} message
 * @param {'success'|'error'|'warning'|'info'} type
 * @param {{ duration?: number, title?: string, items?: string[], persist?: boolean }} options
 * @returns {number} an id, for dismissToast
 */
export function showToast(message, type = 'info', options = {}) {
    if (!message && !options.title) {
        return 0;
    }

    const kind = TYPES[type] ? type : 'info';
    const key = `${kind}:${options.title ?? ''}:${message}`;
    const now = Date.now();

    // Somebody pressing Save twice gets one toast that says so, not two
    // identical ones pushing the rest of the stack off the screen.
    const repeat = live.find((entry) => entry.key === key && now - entry.at < DEDUPE_WINDOW);

    if (repeat) {
        repeat.count += 1;
        repeat.at = now;
        const badge = repeat.node.querySelector('[data-toast-count]');
        badge.textContent = `×${repeat.count}`;
        badge.hidden = false;

        if (repeat.timer) {
            clearTimeout(repeat.timer);
        }
        startTimer(repeat, options.duration ?? (kind === 'error' ? ERROR_DURATION : DEFAULT_DURATION));

        return repeat.id;
    }

    const node = document.createElement('div');
    node.className = `hrms-toast hrms-toast--${kind}`;
    // An error interrupts; everything else waits its turn.
    node.setAttribute('role', kind === 'error' ? 'alert' : 'status');

    const list = (options.items ?? []).filter(Boolean);

    node.innerHTML = `
        <span class="hrms-toast__icon" aria-hidden="true">${TYPES[kind].icon}</span>
        <div class="hrms-toast__body">
            <span class="hrms-toast__type">${TYPES[kind].label}:</span>
            ${options.title ? `<p class="hrms-toast__title"></p>` : ''}
            <p class="hrms-toast__message"></p>
            ${list.length ? '<ul class="hrms-toast__list"></ul>' : ''}
        </div>
        <span class="hrms-toast__count" data-toast-count hidden></span>
        <button type="button" class="hrms-toast__close" aria-label="Dismiss this notification">&times;</button>
    `;

    // Written as text, never as markup: a message can carry somebody's name.
    if (options.title) {
        node.querySelector('.hrms-toast__title').textContent = options.title;
    }
    node.querySelector('.hrms-toast__message').textContent = message ?? '';

    if (list.length) {
        const ul = node.querySelector('.hrms-toast__list');
        list.forEach((item) => {
            const li = document.createElement('li');
            li.textContent = item;
            ul.appendChild(li);
        });
    }

    const entry = { id: ++sequence, key, node, timer: null, count: 1, at: now, isConfirm: false, settle: null };

    node.querySelector('.hrms-toast__close').addEventListener('click', () => remove(entry));

    // Reading a message should not be a race: hovering or tabbing into it
    // stops the clock, leaving starts it again.
    const duration = options.persist
        ? 0
        : (options.duration ?? (kind === 'error' ? ERROR_DURATION : DEFAULT_DURATION));

    const hold = () => entry.timer && clearTimeout(entry.timer);
    const resume = () => {
        hold();
        startTimer(entry, duration);
    };

    node.addEventListener('mouseenter', hold);
    node.addEventListener('mouseleave', resume);
    node.addEventListener('focusin', hold);
    node.addEventListener('focusout', resume);

    toaster().appendChild(node);
    live.push(entry);

    /*
     * A burst of activity trims itself from the oldest end rather than filling
     * the screen and hiding the page underneath. A question is never trimmed:
     * somebody is waiting on its answer, and taking it off the screen would
     * leave the promise behind it unresolved for ever.
     */
    while (live.filter((e) => !e.isConfirm).length > MAX_VISIBLE) {
        const oldest = live.find((e) => !e.isConfirm);
        if (!oldest) break;
        remove(oldest);
    }

    startTimer(entry, duration);

    return entry.id;
}

export function dismissToast(id) {
    const entry = live.find((e) => e.id === id);
    if (entry) {
        remove(entry);
    }
}

export function dismissAllToasts() {
    [...live].forEach(remove);
}

/**
 * Ask before doing something that cannot be undone.
 *
 * Resolves true when Confirm is pressed and false for everything else —
 * Cancel, Escape, or another confirmation being asked in the meantime. Never
 * rejects, so a caller can simply `if (await showConfirmToast(...))`.
 *
 * Focus moves into the toast and lands on **Cancel**: these are overwhelmingly
 * delete buttons, and the safe answer is the one a stray Enter should give.
 * Focus goes back where it came from afterwards.
 *
 * @param {string} message
 * @param {{ confirmLabel?: string, cancelLabel?: string, title?: string }} options
 * @returns {Promise<boolean>}
 */
export function showConfirmToast(message, options = {}) {
    // One question at a time. A second one answers the first with "no" rather
    // than stacking two dialogs nobody asked for.
    if (pendingConfirm) {
        pendingConfirm.settle(false);
    }

    return new Promise((resolve) => {
        const returnFocusTo = document.activeElement;

        const node = document.createElement('div');
        node.className = 'hrms-toast hrms-toast--confirm';
        // Deliberately not `data-confirm`: that is the attribute the shell's
        // click handler watches for, and marking the question with it made the
        // question itself look like something that needs confirming — which
        // swallowed the very click on Cancel that was meant to answer it.
        node.setAttribute('role', 'alertdialog');
        node.setAttribute('aria-labelledby', `toast-confirm-${++sequence}`);

        node.innerHTML = `
            <span class="hrms-toast__icon" aria-hidden="true">?</span>
            <div class="hrms-toast__body">
                ${options.title ? '<p class="hrms-toast__title"></p>' : ''}
                <p class="hrms-toast__message" id="toast-confirm-${sequence}"></p>
                <div class="hrms-toast__actions">
                    <button type="button" class="hrms-toast__action hrms-toast__action--cancel" data-cancel></button>
                    <button type="button" class="hrms-toast__action hrms-toast__action--confirm" data-confirm-action></button>
                </div>
            </div>
        `;

        if (options.title) {
            node.querySelector('.hrms-toast__title').textContent = options.title;
        }
        node.querySelector('.hrms-toast__message').textContent = message;

        const confirmButton = node.querySelector('[data-confirm-action]');
        const cancelButton = node.querySelector('[data-cancel]');

        confirmButton.textContent = options.confirmLabel ?? 'Confirm';
        cancelButton.textContent = options.cancelLabel ?? 'Cancel';

        const entry = { id: sequence, key: `confirm:${message}`, node, timer: null, count: 1, at: Date.now(), isConfirm: true, settle: null };

        const settle = (answer) => {
            if (pendingConfirm !== state) {
                return;
            }

            pendingConfirm = null;
            entry.settle = null;
            document.removeEventListener('keydown', onKeydown, true);
            remove(entry);

            // Put focus back where the person left it, so the page does not
            // silently lose their place.
            if (returnFocusTo instanceof HTMLElement && returnFocusTo.isConnected) {
                returnFocusTo.focus({ preventScroll: true });
            }

            resolve(answer);
        };

        const state = { settle };
        entry.settle = () => settle(false);

        function onKeydown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                settle(false);
                return;
            }

            // A small focus trap: while a question is open, Tab cycles between
            // its two answers rather than wandering off behind it.
            if (event.key === 'Tab') {
                const focusable = [cancelButton, confirmButton];
                const index = focusable.indexOf(document.activeElement);

                if (index === -1) {
                    event.preventDefault();
                    cancelButton.focus();
                    return;
                }

                event.preventDefault();
                focusable[(index + (event.shiftKey ? -1 : 1) + focusable.length) % focusable.length].focus();
            }
        }

        confirmButton.addEventListener('click', () => settle(true));
        cancelButton.addEventListener('click', () => settle(false));
        document.addEventListener('keydown', onKeydown, true);

        toaster().appendChild(node);
        live.push(entry);
        pendingConfirm = state;

        cancelButton.focus({ preventScroll: true });
    });
}

/**
 * Show whatever the server flashed for this request.
 *
 * The payload is JSON in the page rather than inline script, so a message
 * containing a quote or an apostrophe cannot break anything.
 */
export function initToaster() {
    window.hrms = Object.assign(window.hrms ?? {}, {
        showToast,
        showConfirmToast,
        dismissToast,
        dismissAllToasts,
    });

    document.querySelectorAll('[data-toast-payload]').forEach((script) => {
        let messages = [];

        try {
            messages = JSON.parse(script.textContent || '[]');
        } catch (error) {
            console.error('[hrms] could not read the flashed messages', error);
        }

        messages.forEach(({ message, type, title, items, persist }) =>
            showToast(message, type, { title, items, persist })
        );

        script.remove();
    });
}
