import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                primary: { DEFAULT: 'var(--color-primary)', hover: 'var(--color-primary-hover)' },
                secondary: 'var(--color-secondary)',
                accent: { DEFAULT: 'var(--color-accent)', hover: 'var(--color-accent-hover)' },
                background: 'var(--color-background)',
                surface: 'var(--color-surface)',
                foreground: 'var(--color-foreground)',
                muted: 'var(--color-muted)',
                'muted-fg': 'var(--color-muted-fg)',
                border: 'var(--color-border)',
                status: { green: 'var(--color-green)', yellow: 'var(--color-yellow)', red: 'var(--color-red)' },
            },
            fontFamily: {
                heading: ['Poppins', ...defaultTheme.fontFamily.sans],
                sans: ['"Open Sans"', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                token: 'var(--radius)',
            },
            boxShadow: {
                soft: '0 1px 3px rgba(2,132,199,.06), 0 4px 16px rgba(2,132,199,.06)',
                lift: '0 8px 30px rgba(2,132,199,.12)',
            },
        },
    },

    plugins: [forms],
};
