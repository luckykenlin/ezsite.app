/**
 * Sections settling into place as the visitor scrolls.
 *
 * The division of labour is the whole design: this file decides only WHEN an
 * element has arrived — it adds `.is-visible` and stops observing — while what
 * arriving looks like belongs to App\Design\MotionStyle, whose `--reveal-*`
 * variables the stylesheet reads. So there is no "motion is off" branch here:
 * the `still` style is a duration of zero, and this runs identically either way.
 *
 * It also does not touch the OPENING. The first screenful stages itself with a
 * CSS animation (`.site-stage` in resources/css/site.css), because anything this
 * script hides is visible until the bundle lands — and a first screen that
 * appears, vanishes and fades back in is worse than one that never moved. What
 * is left for JavaScript is the part CSS genuinely cannot do: noticing that
 * something further down the page has come into view.
 *
 * The order inside `initReveal` is load-bearing for the same reason.
 * `[data-site-reveal]` is what arms the hidden state in the stylesheet, so it
 * goes on last — after anything already on screen has been marked visible, and
 * only once there is an observer to un-hide the rest. No IntersectionObserver,
 * no attribute, and the page renders as plain HTML.
 *
 * Reduced motion is handled in CSS rather than here, so that honouring the
 * setting never depends on this file having run.
 */

/** Marks the root as reveal-capable; mirrors the selector in site.css. */
export const REVEAL_ATTRIBUTE = 'data-site-reveal';

/** Set on an element once it has arrived. */
export const VISIBLE_CLASS = 'is-visible';

/**
 * Whether an element is showing right now.
 *
 * Asked once per target at startup, and the answer decides whether it is
 * revealed immediately or handed to the observer — which is what keeps content
 * the visitor is already reading from flickering. Both edges count as showing:
 * a section straddling the fold is partly read, and a section taller than the
 * window has its top above it and its bottom below.
 */
export function isOnScreen(
    rect: { top: number; bottom: number },
    viewportHeight: number,
): boolean {
    return rect.top < viewportHeight && rect.bottom > 0;
}

export function initReveal(root: ParentNode = document): void {
    if (typeof IntersectionObserver === 'undefined') {
        return;
    }

    const targets = root.querySelectorAll<HTMLElement>('[data-animate]');

    if (targets.length === 0) {
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting) {
                continue;
            }

            entry.target.classList.add(VISIBLE_CLASS);
            observer.unobserve(entry.target);
        }
    });

    // The default threshold of 0 — any part of the element showing — rather
    // than a fraction or a negative bottom margin. Both of those have the same
    // failure mode from opposite ends: a section taller than the viewport can
    // never show a large fraction of itself, and a section sitting inside the
    // margin on a page too short to scroll never crosses the line. Either way
    // the content stays hidden for good, which is far worse than a reveal that
    // begins a moment early.
    for (const target of targets) {
        if (isOnScreen(target.getBoundingClientRect(), window.innerHeight)) {
            target.classList.add(VISIBLE_CLASS);

            continue;
        }

        observer.observe(target);
    }

    // Last, and only now: the stylesheet hides these elements from this point
    // on. Everything the visitor can currently see is already marked, and the
    // observer is live for the rest — and because none of this yielded, the
    // browser has had no chance to paint an intermediate state.
    revealRoot(root)?.setAttribute(REVEAL_ATTRIBUTE, '');
}

/**
 * The element the marker goes on. `<html>` for a document, so one attribute
 * covers the whole page; the container itself for the element roots tests and
 * future partial renders pass.
 */
function revealRoot(root: ParentNode): Element | null {
    if (root instanceof Document) {
        return root.documentElement;
    }

    return root instanceof Element ? root : null;
}
