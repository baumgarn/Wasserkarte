


export const config = {

	initialLocation : [12.59272, 52.14184], // bad belzig

	minimapZoom: 16,
	mapZoom: 11,

	dataGapLength: 24 * 60 * 60 * 1000 * 5, // 5 Days in ms
	timelineHoverCutoff: 24 * 60 * 60 * 1000 * 2, // 2 Days in ms
	segmentation: false,

	infoArrowDevice : 'wiesenburg-schlosspark',

	allowedTelemetryKeys: [
		'Bodenfeuchte_10cm',
		'Bodenfeuchte_30cm',
		'Bodenfeuchte_60cm',
		'Bodenfeuchte_80cm',
		'Bodentemperatur_10cm',
		'Bodentemperatur_30cm',
		'Bodentemperatur_60cm',
		'Bodentemperatur_80cm'
	],

	excludeFromMapFit: ['wassermeisterei002', 'caputh-schwielosee-1', 'caputh-schwielosee-kiefer', 'caputh-nadelwald'],

	minMaxValues: {
		'Bodenfeuchte': {min: -10, max: 100},
		'Bodentemperatur': {min: -50, max: 100},
	},

	wmsUrls : [
		'https://inspire.brandenburg.de/services/gk_wms?request=GetCapabilities&service=WMS',
		'https://inspire.brandenburg.de/services/so_bk50_wms?request=GetCapabilities&service=WMS',
		'https://inspire.brandenburg.de/services/bokarten_wms?request=GetCapabilities&service=WMS',
		'https://inspire.brandenburg.de/services/gmk_wms?request=GetCapabilities&service=WMS',
	],

	wmsExcludedLayers: [
	],

	graphColors: [
		{ depth: 10, color: 'green' },
		{ depth: 30, color: '#ffae00' },
		{ depth: 60, color: 'red' },
		{ depth: 80, color: '#c41ec4' }
	],

}
