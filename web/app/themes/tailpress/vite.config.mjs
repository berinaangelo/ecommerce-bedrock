import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ command }) => {
    const isBuild = command === 'build';

    return {
        // Bedrock's content directory is /app, not stock WordPress's /wp-content.
        base: isBuild ? '/app/themes/tailpress/dist/' : '/',
        server: {
            port: 3000,
            cors: true,
            origin: 'http://localhost:8000',
        },
        build: {
            manifest: true,
            outDir: 'dist',
            rollupOptions: {
                input: [
                    'resources/js/app.js',
                    'resources/js/angular-app.js',
                    'resources/js/services/product-repository.js',
                    'resources/js/services/price-formatter.js',
                    'resources/js/controllers/catalog-controller.js',
                    'resources/css/app.css',
                    'resources/css/editor-style.css'
                ],
            },
        },
        plugins: [
            tailwindcss(),
        ],
    }
});
