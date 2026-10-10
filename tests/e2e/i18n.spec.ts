import { expect, test } from '@playwright/test';

// The header switch flips the whole UI, including the TS island, and the choice sticks.
test('language switch: English UI and English board island, then back to Bahasa Melayu', async ({ page }) => {
    await page.goto('/masuk-murid');
    await expect(page.locator('html')).toHaveAttribute('lang', 'ms');
    await expect(page.locator('h1')).toHaveText('Masuk murid');

    await page.getByRole('button', { name: 'Switch to English' }).click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('h1')).toHaveText('Student sign-in');

    // The island reads <html lang> and translates its own strings.
    await page.goto('/dev/playground?lesson=papan');
    await expect(page.locator('[data-chessflow] .next-btn')).toHaveText('Continue');
    await expect(page.locator('[data-chessflow] .task small')).toHaveText('Task');

    await page.goto('/masuk-murid');
    await page.getByRole('button', { name: 'Tukar ke Bahasa Melayu' }).click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'ms');
    await expect(page.locator('h1')).toHaveText('Masuk murid');
});
