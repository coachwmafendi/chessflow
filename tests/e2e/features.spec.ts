import { expect, test, type Page } from '@playwright/test';
import { Chess } from 'chess.js';

// End-to-end checks of the features added after the lesson player: badges, teacher assignments,
// "Latih semula", the game (drag, resign confirmation, analysis), promotion by drag and the theme
// toggle. Accounts come from tests/e2e/global-setup.ts (chessflow:e2e-fixtures).

type Fixtures = {
    student: { username: string; pin: string };
    teacher: { email: string; password: string };
    classroomId: number;
};
const fx = (): Fixtures => JSON.parse(process.env.E2E_FIXTURES ?? 'null') as Fixtures;

// The tests share one student, so they run in order.
test.describe.configure({ mode: 'serial' });

async function loginStudent(page: Page): Promise<void> {
    await page.goto('/masuk-murid');
    await page.fill('#username', fx().student.username);
    await page.fill('#pin', fx().student.pin);
    await Promise.all([page.waitForURL('**/peta'), page.click('form:has(#pin) button[type=submit]')]);
}

async function loginTeacher(page: Page): Promise<void> {
    await page.goto('/login');
    await page.fill('input[type=email]', fx().teacher.email);
    await page.fill('input[type=password]', fx().teacher.password);
    await Promise.all([page.waitForURL((u) => !u.pathname.endsWith('/login')), page.click('form:has(input[type=password]) button[type=submit]')]);
}

const sqIndex = (s: string) => s.charCodeAt(0) - 97 + (Number(s[1]) - 1) * 8;
const square = (page: Page, s: string) => page.locator(`[data-chessflow] .sq[data-sq="${sqIndex(s)}"]`);

/** Drag with the real mouse (pointer events), the way a player would. */
async function drag(page: Page, from: string, to: string): Promise<void> {
    const a = await square(page, from).boundingBox();
    const b = await square(page, to).boundingBox();
    if (!a || !b) throw new Error('board not visible');
    await page.mouse.move(a.x + a.width / 2, a.y + a.height / 2);
    await page.mouse.down();
    await page.mouse.move(a.x + a.width / 2 + 12, a.y + a.height / 2 - 12, { steps: 3 });
    await page.mouse.move(b.x + b.width / 2, b.y + b.height / 2, { steps: 8 });
    await page.mouse.up();
}

test('badges: the map celebrates a new badge once and /lencana lists it', async ({ page }) => {
    await loginStudent(page);
    await expect(page.locator('.badge-toast')).toContainText('Langkah Pertama');

    await page.reload();
    await expect(page.locator('.badge-toast')).toHaveCount(0);

    await page.goto('/lencana');
    await expect(page.locator('.badge.earned')).toContainText(['Langkah Pertama']);
    await expect(page.locator('.page-head')).toContainText('1/14 dikumpul');
});

test('assignments: the student sees the task and can open the assigned lesson', async ({ page }) => {
    await loginStudent(page);
    const task = page.locator('.task-card', { hasText: 'Fork Kuda' });
    await expect(task).toContainText('Tugasan E2E');
    await task.click();
    await expect(page).toHaveURL(/\/pelajaran\/fork$/);
    await expect(page.locator('[data-chessflow] .lesson-title')).toHaveText('Fork Kuda');
});

test('latih semula: a due step is asked again and a right answer earns XP', async ({ page }) => {
    await loginStudent(page);
    await expect(page.locator('.qcard', { hasText: 'Latih semula' })).toContainText('1');

    await page.goto('/latih');
    await expect(page.locator('[data-chessflow] .step-title')).toHaveText('Untung atau rugi?');
    await page.waitForTimeout(2500); // the server ignores answers faster than min_seconds_per_step
    await page.click('.opt:has-text("Untung 2 mata")');
    await page.click('[data-chessflow] .next-btn');

    const card = page.locator('.modal .card');
    await expect(card).toContainText('Betul!');
    await expect(card).toContainText('+5 XP');
});

test('teacher: sets a lesson for the class and sees who has done it', async ({ page }) => {
    await loginTeacher(page);
    await page.goto(`/guru/kelas/${fx().classroomId}`);

    await page.selectOption('select[wire\\:model="lessonId"]', { label: 'Pin & Skewer' });
    await page.click('button:has-text("Beri tugasan")');
    await expect(page.locator('.assign-msg')).toHaveText('Tugasan diberi: Pin & Skewer.');

    const fork = page.locator('.assign-list li', { hasText: 'Fork Kuda' });
    await expect(fork).toContainText('0/2');
    await expect(fork).toContainText('Belum siap (2)');
});

