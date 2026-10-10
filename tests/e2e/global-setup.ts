import { execFileSync } from 'node:child_process';

/**
 * Resets the e2e student/teacher accounts and hands their fresh credentials to the tests
 * (process.env set here is inherited by the test workers). See chessflow:e2e-fixtures.
 */
export default function globalSetup(): void {
    const out = execFileSync('php', ['artisan', 'chessflow:e2e-fixtures'], { env: { ...process.env, APP_ENV: 'local' }, encoding: 'utf8' });
    const json = out.trim().split('\n').pop() ?? '';
    JSON.parse(json); // fail early on unexpected output
    process.env.E2E_FIXTURES = json;
}
