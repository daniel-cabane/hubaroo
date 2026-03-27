/** @type {import('tailwindcss').Config} */
export default {
  darkMode: ['class'],
  content: [
    './resources/**/*.{blade.php,js,vue}',
  ],
  theme: {
    extend: {
      colors: {
        // Light mode
        primary: 'hsl(20 88% 44%)',
        'primary-hover': 'hsl(21 97% 60%)',
        secondary: 'hsl(23 57% 67%)',
        'secondary-light': 'hsl(27 84% 80%)',
        success: 'hsl(143 63% 48%)',
        info: 'hsl(202 89% 54%)',
        warning: 'hsl(46 90% 63%)',
        error: 'hsl(0 96% 56%)',
        bg: 'hsl(37 67% 98%)',
        surface: 'hsl(0 0% 100%)',
        'text-main': 'hsl(23 15% 20%)',
        'text-muted': 'hsl(23 8% 52%)',
        border: 'hsl(28 18% 88%)',
      },
      borderRadius: {
        lg: '0.625rem',
        md: 'calc(0.625rem - 2px)',
        sm: 'calc(0.625rem - 4px)',
      },
    },
  },
  plugins: [],
}
