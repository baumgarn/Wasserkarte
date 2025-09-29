// dataStore.js
import { toRaw, reactive } from 'vue';
import { state } from './state.js';
import { dataModel } from './datamodel.js';
import { config } from './config.js';
import pako from 'pako';

// import { vm } from './app_.js';

const backendurl = '/api'
const cacheddevicesurl = '/api/cache/devices.json.gz'


const dataStore = reactive({

	dataCache: {},


	async fetchDevicesData() {
		try {
			const res = await fetch(cacheddevicesurl + '?' + dataStore.getTimestamp());
			const buf = await res.arrayBuffer();
			const text = pako.ungzip(new Uint8Array(buf), { to: 'string' });
			const data = JSON.parse(text);

			if (data?.devices) {
				dataStore.processDevices(data);
			}
		} catch (err) {
			console.error("Failed to fetch cached devices:", err);
		}
	},

	// async fetchDevicesData() {
	// 	// first we are getting the cached device data
	// 	const cacheRequest = fetch(cacheddevicesurl)
	// 		.then(res => res.json())
	// 		.then(data => ({ source: 'cache', data }))
	// 		.catch(() => null);

	// 	// then we are making a request to the apí for the live data
	// 	// if the cached data is beyond a certain date old, the server makes a request to Thingsboard for live data
	// 	const liveRequest = fetch(backendurl)
	// 		.then(res => res.json())
	// 		.then(data => ({ source: 'live', data }))
	// 		.catch(() => null);

	// 	Promise.race([cacheRequest, liveRequest]).then(firstResult => {
	// 		if (firstResult && firstResult.data?.devices) {
	// 			// Process Cached Data
	// 			dataStore.processDevices(firstResult.data);
	// 		}
	// 	});
		
	// 	Promise.all([cacheRequest, liveRequest]).then(([cacheResult, liveResult]) => {
	// 		if (liveResult && liveResult.data?.devices) {
	// 			// Process Live Data
	// 			dataStore.updateLiveTelemetry(liveResult.data); 
	// 		}
	// 	});
	// },

	processDevices(result) {
		state.devices = [];

	
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

	// updateLiveTelemetry(result) {
	// 	console.log('updateLiveTelemetry');
		
	// 	for (const key in result.devices) {
	// 		const liveDevice = result.devices[key];
	// 		const device = this.getDevice(liveDevice.id);
	// 		device.telemetry = liveDevice.telemetry
	// 	}

	// 	console.log('devices', state.devices)
	// },

	async fetchTelemetryData(deviceId, timerange, aggregation) {
		const cacheKey = `${deviceId}_${timerange}_${aggregation}`;

		if (this.dataCache[cacheKey]) {
			return this.dataCache[cacheKey];
		}

		const url = `/api/?deviceId=${deviceId}&time=${timerange}&agg=${aggregation}`;
		let attempts = 0;
		let json;

		while (attempts < 3) {
			try {
				const response = await fetch(url);
				if (!response.ok) throw new Error(`HTTP ${response.status}`);
				json = await response.json();

				if (state.filterFaultyValues) {
					json.telemetry = this.filterTelemetry(json.telemetry, -10, 100);
				}

				if (aggregation === "1d") {
					const device = this.getDevice(deviceId);
					const lastTelemetryData = [...toRaw(device.telemetrySchema.data)[0]];
					json.latestTimestamp = lastTelemetryData[0];
					json.telemetry.data.push(lastTelemetryData);
				}

				this.dataCache[cacheKey] = json;
				return json;

			} catch (error) {
				attempts++;
				console.warn(`Fetch attempt ${attempts} failed:`, error);

				if (attempts >= 3) {
					console.error("Failed to fetch data after 3 attempts:", error);
					return { data: {} };
				}

				await new Promise(r => setTimeout(r, 2000 * attempts));
			}
		}
	},

	filterTelemetry(telemetry, min = -10, max = 100) {
		if (!telemetry || !Array.isArray(telemetry.schema) || !Array.isArray(telemetry.data)) {
			return telemetry;
		}

		// find the indices of the moisture columns in the schema
		const moistureIdx = ["Bodenfeuchte_10cm","Bodenfeuchte_30cm","Bodenfeuchte_60cm","Bodenfeuchte_80cm"]
			.map((k) => telemetry.schema.indexOf(k))
			.filter((i) => i >= 0);

		if (moistureIdx.length === 0) return telemetry;

		const filteredData = telemetry.data.filter((row) => {
			return moistureIdx.every((i) => {
				const v = row[i];
				// Keep row only if all moisture values are within bounds
				return typeof v !== "number" || (v >= min && v <= max);
			});
		});

		return { ...telemetry, data: filteredData };

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
	},

	getTimestamp() {
		const now = new Date();

		const year = now.getFullYear();
		const month = String(now.getMonth() + 1).padStart(2, '0');
		const day = String(now.getDate()).padStart(2, '0');
		const hour = String(now.getHours()).padStart(2, '0');

		return `${year}${month}${day}${hour}`;
	}

});


export default dataStore;
