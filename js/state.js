import { reactive, watch } from 'vue';
import { fromLonLat } from 'ol/proj.js';
import { config } from '@/config.js';



export const state = reactive({
	markerStyle: 'Bodenfeuchte_Farbkreis',
	selectedSoil: 'Alle',
	selectedDevice: null,
	selectedDeviceEmbed: null,
	devicesMultiselect: [],
	mouseoverDevice: null,
	sidebarFullView: false,
	mapZoom: config.mapZoom,
	minimapZoom: config.minimapZoom,
	mapCenter: fromLonLat(config.initialLocation),
	devices: [],
	faultyDevices: [],
	uniqueBoden: [],
	uniqueHumus: [],
	uniqueSoilTypes: [],
	uniqueTelemetryKeys: [],
	loading: true,
	wsmLayers: [],
	wsmtransparency: false,
	wsmlegends: false,
	menuOpen: {},
	chartTimeRange: -1,
	dataAggregation: '1d',
	iframeWidth: '800',
	iframeHeight: '950',
	qrcodeStyle: 'schild',
	windowWidth: 0,
	windowHeight: 0,
	isMobile: false,
	tooltips: true,
	markerClicked: false,
});

localStorageState('chartStyle', 'schichten');
localStorageState('showHelp', true);
localStorageState('showInfoOnStart', true);
localStorageState('showErrors', false);
localStorageState('colorScheme', 'normal');
localStorageState('filterFaultyValues', true);

computedState('sidebarOpen',()=> state.selectedDevice || state.menuOpen.info );



// Stores and retrieves state property in local storage
function localStorageState(key, defaultValue) {
	if (localStorage.getItem(key)) {
		state[key] = JSON.parse(localStorage.getItem(key));
	} else {
		state[key] = defaultValue;
	}
	window.addEventListener('beforeunload', () => {
		localStorage.setItem(key, JSON.stringify(state[key]));
	});
}

// Defines a computed reactive state property.
// Automatically updates the property when dependencies change
export function computedState(key, getter, options = {}) {
	state[key] = getter();
	watch(getter, (val) => {
		state[key] = val;
	}, { immediate: false, ...options });
}


// console.log(state)