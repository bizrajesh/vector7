/** Tailwind CSS 3 — build once with `npm run build:css`; the output public/css/app.css is committed
 *  so the live server never needs Node. */
module.exports = {
  content: ['./resources/views/**/*.blade.php', './app/**/*.php', './public/js/app.js', './config/*.php'],
  theme: {
    extend: {
      colors: {
        navy: { DEFAULT: '#0B1B33', 50: '#E8EBF0', 100: '#C9D0DB', 600: '#14294A', 700: '#0B1B33', 800: '#081428', 900: '#050D1A' },
        teal: { DEFAULT: '#0F8F84', 50: '#E7F5F3', 100: '#B0DAD6', 200: '#8ACBC5', 500: '#13A194', 600: '#0F8F84', 700: '#0B746B', 800: '#095C55' },
        gold: { DEFAULT: '#E0B04A' },
        muted: { DEFAULT: '#5B6677', light: '#929CAA' },
        page: '#EEF1F4',
      },
      fontFamily: { sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
      borderRadius: { card: '12px' },
      boxShadow: {
        card: '0 1px 2px rgba(11,27,51,.06), 0 4px 16px rgba(11,27,51,.06)',
        lift: '0 10px 30px rgba(11,27,51,.14)',
      },
    },
  },
  plugins: [require('@tailwindcss/forms')],
};
