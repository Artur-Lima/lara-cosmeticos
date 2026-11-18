import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Sem dark mode automático pelo sistema: a identidade da loja é clara.
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            // "Frasco e ameixa": berry para ação, quartzo-rosa para superfícies,
            // ameixa escura para texto. Trocar se a Lara trouxer identidade oficial.
            colors: {
                brand: {
                    50: '#fbf5f8', 100: '#f6e7ef', 200: '#ecccdc', 300: '#dda5bf',
                    400: '#c7769a', 500: '#ad527b', 600: '#93386a', 700: '#7a2b57',
                    800: '#632449', 900: '#4b1d39', 950: '#2e1a2b',
                },
                ink: '#2e1a2b',
                mist: '#fbf8fa',
                quartz: '#f6e7ef',
                sage: { 50: '#eef4f0', 600: '#3f6b55', 700: '#335846' },
            },
            fontFamily: {
                sans: ['"Instrument Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Cormorant Garamond"', ...defaultTheme.fontFamily.serif],
            },
        },
    },

    plugins: [forms],
};
