import { reactive, watch, shallowRef } from 'vue';
import { fromLonLat } from 'ol/proj.js';
import { config } from '@/config.js';



export const state = reactive({
	// markerStyle: 'Bodenfeuchte_Farbkreis',
	hoverFilter: null,
	includeFilter: [],
	excludeFilter: [],
	filteredDevices: [],
	activeDevicesData: shallowRef([]),
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
	cacheTime: 0,
	loading: true,
	telemetryLoaded: false,
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
	timelineDate: null,
});

localStorageState('markerStyle', 'Bodenfeuchte_Farbkreis');
localStorageState('focusMode', false);
localStorageState('chartStyle', 'schichten');
localStorageState('timelineRange', '365d');
localStorageState('timelineStyle', 'nfk_avg');
localStorageState('showHelp', true);
localStorageState('showInfoOnStart', true);
localStorageState('showErrors', false);
localStorageState('debugAttributes', false);
localStorageState('colorScheme', 'normal');
localStorageState('filterFaultyValues', true);
localStorageState('showDataGaps', false);

localStorageState('tableview_compact', true);
localStorageState('tableview_attributes', true);
localStorageState('tableview_timelinerange', 365);

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

// watch filter and create the filteredDevices array
watch(
	[() => state.includeFilter, () => state.excludeFilter],
	([include, exclude]) => {
		state.filteredDevices = state.devices.filter(device => {
			const keywords = device.filterKeywords || [];

			// included: ALL includeFilter names must exist in keywords
			const included = include.length
				? include.every(f => keywords.includes(f.name))
				: true;

			// excluded: device is excluded if ANY excludeFilter name exists in keywords
			const excluded = exclude.length
				? exclude.every(f => !keywords.includes(f.name))
				: true;

			return included && excluded;
		});
	},
	{ immediate: true, deep: true }
);


// on load, reset attribute marker styles to bodenfeuchte farbkreis
if (state.markerStyle == 'nutzungsart' || state.markerStyle == 'wasserhaushalt' || state.markerStyle == 'bodenart' || state.markerStyle == 'humusgehalt') { state.markerStyle = 'Bodenfeuchte_Farbkreis' };