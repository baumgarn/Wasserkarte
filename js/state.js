import { reactive, watch, shallowRef } from 'vue';
import { fromLonLat } from 'ol/proj.js';
import { config } from '@/config.js';



export const state = reactive({
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

localStorageState('tableview_col_bookmarks', true);
localStorageState('tableview_col_attributes', true);
localStorageState('tableview_compact', true);
localStorageState('tableview_bookmarksontop', true);
localStorageState('tableview_timelinerange', 'all');
localStorageState('tableview_timelinestyle', 'nfk_avg');
localStorageState('tableview_showdepths', false);

computedState('sidebarOpen',()=> state.selectedDevice || state.menuOpen.info );

// BOOKMARKS

localStorageState('bookmarks', []);

export function isBookmarked(device) {
	const deviceId = device && device.id;
	if (deviceId == null) return false;
	return state.bookmarks.includes(deviceId);
}

export function toggleBookmark(device) {
	const deviceId = device && device.id;
	if (deviceId == null) return false;

	if (isBookmarked(device)) {
		state.bookmarks = state.bookmarks.filter((id) => id !== deviceId);
		return false;
	}

	state.bookmarks = [...state.bookmarks, deviceId];
	return true;
}


// LOCAL STORAGE

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

// FILTER

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
