// Visual check: screenshots of key screens at four viewports.
// Usage: node scripts/screenshots.mjs [baseUrl] [outDir]
// Needs a seeded demo database (php artisan migrate:fresh --seed), a running
// server, and playwright (or playwright-core with an installed Edge/Chrome)
// resolvable via NODE_PATH. Not a project dependency on purpose.
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';

const require = createRequire(import.meta.url);
let pw;
try { pw = require('playwright'); } catch { pw = require('playwright-core'); }

const base = process.argv[2] ?? 'http://127.0.0.1:8000';
const out = process.argv[3] ?? 'docs/screenshots';
fs.mkdirSync(out, { recursive: true });

const viewports = [
    [1920, 1080],
    [1366, 768],
    [1024, 768],
    [390, 844],
];

const admin = [
    ['dashboard', '/app'],
    ['tenants', '/app/tenants'],
    ['tenant-profile', '/app/tenants/9'],
    ['lease', '/app/leases/3'],
    ['invoice', '/app/invoices/1'],
    ['announcement-create', '/app/announcements/create'],
    ['payments', '/app/payments'],
    ['maintenance-board', '/app/maintenance?view=kanban'],
    ['settings', '/app/settings'],
];
const portal = [
    ['portal-home', '/portal'],
    ['portal-pay', '/portal/pay'],
];
const publicPages = [
    ['public-home', '/'],
    ['public-listings', '/listings'],
];

const browser = await pw.chromium.launch({ channel: process.env.PW_CHANNEL ?? 'msedge' });

async function login(context, email) {
    const page = await context.newPage();
    await page.goto(base + '/login');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', 'password');
    await Promise.all([page.waitForNavigation(), page.click('form button[type="submit"]')]);
    await page.close();
}

async function shoot(context, name, url, file, setup) {
    const page = await context.newPage();
    if (setup) await page.addInitScript(setup);
    await page.goto(base + url, { waitUntil: 'networkidle' });
    await page.waitForTimeout(400); // charts and transitions settle
    // Report horizontal overflow and anything left of the viewport, as a
    // cheap automated hint next to the visual check.
    const issues = await page.evaluate(() => {
        const found = [];
        const doc = document.documentElement;
        if (doc.scrollWidth > doc.clientWidth + 1) found.push(`page scrolls sideways (${doc.scrollWidth} > ${doc.clientWidth})`);
        // Anything poking past the right edge of the viewport, unless it sits
        // inside a horizontal scroll container (wide tables).
        // Also skips visually hidden (sr-only, clipped) content such as mobile table headers.
        const scrollsX = (el) => { for (let p = el; p; p = p.parentElement) { const s = getComputedStyle(p); if (p !== el && (s.overflowX === 'auto' || s.overflowX === 'scroll')) return true; if (s.clip !== 'auto' && s.clip !== '') return true; } return false; };
        const seen = new Set();
        for (const el of document.querySelectorAll('main *, header *, footer *')) {
            const r = el.getBoundingClientRect();
            if (r.width === 0 || r.right <= window.innerWidth + 1 || getComputedStyle(el).position === 'fixed' || scrollsX(el)) continue;
            const label = `${el.tagName.toLowerCase()}.${[...el.classList].slice(0, 3).join('.')}`;
            if (!seen.has(label) && seen.size < 5) { seen.add(label); found.push(`${label} overflows right edge (${Math.round(r.right)} > ${window.innerWidth})`); }
        }
        const main = document.querySelector('main');
        const aside = document.querySelector('aside');
        if (main && aside && getComputedStyle(aside).position === 'fixed') {
            const a = aside.getBoundingClientRect();
            const m = main.getBoundingClientRect();
            if (a.right > 0 && a.left >= 0 && a.width > 0 && m.left < a.right - 1 && window.innerWidth >= 1024) {
                found.push(`main (left ${Math.round(m.left)}) under sidebar (right ${Math.round(a.right)})`);
            }
        }
        return found;
    });
    await page.screenshot({ path: path.join(out, file), fullPage: true });
    await page.close();
    return issues.map((i) => `${file}: ${i}`);
}

const problems = [];
for (const [w, h] of viewports) {
    const size = `${w}x${h}`;
    const isMobile = w < 768;

    // Admin as owner, sidebar expanded and (desktop widths) collapsed.
    const ctx = await browser.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: 1, isMobile, hasTouch: isMobile });
    await login(ctx, 'owner@demo.test');
    const states = w >= 1024 ? ['expanded', 'collapsed'] : ['mobile'];
    for (const state of states) {
        const value = state === 'collapsed' ? '1' : '0';
        const setup = `try { localStorage.setItem('rentora.sidebar.collapsed', '${value}') } catch (e) {}`;
        for (const [name, url] of admin) {
            problems.push(...await shoot(ctx, name, url, `${name}_${size}_${state}.png`, setup));
        }
    }
    await ctx.close();

    const tctx = await browser.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: 1, isMobile, hasTouch: isMobile });
    await login(tctx, 'tenant@demo.test');
    for (const [name, url] of portal) problems.push(...await shoot(tctx, name, url, `${name}_${size}.png`));
    await tctx.close();

    const pctx = await browser.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: 1, isMobile, hasTouch: isMobile });
    for (const [name, url] of publicPages) problems.push(...await shoot(pctx, name, url, `${name}_${size}.png`));
    await pctx.close();
    console.log(`done ${size}`);
}

await browser.close();
console.log(problems.length ? 'Possible problems:\n' + problems.join('\n') : 'No automated layout problems found.');
