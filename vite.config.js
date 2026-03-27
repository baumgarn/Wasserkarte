import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';
import fs from 'fs';

// Plugin to watch public folder and add cache busting to CSS
function watchPublicWithCacheBust() {
	let imageTimestamps = new Map();

	return {
		name: 'watch-public-cache-bust',
		configureServer(server) {
			const publicPath = path.resolve(__dirname, 'public');

			// Watch public folder for changes
			server.watcher.add(publicPath);

			server.watcher.on('change', (file) => {
				if (file.startsWith(publicPath) && /\.(png|jpg|jpeg|gif|svg|webp)$/i.test(file)) {
					console.log(`\n🔄 Image changed: ${path.relative(publicPath, file)}`);
					// Update timestamp for this file
					const relativePath = '/' + path.relative(publicPath, file).replace(/\\/g, '/');
					imageTimestamps.set(relativePath, Date.now());

					// Invalidate all CSS/Stylus modules to force re-transformation
					const modules = server.moduleGraph.getModulesByFile(file);
					if (modules) {
						modules.forEach(mod => server.moduleGraph.invalidateModule(mod));
					}

					// Also invalidate all .styl and .css modules
					for (const [id, mod] of server.moduleGraph.idToModuleMap) {
						if (id.endsWith('.styl') || id.endsWith('.css')) {
							server.moduleGraph.invalidateModule(mod);
						}
					}

					// Trigger full reload
					server.ws.send({
						type: 'full-reload',
						path: '*'
					});
				}
			});

			return () => {
				server.middlewares.use((req, res, next) => {
					// Set no-cache headers
					res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
					res.setHeader('Pragma', 'no-cache');
					res.setHeader('Expires', '0');
					next();
				});
			};
		},
		transform(code, id) {
			// Transform CSS files to add cache-busting timestamps
			if (id.endsWith('.styl') || id.endsWith('.css')) {
				let transformed = code;
				const urlRegex = /url\(['"]?(\/img\/[^'"\)]+)['"]?\)/g;

				transformed = transformed.replace(urlRegex, (match, imagePath) => {
					const timestamp = imageTimestamps.get(imagePath) || Date.now();
					const hasQuery = imagePath.includes('?');
					const separator = hasQuery ? '&' : '?';
					return match.replace(imagePath, `${imagePath}${separator}t=${timestamp}`);
				});

				if (transformed !== code) {
					return { code: transformed, map: null };
				}
			}
			return null;
		}
	};
}

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
				target: 'http://localhost/wasserkarte/api/', // Your PHP backend address
				changeOrigin: true,
				rewrite: (path) => path.replace(/^\/api/, '')
			},
		},
		watch: {
			// Watch additional directories including public
			usePolling: true,
			interval: 100
		}
	},
	plugins: [vue(), watchPublicWithCacheBust(), copyApiToDist()],
	resolve: {
		alias: {
			'@': path.resolve(__dirname, 'js'),
		},
	},

});
