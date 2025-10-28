import { config } from './config.js';
import { state } from './state.js';

export const dataModel = {

	color_schemes: {
		
		nfk : {
			normal: [
				{ value: 0, color: '#ff8e70'},
				{ value: 10, color: '#ffcf4c'},
				{ value: 50, color: '#feff4c'},
				{ value: 70, color: '#9ff24c'},
				{ value: 100, color: '#4ce6cc'},
				{ value: 120, color: '#55c9f0'},
			],
			dwd: [
				{ value: 0, color: '#c8632c'},
				{ value: 10, color: '#ff961f'},
				{ value: 20, color: '#fac83d'},
				{ value: 30, color: '#ffe765'},
				{ value: 40, color: '#fafa4b'},
				{ value: 50, color: '#bee68c'},
				{ value: 60, color: '#c7fa4b'},
				{ value: 70, color: '#00b401'},
				{ value: 80, color: '#008c01'},
				{ value: 90, color: '#82e7fa'},
				{ value: 100, color: '#34c8fa'},
				{ value: 110, color: '#0082fa'},
				{ value: 120, color: '#0000fa'},
			],
			blue: [
				{ value: 0, color: '#d8f5ff'},
				{ value: 120, color: '#407dff'},
			],
		},
		
	},

	nfk_labels : [
		{ value: 10, name: 'Sehr trocken'}, //  0 - 10
		{ value: 30, name: 'Trocken'},		// 10 - 30
		{ value: 80, name: 'Optimal'},		// 30 - 80
		{ value: 100, name: 'Nass'},		// 80 - 100
		{ value: 120, name: 'Sehr nass'},	// 100 +
	],

	soil_table : {
		"Ss": {
			name: "sandiger Sand",
			color: "#f4e2d8",
			TW: { "h0-1": 2, "h2": 3, "h3": 4, "h4": 6 },
			FK: { "h0-1": 13, "h2": 16, "h3": 18, "h4": 23 }
		},
		"Sl2": {
			name: "schwach lehmiger Sand",
			color: "#e0c7a7",
			TW: { "h0-1": 6, "h2": 7, "h3": 8, "h4": 9 },
			FK: { "h0-1": 21, "h2": 23, "h3": 26, "h4": 30 }
		},
		"Sl3": {
			name: "mittel lehmiger Sand",
			color: "#c2a97f",
			TW: { "h0-1": 9, "h2": 10, "h3": 10, "h4": 12 },
			FK: { "h0-1": 25, "h2": 27, "h3": 29, "h4": 34 }
		},
		"Ls4": {
			name: "sandiger Lehm",
			color: "#a57f5a",
			TW: { "h0-1": 13, "h2": 14, "h3": 14, "h4": 15 },
			FK: { "h0-1": 28, "h2": 30, "h3": 32, "h4": 36 }
		}
	},

	humus_table : {
		"h0-1": { name: "nahezu humusfrei", color: "#f5f3e7" },
		"h0": { name: "nahezu humusfrei", color: "#f5f3e7" },
		"h1": { name: "sehr schwach humos", color: "#f5f3e7" },
		"h2": { name: "schwach humos", color: "#d2b48c" },
		"h3": { name: "mittel humos", color: "#a47551" },
		"h4": { name: "stark humos", color: "#654321" },
		"org": { name: "organisch", color: "#3b2f2f" } 
	},

	usage_table : {
		"G" : {name: 'Grünland'},
		"RA" : {name: 'Rasen'},
		"A" : {name: 'Acker'},
		"AG" : {name: 'Gemüsegarten'},
		"B" : {name: 'freistehender Baum'},
		"VB" : {name: 'Stadtbaum'},
		"GB" : {name: 'Gebüsch'},
		"SST" : {name: 'Streuobstwiese'},
		"FP" : {name: 'Pflanzung'},
		"LW" : {name: 'Laubwald'},
		"NW" : {name: 'Nadelwald'},
		"MW" : {name: 'Mischwald'},
	},

	get_usage_name (device) {
		return this.usage_table[device.attributes.Nutzungsart]?.name
	},

	get_soil_name (device) {
		return this.soil_table[device.attributes.Bodenart]?.name
	},
	get_soil_color (device) {
		return this.soil_table[device.attributes.Bodenart]?.color
	},

	get_humus_name (device) {
		return this.humus_table[device.attributes.Humusgehalt]?.name;
	},
	get_humus_color (device) {
		return this.humus_table[device.attributes.Humusgehalt]?.color;
	},





	wassergehalt_oberboden (device, hoverData) {
		
		// Annäherungswert Wassergehalt im Oberboden bis 60cm Tiefe.

		var bodenfeuchte10, bodenfeuchte30, bodenfeuchte60, bodenfeuchte80, wassergehalt;
		
		if (hoverData) {
			bodenfeuchte10 = hoverData.Bodenfeuchte_10cm ? parseFloat( hoverData.Bodenfeuchte_10cm.value) : null;
			bodenfeuchte30 = hoverData.Bodenfeuchte_30cm ? parseFloat( hoverData.Bodenfeuchte_30cm.value) : null;
			bodenfeuchte60 = hoverData.Bodenfeuchte_60cm ? parseFloat( hoverData.Bodenfeuchte_60cm.value) : null;
			bodenfeuchte80 = hoverData.Bodenfeuchte_80cm ? parseFloat( hoverData.Bodenfeuchte_80cm.value) : null;
		} else {
			bodenfeuchte10 = device.telemetry.Bodenfeuchte_10cm ? parseFloat( device.telemetry.Bodenfeuchte_10cm[0].value) : null;
			bodenfeuchte30 = device.telemetry.Bodenfeuchte_30cm ? parseFloat( device.telemetry.Bodenfeuchte_30cm[0].value) : null;
			bodenfeuchte60 = device.telemetry.Bodenfeuchte_60cm ? parseFloat( device.telemetry.Bodenfeuchte_60cm[0].value) : null;
			bodenfeuchte80 = device.telemetry.Bodenfeuchte_80cm ? parseFloat( device.telemetry.Bodenfeuchte_80cm[0].value) : null;
		}

		// 10, 30, 60
		if (valid(bodenfeuchte10) && valid(bodenfeuchte30) && valid(bodenfeuchte60)) {
			wassergehalt = bodenfeuchte10 + (bodenfeuchte10 + bodenfeuchte30) / 2 + bodenfeuchte30 * 2 + bodenfeuchte60 * 2;
		}
		// 10, 30
		if (valid(bodenfeuchte10) && valid(bodenfeuchte30) && ! valid(bodenfeuchte60)) {
			wassergehalt = bodenfeuchte10 + (bodenfeuchte10 + bodenfeuchte30) / 2 + bodenfeuchte30 * 4;
		}
		// 10, 60
		if (valid(bodenfeuchte10) && valid(bodenfeuchte60) && ! valid(bodenfeuchte30)) {
			wassergehalt = bodenfeuchte30 * 3 + bodenfeuchte60 * 3;
		}
		// 30, 60
		if (! valid(bodenfeuchte10) && valid(bodenfeuchte30) && valid(bodenfeuchte60)) {
			wassergehalt = bodenfeuchte30 * 4 + bodenfeuchte60 * 2;
		}
		// 10, 30, 80
		if (valid(bodenfeuchte10) && valid(bodenfeuchte30) && !valid(bodenfeuchte60) && valid(bodenfeuchte80)) {
			bodenfeuchte60 = (bodenfeuchte30 + bodenfeuchte80) / 2;
			wassergehalt = bodenfeuchte10 + (bodenfeuchte10 + bodenfeuchte30) / 2 + bodenfeuchte30 * 2 + bodenfeuchte60 * 2;
		}

		if (wassergehalt) {
			return wassergehalt;
		}
	},

	gesamtkapazität_oberboden (device, hoverData) {
		const FK = device.attributes.feldkapazität;
		return FK * 6
	},

	// Gibt volumetrischen Wassergehalt in % (Vol-%) als Mittelwert über 0–60 cm zurück
	vol(device, hoverData) {
		const vol = this.wassergehalt_oberboden(device, hoverData) / 6;
		return vol
	},

	nfk(device, hoverData) {
		const vol = this.vol(device, hoverData) ;
		const TW = device.attributes.totwasserbereich;
		const FK = device.attributes.feldkapazität;

		const nFK = ((vol - TW) / (FK - TW)) * 100;
		return Math.max(0, nFK); // Begrenzung auf >0
	},

	vol_to_nfk(device, value) {
		const TW = device.attributes.totwasserbereich;
		const FK = device.attributes.feldkapazität;

		const nFK = ((value - TW) / (FK - TW)) * 100;
		return Math.max(0, nFK); // Begrenzung auf >0
	},

	get_vol_color(device, value) {
		let nfk = this.vol_to_nfk(device, value)
		return this.get_nfk_color(nfk);
	},
	
	get_vol_color_flat(device, value) {
		let nfk = this.vol_to_nfk(device, value)
		return this.get_color_flat(nfk, this.get_color_scheme('nfk'));
	},

	get_nfk_color(value) {
		if (Number.isNaN(value)){
			return '#9bb9dd'
		}
		return this.get_color(value, this.get_color_scheme('nfk'));
	},

	get_temperature_color_flat(value) {
		return this.get_temperature_color(value);
	},

	get_temperature_color(value) {
		if (value < 0) {
			return "#cfeffb";
		} else {
			return "#feff4c";
		}
	},

	get_color(value, colorTable) {
		if (value <= colorTable[0].value) return colorTable[0].color;
		if (value >= colorTable[colorTable.length - 1].value) return colorTable[colorTable.length - 1].color;

		for (let i = 0; i < colorTable.length - 1; i++) {
			const curr = colorTable[i];
			const next = colorTable[i + 1];

			if (value >= curr.value && value < next.value) {
				const range = next.value - curr.value;
				const t = (value - curr.value) / range;

				const c1 = hexToRgb(curr.color);
				const c2 = hexToRgb(next.color);

				const r = Math.round(c1.r + (c2.r - c1.r) * t);
				const g = Math.round(c1.g + (c2.g - c1.g) * t);
				const b = Math.round(c1.b + (c2.b - c1.b) * t);

				return rgbToHex(r, g, b); 
			}
		}
	},

	get_color_scheme(type) {
		return this.color_schemes[type]?.[state.colorScheme] ?? this.color_schemes[type]?.['normal'];
	},

	get_color_flat(value, colorTable) {
		if (value <= colorTable[0].value) return colorTable[0].color;
		if (value >= colorTable[colorTable.length - 2].value) return colorTable[colorTable.length - 1].color;

		for (let i = 0; i < colorTable.length - 1; i++) {
			const curr = colorTable[i];
			const next = colorTable[i + 1];

			if (value <= curr.value) {
				return curr.color;
			}
		}
	},
	
	get_nfk_color_alpha(value, alpha) {
		var color = this.get_nfk_color(value);
		var hex = color.replace(/^#/, '');
		const alphaHex = Math.round(alpha * 255).toString(16).padStart(2, '0');
		return `#${hex}${alphaHex}`;
	},

	get_vol_nfk_label(device, value) {
		let nfk = this.vol_to_nfk(device, value)
		if (!Number.isNaN(value)) {
			return this.get_nfk_label(nfk);
		}
	},

	get_nfk_label(value) {
		if (value <= this.nfk_labels[0].value) {
			return this.nfk_labels[0].name;
		} 
		if (value >= this.nfk_labels[this.nfk_labels.length - 1].value) {
			return this.nfk_labels[this.nfk_labels.length - 1].name;
		}
		for (let i = 0; i < this.nfk_labels.length - 1; i++) {
			const curr = this.nfk_labels[i];
			const next = this.nfk_labels[i + 1];

			if (value >= curr.value && value < next.value) {
				return next.name;
			}
		}
	},

	wasserkapazität_setzen(device) {

		// setzt die attribute totwasserbereich und feldkapazität

		const humus = device.attributes.Humusgehalt;
		const boden = device.attributes.Bodenart;

		if (!this.soil_table[boden] || !this.soil_table[boden].FK[humus] || !this.soil_table[boden].TW[humus]) {
			console.warn("Unberücksichtigte Kombination aus Bodenart und Humusklasse:", boden, humus);
			return;
		}

		device.attributes.totwasserbereich = this.soil_table[boden].TW[humus];
		device.attributes.feldkapazität = this.soil_table[boden].FK[humus];

	},

	rowToProps(row, schema) {
		return schema.reduce((acc, key, i) => {
			acc[key] = row[i];
			return acc;
		}, {});
	},


}	

function valid(value) {
	return typeof value === 'number' && !isNaN(value);
}

function hexToRgb(hex) {
	const parsed = hex.startsWith('#') ? hex.slice(1) : hex;
	const bigint = parseInt(parsed, 16);
	return {
		r: (bigint >> 16) & 255,
		g: (bigint >> 8) & 255,
		b: bigint & 255
	};
}

function rgbToHex(r, g, b) {
	return '#' + [r, g, b].map(x =>
		x.toString(16).padStart(2, '0')
	).join('');
  }