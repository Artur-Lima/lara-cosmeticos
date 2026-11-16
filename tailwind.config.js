import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            // Paleta provisória (nude/rosé). Trocar pelos tokens da identidade
            // visual da Lara quando o layout for definido.
            colors: {
                brand: {
                    50: '#fdf6f4', 100: '#fae9e4', 200: '#f4d0c6', 300: '#ebb0a0',
                    400: '#de8873', 500: '#cf6a54', 600: '#b95240', 700: '#9a4235',
                    800: '#7f3830', 900: '#69322c', 950: '#391714',
                },
                ink: '#1f1a1a',
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Playfair Display"', ...defaultTheme.fontFamily.serif],
            },
        },
    },

    plugins: [forms],
};
