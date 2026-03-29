import { reactive, watch, shallowRef } from 'vue';
import { fromLonLat } from 'ol/proj.js';
import { dataModel } from '@/datamodel.js'
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
	popupMenuOpen: false,
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
localStorageState('soilchart_texture_showbars', true);
localStorageState('bodenkundeHumusSoilKey', 'Sl2');

localStorageState('tableview_col_bookmarks', true);
localStorageState('tableview_col_attributes', true);
localStorageState('tableview_col_nfkavg', true);
localStorageState('tableview_col_von', true);
localStorageState('tableview_compact', true);
localStorageState('tableview_bookmarksontop', false);
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

	dataModel.bookmarkfilter_obj.count = state.bookmarks.length;
	state.bookmarks = [...state.bookmarks, deviceId];
	return true;
}


dataModel.bookmarkfilter_obj.count = state.bookmarks.length;

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

export function closeAllMenuWindowsAndSidebar() {
	state.selectedDevice = null;
	state.sidebarFullView = false;

	for (const key in state.menuOpen) {
		if (Object.prototype.hasOwnProperty.call(state.menuOpen, key)) {
			state.menuOpen[key] = false;
		}
	}

	state.wsmlegends = false;
	state.mobilemenuOpen = false;
	window.dispatchEvent(new CustomEvent('app:close-popovers'));
}

// FILTER

function deviceMatchesFilter(device, filter) {
	if (!filter) return true;
	if (filter.bookmark) return isBookmarked(device);

	const keywords = device.filterKeywords || [];
	return keywords.includes(filter.name);
}

function removeBookmarkFilters(filters) {
	return filters.filter(filter => !filter || !filter.bookmark);
}

watch(
	() => state.bookmarks.length,
	(bookmarkCount) => {
		if (bookmarkCount > 0) return;

		state.includeFilter = removeBookmarkFilters(state.includeFilter);
		state.excludeFilter = removeBookmarkFilters(state.excludeFilter);
	},
	{ immediate: true }
);

// watch filter and create the filteredDevices array
watch(
	[() => state.devices, () => state.includeFilter, () => state.excludeFilter, () => state.bookmarks],
	([devices, include, exclude]) => {
		state.filteredDevices = devices.filter(device => {

			// included: ALL includeFilter items must match
			const included = include.length
				? include.every(filter => deviceMatchesFilter(device, filter))
				: true;

			// excluded: device is excluded if ANY excludeFilter item matches
			const excluded = exclude.length
				? exclude.every(filter => !deviceMatchesFilter(device, filter))
				: true;

			return included && excluded;
		});
	},
	{ immediate: true, deep: true }
);



// on load, reset attribute marker styles to bodenfeuchte farbkreis
if (state.markerStyle == 'nutzungsart' || state.markerStyle == 'wasserhaushalt' || state.markerStyle == 'bodenart' || state.markerStyle == 'humusgehalt') { state.markerStyle = 'Bodenfeuchte_Farbkreis' };
