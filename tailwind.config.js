import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** Primary shades are CSS variables so the org primary_color can override them at runtime. */
const primary = (shade) => `rgb(var(--color-primary-${shade}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Enums/**/*.php',
        './app/View/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Inter Variable"', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                xs: ['12px', '16px'],
                sm: ['14px', '20px'],
                base: ['16px', '24px'],
                lg: ['20px', '28px'],
                xl: ['24px', '32px'],
                '2xl': ['32px', '40px'],
            },
            colors: {
                primary: {
                    50: primary(50),
                    600: primary(600),
                    700: primary(700),
                },
                surface: '#FFFFFF',
                canvas: '#F8F9FA',
                border: '#E3E6EA',
                ink: {
                    DEFAULT: '#202124',
                    muted: '#5F6368',
                },
                success: { DEFAULT: '#188038', bg: '#E6F4EA' },
                warning: { DEFAULT: '#B06000', bg: '#FEF7E0' },
                danger: { DEFAULT: '#D93025', bg: '#FCE8E6' },
                info: { DEFAULT: '#1967D2', bg: '#E8F0FE' },
            },
            borderRadius: {
                card: '12px',
                control: '8px',
            },
            boxShadow: {
                card: '0 1px 2px rgba(60,64,67,.15), 0 1px 3px 1px rgba(60,64,67,.08)',
            },
            width: {
                sidebar: '256px',
                rail: '72px',
            },
            maxWidth: {
                portal: '720px',
            },
        },
    },
    plugins: [forms],
};
