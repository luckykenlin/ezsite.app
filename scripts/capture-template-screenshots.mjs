/*
 * Capture the gallery's template screenshots.
 *
 * The gallery shows PRE-RENDERED images, not live iframes: eight iframes on
 * one page is eight full page loads, which is unusable on a phone, and the
 * editor's signed preview URLs expire in seven days — no good for a permanent
 * gallery. So the captures are committed, and this is what makes them.
 *
 * Run it against a server that is already serving the demo sites:
 *
 *     php artisan demo:seed
 *     npm run build && php artisan serve --host 0.0.0.0 --port 8000   # or Herd/Valet
 *     node scripts/capture-template-screenshots.mjs
 *
 * The list of templates comes from the central sitemap rather than from a
 * hard-coded array, so a ninth template is captured the day it is declared.
 * Output lands in public/images/templates/{slug}-{width}.png, which is exactly
 * where App\Templates\TemplateGallery looks for it.
 */

import { mkdir, writeFile } from 'node:fs/promises'
import path from 'node:path'
import { chromium } from 'playwright'

const BASE_URL = (process.env.CENTRAL_URL ?? 'http://ezsite.test').replace(/\/$/, '')
const OUTPUT_DIRECTORY = path.resolve(import.meta.dirname, '../public/images/templates')

/**
 * The three widths TemplateGallery reads back, and the format each one is
 * stored in — keep both columns in step with that class.
 *
 * WebP everywhere except the 1440 desktop shot, which is also the `og:image`
 * on every template detail page and stays JPEG for the crawlers. WebP is
 * roughly 45% smaller here, and it costs no dependency: Playwright's own
 * Chromium encodes it through a canvas (see `encode()`), which `page.screenshot()`
 * itself cannot do — it only emits PNG and JPEG.
 */
const VIEWPORTS = [
    { width: 800, height: 700, format: 'image/webp', extension: 'webp', quality: 0.82 },
    { width: 1440, height: 1200, format: 'image/jpeg', extension: 'jpg', quality: 0.82 },
    { width: 780, height: 1400, format: 'image/webp', extension: 'webp', quality: 0.82 },
]

/** Template slugs, read off the central sitemap. */
async function templateSlugs() {
    const response = await fetch(`${BASE_URL}/sitemap.xml`)

    if (!response.ok) {
        throw new Error(`The central sitemap answered ${response.status}. Is the server running at ${BASE_URL}?`)
    }

    const xml = await response.text()
    const slugs = [...xml.matchAll(/\/templates\/([a-z0-9-]+)</g)].map((match) => match[1])

    if (slugs.length === 0) {
        throw new Error('The central sitemap listed no templates.')
    }

    return slugs
}

/**
 * A demo site's URL. The subdomain convention mirrors
 * SiteTemplate::demoSubdomain() — the one thing this script has to know about
 * the application, and the reason it is stated here rather than inferred.
 */
function demoUrl(slug) {
    const url = new URL(BASE_URL)

    return `${url.protocol}//demo-${slug}.${url.host}/`
}

/**
 * Re-encode a PNG screenshot through the page's own canvas.
 *
 * Chromium ships a WebP encoder that `page.screenshot()` does not expose;
 * `canvas.toDataURL('image/webp', q)` does. That is the whole reason this
 * needs no image library — no `sharp`, no `cwebp`, nothing for CI to install.
 * (AVIF is not available this way: Chromium's canvas silently falls back to
 * PNG for it, which is worth knowing before anyone tries.)
 */
async function encode(page, png, { format, quality }) {
    const dataUrl = await page.evaluate(
        async ([source, type, q]) => {
            const image = new Image()
            image.src = source
            await image.decode()

            const canvas = document.createElement('canvas')
            canvas.width = image.naturalWidth
            canvas.height = image.naturalHeight
            canvas.getContext('2d').drawImage(image, 0, 0)

            return canvas.toDataURL(type, q)
        },
        [`data:image/png;base64,${png.toString('base64')}`, format, quality],
    )

    return Buffer.from(dataUrl.split(',')[1], 'base64')
}

async function main() {
    await mkdir(OUTPUT_DIRECTORY, { recursive: true })

    const slugs = await templateSlugs()
    const browser = await chromium.launch()

    try {
        for (const slug of slugs) {
            for (const viewport of VIEWPORTS) {
                const page = await browser.newPage({ viewport: { width: viewport.width, height: viewport.height } })

                await page.goto(demoUrl(slug), { waitUntil: 'networkidle' })

                // The hero photograph is the whole point of the capture, and
                // it arrives lazily. Give the fonts and images a beat to
                // settle before the shutter.
                await page.waitForTimeout(1000)

                const png = await page.screenshot({ fullPage: false })
                const target = path.join(OUTPUT_DIRECTORY, `${slug}-${viewport.width}.${viewport.extension}`)

                await writeFile(target, await encode(page, png, viewport))
                await page.close()

                console.log(`captured ${path.relative(process.cwd(), target)}`)
            }
        }
    } finally {
        await browser.close()
    }
}

await main()
