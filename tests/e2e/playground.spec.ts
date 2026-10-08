import { readFileSync } from 'node:fs';
import { expect, test } from '@playwright/test';

type LessonStub = { id: string; title: string; steps: unknown[] };

const { lessons } = JSON.parse(readFileSync('data/lessons.json', 'utf8')) as { lessons: LessonStub[] };

test('playground lists every lesson', async ({ page }) => {
    await page.goto('/dev/playground');
    await expect(page.locator('a[href*="lesson="]')).toHaveCount(lessons.length);
});

for (const lesson of lessons) {
    test(`lesson ${lesson.id} loads and steps through without console errors`, async ({ page }) => {
        const errors: string[] = [];
        page.on('pageerror', (e) => errors.push(e.message));
        page.on('console', (m) => {
            if (m.type() === 'error') errors.push(m.text());
        });

        await page.goto(`/dev/playground?lesson=${encodeURIComponent(lesson.id)}`);

        const root = page.locator('[data-chessflow]');
        await expect(root.locator('.lesson-title')).toHaveText(lesson.title);
        await expect(root.locator('.prog span')).toHaveCount(lesson.steps.length);
        await expect(root.locator('.step-title')).not.toBeEmpty();
        await expect(root.locator('.bw .sq').first()).toBeVisible();

        // Click "Teruskan" while the current step allows it (explain steps, etc.).
        const next = root.locator('.next-btn');
        for (let i = 0; i < lesson.steps.length && (await next.isEnabled()); i++) {
            await next.click();
        }

        expect(errors).toEqual([]);
    });
}
