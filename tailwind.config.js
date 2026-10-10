import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './public/js/*.js',
    ],

    // These classes are never literally written in a .blade.php file (either
    // injected by the Leaflet library itself, or added via JS classList/className
    // in public/js/*.js, which isn't in the content scan above), so Tailwind's
    // content scanner never "sees" them and drops their custom CSS rules from
    // the build even though they're styled in app.css.
    safelist: ['is-visible', 'leaflet-container', 'leaflet-popup-content', 'custom-pin-marker'],

    theme: {
        extend: {
            colors: {
                primary: { DEFAULT: '#086B59', hover: '#075545', soft: '#E5ECE1' },
                hero: '#E8EDE1',
                background: '#F6F5EF',
                surface: '#FFFFFF',
                foreground: '#202923',
                muted: '#ECEEE6',
                'muted-fg': '#59635B',
                border: '#D6DBD3',
                accent: { DEFAULT: '#DC2626', hover: '#B91C1C' },
                status: { green: '#059669', yellow: '#D97706', red: '#DC2626' },
            },
            fontFamily: {
                heading: ['Manrope', ...defaultTheme.fontFamily.sans],
                sans: ['Manrope', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                token: '0.5rem',
            },
            boxShadow: {
                soft: '0 1px 2px rgba(15,23,42,0.04), 0 4px 12px -2px rgba(15,23,42,0.06)',
                lift: '0 10px 25px -5px rgba(15,23,42,0.10), 0 8px 10px -6px rgba(15,23,42,0.06)',
            },
        },
    },

    plugins: [forms],
};
