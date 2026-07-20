import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    // is-visible is only ever added via classList.add() in resources/js/reveal.js,
    // never literally written in a .blade.php file, so Tailwind's content scanner
    // never "sees" it and drops the [data-reveal].is-visible rule from the build.
    safelist: ['is-visible'],

    theme: {
        extend: {
            colors: {
                primary: { DEFAULT: '#0F766E', hover: '#0B5D57', soft: '#F0FDFA' },
                hero: '#134E4A',
                background: '#F4F7FA',
                surface: '#FFFFFF',
                foreground: '#0F172A',
                muted: '#F1F5F9',
                'muted-fg': '#64748B',
                border: '#E2E8F0',
                accent: { DEFAULT: '#DC2626', hover: '#B91C1C' },
                status: { green: '#059669', yellow: '#D97706', red: '#DC2626' },
            },
            fontFamily: {
                heading: ['Poppins', ...defaultTheme.fontFamily.sans],
                sans: ['"Open Sans"', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                token: '0.75rem',
            },
            boxShadow: {
                soft: '0 1px 2px rgba(15,23,42,0.04), 0 4px 12px -2px rgba(15,23,42,0.06)',
                lift: '0 10px 25px -5px rgba(15,23,42,0.10), 0 8px 10px -6px rgba(15,23,42,0.06)',
            },
        },
    },

    plugins: [forms],
};
