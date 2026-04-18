<template>
	<canvas ref="barrelCanvas"></canvas>
</template>
	
	<script>
	import { state } from '@/state.js'
	import { dataModel } from '@/datamodel.js'
	import { config } from '@/config.js'
	import dataStore from '@/datastore.js'
	import linePatternUrl from '/img/totwasser2.png';

	export default {
	name: 'Barrel',
	setup() {
		return {state};
	},
	props: {
		device: Object,
		hoverData: Object
	},
	data() {
		return {
			ellipseRatio: 0.25,
			margin: 3,
			dpr: window.devicePixelRatio || 1,
			patternImage : null,
		};
	},
	mounted() {
		this.patternImage = new Image();
		this.patternImage.src = linePatternUrl;
		this.patternImage.onload = () => {
			this.drawBarrel();
		}
		this.drawBarrel();
	},
	computed: {
		barrel_width() {
			// return (state.windowWidth < 600) ? 140 : 175;
			return (state.windowWidth < 600) ? 130 : 160;
		},
		barrel_height() {
			// return (state.windowWidth < 600) ? 125 : 150;
			return (state.windowWidth < 600) ? 110 : 135;
		},
		canvasWidth() {
			return this.barrel_width + this.margin * 2;
		},
		canvasHeight() {
			const ellipseH = this.ellipseRatio * this.barrel_width;
			return this.barrel_height + ellipseH + this.margin * 2;
		},
		wassergehalt_oberboden() {
			const wassergehalt = this.hoverOrLastData.vol_avg * 6;
			if (!wassergehalt) return '–';
			if (wassergehalt < 0 ) return 0;
			if (wassergehalt < 10 ) return parseFloat(wassergehalt.toFixed(1));
			return wassergehalt.toFixed(0)	 
		},
		is_valid() {
			return (this.wassergehalt_oberboden == '–')
		},
		gesamtkapazität_oberboden() {
			return Math.round(this.device.attributes.avg_FK * 6)
		},
		vol() {
			const vol = this.hoverOrLastData.vol_avg;
			if (isNaN(vol)) return '–'
			return parseFloat(vol.toFixed(0));
		},
		nfk() {
			const nfk = this.hoverOrLastData.nfk_avg;
			if (isNaN(nfk)) return '–'
			return parseFloat(nfk.toFixed(0));
		},
		hasSoilAttributes() {
			return (this.device.attributes.avg_FK && this.device.attributes.avg_TW)
		},
		percentage() {
			const p = this.wassergehalt_oberboden / this.gesamtkapazität_oberboden
			return Math.min( Math.max(p, 0), 1)  ;
		},
		totwasser_percentage() {
			let p;
			p = this.device.attributes.avg_TW / this.device.attributes.avg_FK
			return p;
			// return Math.min( Math.max(p, 0), 1);
		},
		colorScheme() {
			return state.colorScheme;
		}, 
			windowWidth() {
				return state.windowWidth;
			},
			hoverOrLastData() {
				const lastRow = this.device?.telemetrySchema?.data?.[0];
				const schema = this.device?.telemetrySchema?.schema;
				return this.hoverData || (Array.isArray(lastRow) && Array.isArray(schema) ? dataModel.rowToProps(lastRow, schema) : {})
			},
			timelineDate() {
				return state.timelineDate;
			},
		hoursSinceLastTelemetry() {
			return dataStore.hoursSinceLastTelemetry(this.device.id);
		},
		isInactive() { // no current telemetry
			if ( this.nfk == '–' || (!this.timelineDate && this.hoursSinceLastTelemetry > config.noTelemetryCutoff )) {
				return true;
			} 
			// TODO return true if inactive on timeline
		},
	},
	watch: {
		nfk() {
			this.drawBarrel();
		},
		percentage() {
			this.drawBarrel();
		},
		ellipseRatio() {
			this.drawBarrel();
		},
		colorScheme() {
			this.drawBarrel();
		},
		windowWidth() {
			this.drawBarrel();
		}
	},
	methods: {
		drawBarrel() {
			const liquidColor = dataModel.get_nfk_color(this.nfk);
			// const strokeColor = '#000';
			// const strokeWidth = 1;
			// const strokeColor = '#aaa';
			const strokeColor = '#00000033';
			const strokeColorLight = '#ddd';
			const strokeWidth = 1.5;

			const canvas = this.$refs.barrelCanvas;
			const ctx = canvas.getContext('2d');
			const dpr = this.dpr;

			canvas.width = this.canvasWidth * dpr;
			canvas.height = this.canvasHeight * dpr;
			canvas.style.width = this.canvasWidth + 'px';
			canvas.style.height = this.canvasHeight + 'px';

			ctx.setTransform(1, 0, 0, 1, 0, 0);
			ctx.scale(dpr, dpr);

			ctx.clearRect(0, 0, this.canvasWidth, this.canvasHeight);

			const ellipseH = this.ellipseRatio * this.barrel_width;
			const centerX = this.canvasWidth / 2;
			const centerY = this.canvasHeight / 2;

			const topY = centerY - this.barrel_height / 2;
			const bottomY = centerY + this.barrel_height / 2;

			const liquidTopY = topY + (bottomY - topY) * (1 - this.percentage);

			const totwasserTopY = topY + (bottomY - topY) * (1 - this.totwasser_percentage);

			if (!this.isInactive && !isNaN(this.percentage)) {
				// Liquid body
				ctx.beginPath();
				ctx.moveTo(centerX - this.barrel_width / 2, liquidTopY);
				ctx.lineTo(centerX - this.barrel_width / 2, bottomY);
				ctx.lineTo(centerX + this.barrel_width / 2, bottomY);
				ctx.lineTo(centerX + this.barrel_width / 2, liquidTopY);
				ctx.closePath();
				ctx.fillStyle = liquidColor;
				ctx.fill();

				// Liquid bottom ellipse
				ctx.beginPath();
				ctx.ellipse(centerX, bottomY, this.barrel_width / 2, ellipseH / 2, 0, 0, Math.PI * 2);
				ctx.fillStyle = liquidColor;
				ctx.fill();

				// Liquid top ellipse
				ctx.beginPath();
				ctx.ellipse(centerX, liquidTopY, this.barrel_width / 2, ellipseH / 2, 0, 0, Math.PI * 2);
				ctx.fillStyle = liquidColor;
				ctx.fill();

				// Draw bottom half of bottom ellipse (thinner line)
				ctx.beginPath();
				ctx.ellipse(centerX, bottomY, this.barrel_width / 2, ellipseH / 2, 0, 0, Math.PI);
				ctx.lineWidth = strokeWidth;
				ctx.strokeStyle = strokeColor;
				ctx.stroke();
			} else {
					// Draw bottom half of bottom ellipse (thinner line)
				ctx.beginPath();
				ctx.ellipse(centerX, bottomY, this.barrel_width / 2, ellipseH / 2, 0, Math.PI, 2 * Math.PI);
				// ctx.strokeStyle = strokeColorLight;
				ctx.strokeStyle = strokeColor;
				ctx.stroke();
				
				ctx.beginPath();
				ctx.ellipse(centerX, bottomY, this.barrel_width / 2, ellipseH / 2, 0, 0, Math.PI);
				ctx.lineWidth = strokeWidth;
				ctx.strokeStyle = strokeColor;
				ctx.stroke();
				// // Draw bottom ellipse
				// ctx.beginPath();
				// ctx.ellipse(centerX, bottomY, this.barrel_width / 2, ellipseH / 2, 0, 0, Math.PI * 2);
				// ctx.lineWidth = strokeWidth;
				// ctx.strokeStyle = strokeColor;
				// ctx.stroke();
			}

			// Totwasser area offscreen canvas

			if (!this.isInactive && !isNaN(this.percentage)) {

				// Create offscreen canvas
				const offscreen = document.createElement('canvas');
				offscreen.width = this.canvasWidth;
				offscreen.height = this.canvasHeight;
				const offCtx = offscreen.getContext('2d');
				const maskTopY = Math.max(totwasserTopY, liquidTopY)

				// Draw black mask shapes on offscreen
				offCtx.fillStyle = '#000';
				offCtx.beginPath();
				offCtx.ellipse(centerX, bottomY, this.barrel_width / 2, ellipseH / 2, 0, 0, Math.PI * 2);
				offCtx.fill();

				offCtx.beginPath();
				offCtx.moveTo(centerX - this.barrel_width / 2, maskTopY);
				offCtx.lineTo(centerX - this.barrel_width / 2, bottomY);
				offCtx.lineTo(centerX + this.barrel_width / 2, bottomY);
				offCtx.lineTo(centerX + this.barrel_width / 2, maskTopY);
				offCtx.closePath();
				offCtx.fill();

				// Draw white ellipse mask area (will exclude pattern here)
				offCtx.globalCompositeOperation = 'destination-out';
				offCtx.beginPath();
				offCtx.ellipse(centerX, maskTopY, this.barrel_width / 2, ellipseH / 2, 0, 0, Math.PI * 2);
				offCtx.fill();

				// Draw pattern only inside black mask (source-in)
				offCtx.globalCompositeOperation = 'source-in';

				// Create pattern from patternImage
				const pattern = offCtx.createPattern(this.patternImage, 'repeat');
				offCtx.globalAlpha = .3;
				offCtx.fillStyle = pattern;
				offCtx.fillRect(0, 0, offscreen.width, offscreen.height);
				offCtx.globalAlpha = 1;

				// Draw the offscreen canvas with the pattern mask on top of main canvas
				ctx.drawImage(offscreen, 0, 0);
			}

			// Liquid top ellipse fill and line
		
			if (! this.isInactive) {

				ctx.beginPath()
				ctx.ellipse(centerX, liquidTopY, this.barrel_width / 2, ellipseH / 2, 0, 0, Math.PI * 2);
				if (this.percentage > this.totwasser_percentage) {
					ctx.fillStyle = '#ffffff22';
					ctx.strokeStyle = '#ffffff88';
				} else {
					ctx.fillStyle = '#0000002';
					ctx.strokeStyle = '#ffffff10';
				}	
				ctx.fill();
				if (this.percentage < 1) {
					ctx.lineWidth = 2;
					ctx.stroke();
				}
				
				ctx.beginPath();
				ctx.ellipse(centerX, liquidTopY, this.barrel_width / 2, ellipseH / 2, 0, Math.PI, 0);
				ctx.lineWidth = 1.5;
				ctx.strokeStyle = '#00000020';
				ctx.stroke();
			}
			

			// totwasser ellipsis only if percentage less than totwasser
			if (! this.isInactive && (this.percentage < this.totwasser_percentage && !isNaN(this.percentage))) {

				ctx.beginPath();
				ctx.lineWidth = 1;
				ctx.setLineDash([2, 2]);
				ctx.strokeStyle = '#00000077';
				ctx.ellipse(centerX, totwasserTopY, this.barrel_width / 2, ellipseH / 2, 0, 0, Math.PI * 2);
				ctx.stroke();
				ctx.setLineDash([]);

			}



			// Draw sides
			ctx.beginPath();
			ctx.moveTo(centerX - this.barrel_width / 2, topY);
			ctx.lineTo(centerX - this.barrel_width / 2, bottomY);
			ctx.moveTo(centerX + this.barrel_width / 2, topY);
			ctx.lineTo(centerX + this.barrel_width / 2, bottomY);
			ctx.strokeStyle = strokeColor;
			ctx.lineWidth = strokeWidth;
			ctx.stroke();

			// Draw top ellipse
			ctx.beginPath();
			ctx.ellipse(centerX, topY, this.barrel_width / 2, ellipseH / 2, 0, 0, Math.PI * 2);
			ctx.lineWidth = strokeWidth;
			ctx.strokeStyle = strokeColor;
			ctx.stroke();
		}
	}
	};
	</script>
	
	<style scoped>

		canvas {
			display: block;
			margin: auto;
		}

	</style>
