/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Geist', '-apple-system', 'BlinkMacSystemFont', 'Inter', 'Segoe UI', 'sans-serif'],
        mono: ['Geist Mono', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
      },
      colors: {
        ink:        '#0E0F10',
        'ink-soft': '#1F2124',
        text:       '#2A2C30',
        'text-soft': '#5A5E66',
        'text-muted':'#8A8F99',
        'text-faint':'#B5BAC4',
        surface:    '#F4F4F1',
        'surface-2':'#FFFFFF',
        border:     '#E6E5E0',
        'border-soft':'#EFEEE9',
        cyan: {
          DEFAULT: '#00B5BD',
          dark:    '#00969C',
          tint:    'rgba(0, 181, 189, 0.10)',
        },
      },
      boxShadow: {
        soft:  '0 1px 2px rgba(20, 22, 26, 0.04), 0 1px 1px rgba(20, 22, 26, 0.03)',
        card:  '0 4px 12px rgba(20, 22, 26, 0.05), 0 1px 3px rgba(20, 22, 26, 0.04)',
        pop:   '0 18px 40px -16px rgba(20, 22, 26, 0.18), 0 6px 16px -8px rgba(20, 22, 26, 0.06)',
      },
      letterSpacing: {
        tightest: '-0.02em',
      },
      transitionTimingFunction: {
        'out-soft': 'cubic-bezier(0.22, 0.61, 0.36, 1)',
        'spring':   'cubic-bezier(0.34, 1.56, 0.64, 1)',
      },
    },
  },
}
