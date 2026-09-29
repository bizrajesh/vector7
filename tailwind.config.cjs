/** Vector7 design tokens — palette: Navy #1C315E, Teal #227C70, Sage #88A47C, Cream #E6E2C3. */
module.exports = {
    content: ['./resources/views/**/*.blade.php', './app/**/*.php', './public/js/app.js'],
    // Status classes are built dynamically (badge-{css}, tile-{css}) so they must be kept explicitly.
    safelist: [
        ...['av', 'bk', 'rs', 'os', 'ror', 'reg', 'sold', 'od'].flatMap((s) => [`badge-${s}`, `tile-${s}`]),
    ],
    theme: {
        extend: {
            colors: {
                navy: { DEFAULT: '#1C315E', 900: '#14244A', 800: '#1C315E', 700: '#2A3F6E', 600: '#2E4A80', 200: '#B9C4DB', 100: '#DDE3EF' },
                teal: { DEFAULT: '#227C70', 700: '#1A6258', 600: '#227C70', 100: '#D9EEEA', 50: '#EEF7F5' },
                sage: { DEFAULT: '#88A47C', 700: '#5E7A53', 100: '#E6ECE0' },
                cream: { DEFAULT: '#E6E2C3', 300: '#EFECD6', 100: '#F7F5EA' },
                ground: '#F5F3E8',
                line: { DEFAULT: '#E2DECB', soft: '#EEEBDD' },
                ink: { DEFAULT: '#1A2238', 2: '#3D4560', muted: '#58607A' },
            },
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
            },
            borderRadius: { xl: '14px', '2xl': '16px' },
            boxShadow: { card: '0 6px 20px rgba(28,49,94,0.08)', sheet: '0 -12px 40px rgba(0,0,0,0.18)' },
        },
    },
    plugins: [],
};
