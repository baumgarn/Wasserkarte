import { config } from './config.js';
import { state } from './state.js';
import soilTableData from '../data/soil-table.json';

export const dataModel = {

	soilColors: {
		sand: '#fff1a3',
		lehm: '#d7d6ce',
		schluff: '#ffd3a3',
		ton: '#ffddf5',
	},

	soilColorMixTable: {
		Ss: { sand: 1 },
		Sl2: { sand: 0.8, lehm: 0.2 },
		Sl3: { sand: 0.65, lehm: 0.35 },
		Ls4: { sand: 0.35, lehm: 0.65 },
	},

	color_schemes: {
		
		nfk : {
			normal: {
				name: 'Standard',
				colors: [
					{ value: 0, color: '#ff8e70'},
					{ value: 10, color: '#ffcf4c'},
					{ value: 40, color: '#feff4c'},
					{ value: 70, color: '#9ff24c'},
					{ value: 100, color: '#4ce6cc'},
					{ value: 120, color: '#55c9f0'},
				],
			},
			dwd: {
				name: 'DWD',
				colors: [
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
			},
			blau: {
				name: 'Blau',
				colors: [
					{ value: 0, color: '#d8f5ff'},
					{ value: 120, color: '#407dff'},
				],
			},
		},
		
	},


	nfk_labels : [
		{ value: 0, name: 'Kein Wasser verfügbar'}, 	//  < 0
		{ value: 30, name: 'Sehr trocken'}, 			//  0 - 30
		{ value: 50, name: 'Leicht trocken'}, 			// 30 - 50
		{ value: 90, name: 'Optimal'},					// 50 - 90
		{ value: 110, name: 'Nass'},					// 90 - 110
		{ value: 120, name: 'Sehr nass'},				// > 110
	],

	
	soil_table : soilTableData,

	humus_table : {
		"h0": {
			name: "Nahezu humusfrei",
			short:'h0',
			sort: 0,
			color: '#f7f6f6',
			humus: '<0,2 %',
			corg: '<0,344 %'
		},
		"h1": {
			name: "Sehr schwach humos",
			short:'h1',
			sort: 1,
			color: '#fbf9f8',
			humus: '0,2 – <0,5 %',
			corg: '0,3 – <0,9 %'
		},
		"h2": {
			name: "Schwach humos",
			short:'h2',
			sort: 2,
			color: '#f0e7e4',
			humus: '0,5 – <1 %',
			corg: '0,8 – <1,7 %'
		},
		"h3": {
			name: "Mittel humos",
			short:'h3',
			sort: 3,
			color: '#decec9',
			humus: '1 – <2,5 %',
			corg: '1,7 – <4,4 %'
		},
		"h4": {
			name: "Stark humos",
			short:'h4',
			sort: 4,
			color: '#c5aba0',
			humus: '2,5 – <5 %',
			corg: '4,3 – <8,8 %'
		},
		// "org": { name: "Organisch", short:'org' } 
	},

	usage_table : {
		"G": {
			name: 'Grünland',
			img: "Nutzungsart_Gruenland.svg",
			description: 'Landwirtschatlich genutzt, größer als 0,2 ha'
		},
		"RA" : {
			name: 'Rasen',
			img: "Nutzungsart_Rasen.svg",
			description: 'Kleine Wiese, kleiner als 0,2 ha'
		},
		"A" : {
			name: 'Acker',
			img: "Nutzungsart_Acker.svg",
			description: 'Landwirtschaftlich genutzt, mind. 0,2 ha'
		},
		"AG" : {
			name: 'Gemüsegarten',
			img: "Nutzungsart_Gemuesegarten.svg",
			description: 'Anbaufläche, kleiner als 0,2 ha'
		},
		"B" : {
			name: 'Baum',
			img: "Nutzungsart_Baum.svg",
			description: 'Freistehender Baum'
		},
		"VB" : {
			name: 'Stadtbaum',
			img: "Nutzungsart_Stadtbaum.svg",
			description: 'Baum am Straßenrand, versiegelt, z.B. Asphalt, Beton, Pflastersteine'
		},
		"GB" : {
			name: 'Gebüsch',
			img: "Nutzungsart_Gebuesch.svg",
			description: 'Gebüsch, Sträucher, Stauden etc.'
		},
		"SST" : {
			name: 'Streuobstwiese',
			img: "Nutzungsart_Streuobstwiese.svg",
		},
		"FP" : {
			name: 'Pflanzung',
			img: "Nutzungsart_Pflanzung.svg",
			description: 'Pflanzung, Agroforst oder Plantage, größer als 0,2 ha'
		},
		"LW" : {
			name: 'Laubwald',
			img: "Nutzungsart_Laubwald.svg",
		},
		"NW" : {
			name: 'Nadelwald',
			img: "Nutzungsart_Nadelwald.svg",
		},
		"MW" : {
			name: 'Mischwald',
			img: "Nutzungsart_Mischwald.svg",
		},
	},

	bewaessert_obj : {
		name: 'Bewässert',
		sort: 2,
		img: 'Bewaessert.svg',
		description: 'Regelmäßig bewässerte Standorte'
	},
	
	grundwasser_obj : {
		sort: 3,
		name: 'Grundwassernah',
		img: 'Grundwasser.svg',
		description: 'Höhere Wasserversorgung aufgrund von Grundwassereinfluss'
	},

	regenabhängig_obj : {
		sort: 1,
		name: 'Regenabhängig',
		img: 'regenabhaengig.svg',
		description: 'Keine regelmäßige Bewässerung und kein größerer Grundwassereinfluss'
	},

	bookmarkfilter_obj : {
		sort: 0,
		name: 'Bookmarks',
		img: 'bookmarkfill.svg',
		bookmark: true,
	},

	get_usage_name (device) {
		const usage = this.usage_table[device.attributes.Nutzungsart];
		return usage ? usage.name : undefined;
	},
	get_soil_name (device) {
		const soil = this.soil_table[device.attributes.Bodenart];
		return soil ? soil.name : undefined;
	},
	get_humus_name (device) {
		const humus = this.humus_table[device.attributes.Humusgehalt];
		return humus ? humus.name : undefined;
	},

	get_usage_obj(device) {
		const key = device.attributes.Nutzungsart;
		return this.usage_table[key] || null;
	},

	get_soil_obj(device) {
		const key = device.attributes.Bodenart;
		return this.soil_table[key] || null;
	},

	get_humus_obj(device) {
		const key = device.attributes.Humusgehalt;
		return this.humus_table[key] || null;
	},

	get_water_obj(device) {
		if (device.attributes.Grundwasser) { 
			return this.grundwasser_obj;
		} else if (device.attributes.Bewässerung) {
			return this.bewaessert_obj;
		} else {
			return this.regenabhängig_obj;
		}
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
		if (value === null) return 'transparent'
		if (Number.isNaN(value)) return 'transparent'
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

	get_color_scheme_entry(type, key = state.colorScheme) {
		const schemes = this.color_schemes[type];
		if (!schemes) return undefined;
		return schemes[key] || schemes.normal || Object.values(schemes)[0];
	},

	get_color_scheme(type, key = state.colorScheme) {
		return this.get_color_scheme_entry(type, key)?.colors;
	},

	get_color_scheme_name(type, key = state.colorScheme) {
		return this.get_color_scheme_entry(type, key)?.name || key;
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

		let humus = device.attributes.Humusgehalt;
		const boden = device.attributes.Bodenart;

		if (humus == 'h0' || humus == 'h1') humus = 'h0-1'

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

	buildSoilTable(rawTable) {
		return Object.entries(rawTable).reduce((acc, [key, soil], index) => {
			const textur = this.augmentTextur(soil.textur);
			acc[key] = {
				...soil,
				textur,
				short: key,
				sort: index + 1,
				color: this.getSoilColor(key, { ...soil, textur }),
			};
			return acc;
		}, {});
	},

	augmentTextur(textur) {
		if (!textur || typeof textur !== 'object') {
			return textur;
		}

		return Object.fromEntries(
			Object.entries(textur).map(([type, range]) => {
				if (typeof range === 'number') {
					return [type, { avg: range }];
				}

				if (!range || typeof range !== 'object') {
					return [type, range];
				}

				return [type, {
					...range,
					avg: this.getTexturWeight(range),
				}];
			})
		);
	},

	getSoilColor(key, soil) {
		const colorMix = this.soilColorMixTable[key];
		if (colorMix && typeof colorMix === 'object') {
			const instructions = Object.entries(colorMix)
				.filter(([, amount]) => amount > 0);
			if (instructions.length) {
				return this.mixColors(instructions);
			}
		}

		return this.soilColors.lehm;
	},

	getTexturWeight(range) {
		if (typeof range === 'number') {
			return range;
		}

		if (!range || typeof range !== 'object') {
			return 0;
		}

		if (typeof range.avg === 'number') {
			return range.avg;
		}

		const min = typeof range.min === 'number' ? range.min : null;
		const max = typeof range.max === 'number' ? range.max : null;

		if (min != null && max != null) {
			return (min + max) / 2;
		}

		if (max != null) {
			return max;
		}

		if (min != null) {
			return min;
		}

		return 0;
	},

	mixColors(instructions) {
		if (!instructions || instructions.length === 0) {
			return '#000000';
		}

		const hexToRgbValue = hex => {
			hex = hex.replace('#', '');
			if (hex.length === 3) hex = hex.split('').map(x => x + x).join('');
			const num = parseInt(hex, 16);
			return {
				r: (num >> 16) & 255,
				g: (num >> 8) & 255,
				b: num & 255
			};
		};

		const toHex = value => {
			const hex = Math.round(value).toString(16);
			return hex.length === 1 ? '0' + hex : hex;
		};

		if (instructions.length === 1) {
			const [type, amount] = instructions[0];
			const baseHex = this.soilColors[type];
			if (!baseHex) return '#000000';

			const p = Math.max(0, Math.min(1, amount));
			const base = hexToRgbValue(baseHex);
			const white = { r: 255, g: 255, b: 255 };

			return '#' + [
				base.r * p + white.r * (1 - p),
				base.g * p + white.g * (1 - p),
				base.b * p + white.b * (1 - p)
			].map(toHex).join('');
		}

		let total = 0;
		let r = 0;
		let g = 0;
		let b = 0;

		for (const [type, amount] of instructions) {
			const hex = this.soilColors[type];
			if (!hex) continue;

			const weight = Math.max(0, amount);
			const rgb = hexToRgbValue(hex);

			total += weight;
			r += rgb.r * weight;
			g += rgb.g * weight;
			b += rgb.b * weight;
		}

		if (total === 0) {
			return '#000000';
		}

		return '#' + [r / total, g / total, b / total].map(toHex).join('');
	}


}

dataModel.soil_table = dataModel.buildSoilTable(dataModel.soil_table);

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
