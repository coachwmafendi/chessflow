import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        include: ['resources/ts/**/*.test.ts'],
        environment: 'node',
    },
});
