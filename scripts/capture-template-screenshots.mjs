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

/** The two widths TemplateGallery reads back. Heights are full-page-capped. */
const VIEWPORTS = [
    { width: 1440, height: 1200 },
    { width: 390, height: 900 },
]

/*
 * JPEG at 1x, matching TemplateGallery.SCREENSHOT_EXTENSION. Lossless 2x
 * captures came out at 52 MB for sixteen files — for images that render at a
 * third of their captured width, inside a git history.
 */
const FORMAT = { type: 'jpeg', quality: 82 }

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

async function main() {
    await mkdir(OUTPUT_DIRECTORY, { recursive: true })

    const slugs = await templateSlugs()
    const browser = await chromium.launch()

    try {
        for (const slug of slugs) {
            for (const viewport of VIEWPORTS) {
                const page = await browser.newPage({ viewport })

                await page.goto(demoUrl(slug), { waitUntil: 'networkidle' })

                // The hero photograph is the whole point of the capture, and
                // it arrives lazily. Give the fonts and images a beat to
                // settle before the shutter.
                await page.waitForTimeout(1000)

                const target = path.join(OUTPUT_DIRECTORY, `${slug}-${viewport.width}.jpg`)

                await writeFile(target, await page.screenshot({ fullPage: false, ...FORMAT }))
                await page.close()

                console.log(`captured ${path.relative(process.cwd(), target)}`)
            }
        }
    } finally {
        await browser.close()
    }
}

await main()
