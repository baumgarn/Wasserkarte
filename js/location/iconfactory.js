export const IconFactory = {
	size: 36 * (window.devicePixelRatio || 1),
	cache: new Map(),
	textures: {},
	textureUrls: {
		soil: '/img/soil_texture.png',
	},

	soilColors: {
		sand: '#f3efd2ff',
		lehm: '#d5b688ff',
		ton: '#c04475',
		schluff: '#757575',
	},
	preloadTextures() {
		// only preload if not already loaded
		Object.entries(this.textureUrls).forEach(([name, url]) => {
			if (!this.textures[name]) {
				const img = new Image();
				img.src = url;
				this.textures[name] = img;
			}
		});
	},

	getSoilIcon(instructions) {
		this.preloadTextures();

		const key = JSON.stringify([instructions]);
		if (this.cache.has(key)) return this.cache.get(key);

		const dpr = window.devicePixelRatio || 1;
		const px = this.size; // already multiplied by dpr
		const canvas = document.createElement('canvas');
		canvas.width = px;
		canvas.height = px;
		const ctx = canvas.getContext('2d');

		ctx.setTransform(dpr, 0, 0, dpr, 0, 0); // scale canvas to handle DPR

		let maxRadius = 0;
		const cx = px / (2 * dpr);
		const cy = px / (2 * dpr);

		// Draw soil circles
		instructions.forEach(([type, scale]) => {
			const radius = (px / 2) * scale / dpr;
			maxRadius = Math.max(maxRadius, radius);

			ctx.beginPath();
			ctx.arc(cx, cy, radius, 0, Math.PI * 2);
			ctx.fillStyle = this.soilColors[type] || '#ccc';
			ctx.fill();
		});

		// Overlay repeating texture, clipped
		if (this.textures.soil?.complete) {
			const pattern = ctx.createPattern(this.textures.soil, 'repeat');
			ctx.save();
			ctx.beginPath();
			ctx.arc(cx, cy, maxRadius, 0, Math.PI * 2);
			ctx.clip();

			ctx.globalAlpha = .25;
			ctx.fillStyle = pattern;
			ctx.fill();
			ctx.globalAlpha = 1;
			ctx.restore();
		}

		const dataUrl = canvas.toDataURL();
		this.cache.set(key, dataUrl);
		return dataUrl;
	}
};


IconFactory.preloadTextures();