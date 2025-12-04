export const IconFactory = {
	size: 30 * (window.devicePixelRatio || 1),
	cache: new Map(),
	textures: {},
	textureUrls: {
		soil: '/img/soil_texture.png',
		humus: '/img/humus_texture_6.png',
	},

	soilColors: {
		sand: '#fff1a3',
		lehm: '#ccb592',
		ton: '#c04475',
		schluff: '#757575',

	},

	humusColor: '#ae8777',

	preloadTextures() {
		const promises = Object.entries(this.textureUrls).map(([name, url]) => {
			if (this.textures[name]) return Promise.resolve(); // already loaded
			return new Promise((resolve, reject) => {
				const img = new Image();
				img.src = url;
				img.onload = () => {
					this.textures[name] = img;
					resolve();
				};
				img.onerror = reject;
			});
		});
		return Promise.all(promises);
	},

	async getSoilIcon(obj) {
		
		const key = obj.short;
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

		const radius = (px / 2) / dpr;
		ctx.beginPath();
		ctx.arc(cx, cy, radius, 0, Math.PI * 2);
		ctx.fillStyle = obj.color || '#ccc';
		ctx.fill();

		if (obj.short) {
			ctx.font = '12px Arial';
			ctx.fillStyle = '#00000070'
			ctx.textAlign = "center";
			ctx.fillText(obj.short, cx, cy*1.3);

		}

		const dataUrl = canvas.toDataURL();
		this.cache.set(key, dataUrl);
		return dataUrl;
	},
	
	// async getSoilIcon(obj) {
	// 	await this.preloadTextures();
		
	// 	const instructions = obj.soilIcon;

	// 	const key = JSON.stringify([instructions]);
	// 	if (this.cache.has(key)) return this.cache.get(key);

	// 	const dpr = window.devicePixelRatio || 1;
	// 	const px = this.size; // already multiplied by dpr
	// 	const canvas = document.createElement('canvas');
	// 	canvas.width = px;
	// 	canvas.height = px;
	// 	const ctx = canvas.getContext('2d');

	// 	ctx.setTransform(dpr, 0, 0, dpr, 0, 0); // scale canvas to handle DPR

	// 	let maxRadius = 0;
	// 	const cx = px / (2 * dpr);
	// 	const cy = px / (2 * dpr);

	// 	// Draw soil circles
	// 	// instructions.forEach(([type, scale]) => {
	// 	// 	const radius = (px / 2) * scale / dpr;
	// 	// 	maxRadius = Math.max(maxRadius, radius);
	// 	// 	ctx.beginPath();
	// 	// 	ctx.arc(cx, cy, radius, 0, Math.PI * 2);
	// 	// 	ctx.fillStyle = this.soilColors[type] || '#ccc';
	// 	// 	ctx.fill();
	// 	// });

	// 	const radius = (px / 2) / dpr;
	// 	ctx.beginPath();
	// 	ctx.arc(cx, cy, radius, 0, Math.PI * 2);
	// 	ctx.fillStyle = obj.color || '#ccc';
	// 	ctx.fill();

	// 	if (obj.short) {
	// 		ctx.font = '12px Arial';
	// 		ctx.fillStyle = '#00000077'
	// 		ctx.textAlign = "center";
	// 		ctx.fillText(obj.short, cx, cy*1.3);

	// 	}

	// 	// // Overlay repeating texture, clipped
	// 	// if (this.textures.soil?.complete) {
	// 	// 	const pattern = ctx.createPattern(this.textures.soil, 'repeat');
	// 	// 	ctx.save();
	// 	// 	ctx.beginPath();
	// 	// 	ctx.arc(cx, cy, maxRadius, 0, Math.PI * 2);
	// 	// 	ctx.clip();

	// 	// 	ctx.globalAlpha = .25;
	// 	// 	ctx.fillStyle = pattern;
	// 	// 	ctx.fill();
	// 	// 	ctx.globalAlpha = 1;
	// 	// 	ctx.restore();
	// 	// }

	// 	const dataUrl = canvas.toDataURL();
	// 	this.cache.set(key, dataUrl);
	// 	return dataUrl;
	// },

	async getHumusIcon(obj) {

		const humuslevel = obj.humusIcon;

		const key = 'humus' + humuslevel;
		if (this.cache.has(key)) return this.cache.get(key);

		const dpr = window.devicePixelRatio || 1;
		const px = this.size;
		const canvas = document.createElement('canvas');
		canvas.width = px;
		canvas.height = px;
		const ctx = canvas.getContext('2d');

		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

		const cx = px / (2 * dpr);
		const cy = px / (2 * dpr);
		const radius = (px / 2) / dpr;

		ctx.beginPath();
		ctx.arc(cx, cy, radius, 0, Math.PI * 2);
		ctx.fillStyle = '#ffffff';
		ctx.fill();

		const alphas = [.05, .05, .2, .4, .6, 1];
		ctx.globalAlpha = alphas[humuslevel] ?? 1;
		
		ctx.beginPath();
		ctx.arc(cx, cy, radius, 0, Math.PI * 2);
		ctx.fillStyle = this.humusColor;
		ctx.fill();
		
		if (obj.short) {
			ctx.globalAlpha = 1;
			ctx.font = '12px Arial';
			ctx.fillStyle = '#00000070'
			ctx.textAlign = "center";
			ctx.fillText(obj.short, cx, cy * 1.3);

		}

		const dataUrl = canvas.toDataURL();
		this.cache.set(key, dataUrl);
		return dataUrl;
	},

	// async getHumusIcon(obj) {
	// 	await this.preloadTextures();

	// 	const humuslevel = obj.humusIcon;

	// 	const key = 'humus' + humuslevel;
	// 	if (this.cache.has(key)) return this.cache.get(key);

	// 	const dpr = window.devicePixelRatio || 1;
	// 	const px = this.size;
	// 	const canvas = document.createElement('canvas');
	// 	canvas.width = px;
	// 	canvas.height = px;
	// 	const ctx = canvas.getContext('2d');

	// 	ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

	// 	const cx = px / (2 * dpr);
	// 	const cy = px / (2 * dpr);
	// 	const radius = (px / 2) / dpr;

	// 	ctx.beginPath();
	// 	ctx.arc(cx, cy, radius, 0, Math.PI * 2);
	// 	ctx.fillStyle = '#ffffff';
	// 	ctx.fill();

	// 	// // global alpha from your original code
	// 	const alphas = [.05, .05, .2, .4, .6, 1];
	// 	ctx.globalAlpha = alphas[humuslevel] ?? 1;

	// 	ctx.beginPath();
	// 	ctx.arc(cx, cy, radius, 0, Math.PI * 2);
	// 	ctx.fillStyle = this.humusColor;
	// 	ctx.fill();


	// 	// // create vertical gradient from top to bottom of the circle
	// 	// const grad = ctx.createLinearGradient(cx, cy - radius, cx, cy + radius);

	// 	// // top: full color
	// 	// grad.addColorStop(0, this.humusColor);

	// 	// // bottom: same color, 50% opacity
	// 	// grad.addColorStop(0.8, `${this.humusColor}00`);  // works if humusColor is #rrggbb

	// 	// ctx.beginPath();
	// 	// ctx.arc(cx, cy, radius, 0, Math.PI * 2);
	// 	// ctx.fillStyle = grad;
	// 	// ctx.fill();
	// 	// ctx.globalAlpha = 1;
	// 	// ------------------------

	// 	// texture overlay (unchanged)
	// 	// if (this.textures.humus?.complete) {
	// 	// 	const pattern = ctx.createPattern(this.textures.humus, 'repeat');
	// 	// 	ctx.save();
	// 	// 	ctx.beginPath();
	// 	// 	ctx.arc(cx, cy, radius, 0, Math.PI * 2);
	// 	// 	ctx.clip();

	// 	// 	ctx.globalAlpha = humuslevel <= 1 ? .15 : .25;
	// 	// 	ctx.fillStyle = pattern;
	// 	// 	ctx.fill();
	// 	// 	ctx.globalAlpha = 1;
	// 	// 	ctx.restore();
	// 	// }

	// 	const dataUrl = canvas.toDataURL();
	// 	this.cache.set(key, dataUrl);
	// 	return dataUrl;
	// }
};


IconFactory.preloadTextures();