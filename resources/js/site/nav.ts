/**
 * The small-screen menu's manners.
 *
 * The menu itself is a `<details data-site-nav>` in the header views, so it
 * opens and closes with no JavaScript at all — a visitor whose bundle never
 * arrives still gets a working navigation, which is the whole reason it is a
 * disclosure and not a scripted drawer. This module only adds the three things
 * a bare `<details>` does not do:
 *
 *   - Escape closes it, the gesture anything overlay-shaped owes the keyboard;
 *   - a pointer landing outside it closes it, because it covers the page and a
 *     visitor who taps the page has said they are done with the menu;
 *   - following a link inside it closes it, which matters for the in-page
 *     anchors (`#contact`) that navigate without a reload — the menu would
 *     otherwise stay open over the section it just scrolled to.
 *
 * Deliberately NO `aria-expanded` bookkeeping: `<summary>` already exposes the
 * open state of its `<details>` natively, and a hand-maintained attribute on
 * top of that is a second source of truth for assistive tech to disagree with.
 *
 * The decisions are exported as pure functions and unit-tested; the wiring that
 * queries the DOM and arms events is covered by the browser suite, per the
 * split documented in vitest.config.ts.
 */

export function isDismissKey(key: string): boolean {
    return key === 'Escape';
}

/**
 * A pointer event closes the menu only when it is open AND landed outside it.
 * Both halves matter: without the first, every tap on the page runs a needless
 * write, and without the second, tapping the menu's own links would close it
 * before the link resolved.
 */
export function shouldCloseOnPointer(state: {
    open: boolean;
    insideMenu: boolean;
}): boolean {
    return state.open && !state.insideMenu;
}

export function initNav(root: ParentNode = document): void {
    const menus = Array.from(
        root.querySelectorAll<HTMLDetailsElement>('[data-site-nav]'),
    );

    if (menus.length === 0) {
        return;
    }

    document.addEventListener('keydown', (event: KeyboardEvent) => {
        if (!isDismissKey(event.key)) {
            return;
        }

        for (const menu of menus) {
            if (!menu.open) {
                continue;
            }

            menu.open = false;
            // Focus goes back to the control that opened it, or the visitor is
            // left tabbing from the top of the document.
            menu.querySelector('summary')?.focus();
        }
    });

    document.addEventListener('pointerdown', (event: PointerEvent) => {
        const target = event.target;

        for (const menu of menus) {
            const insideMenu = target instanceof Node && menu.contains(target);

            if (shouldCloseOnPointer({ open: menu.open, insideMenu })) {
                menu.open = false;
            }
        }
    });

    for (const menu of menus) {
        // `click` rather than `pointerdown` here: the menu must still be open
        // when the browser resolves the activation, and a keyboard Enter on a
        // link never fires a pointer event at all.
        menu.addEventListener('click', (event: MouseEvent) => {
            const target = event.target;

            if (target instanceof Element && target.closest('a')) {
                menu.open = false;
            }
        });
    }
}
