/**
 * Progressive enhancement for the public capture forms.
 *
 * Every form works without this file — it posts, the server redirects back,
 * and the thank-you comes through the session. What the enhancement buys is
 * submitting WITHOUT a page reload, which the popup needs to exist at all (a
 * redirect would close it) and which stops every other surface throwing the
 * visitor back to the top of the page.
 *
 * The markup contract is `resources/views/components/lead-form.blade.php`:
 * a `[data-lead-form]` form next to a `[data-lead-success]` panel, both
 * inside the same wrapper, toggled by the `hidden` attribute.
 */

/** How the server answers an enhanced submit. */
type LeadResponse = { ok?: boolean; form_id?: string };

const FORM = '[data-lead-form]';
const SUCCESS = '[data-lead-success]';
const ERROR_CLASS = 'text-sm text-error';

/**
 * Set once a visitor converts, so the popup can stop interrupting someone who
 * has already done what it was asking for. Session-scoped rather than
 * localStorage: a returning visitor next week is a fresh prospect.
 */
export const CONVERTED_KEY = 'ezsite:lead-submitted';

export function initLeadForms(root: ParentNode = document): void {
    root.querySelectorAll<HTMLFormElement>(FORM).forEach(enhance);
}

function enhance(form: HTMLFormElement): void {
    form.addEventListener('submit', (event: SubmitEvent) => {
        event.preventDefault();
        void submit(form);
    });
}

async function submit(form: HTMLFormElement): Promise<void> {
    const button = form.querySelector<HTMLButtonElement>(
        'button[type="submit"]',
    );

    if (button?.disabled === true) {
        return;
    }

    setBusy(form, button, true);

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (response.status === 422) {
            showErrors(form, await readErrors(response));

            return;
        }

        if (!response.ok) {
            throw new Error(`Unexpected status ${String(response.status)}`);
        }

        const body = (await response.json()) as LeadResponse;

        if (body.ok !== true) {
            throw new Error('Submission was not accepted');
        }

        succeed(form);
    } catch {
        // The network or the server failed in a way we can't explain in the
        // page. Falling back to a native submit is better than a dead button:
        // the visitor gets the server's own error page, or the lead simply
        // goes through the way it would with JS off.
        form.submit();
    } finally {
        setBusy(form, button, false);
    }
}

/**
 * Swap the form for its thank-you panel. The form is hidden rather than
 * removed so a second enquiry in the same session is still possible.
 */
function succeed(form: HTMLFormElement): void {
    clearErrors(form);

    const success = form.parentElement?.querySelector<HTMLElement>(SUCCESS);

    form.hidden = true;

    if (success) {
        success.hidden = false;
        // Announce it to a screen reader, which will not have noticed a
        // silent attribute change on a region it was not watching.
        success.setAttribute('role', 'status');
    }

    try {
        sessionStorage.setItem(CONVERTED_KEY, '1');
    } catch {
        // Private browsing can refuse storage; converting still worked.
    }

    form.dispatchEvent(
        new CustomEvent('ezsite:lead-captured', { bubbles: true }),
    );
}

export function hasConverted(): boolean {
    try {
        return sessionStorage.getItem(CONVERTED_KEY) === '1';
    } catch {
        return false;
    }
}

async function readErrors(
    response: Response,
): Promise<Record<string, string[]>> {
    try {
        const body = (await response.json()) as {
            errors?: Record<string, string[]>;
        };

        return body.errors ?? {};
    } catch {
        return {};
    }
}

function showErrors(
    form: HTMLFormElement,
    errors: Record<string, string[]>,
): void {
    clearErrors(form);

    let first: HTMLElement | null = null;

    for (const [field, messages] of Object.entries(errors)) {
        const input = form.querySelector<HTMLElement>(
            `[name="${CSS.escape(field)}"]`,
        );
        const message = messages[0];

        if (!input || message === undefined) {
            continue;
        }

        input.classList.add(
            input.tagName === 'TEXTAREA' ? 'textarea-error' : 'input-error',
        );
        input.setAttribute('aria-invalid', 'true');

        const note = document.createElement('p');
        note.className = ERROR_CLASS;
        note.dataset.leadError = '';
        note.textContent = message;
        // The label wraps the input, so the note belongs after the label.
        (input.closest('label') ?? input).after(note);

        first ??= input;
    }

    first?.focus();
}

function clearErrors(form: HTMLFormElement): void {
    form.querySelectorAll('[data-lead-error]').forEach((note) => {
        note.remove();
    });
    form.querySelectorAll('.input-error, .textarea-error').forEach((input) => {
        input.classList.remove('input-error', 'textarea-error');
        input.removeAttribute('aria-invalid');
    });
}

function setBusy(
    form: HTMLFormElement,
    button: HTMLButtonElement | null,
    busy: boolean,
): void {
    form.setAttribute('aria-busy', busy ? 'true' : 'false');

    if (button) {
        button.disabled = busy;
    }
}
