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
            stylesheet: 'resources/css/app.css',
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
                'resources/css/app.css',
                'resources/css/site.css',
                'resources/css/page-editor-canvas.css',
                'resources/js/app.ts',
                'resources/js/page-editor/canvas.ts',
                'resources/js/page-editor/editor.ts',
                'resources/js/page-canvas/canvas.ts',
                'resources/css/filament/tenant/theme.css',
            ],
            refresh: true,
            fonts: [
                // Every family a FontPair (app/Design/FontPair.php) can pick
                // must be bundled here; names must match viteAliases() exactly.
                bunny('Instrument Sans', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Playfair Display', {
                    weights: [600, 700],
                }),
                bunny('Source Sans 3', {
                    weights: [400, 600],
                }),
                bunny('Fraunces', {
                    weights: [600, 700],
                }),
                bunny('Inter', {
                    weights: [400, 500, 600],
                }),
                bunny('Nunito', {
                    weights: [700, 800],
                }),
                bunny('Nunito Sans', {
                    weights: [400, 600],
                }),
                bunny('Space Grotesk', {
                    weights: [500, 700],
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
