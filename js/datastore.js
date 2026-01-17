
import { toRaw } from 'vue';
import { state } from './state.js';
import { dataModel } from './datamodel.js';
import { config } from './config.js';
import pako from 'pako';

const cacheddevicesurl = '/api/cache/devices.json.gz'
const alltelemetryurl = '/api/cache/alltelemetry.json.gz'

const dataStore = {

	dataCache: {},

	async fetchDevicesData() {
		try {
			// Fetch device data file and all telemetry file 
			const devicesPromise = fetch(cacheddevicesurl + '?' + dataStore.getTimestampForApiRequest())
				.then(res => res.arrayBuffer())
				.then(buf => {
					const text = pako.ungzip(new Uint8Array(buf), { to: 'string' });
					const data = JSON.parse(text);
					if (data?.devices) {
						dataStore.processDevices(data);
					}
					return data; //
				});

			const telemetryPromise = fetch(alltelemetryurl + '?' + dataStore.getTimestampForApiRequest())
				.then(res => res.arrayBuffer())
				.then(buf => {

					let start = performance.now();
					const text = pako.ungzip(new Uint8Array(buf), { to: 'string' });
					console.log("Telemetry data uncompressed in", (performance.now() - start).toFixed(2), "ms");

					start = performance.now();
					const json = JSON.parse(text);
					console.log("Telemetry data json parsed in ", (performance.now() - start).toFixed(2), "ms");

					return json;
				});

			const [devicesData, telemetryData] = await Promise.all([devicesPromise, telemetryPromise]);

			if (telemetryData) {
				dataStore.processAllTelemetry(telemetryData);
			}

		} catch (err) {
			console.error("Failed to fetch data:", err);
		}
	},

	processDevices(result) {
		state.devices = [];
		state.cacheTime = result.telemetryCacheTimestamp;
		let index = 0;
		
		for (const key in result.devices) {
			var device = result.devices[key];
			device.index = index;
			
			if (device.attributes?.Humusgehalt == 'h0-1') device.attributes.Humusgehalt = 'h0';
			if (device.attributes?.Humusgehalt_10cm == 'h0-1') device.attributes.Humusgehalt_10cm = 'h0';
			if (device.attributes?.Humusgehalt_20cm == 'h0-1') device.attributes.Humusgehalt_20cm = 'h0';
			if (device.attributes?.Humusgehalt_30cm == 'h0-1') device.attributes.Humusgehalt_30cm = 'h0';
			if (device.attributes?.Humusgehalt_40cm == 'h0-1') device.attributes.Humusgehalt_40cm = 'h0';
			
			dataStore.processFilterKeywords(device)
			
			state.devices[index] = device;
			index++;
		}

		this.sortFaultyDevices();

		document.body.classList.remove('loading');
		document.body.classList.add('loaded');
		state.loading = false;
	},


	processFilterKeywords(device) {
		device.filterKeywords = [];
		const nutzung = dataModel.get_usage_obj(device);
		const boden = dataModel.get_soil_obj(device);
		const humus = dataModel.get_humus_obj(device);
		if (nutzung) {
			device.filterKeywords.push(nutzung.name);
			if (!nutzung.count) nutzung.count = 0;
			nutzung.count ++;
		}
		if (boden) {
			device.filterKeywords.push(boden.name);
			if (!boden.count) boden.count = 0;
			boden.count++;
		}
		if (humus) {
			device.filterKeywords.push(humus.name);
			if (!humus.count) humus.count = 0;
			humus.count++;
		}
		if (device?.attributes?.Bewässerung) { 
			device.filterKeywords.push(dataModel.bewaessert_obj.name);
			if (!dataModel.bewaessert_obj.count) dataModel.bewaessert_obj.count = 0;
			dataModel.bewaessert_obj.count++;
		} else if (device?.attributes?.Grundwasser) { 
			device.filterKeywords.push(dataModel.grundwasser_obj.name);
			if (!dataModel.grundwasser_obj.count) dataModel.grundwasser_obj.count = 0;
			dataModel.grundwasser_obj.count++;
		} else {
			device.filterKeywords.push(dataModel.regenabhängig_obj.name);
			if (!dataModel.regenabhängig_obj.count) dataModel.regenabhängig_obj.count = 0;
			dataModel.regenabhängig_obj.count++;
		}
	},

	processAllTelemetry(result) {

		this.nfk_daily_averages = result.nfk_daily_averages;

		for (const [deviceId, deviceTelemetry] of Object.entries(result.devices)) {
			
			const device = this.getDeviceById(deviceId);
			let telemetry = deviceTelemetry;
			
			// for daily aggregated data, add latest life data point, because daily aggregates are cut off at the last day
			const lastTelemetryData = [...toRaw(device.telemetrySchema.data)[0]];

			// TODO fehlerhafte letzte telemetrie ausschließen

			telemetry.data.push(lastTelemetryData);
			if (state.filterFaultyValues) {
				telemetry = this.filterTelemetry(deviceTelemetry, -5, 100);
			}

			const cacheKey = `${deviceId}_all_1d`;
			this.dataCache[cacheKey] = telemetry;
			state.telemetryLoaded = true; 
		}
	},

	fetchTelemetryCache(deviceId) {
		const cacheKey = `${deviceId}_all_1d`;

		if (this.dataCache[cacheKey]) {
			return this.dataCache[cacheKey];
		}
	},

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
		
				// for daily aggregated data, add latest life data point, because daily aggregates are cut off at the last day
				// if (aggregation === "1d") { 
				// 	const device = this.getDeviceById(deviceId);
				// 	const lastTelemetryData = [...toRaw(device.telemetrySchema.data)[0]];
				// 	json.telemetry.data.push(lastTelemetryData);
				// }

				if (state.filterFaultyValues) {
					json.telemetry = this.filterTelemetry(json.telemetry, -5, 100);
				}
				
				this.dataCache[cacheKey] = json.telemetry;
				return json.telemetry;

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

	filterTelemetry(telemetry, min = -5, max = 100) {
		if (!telemetry || !Array.isArray(telemetry.schema) || !Array.isArray(telemetry.data)) {
			return telemetry;
		}

		// find the indices of the moisture columns in the schema
		const moistureIndices = ["Bodenfeuchte_10cm","Bodenfeuchte_30cm","Bodenfeuchte_60cm","Bodenfeuchte_80cm"]
			.map((k) => telemetry.schema.indexOf(k))
			.filter((i) => i >= 0);

		if (moistureIndices.length === 0) return telemetry;

		const filteredData = telemetry.data.filter((row) => {
			return moistureIndices.every((i) => {
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

	// timestamp used to prevend already cached json request
	getTimestampForApiRequest() {

		const now = new Date();
		const year = now.getFullYear();
		const month = String(now.getMonth() + 1).padStart(2, '0');
		const day = String(now.getDate()).padStart(2, '0');
		const hour = String(now.getHours()).padStart(2, '0');

		return `${year}${month}${day}${hour}`;
	},


	hoursSinceLastTelemetry(deviceId) {

		if (this.dataCache[deviceId]) {
			const latestTimestamp = this.dataCache[deviceId].latestTimestamp;
			const hours = (Date.now() - latestTimestamp) / (1000 * 60 * 60);
			return hours
		}

		const device = this.getDeviceById(deviceId);

		let latestTimestamp = 0;

		// Traverse all telemetry properties
		Object.entries(device.telemetry).forEach(([key, dataArray]) => {
			if (config.allowedTelemetryKeys.includes(key) && Array.isArray(dataArray) && dataArray.length > 0) {
				const latestEntry = dataArray[dataArray.length - 1];
				if (latestEntry.ts > latestTimestamp) {
					latestTimestamp = latestEntry.ts;
				}
			}
		});

		if (latestTimestamp == 0) {
			return -1
		}
		const ms = (Date.now() - latestTimestamp);
		const hours = ms / (1000 * 60 * 60);
		return hours;
	},

	lastTelemetry(deviceId) {
		const device = this.getDeviceById(deviceId);
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

	sortFaultyDevices() {
		state.faultyDevices = [];

		for (const device of state.devices) {
			const hours = this.hoursSinceLastTelemetry(device.id);

			if (
				(hours >= 48 || hours == -1) ||
				(!device.attributes?.longitude || !device.attributes?.latitude) ||
				(!device.attributes?.Bodenart || !device.attributes?.Humusgehalt)
			) {
				state.faultyDevices.push(device);
			}
		}

		state.faultyDevices.sort((a, b) => {
			const hoursA = this.hoursSinceLastTelemetry(a.id);
			const hoursB = this.hoursSinceLastTelemetry(b.id);

			// -1 should always be at the top
			if (hoursA === -1 && hoursB !== -1) return 1;
			if (hoursB === -1 && hoursA !== -1) return -1;

			// reverse order: smaller first
			return hoursA - hoursB;
		});

		return state.faultyDevices;
	},

	getDeviceByIndex(index) {
		return state.devices[index];
	},

	getDeviceById(deviceId) {
		return state.devices.find(device => device.id === deviceId);
	},
	
	getDeviceByName(deviceName) {
		if (!deviceName) return null;
		return state.devices.find(device => device.name === deviceName);
	},

	
	// getDataAtTimestamp(deviceId, timestamp) {
	// 	const data = this.fetchTelemetryCache(deviceId)?.data;
	// 	const index = this.get_telemetry_index_binary(data, timestamp);
	// 	if (index > -1) {
	// 		return data[index];
	// 	}
	// },

	getDataAtTimestamp(deviceIndex, timestamp) {

		const telemetryRows = dataStore.getTelemetryForDay(timestamp);
		// For a device:
		const row = telemetryRows[deviceIndex];
		if (row) {
			return row;
		}
	},

	// Gets all telemetry rows for a given day and caches them 
	getTelemetryForDay(timestamp) {
		if (!this.dayCache) this.dayCache = {};

		// Return cached if available
		if (this.dayCache[timestamp]) return this.dayCache[timestamp];
		
		// Build cache for this day
		const rows = state.devices.map(device => {
			const telemetry = this.fetchTelemetryCache(device.id)?.data;
			if (!telemetry?.length) return null;

			const idx = this.get_telemetry_index_binary(telemetry, timestamp);
			return idx >= 0 ? telemetry[idx] : null;
		});

		this.dayCache[timestamp] = rows;
		return rows;
	},

	// Binary search timestamp in telemetry rows
	get_telemetry_index_binary(data, timestamp) { 
		const n = data.length;
		if (n < 2) return -1;
		let lo = 0, hi = n; // search in [0, n)
		while (lo < hi) {
			const mid = (lo + hi) >>> 1;
			if (this.tsAt(data, mid) <= timestamp) lo = mid + 1;
			else hi = mid;
		}
		const i = lo - 1; // rightmost <= T
		if (i < 0 || i >= n - 1) return -1;
		return i;
	},

	tsAt(data, i) {
		return data[i][0];
	},

};

export default dataStore;
