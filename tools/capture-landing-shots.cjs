#!/usr/bin/env node
// Captures the product screenshots shown on the landing page (public/images/landing/).
// Needs a running app and a student with some progress:
//   SHOT_URL=http://127.0.0.1:8001 SHOT_USER=demo.landing SHOT_PIN=xxxxxx node tools/capture-landing-shots.cjs
const { chromium } = require('@playwright/test');
const path = require('node:path');

const base = process.env.SHOT_URL || 'http://127.0.0.1:8000';
const user = process.env.SHOT_USER;
const pin = process.env.SHOT_PIN;
const lesson = process.env.SHOT_LESSON || 'fork';
const out = path.join(__dirname, '..', 'public', 'images', 'landing');

if (!user || !pin) {
    console.error('Set SHOT_USER and SHOT_PIN (a local demo student).');
    process.exit(1);
}

(async () => {
    const browser = await chromium.launch({ channel: process.env.PW_CHANNEL ?? 'chrome' });
    const page = await browser.newPage({ viewport: { width: 1100, height: 720 }, deviceScaleFactor: 1.5, colorScheme: 'light' });

    await page.goto(`${base}/masuk-murid`);
    await page.fill('#username', user);
    await page.fill('#pin', pin);
    await Promise.all([page.waitForURL('**/peta'), page.click('button[type=submit]')]);

    // Top of the map: greeting, streak, daily puzzle and the first finished stops.
    await page.waitForTimeout(600);
    await page.screenshot({ path: path.join(out, 'peta.jpg'), type: 'jpeg', quality: 80 });

    await page.goto(`${base}/pelajaran/${lesson}`);
    await page.waitForSelector('[data-chessflow] .sq, [data-chessflow] cg-board, [data-chessflow] .board', { timeout: 10000 }).catch(() => {});
    // Shoot the fork moment: wait for the demo's attack arrows, then for the move and arrow animations to finish.
    await page.waitForSelector('[data-chessflow] .arrows line', { timeout: 10000 }).catch(() => {});
    await page.waitForTimeout(700);
    await page.screenshot({ path: path.join(out, 'pelajaran.jpg'), type: 'jpeg', quality: 80 });

    await browser.close();
    console.log(`Saved peta.jpg and pelajaran.jpg to ${out}`);
})().catch((e) => {
    console.error(e);
    process.exit(1);
});
