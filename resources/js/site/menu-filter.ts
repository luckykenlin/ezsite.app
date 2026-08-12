/**
 * Course tabs on the offerings `menu-card` variant.
 *
 * The tabs are server-rendered `hidden` and only unhidden here, so a visitor
 * without JavaScript never sees dead chrome — they get the full menu, which is
 * the correct degradation for a filter whose only job is to shorten it. One
 * tab per course plus "All"; picking one hides every other course's section.
 *
 * The decision is exported as a pure function and unit-tested; the wiring that
 * queries the DOM and arms events is covered by the browser suite, per the
 * split documented in vitest.config.ts.
 */

/**
 * Whether a course section stays visible under the active tab. The empty
 * string is the "All" tab — the server renders `data-menu-tab=""` for it —
 * and shows everything, including sections that never had a course label.
 */
export function isGroupVisible(active: string, group: string): boolean {
    return active === '' || active === group;
}

export function initMenuFilter(root: ParentNode = document): void {
    const menus = Array.from(
        root.querySelectorAll<HTMLElement>('[data-menu-filter]'),
    );

    if (menus.length === 0) {
        return;
    }

    for (const menu of menus) {
        const tabBar = menu.querySelector<HTMLElement>('[data-menu-tabs]');
        const tabs = Array.from(
            menu.querySelectorAll<HTMLButtonElement>('[data-menu-tab]'),
        );
        const groups = Array.from(
            menu.querySelectorAll<HTMLElement>('[data-menu-group]'),
        );

        if (tabBar === null || tabs.length === 0 || groups.length === 0) {
            continue;
        }

        tabBar.hidden = false;

        for (const tab of tabs) {
            tab.addEventListener('click', () => {
                const active = tab.dataset.menuTab ?? '';

                for (const other of tabs) {
                    other.setAttribute('aria-pressed', String(other === tab));
                }

                for (const group of groups) {
                    group.hidden = !isGroupVisible(
                        active,
                        group.dataset.menuGroup ?? '',
                    );
                }
            });
        }
    }
}
