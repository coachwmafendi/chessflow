import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

// Accessibility check of every page type with axe-core (WCAG 2.1 A/AA rules), in light and dark mode.
// Uses the read-only "viewer" student so it never races the feature specs.

type Fixtures = { viewer: { username: string; pin: string }; teacher: { email: string; password: string }; classroomId: number };
const fx = (): Fixtures => JSON.parse(process.env.E2E_FIXTURES ?? 'null') as Fixtures;

async function loginViewer(page: Page): Promise<void> {
    await page.goto('/masuk-murid');
    await page.fill('#username', fx().viewer.username);
    await page.fill('#pin', fx().viewer.pin);
    await Promise.all([page.waitForURL('**/peta'), page.click('button[type=submit]')]);
}

async function loginTeacher(page: Page): Promise<void> {
    await page.goto('/login');
    await page.fill('input[type=email]', fx().teacher.email);
    await page.fill('input[type=password]', fx().teacher.password);
    await Promise.all([page.waitForURL((u) => !u.pathname.endsWith('/login')), page.click('button[type=submit]')]);
}

async function audit(page: Page, path: string): Promise<string[]> {
    await page.goto(path);
    // Measure the settled page: no fixed sleep, which proved flaky under a full parallel run.
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => document.fonts.ready);
    const r = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    return r.violations.map((v) => `${path} [${v.impact}] ${v.id}: ${v.help} — ${v.nodes.slice(0, 3).map((n) => n.target.join(' ') + (n.any[0]?.data ? ' ' + JSON.stringify(n.any[0].data) : '')).join(' | ')}`);
}

for (const scheme of ['light', 'dark'] as const) {
    test.describe(`a11y (${scheme})`, () => {
        // Reduced motion: colours are measured after transitions, not halfway through one.
        test.use({ colorScheme: scheme, reducedMotion: 'reduce' });

        test('public pages', async ({ page }) => {
            const found: string[] = [];
            for (const path of ['/', '/tentang', '/privasi', '/terma', '/masuk-murid', '/login', '/register']) found.push(...(await audit(page, path)));
            expect(found, found.join('\n')).toEqual([]);
        });

        test('student pages', async ({ page }) => {
            await loginViewer(page);
            const found: string[] = [];
            for (const path of ['/peta', '/pelajaran/papan', '/harian', '/main', '/latih', '/lencana']) found.push(...(await audit(page, path)));
            expect(found, found.join('\n')).toEqual([]);
        });

        test('teacher and parent pages', async ({ page }) => {
            await loginTeacher(page);
            const found: string[] = [];
            for (const path of ['/guru', `/guru/kelas/${fx().classroomId}`, '/anak', '/settings/profile']) found.push(...(await audit(page, path)));
            expect(found, found.join('\n')).toEqual([]);
        });
    });
}
