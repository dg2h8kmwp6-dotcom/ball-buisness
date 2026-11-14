/**
 * Visual check for the ball pit, in a real browser.
 *
 * The headless canvas stub in ball-pit.test.mjs records drawImage calls, so
 * it proves balls are *drawn* — it cannot see one shape painted over another.
 * A fill that swallowed the whole pit shipped past it once. This renders the
 * real page in Chromium and samples actual pixels instead.
 *
 * Usage: node tests/js/ball-pit.visual.mjs [baseUrl] [--screenshot path]
 */

import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { dirname } from 'node:path';

const baseUrl = process.argv[2]?.startsWith('http') ? process.argv[2] : 'http://127.0.0.1:8210';
const shotIndex = process.argv.indexOf('--screenshot');
const screenshotPath = shotIndex === -1 ? null : process.argv[shotIndex + 1];

/** Ball colours we expect to find, as RGB. */
const EXPECTED = {
    '#e8392e': [232, 57, 46],
    '#2b7fd4': [43, 127, 212],
    '#f6c62b': [246, 198, 43],
};

const failures = [];

function check(name, condition, detail = '') {
    if (condition) {
        console.log(`  PASS  ${name}`);
    } else {
        console.log(`  FAIL  ${name} ${detail}`);
        failures.push(name);
    }
}

/** How close two colours are, ignoring the pit's shading. */
function near([r, g, b], [tr, tg, tb], tolerance = 60) {
    return Math.abs(r - tr) < tolerance && Math.abs(g - tg) < tolerance && Math.abs(b - tb) < tolerance;
}

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 1000 }, deviceScaleFactor: 1 });

page.on('pageerror', (error) => {
    failures.push('page error');
    console.log(`  FAIL  page threw: ${error.message}`);
});
page.on('console', (message) => {
    if (message.type() === 'error') {
        console.log(`  note  console error: ${message.text()}`);
    }
});

console.log(`\nLoading ${baseUrl}/order`);
await page.goto(`${baseUrl}/order`, { waitUntil: 'networkidle' });

const canvas = page.locator('[data-ball-pit]');
await canvas.waitFor({ state: 'visible' });

// Order three colours so the pit has an obvious mix.
const rows = page.locator('[data-ball-row]');
for (const [index, quantity] of [[0, 60], [1, 40], [2, 30]]) {
    const input = rows.nth(index).locator('[data-qty-input]');
    await input.fill(String(quantity));
    await input.dispatchEvent('change');
}

// Let the debounce fire, the balls pour, and the pile settle.
await page.waitForTimeout(6500);

/** Read the canvas back as pixels. */
const sample = await page.evaluate(() => {
    const el = document.querySelector('[data-ball-pit]');
    const ctx = el.getContext('2d');
    const { width, height } = el;
    const data = ctx.getImageData(0, 0, width, height).data;

    const pixels = [];
    for (let i = 0; i < data.length; i += 4) {
        pixels.push([data[i], data[i + 1], data[i + 2], data[i + 3]]);
    }

    return { width, height, pixels };
});

console.log(`\nCanvas ${sample.width}x${sample.height}, ${sample.pixels.length} pixels\n`);

const opaque = sample.pixels.filter((p) => p[3] > 10);
check('the canvas is painted at all', opaque.length > 1000, `${opaque.length} opaque pixels`);

// Count pixels belonging to each ordered colour.
const hits = {};
for (const [hex, rgb] of Object.entries(EXPECTED)) {
    hits[hex] = sample.pixels.filter((p) => p[3] > 10 && near(p, rgb)).length;
}

const total = Object.values(hits).reduce((a, b) => a + b, 0);
const coverage = total / sample.pixels.length;

console.log(`  ball pixels: ${JSON.stringify(hits)}`);
console.log(`  coverage:    ${(coverage * 100).toFixed(1)}% of the canvas\n`);

check('balls are actually visible', total > 2000, `only ${total} ball pixels`);
check(
    'every ordered colour can be seen',
    Object.entries(hits).every(([, n]) => n > 200),
    JSON.stringify(hits),
);
check(
    'balls fill a meaningful part of the pit',
    coverage > 0.06,
    `${(coverage * 100).toFixed(1)}% coverage`,
);

// The pile should sit in the lower-middle of the canvas, not float or vanish.
const rowsWithBalls = [];
for (let y = 0; y < sample.height; y += 1) {
    let count = 0;
    for (let x = 0; x < sample.width; x += 1) {
        const p = sample.pixels[y * sample.width + x];
        if (p[3] > 10 && Object.values(EXPECTED).some((rgb) => near(p, rgb))) {
            count += 1;
        }
    }
    if (count > 0) {
        rowsWithBalls.push(y);
    }
}

const top = rowsWithBalls[0] ?? -1;
const bottom = rowsWithBalls[rowsWithBalls.length - 1] ?? -1;

console.log(`  vertical span: rows ${top}–${bottom} of ${sample.height}`);

check('the pile has real height', bottom - top > sample.height * 0.15, `span ${bottom - top}px`);
check('the pile sits inside the pit, not at the very top', top > sample.height * 0.08, `top row ${top}`);

if (screenshotPath) {
    mkdirSync(dirname(screenshotPath), { recursive: true });
    await canvas.screenshot({ path: screenshotPath });
    console.log(`\n  screenshot: ${screenshotPath}`);
}

await browser.close();

console.log(
    failures.length === 0
        ? '\nAll visual checks passed.\n'
        : `\n${failures.length} check(s) failed: ${failures.join(', ')}\n`,
);

process.exit(failures.length === 0 ? 0 : 1);
