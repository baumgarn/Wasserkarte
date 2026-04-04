import { dataModel } from '../datamodel.js';

export const IconFactory = {
	size: 30,
	cache: new Map(),
	textures: {},
	textureUrls: {
		// soil: '/img/soil_texture.png',
		// humus: '/img/humus_texture_6.png',
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

	createCanvas(size) {
		const dpr = window.devicePixelRatio || 1;
		const px = size * dpr;
		const canvas = document.createElement('canvas');
		canvas.width = px;
		canvas.height = px;

		const ctx = canvas.getContext('2d');
		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

		return { canvas, ctx, dpr };
	},

	drawCircle(ctx, size, fillStyle) {
		const radius = size / 2;
		ctx.beginPath();
		ctx.arc(radius, radius, radius, 0, Math.PI * 2);
		ctx.fillStyle = fillStyle;
		ctx.fill();
	},

	async getShortcodeIcon(obj, size) {
		
		if (!size) size = this.size
		
		const key = obj.short + '_' + size;

		if (this.cache.has(key)) return this.cache.get(key);

		const { canvas, ctx } = this.createCanvas(size);

		const cx = size / 2;
		const cy = size / 2;

		this.drawCircle(ctx, size, obj.color || '#ccc');

		if (obj.short) {
			const fontsize = size * 0.5;
			ctx.font = fontsize+'px Arial';
			ctx.fillStyle = '#00000066'
			ctx.textAlign = "center";
			ctx.fillText(obj.short, cx, cy*1.4);
		}

		const dataUrl = canvas.toDataURL();
		this.cache.set(key, dataUrl);
		return dataUrl;
	},
	

	getStandorttabelleIcon(colorScheme) {
		const key = 'standorttabelle_' + colorScheme;
		if (this.cache.has(key)) return this.cache.get(key);

		const SIZE = this.size;
		const BAR_VALUES  = [120, 100, 15, 5];
		const BAR_WIDTHS  = [1.0, 0.9, 0.8, 0.7];
		const BAR_HEIGHT  = SIZE * 0.125;
		const BAR_GAP     = SIZE * 0.1;
		const MARGIN_X    = SIZE * 0.08;

		const totalH = BAR_VALUES.length * BAR_HEIGHT + (BAR_VALUES.length - 1) * BAR_GAP;
		const startY = (SIZE - totalH) / 2;

		const dpr = window.devicePixelRatio || 1;
		const px = SIZE * dpr;
		const canvas = document.createElement('canvas');
		canvas.width = px;
		canvas.height = px;
		const ctx = canvas.getContext('2d');
		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

		BAR_VALUES.forEach((val, i) => {
			const color = dataModel.get_nfk_color(val);
			const barW = (SIZE - 2 * MARGIN_X) * BAR_WIDTHS[i];
			const x = MARGIN_X;
			const y = startY + i * (BAR_HEIGHT + BAR_GAP);
			ctx.fillStyle = color;
			ctx.fillRect(x, y, barW, BAR_HEIGHT);
		});

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
	// 	const px = size;
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
