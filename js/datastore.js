// dataStore.js
import { toRaw, reactive } from 'vue';
import { state } from './state.js';
import { dataModel } from './datamodel.js';
import { config } from './config.js';
// import { vm } from './app_.js';

const backendurl = '/api'
const cacheddevicesurl = '/api/cache/devices.json'


const dataStore = reactive({

	dataCache: {},

	async fetchDevicesData() {
		// first we are getting the cached device data
		const cacheRequest = fetch(cacheddevicesurl)
			.then(res => res.json())
			.then(data => ({ source: 'cache', data }))
			.catch(() => null);

		// then we are making a request to the apí for the live data
		// if the cached data is beyond a certain date old, the server makes a request to Thingsboard for live data
		const liveRequest = fetch(backendurl)
			.then(res => res.json())
			.then(data => ({ source: 'live', data }))
			.catch(() => null);

		Promise.race([cacheRequest, liveRequest]).then(firstResult => {
			if (firstResult && firstResult.data?.devices) {
				// Process Cached Data
				dataStore.processDevices(firstResult.data);
			}
		});
		
		Promise.all([cacheRequest, liveRequest]).then(([cacheResult, liveResult]) => {
			if (liveResult && liveResult.data?.devices) {
				// Process Live Data
				dataStore.updateLiveTelemetry(liveResult.data); 
			}
		});
	},

	processDevices(result) {
		state.devices = [];
		console.log('processDevices');

		for (const key in result.devices) {
			const device = result.devices[key];
			dataModel.wasserkapazität_setzen(device);
			state.devices.push(device);
		}

		state.uniqueTelemetryKeys = dataStore.extractUniqueTelemetryKeys(result.devices);
		state.uniqueBoden = dataStore.extractUniqueAttributes(result.devices, 'Bodenart');
		state.uniqueHumus = dataStore.extractUniqueAttributes(result.devices, 'Humusgehalt');

		this.sortFaultyDevices();

		document.body.classList.remove('loading');
		document.body.classList.add('loaded');
		state.loading = false;

	},

	updateLiveTelemetry(result) {
		console.log('updateLiveTelemetry');
		
		for (const key in result.devices) {
			const liveDevice = result.devices[key];
			const device = this.getDevice(liveDevice.id);
			device.telemetry = liveDevice.telemetry
		}

		console.log('devices', state.devices)
	},

	async fetchTelemetryData(deviceId, timerange, aggregation) {
		const cacheKey = `${deviceId}_${timerange}_${aggregation}`;

		if (this.dataCache[cacheKey]) {
			return this.dataCache[cacheKey];
		}

		const url = `/api/?deviceId=${deviceId}&time=${timerange}&agg=${aggregation}`;
		try {

			const response = await fetch(url);
			const json = await response.json();

			if (aggregation == '1d') { 
				// with daily aggregates, we want to append the last live data point because aggregated data ends at the last day
				// but only if last data is today
				const device = this.getDevice(deviceId);
				const lastTelemetryData = [...toRaw(device.telemetrySchema.data)[0]];
				json.latestTimestamp = lastTelemetryData[0];
				json.telemetry.data.push(lastTelemetryData)
			}

			this.dataCache[cacheKey] = json;


			return json;

		} catch (error) {
			console.error("Failed to fetch data:", error);
			return { data: {} };
		}
	},
	
	getApiUrl(deviceId, timerange, aggregation) {
		return `/api/?deviceId=${deviceId}&time=${timerange}&agg=${aggregation}`;
	},

	timeSinceLastTelemetry(deviceId) {
		
		if (this.dataCache[deviceId]) {
			const latestTimestamp = this.dataCache[deviceId].latestTimestamp;
			const hours = (Date.now() - latestTimestamp) / (1000 * 60 * 60);
			return hours
		}
		
		const device = this.getDevice(deviceId);

		let latestTimestamp = 0;

		// Traverse all telemetry properties
		Object.values(device.telemetry).forEach(dataArray => {
			if (Array.isArray(dataArray) && dataArray.length > 0) {
				const latestEntry = dataArray[dataArray.length - 1]; // Get the last entry in the array
				if (latestEntry.ts > latestTimestamp) {
					latestTimestamp = latestEntry.ts; // Update the latest timestamp
				}
			}
		});

		const hours = (Date.now() - latestTimestamp) / (1000 * 60 * 60);
		return hours;
	},

	lastTelemetry(deviceId) {
		const device = this.getDevice(deviceId);
		if (!device || !device.telemetry || !device.telemetry.received_at) {
			return '-';
		}

		const measurements = device.telemetry.received_at;
		if (!Array.isArray(measurements) || measurements.length === 0) {
			return '-';
		}

		const receivedTime = new Date(measurements[0].value);
		if (isNaN(receivedTime.getTime())) {
			return '-';
		}

		const date = receivedTime.toLocaleDateString('de-DE', {
			day: 'numeric',
			month: 'long'
		});
		const time = receivedTime.toLocaleTimeString('de-DE', {
			hour: '2-digit',
			minute: '2-digit'
		});
		return `${date} ${time} Uhr`;
	},

	extractUniqueTelemetryKeys(devices) {
		const uniqueKeys = new Set();
		devices.forEach(device => {
			if (device.telemetry) {
				Object.keys(device.telemetry).forEach(key => {
					if (config.allowedTelemetryKeys.includes(key)) {
						uniqueKeys.add(key);
					}
				});
			}
		});
		return [...uniqueKeys].sort();
	},

	extractUniqueAttributes(devices, attribute) {
		const uniqueKeys = new Set();
		devices.forEach(device => {
			if (device.attributes) {
				Object.keys(device.attributes).forEach(key => {
					if (key.includes(attribute)) {
						uniqueKeys.add(device.attributes[key]);
					}
				});
			}
		});
		return [...uniqueKeys].sort();
	},

	sortFaultyDevices() {
		state.faultyDevices = []
		for (const device of state.devices) {
			if (this.timeSinceLastTelemetry(device.id) >= 48 
				|| (!device.attributes?.longitude || !device.attributes?.latitude)
				|| (!device.attributes?.Bodenart || !device.attributes?.Humusgehalt)) {
				state.faultyDevices.push(device);
			}
		}
		return state.faultyDevices;
	},

	getDevice(deviceId) {
		return state.devices.find(device => device.id === deviceId);
	},
	
	getDeviceByName(deviceName) {
		if (!deviceName) return null;
		return state.devices.find(device => device.name === deviceName);
	}
});


export default dataStore;
