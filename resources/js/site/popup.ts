/**
 * The site-wide offer popup's trigger and frequency cap.
 *
 * Everything interruptive about this feature lives here, deliberately, so the
 * rules that keep it from being obnoxious are in one readable place:
 *
 *   - it never opens for a visitor who has already converted this session;
 *   - it never opens twice inside the operator's chosen cooling-off window;
 *   - it never opens on the page editor's canvas (the shell omits it there,
 *     and `site.ts` bails out first — this is the third belt);
 *   - it is always dismissible, and dismissing counts as a showing.
 *
 * Markup contract: `resources/views/components/site/popup.blade.php` renders a
 * `<dialog data-site-popup>` carrying its trigger config as data attributes.
 * A native dialog gives focus trapping and Escape for free.
 *
 * The decisions above are exported as pure functions and unit-tested; the
 * wiring that reads the DOM and arms browser events is covered by the browser
 * suite, per the split documented in vitest.config.ts.
 */

import { hasConverted } from './lead-form';

/** Mirrors App\Enums\PopupTrigger. */
export type Trigger = 'delay' | 'scroll' | 'exit_intent';

export const SEEN_KEY = 'ezsite:popup-seen';

const DAY_MS = 86_400_000;

/**
 * Whether the popup may be armed at all for this visitor.
 *
 * `frequencyDays` of 0 (or a malformed value) means "every page load", which
 * is the operator's choice to make and the least surprising reading of an
 * unset field.
 */
export function shouldArm(options: {
    converted: boolean;
    frequencyDays: number;
    seenAt: number | null;
    now: number;
}): boolean {
    if (options.converted) {
        return false;
    }

    const days = Number.isFinite(options.frequencyDays)
        ? options.frequencyDays
        : 0;

    if (days <= 0) {
        return true;
    }

    return (
        options.seenAt === null || options.now - options.seenAt > days * DAY_MS
    );
}

/**
 * How far down the document the visitor has scrolled, as a percentage.
 *
 * A page shorter than the viewport can never be scrolled, so it counts as
 * fully read — otherwise a scroll-triggered popup would simply never fire on
 * a short page, which reads as the feature being broken.
 */
export function scrolledPercent(viewport: {
    scrollY: number;
    innerHeight: number;
    scrollHeight: number;
}): number {
    const scrollable = viewport.scrollHeight - viewport.innerHeight;

    if (scrollable <= 0) {
        return 100;
    }

    return (viewport.scrollY / scrollable) * 100;
}

/**
 * The pointer leaving through the TOP of the viewport is the conventional
 * proxy for "reaching for the tab bar or the back button". Only the top edge —
 * leaving sideways or downward is ordinary mousing.
 */
export function isExitIntent(clientY: number): boolean {
    return clientY <= 0;
}

export function initPopup(root: ParentNode = document): void {
    const dialog = root.querySelector<HTMLDialogElement>('[data-site-popup]');

    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    const trigger = (dialog.dataset.popupTrigger ?? 'delay') as Trigger;
    const value = Number.parseInt(dialog.dataset.popupValue ?? '', 10);
    const frequencyDays = Number.parseInt(
        dialog.dataset.popupFrequency ?? '',
        10,
    );

    // The call bar's "Message us" opens the popup ON DEMAND, so it is wired
    // up regardless of the automatic trigger — a visitor asking for the form
    // is never an interruption, and the frequency cap must not silence them.
    revealOnDemandButton(root, dialog);

    const armable = shouldArm({
        converted: hasConverted(),
        frequencyDays,
        seenAt: readSeen(),
        now: Date.now(),
    });

    if (!armable) {
        return;
    }

    const open = once(() => {
        // Re-checked at fire time, not just at arm time: the visitor may have
        // converted through another form while the timer was running.
        if (hasConverted() || dialog.open) {
            return;
        }

        markSeen();
        dialog.showModal();
    });

    // Converting anywhere on the page disarms it for the rest of the visit.
    document.addEventListener('ezsite:lead-captured', () => {
        markSeen();

        if (dialog.open) {
            dialog.close();
        }
    });

    arm(trigger, Number.isFinite(value) ? value : 0, open);
}

function arm(trigger: Trigger, value: number, open: () => void): void {
    if (trigger === 'delay') {
        window.setTimeout(open, Math.max(0, value) * 1000);

        return;
    }

    if (trigger === 'scroll') {
        const onScroll = (): void => {
            const percent = scrolledPercent({
                scrollY: window.scrollY,
                innerHeight: window.innerHeight,
                scrollHeight: document.documentElement.scrollHeight,
            });

            if (percent >= Math.max(1, value)) {
                window.removeEventListener('scroll', onScroll);
                open();
            }
        };

        window.addEventListener('scroll', onScroll, { passive: true });

        return;
    }

    // Pointer-only, so it never fires on touch, where there is no such
    // gesture and an unprompted modal is pure obstruction.
    const onLeave = (event: MouseEvent): void => {
        if (isExitIntent(event.clientY)) {
            document.removeEventListener('mouseout', onLeave);
            open();
        }
    };

    document.addEventListener('mouseout', onLeave);
}

/**
 * Un-hides the call bar's "Message us" button and points it at the dialog.
 *
 * It ships hidden so it never appears without the script that makes it work —
 * a button that does nothing is worse than one that isn't there.
 */
function revealOnDemandButton(
    root: ParentNode,
    dialog: HTMLDialogElement,
): void {
    const button = root.querySelector<HTMLElement>('[data-call-bar-popup]');

    if (!button) {
        return;
    }

    button.hidden = false;
    button.addEventListener('click', () => {
        if (!dialog.open) {
            dialog.showModal();
        }
    });
}

function readSeen(): number | null {
    try {
        const raw = localStorage.getItem(SEEN_KEY);
        const parsed = raw === null ? Number.NaN : Number.parseInt(raw, 10);

        return Number.isFinite(parsed) ? parsed : null;
    } catch {
        return null;
    }
}

function markSeen(): void {
    try {
        localStorage.setItem(SEEN_KEY, String(Date.now()));
    } catch {
        // Storage refused; the popup simply isn't frequency-capped here.
    }
}

function once(fn: () => void): () => void {
    let called = false;

    return () => {
        if (called) {
            return;
        }

        called = true;
        fn();
    };
}
