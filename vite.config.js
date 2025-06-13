import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
	build: {
		// Generate source maps for production builds
		sourcemap: true,
		
		// Customize the output directory
		outDir: 'dist',
		
		// Configure asset fingerprinting
		rollupOptions: {
			output: {
				// Ensure assets have unique names
				assetFileNames: (assetInfo) => {
					const extType = assetInfo.name.split('.')[1];
					if (/png|jpe?g|svg|gif|tiff|bmp|ico/i.test(extType)) {
						return `assets/images/[name]-[hash][extname]`;
					}
					return `assets/[name]-[hash][extname]`;
				},
				chunkFileNames: 'assets/js/[name]-[hash].js',
				entryFileNames: 'assets/js/[name]-[hash].js',
			}
		}
	},
	server: {
		proxy: {
			'/api': {
				target: 'http://localhost/wasserkarte/api/', // Your PHP backend address
				changeOrigin: true,
				rewrite: (path) => path.replace(/^\/api/, '')
			},
		}
	},
	plugins: [vue()],
	resolve: {
		alias: {
			'@': path.resolve(__dirname, 'js'),
		},
	},

});
