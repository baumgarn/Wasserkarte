// dataStore.js
import { reactive } from 'vue';
import { state } from './state.js';
import { dataModel } from './datamodel.js';
import { config } from './config.js';
// import { vm } from './app_.js';

const backendurl = '/api'
const cacheddevicesurl = '/api/cache/devices.json'


const dataStore = reactive({

	dataCache: {},

	async fetchDevicesData() {

		const cacheRequest = fetch(cacheddevicesurl)
			.then(res => res.json())
			.then(data => ({ source: 'cache', data }))
			.catch(() => null);

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
				dataStore.processDevices(liveResult.data); 
			}
		});
	},

	processDevices(result) {
		state.devices = [];

		for (const key in result.devices) {
			const device = result.devices[key];
			if (device.attributes && Array.isArray(device.attributes)) {
				let attr = device.attributes.reduce((obj, attr) => {
					obj[attr.key] = attr.value;
					return obj;
				}, {});
				device.attributes = attr;

			}
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



	async fetchTelemetryData(deviceId, timerange, aggregation) {
		const key = `${deviceId}_${timerange}_${aggregation}`;

		if (this.dataCache[key]) {
			return this.dataCache[key];
		}

		const url = `/api/?deviceId=${deviceId}&time=${timerange}&agg=${aggregation}`;

		try {
			const response = await fetch(url);
			const json = await response.json();
			this.dataCache[key] = json;
			return json;
		} catch (error) {
			console.error("Failed to fetch data:", error);
			return [];
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
			if (this.timeSinceLastTelemetry(device.id.id) >= 48 
				|| (!device.attributes?.longitude || !device.attributes?.latitude)
				|| (!device.attributes?.Bodenart || !device.attributes?.Humusgehalt)) {
				state.faultyDevices.push(device);
			}
		}
		return state.faultyDevices;
	},

	getDevice(deviceId) {
		return state.devices.find(device => device.id.id === deviceId);
	},
	
	getDeviceByName(deviceName) {
		if (!deviceName) return null;
		return state.devices.find(device => device.name === deviceName);
	}
});


export default dataStore;
