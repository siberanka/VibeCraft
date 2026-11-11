const copyStatus = document.querySelector('[data-copy-status]');
const copyTimers = new WeakMap();
for (const button of document.querySelectorAll('[data-copy]')) {
    button.addEventListener('click', async () => {
        const value = button.dataset.copy;
        const label = button.querySelector('[data-copy-label]');
        clearTimeout(copyTimers.get(button));
        try {
            if (!navigator.clipboard?.writeText) throw new Error('clipboard unavailable');
            await navigator.clipboard.writeText(value);
            button.dataset.state = 'copied';
            if (label) label.textContent = button.dataset.copyDone;
            if (copyStatus) {
                copyStatus.classList.add('sr-only');
                copyStatus.textContent = button.dataset.copied;
            }
            copyTimers.set(button, setTimeout(() => {
                delete button.dataset.state;
                if (label) label.textContent = button.dataset.copyReady;
            }, 2500));
        } catch {
            button.dataset.state = 'copy-fallback';
            if (label) label.textContent = button.dataset.copyReady;
            const address = button.querySelector('.copy-address strong');
            if (address && window.getSelection) {
                const range = document.createRange();
                range.selectNodeContents(address);
                const selection = window.getSelection();
                if (selection) {
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
            }
            if (copyStatus) {
                copyStatus.classList.remove('sr-only');
                copyStatus.textContent = button.dataset.copyFallback + ' ' + value;
            }
        }
    });
}
const disclosures = [...document.querySelectorAll('details[data-disclosure]')];
function closeDisclosure(details, restoreFocus = false) {
    details.open = false;
    if (restoreFocus) details.querySelector(':scope > summary')?.focus();
}
document.addEventListener('pointerdown', event => {
    for (const details of disclosures) {
        if (details.open && !details.contains(event.target)) closeDisclosure(details);
    }
});
document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    const details = disclosures.filter(item => item.open && item.contains(document.activeElement)).at(-1);
    if (details) {
        event.preventDefault();
        closeDisclosure(details, true);
    }
});
for (const details of disclosures) {
    details.addEventListener('focusout', () => {
        queueMicrotask(() => {
            if (details.open && !details.contains(document.activeElement)) closeDisclosure(details);
        });
    });
}

const dialog = document.querySelector('[data-confirm-dialog]');
const confirmationMessage = dialog?.querySelector('[data-confirm-message]');
const allowedSubmissions = new WeakSet();
const pendingForms = new Map();
const actionStatus = document.querySelector('[data-action-status]');
let confirmation = null;
function resumeSubmission(form, submitter) {
    allowedSubmissions.add(form);
    try {
        if (typeof form.requestSubmit === 'function') form.requestSubmit(submitter || undefined);
        else if (submitter) submitter.click();
        else {
            const trigger = document.createElement('button');
            trigger.type = 'submit';
            trigger.hidden = true;
            form.append(trigger);
            trigger.click();
            trigger.remove();
        }
    } finally {
        allowedSubmissions.delete(form);
    }
}
function cancelConfirmation() {
    const previous = confirmation;
    confirmation = null;
    dialog?.close();
    if (previous?.submitter?.isConnected) previous.submitter.focus();
}
dialog?.querySelector('[data-confirm-cancel]')?.addEventListener('click', cancelConfirmation);
dialog?.addEventListener('cancel', () => { confirmation = null; });
dialog?.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) cancelConfirmation();
});
dialog?.querySelector('[data-confirm-accept]')?.addEventListener('click', () => {
    const previous = confirmation;
    confirmation = null;
    dialog.close();
    if (previous?.form?.isConnected) resumeSubmission(previous.form, previous.submitter);
});
document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    if (pendingForms.has(form)) {
        event.preventDefault();
        return;
    }
    if (form.dataset.confirm && !allowedSubmissions.has(form)) {
        if (dialog && typeof dialog.showModal === 'function') {
            event.preventDefault();
            confirmation = { form, submitter: event.submitter };
            confirmationMessage.textContent = form.dataset.confirm;
            dialog.showModal();
            dialog.querySelector('[data-confirm-cancel]').focus();
            return;
        }
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
            return;
        }
    }
    if (!form.hasAttribute('data-submit-once') || !form.checkValidity()) return;
    const buttons = [...form.querySelectorAll('button[type="submit"], input[type="submit"]')].filter(button => !button.disabled);
    pendingForms.set(form, buttons.map(button => ({ button, busy: button.getAttribute('aria-busy'), disabled: button.getAttribute('aria-disabled') })));
    form.setAttribute('aria-busy', 'true');
    for (const button of buttons) {
        // Native disabled would omit a named submitter from the serialized form.
        button.setAttribute('aria-disabled', 'true');
        if (button === event.submitter) button.setAttribute('aria-busy', 'true');
    }
    if (actionStatus) actionStatus.textContent = actionStatus.dataset.busyLabel;
});
window.addEventListener('pageshow', () => {
    for (const [form, buttons] of pendingForms) {
        form.removeAttribute('aria-busy');
        for (const { button, busy, disabled } of buttons) {
            if (busy === null) button.removeAttribute('aria-busy');
            else button.setAttribute('aria-busy', busy);
            if (disabled === null) button.removeAttribute('aria-disabled');
            else button.setAttribute('aria-disabled', disabled);
        }
    }
    pendingForms.clear();
    if (actionStatus) actionStatus.textContent = '';
});

