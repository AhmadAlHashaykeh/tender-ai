import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

const pageScripts = [
    'resources/js/pages/dashboard.js',
    'resources/js/pages/upload.js',
    'resources/js/pages/management.js',
    'resources/js/pages/companies.js',
    'resources/js/pages/company-detail.js',
    'resources/js/pages/tenders.js',
    'resources/js/pages/tender-details.js',
    'resources/js/pages/drugs.js',
    'resources/js/pages/drug-details.js',
    'resources/js/pages/drug-standardization.js',
    'resources/js/pages/product-matching.js',
    'resources/js/pages/ai-recommendations.js',
    'resources/js/pages/prediction-history.js',
    'resources/js/pages/reports.js',
    'resources/js/pages/settings.js',
    'resources/js/pages/login.js',
];

export default defineConfig({
    // Set VITE_BASE_PATH=/tenderai/public/ on Hostinger before npm run build
    base: process.env.VITE_BASE_PATH || '/',
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/pages/login.css',
                'resources/js/app.js',
                ...pageScripts,
            ],
            refresh: true,
        }),
    ],
});
