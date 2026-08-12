import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite-plus';

export default defineConfig({
    lint: {
        options: {
            typeAware: true,
            typeCheck: true,
        },
        plugins: ['eslint', 'typescript'],
        ignorePatterns: [
            'vite.config.js',
            'public/**',
            'vendor/**',
            'bootstrap/ssr/**',
            'storage/**',
        ],
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        useTabs: false,
        semi: true,
        singleQuote: true,
        overrides: [
            {
                files: ['**/*.yml'],
                options: {
                    tabWidth: 2,
                },
            },
        ],
        sortTailwindcss: {
            stylesheet: 'resources/css/site.css',
        },
        sortImports: {
            groups: [
                'builtin',
                'external',
                'internal',
                'parent',
                'sibling',
                'index',
            ],
            newlinesBetween: false,
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/site.css',
                'resources/css/central.css',
                'resources/css/page-editor-canvas.css',
                'resources/css/page-editor.css',
                'resources/css/page-canvas.css',
                'resources/js/site.ts',
                'resources/js/page-editor/canvas-glue.ts',
                'resources/js/page-editor/editor.ts',
                'resources/js/page-canvas/canvas.ts',
                'resources/css/filament/tenant/theme.css',
            ],
            refresh: true,
            fonts: [
                // Every family a FontPair (app/Design/FontPair.php) can pick
                // must be bundled here, and nothing else may be — both
                // directions are asserted by FontPairTest, which parses THIS
                // list rather than mirroring it. Names must match
                // viteAliases() exactly.

                // Signage. Archivo Black ships one cut, and that cut is the
                // look — a heading style asking for 700 or 800 snaps down to
                // it, which is the right answer rather than a near miss.
                bunny('Archivo Black', {
                    weights: [400],
                }),
                bunny('Archivo', {
                    weights: [400, 500, 600, 700],
                }),

                // Warm editorial. The one face bundled below 500:
                // TypeStyle::Serene sets its display at 300, and a
                // high-contrast serif snapped up to 500 is not a softer
                // version of that look but a different one.
                bunny('Newsreader', {
                    weights: [300, 400, 500, 600, 700],
                }),
                bunny('Figtree', {
                    weights: [400, 500, 600],
                }),

                // High contrast.
                bunny('Bodoni Moda', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Karla', {
                    weights: [400, 500, 600],
                }),

                // Neo-grotesque (the default) and contemporary, which share a
                // body face — one download covers both.
                bunny('Schibsted Grotesk', {
                    weights: [400, 500, 600, 700, 800],
                }),
                bunny('Bricolage Grotesque', {
                    weights: [500, 600, 700, 800],
                }),
                bunny('Public Sans', {
                    weights: [400, 500, 600],
                }),

                // Soft.
                bunny('Gabarito', {
                    weights: [500, 600, 700, 800],
                }),
                bunny('Onest', {
                    weights: [400, 500, 600],
                }),

                // Quiet serif. Instrument Serif is a single-weight display
                // face by design, same argument as Archivo Black.
                bunny('Instrument Serif', {
                    weights: [400],
                }),
                bunny('Instrument Sans', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
