import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import fs from 'fs';
import path from 'path';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        ...getAllCacheFiles('./storage/framework/cache/data/'),
        './resources/views/**/*.blade.php',
        './resources/views/**/**/*.blade.php',
        './resources/views/**/**/**/*.blade.php',
        './resources/views/**/**/**/**/*.blade.php',
        './resources/views/**/**/**/**/**/*.blade.php',
        './app/Livewire/**/*.php',
        './app/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Quicksand', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: '#2B9D3C',
                    light: '#0b5879',
                    dark: '#084058',
                    50: '#eefaff',
                    100: '#94defd',
                    200: '#75cdf3',
                    300: '#46b6e6',
                    400: '#289aca',
                    500: '#1488b9',
                    600: '#1075a0',
                    700: '#0d648a',
                    800: '#0b5879',
                    900: '#2ea640',
                },
                secondary: {
                    DEFAULT: '#2E5B9E',
                    light: '#10b3aa',
                    dark: '#0c968e',
                    50: '#e6ecff',
                    100: '#b3fffb',
                    200: '#2fcec6', 
                    300: '#2fcec6',
                    400: '#2fcec6',
                    500: '#2fcec6',
                    600: '#2fcec6',
                    700: '#2fcec6',
                    800: '#10b3aa',
                    900: '#1e40af',
                },
                transparent: {
                    DEFAULT: '#fff0',
                },
            },
        },
    },

    plugins: [forms, typography],
    safelist: [
          { pattern: /(bg|text|border)-(gray|amber|blue|green|red|slate)-(50|100|200|500|600|700)/ },
        'bg-green-500', 
        'bg-yellow-500', 
        'bg-red-500', 
        'bg-blue-500', 
        'bg-white',
        'fill-green-500',
        'fill-yellow-500', 
        'fill-red-500', 
        'fill-blue-500', 
        'fill-white',
    'flex-col','divide-y',
    'sm:flex-row','sm:divide-x','sm:divide-y-0',
    'md:flex-row','md:divide-x','md:divide-y-0',
    'lg:flex-row','lg:divide-x','lg:divide-y-0',
    'xl:flex-row','xl:divide-x','xl:divide-y-0',
    '2xl:flex-row','2xl:divide-x','2xl:divide-y-0',
    'first:rounded-t-md','last:rounded-b-md',
    'md:first:rounded-l-md','md:last:rounded-r-md',
    'peer-checked:bg-blue-600','peer-checked:text-white','peer-checked:hover:bg-blue-700',
      ],
};
function getAllCacheFiles(dir, fileList = []) {
    try {
        const files = fs.readdirSync(dir);
        files.forEach(file => {
            const filePath = path.join(dir, file);
            if (fs.statSync(filePath).isDirectory()) {
                getAllCacheFiles(filePath, fileList); // Rekursive Suche in Unterordnern
            } else {
                fileList.push(filePath); // Datei zur Liste hinzufügen
            }
        });
    } catch (err) {
        console.error("Fehler beim Lesen der Cache-Dateien:", err);
    }
    return fileList;
}