// Prism theme: the glass panes share one highlight that follows a fine pointer.
if (document.documentElement.dataset.theme === 'prism' && matchMedia('(pointer: fine)').matches && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    let lightFrame = 0;
    window.addEventListener('pointermove', event => {
        if (lightFrame) return;
        lightFrame = requestAnimationFrame(() => {
            lightFrame = 0;
            document.documentElement.style.setProperty('--prism-light-x', `${event.clientX}px`);
            document.documentElement.style.setProperty('--prism-light-y', `${event.clientY}px`);
        });
    }, { passive: true });
}

// Hidden celebration. Each theme styles it; nothing here creates content or reads input values.
const celebrationKeys = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
let celebrationStep = 0;
let celebrationTimer = 0;
function celebrate() {
    const root = document.documentElement;
    clearTimeout(celebrationTimer);
    root.dataset.egg = 'on';
    celebrationTimer = setTimeout(() => { delete root.dataset.egg; }, 6000);
}
document.addEventListener('keydown', event => {
    if (event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable]')) return;
    const key = event.key.length === 1 ? event.key.toLowerCase() : event.key;
    celebrationStep = key === celebrationKeys[celebrationStep] ? celebrationStep + 1 : (key === celebrationKeys[0] ? 1 : 0);
    if (celebrationStep === celebrationKeys.length) {
        celebrationStep = 0;
        celebrate();
    }
});
const siteMark = document.querySelector('.home-page .site-header .wordmark');
let markPresses = [];
siteMark?.addEventListener('click', event => {
    // On the home page the mark returns to the top instead of reloading the same page.
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
    event.preventDefault();
    window.scrollTo({ top: 0 });
    const now = Date.now();
    markPresses = markPresses.filter(time => now - time < 2500);
    markPresses.push(now);
    if (markPresses.length >= 5) {
        markPresses = [];
        celebrate();
    }
});

// Live pointer effects: the public page runs the theme's effect; the admin preview pad runs the one being edited.
const pointerRoot = document.documentElement;
const pointerPreview = document.querySelector('[data-pointer-preview]');
if ((pointerRoot.dataset.pointer || pointerPreview) && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    import('./pointer-fx.js').then(({ start }) => {
        if (pointerRoot.dataset.pointer) {
            start({ effect: pointerRoot.dataset.pointer, strength: pointerRoot.dataset.pointerStrength, touch: 'pointerTouch' in pointerRoot.dataset });
            return;
        }
        const form = pointerPreview.closest('form');
        const chosen = () => {
            const value = form?.querySelector('[name="pointer_effect"]')?.value ?? 'default';
            return value === 'default' ? pointerPreview.dataset.pointerDefault : value;
        };
        const strength = () => form?.querySelector('[name="pointer_strength"]')?.value ?? 'normal';
        const effects = start({ effect: chosen(), strength: strength(), touch: true, scope: pointerPreview });
        form?.addEventListener('change', () => effects.set(chosen(), strength()));
    }).catch(() => {});
}
