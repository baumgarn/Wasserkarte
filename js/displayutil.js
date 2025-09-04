import { config } from './config.js';



export const displayutil = {

	soilMoistureLevels : {
		"reiner-sand-humusarm": [0, 3.2, 4.53, 9.85, 12.51, 15.17, 19.16],
		"reiner-sand-humusreich": [0, 5, 6.33, 11.65, 14.31, 16.97, 20.96],
		"reiner-sand-sehr-humusreich": [0, 5, 6.33, 11.65, 14.31, 16.97, 20.96],
		"schwach-lehmiger-sand-humusarm": [0, 6, 7.76, 14.8, 18.32, 21.84, 27.12],
		"schwach-lehmiger-sand-humusreich": [0, 6.4, 8.08, 14.8, 18.16, 21.52, 26.56],
		"stark-lehmiger-sand-humusarm": [0, 8.3, 10.03, 16.95, 20.41, 23.87, 29.06],
		"stark-lehmiger-sand-humusreich": [0, 10.6, 12.29, 19.05, 22.43, 25.81, 30.88]
	},

	soilMoistureLevelNames : {
		0: "Wasser nicht verfügbar",
		1: "Extremer Trockenstress",
		2: "Trockenstress",
		3: "Ausreichende Wasserversorgung",
		4: "Gute Wasserversorgung",
		5: "Möglicher Sauerstoffmangel",
		6: "Sauerstoffmangel",
	},

	soilMoistureLevelNFK : {
		0: "0",
		1: "0-10",
		2: "10-50",
		3: "50-70",
		4: "70-90",
		5: "90-120",
		6: ">120",
	},

	
	soilMoistureLevelColors : {
		0: "#ff8e70",
		1: "#ffcf4c",
		2: "#feff4c",
		3: "#ddf186",
		4: "#9ff24c",
		5: "#4ce6cc",
		6: "#55c9f0",
	},
	
	defaultColor: "#7adad0",

	soilMoistureLevelColorsDark : {
		0: "#ff1c00",
		1: "#ffbb00",
		2: "#ffff00",
		3: "#cfeb52",
		4: "#75eb00",
		5: "#00dbb8",
		6: "#11b5ea",
	},

	getSoilMoistureLevelName: function(soilType, moisture) {
		const index = this.getSoilMoistureIndex(soilType, moisture);
		return this.soilMoistureLevelNames[index];
	},

	getSoilMoistureLevelNFK: function(soilType, moisture) {
		const index = this.getSoilMoistureIndex(soilType, moisture);
		return this.soilMoistureLevelNFK[index];
	},

	getSoilMoistureIndex: function(soilType, moisture) {
		const levels = this.soilMoistureLevels[soilType];
		if (!levels) {
			// console.log(`Unbekannter Bodentyp: ${soilType}`);
			return 0; 
		}
		for (let i = levels.length - 1; i >= 0; i--) {
			if (moisture >= levels[i]) {
				return i;
			}
		}
		return 0; 
	},

	getSoilMoistureIndexInterpolated: function(soilType, moisture) {
		
		const levels = this.soilMoistureLevels[soilType];
		if (!levels || !soilType) {
			// console.log(`Unbekannter Bodentyp: ${soilType}`);
			return -1; 
		}

		if (moisture <= levels[0]) return 0;
		if (moisture >= levels[levels.length - 1]) return levels.length - 1;

		for (let i = 1; i < levels.length; i++) {
			if (moisture <= levels[i]) {
				const lowerValue = levels[i-1];
				const upperValue = levels[i];
				const lowerIndex = i-1;
				const upperIndex = i;
				return lowerIndex + (moisture - lowerValue) * (1) / (upperValue - lowerValue);
			}
		}
		return -1;
	},

	getBackgroundColorStyle: function (soilType, moisture) {
		if (!soilType) {
			return '';
		}
		try {
			const color = this.getSoilMoistureColor(soilType, moisture);
			return `background-color: ${color}`;
		} catch (e) {
			return '';
		}
	},

	getTemperatureColor: function (temperature) {	
		if (temperature < 0) {
			return "#cfeffb";
		} else {
			return "#feff4c";
		}
	},

	randomColor: function() {
		return '#' + Math.floor(Math.random()*16777215).toString(16);
	},

	getDepthColor(depth) {
		const match = config.graphColors.find(dc => dc.depth === depth);
		return match ? match.color : '#000000';
	},

	getColor: function(type, value, soilType) {
		switch (type) {
			case 'bodenfeuchte':
				return this.getSoilMoistureColor(soilType, value);
			case 'bodenfeuchteflat':
				return this.getSoilMoistureColorFlat(soilType, value);
			case 'temperatur':
				return this.getTemperatureColor(value);
		}
	},

	getSoilMoistureColorFlat: function (soilType, moisture) {
		const index = this.getSoilMoistureIndex(soilType, moisture);
		return this.soilMoistureLevelColors[index];
	},

	getSoilMoistureColor: function (soilType, moisture) {

		const index = this.getSoilMoistureIndexInterpolated(soilType, moisture);
		if (index === -1) {
			return this.defaultColor;
		}
		const lowerIndex = Math.floor(index);
		const upperIndex = Math.ceil(index);

		if (lowerIndex === upperIndex) {
			return this.soilMoistureLevelColors[lowerIndex];
		}

		// Convert hex colors to RGB
		const color1 = this.hexToRgb(this.soilMoistureLevelColors[lowerIndex]);
		const color2 = this.hexToRgb(this.soilMoistureLevelColors[upperIndex]);

		// Calculate interpolation factor
		const factor = index - lowerIndex;

		// Interpolate RGB values
		const r = Math.round(color1.r + (color2.r - color1.r) * factor);
		const g = Math.round(color1.g + (color2.g - color1.g) * factor);
		const b = Math.round(color1.b + (color2.b - color1.b) * factor);

		// Convert back to hex
		return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
	},


	// Helper function to convert hex to RGB
	hexToRgb: function (hex) {
		const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
		return result ? {
			r: parseInt(result[1], 16),
			g: parseInt(result[2], 16),
			b: parseInt(result[3], 16)
		} : null;
	},

	title: function(name) {
		switch (name) {
			case "Bodenfeuchte_10cm":
				return "Bodenfeuchte 10 cm";
			case "Bodenfeuchte_30cm":
				return "Bodenfeuchte 30 cm";
			case "Bodenfeuchte_60cm":
				return "Bodenfeuchte 60 cm";
			case "Bodenfeuchte_80cm":
				return "Bodenfeuchte 80 cm";
			case "Bodentemperatur_10cm":
				return "Bodentemperatur 10 cm";
			case "Bodentemperatur_30cm":
				return "Bodentemperatur 30 cm";
			case "Bodentemperatur_60cm":
				return "Bodentemperatur 60 cm";
			case "Bodentemperatur_80cm":
				return "Bodentemperatur 80 cm";
			case "Bodenfeuchte":
				return "Bodenfeuchte Übersicht";
			case "reiner-sand-humusarm":
				return "Reiner Sand humusarm";
			case "reiner-sand-humusreich":
				return "Reiner Sand humusreich";
			case "reiner-sand-sehr-humusreich":
				return "Reiner Sand sehr humusreich";
			case "schwach-lehmiger-sand-humusarm":
				return "Schwach lehmiger Sand humusarm";
			case "lehmiger-sand-humusreich":
				return "Lehmiger Sand humusreich";
			case "schwach-lehmiger-sand-humusreich":
				return "Schwach lehmiger Sand humusreich";
			case "stark-lehmiger-sand-humusarm":
				return "Stark lehmiger Sand humusarm";
			case "stark-lehmiger-sand-humusreich":
				return "Stark lehmiger Sand humusreich";
			default:
				return name;
		}
	},
	depth: function(name) {
		switch (name) {
			case "Bodenfeuchte_10cm":
				return "10 cm";
			case "Bodenfeuchte_30cm":
				return "30 cm";
			case "Bodenfeuchte_60cm":
				return "60 cm";
			case "Bodenfeuchte_80cm":
				return "80 cm";
			case "Bodentemperatur_10cm":
				return "10 cm";
			case "Bodentemperatur_30cm":
				return "30 cm";
			case "Bodentemperatur_60cm":
				return "60 cm";
			case "Bodentemperatur_80cm":
				return "80 cm";
			default:
				return name;
		}
	},
	depthValue: function(name) {
		switch (name) {
			case "Bodenfeuchte_10cm":
				return 10;
			case "Bodenfeuchte_30cm":
				return 30;
			case "Bodenfeuchte_60cm":
				return 60;
			case "Bodenfeuchte_80cm":
				return 80;
			case "Bodentemperatur_10cm":
				return 10;
			case "Bodentemperatur_30cm":
				return 30;
			case "Bodentemperatur_60cm":
				return 60;
			case "Bodentemperatur_80cm":
				return 80;
			default:
				return name;
		}
	},

	unit: function(name) {
		switch (name) {
			case "Bodenfeuchte_10cm":
				return "%";
			case "Bodenfeuchte_30cm":
				return "%";
			case "Bodenfeuchte_60cm":
				return "%";
			case "Bodenfeuchte_80cm":
				return "%";
			case "Bodentemperatur_10cm":
				return "°C";
			case "Bodentemperatur_30cm":
				return "°C";
			case "Bodentemperatur_60cm":
				return "°C";
			case "Bodentemperatur_80cm":
				return "°C";
			default:
				return '';
		}
	},

	formatDateShort: function (timestamp) {
		const date = new Date(timestamp);
		const dayOfMonth = date.getDate();
		const month = date.toLocaleString('de-DE', { month: 'short' });
		const year = date.toLocaleString('de-DE', { year: 'numeric' });
		return `${dayOfMonth}. ${month} ${year}`;
	},
	formatDateAggregated: function (timestamp) {
		// we are subtracting one day here since we have our aggregated data timestamp at midnight the end of the day, but don't want to display as next day 
		const date = new Date(timestamp);
		date.setDate(date.getDate() - 1);
		const dayOfMonth = date.getDate();
		const month = date.toLocaleString('de-DE', { month: 'short' });
		const year = date.toLocaleString('de-DE', { year: 'numeric' });
		return `${dayOfMonth}. ${month} ${year}`;
	},
	formatDateTime: function (timestamp) {
		const date = new Date(timestamp);
		return date.toLocaleString('de-DE', { hour: '2-digit', minute: '2-digit' });
	},
	formatDate: function (timestamp) {
		const date = new Date(timestamp);
		return new Intl.DateTimeFormat('de-DE', {
			day: '2-digit',
			month: 'long',
			year: 'numeric',
			hour: '2-digit',
			minute: '2-digit',
			hour12: false
		}).format(date)
			.replace(/([A-Za-z]{3})\.(\s)/g, '$1$2')  // Remove dot after month abbreviation
			.replace(' um ', ', ');  // Remove the "um" word
	},
	formatDateNoTime: function (timestamp) {
		const date = new Date(timestamp);
		return new Intl.DateTimeFormat('de-DE', {
			day: '2-digit',
			month: 'short',
			year: 'numeric'
		}).format(date)
			.replace(/([A-Za-z]{3})\.(\s)/g, '$1$2');  // Remove dot after month abbreviation
	},
}