test('game: drag moves, confirm before resigning, then review the game', async ({ page }) => {
    test.setTimeout(120_000);
    await loginStudent(page);
    await page.goto('/main');
    await expect(square(page, 'e2')).toBeVisible();

    // Quiet moves only, mirrored in chess.js; Pak Kuda's replies are read from the last-move highlight.
    const g = new Chess();
    for (let turn = 0; turn < 4; turn++) {
        const moves = g.moves({ verbose: true }).filter((m) => !m.captured && !m.promotion);
        const mv = moves[0];
        await drag(page, mv.from, mv.to);
        g.move(mv);
        await expect(page.locator('.moves ol li')).toHaveCount(turn + 1);
        await expect(page.locator('[data-chessflow] .turn')).toHaveText('Giliran awak', { timeout: 15_000 });
        const last = await page.evaluate(() => [...document.querySelectorAll('.sq.last')].map((e) => Number((e as HTMLElement).dataset.sq)));
        const reply = g.moves({ verbose: true }).find((m) => last.includes(sqIndex(m.from)) && last.includes(sqIndex(m.to)));
        expect(reply, 'Pak Kuda replied').toBeTruthy();
        g.move(reply!);
    }

    await page.click('[data-act="resign"]');
    await expect(page.getByRole('alertdialog')).toBeVisible();
    await page.click('[data-r="no"]');
    await expect(page.getByRole('alertdialog')).toHaveCount(0);
    await expect(page.locator('[data-chessflow] .status')).not.toContainText('mengaku kalah');

    await page.click('[data-act="resign"]');
    await page.click('[data-r="yes"]');
    await expect(page.locator('[data-chessflow] .status')).toContainText('mengaku kalah');

    await page.click('.analysis [data-act="analyse"]');
    await expect(page.locator('.analysis .mistakes, .analysis .analysis-good')).toBeVisible({ timeout: 60_000 });
});

test('puzzle: capture and promote by dragging, choosing the piece', async ({ page }) => {
    await page.goto('/dev/playground?lesson=bidak');
    await page.click('[data-chessflow] .next-btn');
    await expect(page.locator('[data-chessflow] .step-title')).toHaveText('Bidak makan serong');
    await drag(page, 'e4', 'd5');
    await expect(page.locator('[data-chessflow] .status')).toContainText('Bagus!');

    await page.click('[data-chessflow] .next-btn');
    await expect(page.locator('[data-chessflow] .step-title')).toHaveText('Promosi!');

    // The wrong piece is judged by the puzzle line...
    await drag(page, 'b7', 'b8');
    await page.click('.promo [data-p="n"]');
    await expect(page.locator('[data-chessflow] .status')).toHaveClass(/bad/);
    await expect(page.locator('[data-chessflow] .status')).toContainText('pilih buah lain');

    // ...and after it is undone, the queen is right.
    await expect(page.locator('[data-chessflow] .pc.wP')).toBeVisible();
    await page.waitForTimeout(1200);
    await drag(page, 'b7', 'b8');
    await expect(page.locator('.promo')).toBeVisible();
    await page.click('.promo [data-p="q"]');
    await expect(page.locator('[data-chessflow] .status')).toContainText('Promosi!');
});

test('theme: the toggle cycles, survives a reload and reaches the Flux pages', async ({ page }) => {
    await page.goto('/');
    const html = page.locator('html');
    await page.click('[data-theme-toggle]');
    await expect(html).toHaveAttribute('data-theme', 'light');
    await page.click('[data-theme-toggle]');
    await expect(html).toHaveAttribute('data-theme', 'dark');

    await page.reload();
    await expect(html).toHaveAttribute('data-theme', 'dark');
    await expect(page.locator('[data-theme-toggle]')).toHaveAttribute('aria-label', 'Tema: Gelap');
    await expect(page.locator('[data-theme-toggle]')).toHaveAttribute('data-tip', /Tema: Gelap/);

    await page.goto('/login');
    await expect(html).toHaveClass(/dark/);
});
