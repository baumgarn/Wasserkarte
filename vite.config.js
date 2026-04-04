import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';
import fs from 'fs';


function copyApiToDist() {
	return {
		name: 'copy-api-to-dist',
		closeBundle() {
			const sourceDir = path.resolve(__dirname, 'api');
			const targetDir = path.resolve(__dirname, 'dist/api');
			const sourceDataDir = path.resolve(__dirname, 'data');
			const targetDataDir = path.resolve(__dirname, 'dist/data');
			const sourceCacheDir = path.join(sourceDir, 'cache');
			const targetCacheDir = path.join(targetDir, 'cache');

			if (!fs.existsSync(sourceDir)) {
				return;
			}

			fs.rmSync(targetDir, { recursive: true, force: true });
			fs.mkdirSync(targetDir, { recursive: true });

			fs.cpSync(sourceDir, targetDir, {
				recursive: true,
				filter: (src) => {
					const relativePath = path.relative(sourceDir, src);
					if (!relativePath) return true;
					if (relativePath === '.DS_Store') return false;
					if (relativePath === 'cache') return false;
					if (relativePath.startsWith(`cache${path.sep}`)) return false;
					return true;
				},
			});

			if (fs.existsSync(sourceCacheDir)) {
				fs.mkdirSync(targetCacheDir, { recursive: true });
			}

			if (fs.existsSync(sourceDataDir)) {
				fs.rmSync(targetDataDir, { recursive: true, force: true });
				fs.cpSync(sourceDataDir, targetDataDir, {
					recursive: true,
					filter: (src) => path.basename(src) !== '.DS_Store',
				});
			}
		},
	};
}

export default defineConfig({
	esbuild: {
		target: 'es2020',
	},
	build: {
		target: 'es2020',
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
				target: 'http://localhost/wasserkarte/api/',
				changeOrigin: true,
				rewrite: (path) => path.replace(/^\/api/, '')
			},
		},
		watch: {
			usePolling: true,
			interval: 100
		}
	},
	plugins: [vue(), copyApiToDist()],
	resolve: {
		alias: {
			'@': path.resolve(__dirname, 'js'),
		},
	},

});
