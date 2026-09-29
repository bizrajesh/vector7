// Copies self-hosted vendor assets into /public (no third-party CDNs at runtime; strict CSP).
import { copyFileSync, mkdirSync } from 'node:fs';

mkdirSync('public/fonts', { recursive: true });
mkdirSync('public/js/vendor', { recursive: true });

for (const weight of [400, 500, 600, 700, 800]) {
    copyFileSync(
        `node_modules/@fontsource/plus-jakarta-sans/files/plus-jakarta-sans-latin-${weight}-normal.woff2`,
        `public/fonts/plus-jakarta-sans-${weight}.woff2`,
    );
}
copyFileSync('node_modules/chart.js/dist/chart.umd.min.js', 'public/js/vendor/chart.umd.min.js');
console.log('Vendor assets copied.');
