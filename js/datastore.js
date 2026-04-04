
import { toRaw } from 'vue';
import { state } from './state.js';
import { dataModel } from './datamodel.js';
import { config } from './config.js';
import { displayutil } from './displayutil.js';
import pako from 'pako';

const cacheddevicesurl = '/api/cache/devices.json.gz'
const alltelemetryurl = '/api/cache/alltelemetry.json.gz'
const textDecoder = new TextDecoder();

function parseCompressedOrPlainJson(buf) {
	const bytes = new Uint8Array(buf);

	if (bytes.length >= 2 && bytes[0] === 0x1f && bytes[1] === 0x8b) {
		return JSON.parse(pako.ungzip(bytes, { to: 'string' }));
	}

	return JSON.parse(textDecoder.decode(bytes));
}

const dataStore = {

	dataCache: {},
	timelineCache: {},
	_sortedDayTimestamps: null,

	async fetchDevicesData() {
		try {
			// Fetch device data file and all telemetry file 
			const devicesPromise = fetch(cacheddevicesurl + '?' + dataStore.getTimestampForApiRequest())
				.then(res => {
					if (!res.ok) {
						throw new Error(`HTTP ${res.status}`);
					}
					return res.arrayBuffer();
				})
				.then(buf => {
					const data = parseCompressedOrPlainJson(buf);
					if (data?.devices) {
						dataStore.processDevices(data);
					}
					return data;
				});

			const telemetryPromise = fetch(alltelemetryurl + '?' + dataStore.getTimestampForApiRequest())
				.then(res => {
					if (!res.ok) {
						throw new Error(`HTTP ${res.status}`);
					}
					return res.arrayBuffer();
				})
				.then(buf => {

					let start = performance.now();
					const json = parseCompressedOrPlainJson(buf);
					console.log("Telemetry data decoded in", (performance.now() - start).toFixed(2), "ms");

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
			dataModel.wasserkapazität_setzen(device);

			state.devices[index] = device;
			index++;
		}

		this.sortFaultyDevices();

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

		this.earliestTimestamp = result.earliestTimestamp;
		this.latestTimestamp = result.latestTimestamp;
		
		for (const [deviceId, deviceTelemetry] of Object.entries(result.devices)) {
			
			const device = this.getDeviceById(deviceId);
			if (!device || !Array.isArray(deviceTelemetry?.data)) {
				console.warn('Skipping telemetry cache entry with missing device or rows', deviceId);
				continue;
			}

			let telemetry = deviceTelemetry;
			
			// for daily aggregated data, add latest life data point, because daily aggregates are cut off at the last day
			const lastTelemetryData = this.getLatestDeviceTelemetryRow(device);

			// TODO fehlerhafte letzte telemetrie ausschließen

			if (lastTelemetryData && this.shouldAppendTelemetryRow(telemetry.data, lastTelemetryData)) {
				telemetry.data.push(lastTelemetryData);
			}
			if (state.filterFaultyValues) {
				telemetry = this.filterTelemetry(deviceTelemetry, -5, 100);
			}

			const cacheKey = `${deviceId}_all_1d`;
			this.dataCache[cacheKey] = telemetry;
		}
		this.createDeviceSchemaIndex();
		this.buildTimelineCache()
		state.telemetryLoaded = true;

		window.setTimeout(()=>{
			// this.loaded = true;
			document.body.classList.remove('loading');
			document.body.classList.add('loaded');
			state.loading = false;
		},100)
	},

	// The data rows for each device have a different schema. 
	// This creates an array map for all devices and their telemetry row indices
	createDeviceSchemaIndex() {
		const index = [];

		for (const device of state.devices) {
			const schema = device.telemetrySchema?.schema;

			if (!Array.isArray(schema)) {
				index[device.index] = null;
				continue;
			}

			const map = Object.create(null);

			for (let i = 0; i < schema.length; i++) {
				map[schema[i]] = i;
			}

			index[device.index] = map;
			device.schemaIndex = map;
		}

		this.deviceSchemaIndex = index;
	},

	fetchTelemetryCache(deviceId) {
		const cacheKey = `${deviceId}_all_1d`;

		if (this.dataCache[cacheKey]) {
			return this.dataCache[cacheKey];
		}

		return this.normalizeTelemetryResponse(deviceId);
	},

	// request telemetry from backend, used to retrieve raw data for single locations now, as all aggregated daily telemetry is already loaded initially
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
				const telemetry = this.normalizeTelemetryResponse(deviceId, json?.telemetry);

				if (state.filterFaultyValues) {
					this.dataCache[cacheKey] = this.filterTelemetry(telemetry, -5, 100);
				} else {
					this.dataCache[cacheKey] = telemetry;
				}
				return this.dataCache[cacheKey];

			} catch (error) {
				attempts++;
				console.warn(`Fetch attempt ${attempts} failed:`, error);

				if (attempts >= 3) {
					console.error("Failed to fetch data after 3 attempts:", error);
					return this.normalizeTelemetryResponse(deviceId);
				}

				await new Promise(r => setTimeout(r, 2000 * attempts));
			}
		}
	},

	normalizeTelemetryResponse(deviceId, telemetry = null) {
		const deviceSchema = this.getDeviceById(deviceId)?.telemetrySchema?.schema;
		return {
			schema: Array.isArray(telemetry?.schema) ? telemetry.schema : (Array.isArray(deviceSchema) ? [...deviceSchema] : []),
			data: Array.isArray(telemetry?.data) ? telemetry.data : [],
		};
	},

	// only include valid Bodenfeuchte telemetry rows withing range
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

	getLatestDeviceTelemetryRow(device) {
		const liveRow = toRaw(device?.telemetrySchema?.data)?.[0];
		return Array.isArray(liveRow) ? [...liveRow] : null;
	},

	shouldAppendTelemetryRow(rows, row) {
		if (!Array.isArray(rows) || !Array.isArray(row) || !row.length) {
			return false;
		}

		const lastRow = rows[rows.length - 1];
		if (!Array.isArray(lastRow) || !lastRow.length) {
			return true;
		}

		return lastRow[0] !== row[0];
	},
		
	getApiUrl(deviceId, timerange, aggregation) {
		return `/api/?deviceId=${deviceId}&time=${timerange}&agg=${aggregation}`;
	},

	// timestamp for json request cache busting
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

	floorToMidnight(timestamp) {
		const msPerDay = 24 * 60 * 60 * 1000;
		return Math.floor(timestamp / msPerDay) * msPerDay;
	},

	ceilToMidnight(timestamp) {
		const msPerDay = 24 * 60 * 60 * 1000;
		return Math.floor(timestamp / msPerDay) * msPerDay + msPerDay;
	},

	getDataAtTimestamp(deviceIndex, timestamp) {
		const telemetryRows = dataStore.getTelemetryForDay(timestamp);
		const row = telemetryRows[deviceIndex];
		if (row) {
			return row;
		}
	},

	// BUILD TIMELINE CACHE all timestamps and references to data rows for all devices

	buildTimelineCache() {
		const startTime = performance.now();

		// Clear day cache
		this.timelineCache = {};
		this._sortedDayTimestamps = null;

		if (!Number.isFinite(this.earliestTimestamp) || !Number.isFinite(this.latestTimestamp)) {
			return;
		}

		const msPerDay = 24 * 60 * 60 * 1000;

		// Floor earliest timestamp to midnight
		// let dayTs = this.ceilToMidnight(this.earliestTimestamp);
		let dayTs = this.floorToMidnight(this.earliestTimestamp);

		let numDays = 0;

		while (dayTs <= this.latestTimestamp) {
			// Populate day cache for this day
			this.getTelemetryForDay(dayTs);
			dayTs += msPerDay;
			numDays++;
		}

		const endTime = performance.now();
		console.log(`Timeline cached for ${numDays} days in ${(endTime - startTime).toFixed(2)} ms`);
	},

	// timeline telemetry cache for single day
	getTelemetryForDay(timestamp) {
		timestamp = this.floorToMidnight(timestamp)

		if (!this.timelineCache) this.timelineCache = {};

		// Return cached if available
		if (this.timelineCache[timestamp]) return this.timelineCache[timestamp];

		// Build cache for this day
		const rows = state.devices.map(device => {
			const telemetry = this.fetchTelemetryCache(device.id)?.data;
			if (!telemetry?.length) return null;

			const idx = this.get_telemetry_index_binary(telemetry, timestamp);
			return idx >= 0 ? telemetry[idx] : null;
		});

		this.timelineCache[timestamp] = rows;
		return rows;
	},

	// TELEMETRY ROWS TIMESTAMP BINARY SEARCH

	get_telemetry_index_binary(data, timestamp) {
		const n = data.length;
		if (n < 1) return -1;

		const msPerDay = 24 * 60 * 60 * 1000;
		const dayEnd = timestamp + msPerDay;

		// Binary search for first entry >= timestamp
		let lo = 0, hi = n;
		while (lo < hi) {
			const mid = (lo + hi) >>> 1;
			if (data[mid][0] < timestamp) lo = mid + 1;
			else hi = mid;
		}

		// Return index if entry is within the day range [timestamp, timestamp + 24h)
		if (lo < n && data[lo][0] < dayEnd) {
			return lo;
		}

		return -1;
	},

	// NFK DAILY AVERAGES CALCULATIONS

	// calculates nfk daily averages for all locations and days
	getNfkDailyAverages() {
		const startTime = performance.now();
		const timelineCache = this.timelineCache ?? {};

		if (!Object.keys(timelineCache).length) {
			return [];
		}

		// cache sorted timestamps
		if (!this._sortedDayTimestamps) {
			this._sortedDayTimestamps = Object.keys(timelineCache)
				.map(Number)
				.sort((a, b) => a - b);
		}
		const timestamps = this._sortedDayTimestamps;

		// build fast filter lookup
		const useFilter = state.includeFilter.length > 0 || state.excludeFilter.length > 0;
		const deviceAllowed = new Uint8Array(state.devices.length);
		if (useFilter) {
			for (const d of state.filteredDevices) {
				deviceAllowed[d.index] = 1;
			}
		} else {
			deviceAllowed.fill(1);
		}

		const result = [];

		for (let t = 0; t < timestamps.length; t++) {
			const ts = timestamps[t];
			const rowsForDay = timelineCache[ts];
			result.push(this.getNfkAveragesForDay(ts, rowsForDay, deviceAllowed));
		}

		result.push(this.getNfkAveragesForLastTelemetry());

		console.log(
			`nFK daily averages calculated in ${(performance.now() - startTime).toFixed(2)} ms`
		);
		return result;
	},

	// calculates nfk averages for current day because daily aggregates do not yet exist
	getNfkAveragesForLastTelemetry() {
		if (!state.devices.length) {
			return this.getEmptyNfkAverages();
		}

		const useFilter = state.includeFilter.length > 0 || state.excludeFilter.length > 0;
		const deviceAllowed = new Uint8Array(state.devices.length);
		if (useFilter) {
			for (const d of state.filteredDevices) {
				deviceAllowed[d.index] = 1;
			}
		} else {
			deviceAllowed.fill(1);
		}

		let telemetryRows = [];

		let ts = 0;

		for (let i = 0; i < state.devices.length; i++) {
			const data = state.devices[i].telemetrySchema?.data?.[0];
			if (!Array.isArray(data) || !data.length) {
				telemetryRows[i] = null;
				continue;
			}

			if (data[0] > ts) ts = data[0];
			if (data[0] > this.latestTimestamp - config.timelineHoverCutoff && this.isRowValid(data, state.devices[i].schemaIndex)) {
				telemetryRows[i] = data; 
			} else {
				telemetryRows[i] = null; 
			}
		}
		const result = this.getNfkAveragesForDay(ts, telemetryRows, deviceAllowed);

		return result;
	},

	// locations in nfk averages for current day will only be considered if all soil moisture values are betwen -10 and 100
	// past daily averages are already sanitized in the api cache
	isRowValid(row, schemaIndex) {
		if (!Array.isArray(row) || !schemaIndex) {
			return false;
		}

		const isValueInRange = (key) => {
			const index = schemaIndex[key];
			if (!Number.isInteger(index) || index < 0) {
				return true;
			}

			const value = row[index];
			return typeof value !== 'number' || (value > -10 && value < 100);
		};

		return (
			isValueInRange('Bodenfeuchte_10cm') &&
			isValueInRange('Bodenfeuchte_30cm') &&
			isValueInRange('Bodenfeuchte_60cm') &&
			isValueInRange('Bodenfeuchte_80cm')
		)
	},

	// Calculate averages data for a single day
	getNfkAveragesForDay(ts, rowsForDay, deviceAllowed) {
		let sum = 0;
		let count = 0;
		const nfk_level = [0, 0, 0, 0, 0, 0]; // how many devices fall into each nfk category on a given day
		let trockenstress = 0; // how many devices are nfk < 30% on a given day
		let trocken = 0; // how many devices are nfk < 50% on a given day

		for (let i = 0; i < state.devices.length; i++) {
			if (!deviceAllowed[i]) continue;
			const row = rowsForDay[i];
			if (!row) continue;
			const idx = state.devices[i].schemaIndex?.nfk_avg;
			if (!Number.isInteger(idx) || idx < 0) continue;
			const v = row[idx];
			if (typeof v !== 'number' || Number.isNaN(v)) continue;

			sum += v;
			count++;

			if (v < 30) trockenstress++;
			if (v < 50) trocken++;

			// categorize value
			if (v < 0) nfk_level[0]++;
			else if (v < 30) nfk_level[1]++;
			else if (v < 50) nfk_level[2]++;
			else if (v < 90) nfk_level[3]++;
			else if (v < 110) nfk_level[4]++;
			else nfk_level[5]++;
		}

		return {
			ts,
			nfk_avg: count ? sum / count : null,
			count,
			nfk_level,
			trockenstress,
			trocken
		};
	},

	// Get nFK average of all days of a single device

	getDeviceNfkAverage(device) {
		if (!device) return null;

		if (Object.prototype.hasOwnProperty.call(device, 'nfk_avg_all')) {
			return device.nfk_avg_all;
		}

		const telemetry = this.fetchTelemetryCache(device.id);
		const rows = telemetry?.data;
		const schemaIndex = Array.isArray(telemetry?.schema) ? telemetry.schema.indexOf('nfk_avg') : -1;
		const valueIndex = schemaIndex >= 0 ? schemaIndex : device.schemaIndex?.nfk_avg;

		if (!Array.isArray(rows) || !rows.length || !(valueIndex >= 0)) {
			device.nfk_avg_all = null;
			return device.nfk_avg_all;
		}

		let sum = 0;
		let count = 0;

		for (const row of rows) {
			const value = row?.[valueIndex];
			if (typeof value !== 'number' || Number.isNaN(value)) continue;
			sum += value;
			count++;
		}

		device.nfk_avg_all = Math.max(0,sum / count);
		
		return device.nfk_avg_all;
	},

	getDeviceMeasurementRange(device) {
		const normalizeMeasurementTimestamp = timestamp => (
			Number.isFinite(timestamp) && timestamp > 0 ? timestamp : null
		);

		if (!device) {
			return {
				first: null,
				last: null,
			};
		}

		const hasFirst = Object.prototype.hasOwnProperty.call(device, 'first_measurement_ts');
		const hasLast = Object.prototype.hasOwnProperty.call(device, 'last_measurement_ts');
		if (hasFirst && hasLast) {
			device.first_measurement_ts = normalizeMeasurementTimestamp(device.first_measurement_ts);
			device.last_measurement_ts = normalizeMeasurementTimestamp(device.last_measurement_ts);
			return {
				first: device.first_measurement_ts,
				last: device.last_measurement_ts,
			};
		}

		const rows = this.fetchTelemetryCache(device.id)?.data;
		if (!Array.isArray(rows) || !rows.length) {
			device.first_measurement_ts = null;
			device.last_measurement_ts = null;
			return {
				first: device.first_measurement_ts,
				last: device.last_measurement_ts,
			};
		}

		let first = null;
		let last = null;

		for (const row of rows) {
			const ts = row?.[0];
			if (!Number.isFinite(ts) || ts <= 0) continue;
			if (first == null || ts < first) first = ts;
			if (last == null || ts > last) last = ts;
		}

		device.first_measurement_ts = normalizeMeasurementTimestamp(first);
		device.last_measurement_ts = normalizeMeasurementTimestamp(last);

		return {
			first: device.first_measurement_ts,
			last: device.last_measurement_ts,
		};
	}

};

export default dataStore;
