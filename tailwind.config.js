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
                primary: { DEFAULT: '#2563EB', hover: '#1D4ED8' },
                secondary: '#0891B2',
                accent: { DEFAULT: '#DC2626', hover: '#B91C1C' },
                emerald: { soft: '#10B981' },
                background: '#F0F3F7',
                surface: '#FFFFFF',
                foreground: '#0F172A',
                muted: '#F1F5F9',
                'muted-fg': '#64748B',
                border: '#E2E8F0',
                status: { green: '#22C55E', yellow: '#F59E0B', red: '#EF4444' },
            },
            fontFamily: {
                heading: ['Poppins', ...defaultTheme.fontFamily.sans],
                sans: ['"Open Sans"', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                token: '0.75rem',
            },
            boxShadow: {
                soft: '0 4px 20px -2px rgba(37,99,235,0.08)',
                lift: '0 10px 25px -5px rgba(37,99,235,0.12), 0 8px 10px -6px rgba(37,99,235,0.08)',
            },
        },
    },

    plugins: [forms],
};
