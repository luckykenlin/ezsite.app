import { defineConfig } from 'vitest/config';

/**
 * Unit tests for the editor's browser code.
 *
 * Deliberately its own config rather than a `test` key on vite.config.js: that
 * file loads the Laravel and Tailwind plugins, none of which a plain function
 * test needs, and one of which wants a dev server to talk to.
 *
 * `node` is not the default for a front-end project, and it is the point. Only
 * code that can be exercised without a document belongs here — the parts that
 * need a real DOM are covered by the browser suite (tests/Browser) against a
 * real Chromium, not a simulated one. Keeping this environment DOM-less is what
 * stops jsdom/happy-dom from arriving as a dependency, and what keeps the line
 * between the two tiers from blurring.
 */
export default defineConfig({
    test: {
        include: ['resources/js/**/*.test.ts'],
        environment: 'node',
    },
});
