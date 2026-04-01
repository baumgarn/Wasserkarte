import { config } from '@/config.js';
import { dataModel } from '@/dataModel.js';
import dataStore from '@/datastore.js';

const timelineImageCache = new Map();
const queuedTimelineCacheKeys = new Set();
const timelinePrewarmQueue = [];
const sharedTimelineCanvas = typeof document !== 'undefined' ? document.createElement('canvas') : null;

let timelinePrewarmIdleHandle = null;
let timelinePrewarmTimeoutHandle = null;

function getNow() {
	if (typeof performance !== 'undefined' && typeof performance.now === 'function') {
		return performance.now();
	}
	return Date.now();
}

function scheduleTimelinePrewarmQueue() {
	if (timelinePrewarmIdleHandle != null || timelinePrewarmTimeoutHandle != null) return;

	if (typeof requestIdleCallback === 'function') {
		timelinePrewarmIdleHandle = requestIdleCallback(processTimelinePrewarmQueue, { timeout: 240 });
		return;
	}

	timelinePrewarmTimeoutHandle = setTimeout(() => processTimelinePrewarmQueue(), 16);
}

function processTimelinePrewarmQueue(deadline = null) {
	timelinePrewarmIdleHandle = null;
	if (timelinePrewarmTimeoutHandle != null) {
		clearTimeout(timelinePrewarmTimeoutHandle);
		timelinePrewarmTimeoutHandle = null;
	}

	const sliceStart = getNow();

	while (timelinePrewarmQueue.length > 0) {
		if (deadline && typeof deadline.timeRemaining === 'function' && deadline.timeRemaining() < 4) {
			break;
		}
		if (!deadline && (getNow() - sliceStart) > 12) {
			break;
		}

		const job = timelinePrewarmQueue.shift();
		queuedTimelineCacheKeys.delete(job.cacheKey);
		renderTimelineImage(job);
	}

	if (timelinePrewarmQueue.length > 0) {
		scheduleTimelinePrewarmQueue();
	}
}

export function buildTimelineImageCacheKey({
	deviceId,
	telemetryFingerprint,
	startTimestamp,
	endTimestamp,
	showDepths,
	showDataGaps,
	colorScheme,
}) {
	return [
		deviceId ?? 'none',
		telemetryFingerprint ?? 'empty',
		startTimestamp ?? 0,
		endTimestamp ?? 0,
		showDepths ? 1 : 0,
		showDataGaps ? 1 : 0,
		colorScheme ?? 'normal',
	].join('|');
}

export function getCachedTimelineImage(cacheKey) {
	return timelineImageCache.get(cacheKey) || null;
}

export function clearTimelineImagePrewarmQueue() {
	timelinePrewarmQueue.length = 0;
	queuedTimelineCacheKeys.clear();

	if (timelinePrewarmIdleHandle != null && typeof cancelIdleCallback === 'function') {
		cancelIdleCallback(timelinePrewarmIdleHandle);
		timelinePrewarmIdleHandle = null;
	}
	if (timelinePrewarmTimeoutHandle != null) {
		clearTimeout(timelinePrewarmTimeoutHandle);
		timelinePrewarmTimeoutHandle = null;
	}
}

export function buildDeviceTimelineTelemetryState(device, { showDepths, showDataGaps }) {
	if (!device) {
		return {
			telemetryData: null,
			telemetryFingerprint: 'empty',
			heatmapSegments: [],
			heatmapNumBands: showDepths ? 0 : 1,
		};
	}

	const telemetryData = dataStore.fetchTelemetryCache(device.id).data;
	const rows = telemetryData || [];
	const schema = device?.telemetrySchema?.schema || [];
	const firstTs = rows.length ? rows[0][0] : 0;
	const lastTs = rows.length ? rows[rows.length - 1][0] : 0;
	const telemetryFingerprint = [
		rows.length,
		firstTs,
		lastTs,
		schema.join(','),
	].join(':');

	return {
		telemetryData,
		telemetryFingerprint,
		...buildHeatmapSegments({ telemetryData, schema, showDepths, showDataGaps }),
	};
}

export function queueTimelineImagePrewarm({
	device,
	startTimestamp,
	endTimestamp,
	showDepths,
	showDataGaps,
	colorScheme,
}) {
	const timelineState = buildDeviceTimelineTelemetryState(device, { showDepths, showDataGaps });
	const cacheKey = buildTimelineImageCacheKey({
		deviceId: device?.id || device?.name,
		telemetryFingerprint: timelineState.telemetryFingerprint,
		startTimestamp,
		endTimestamp,
		showDepths,
		showDataGaps,
		colorScheme,
	});

	if (!timelineState.heatmapSegments?.length) return cacheKey;
	if (timelineImageCache.has(cacheKey) || queuedTimelineCacheKeys.has(cacheKey)) return cacheKey;

	queuedTimelineCacheKeys.add(cacheKey);
	timelinePrewarmQueue.push({
		cacheKey,
		startTimestamp,
		endTimestamp,
		colorScheme,
		heatmapSegments: timelineState.heatmapSegments,
		heatmapNumBands: timelineState.heatmapNumBands,
	});
	scheduleTimelinePrewarmQueue();
	return cacheKey;
}

function buildHeatmapSegments({
	telemetryData,
	schema,
	showDepths,
	showDataGaps,
}) {
	if (!telemetryData || telemetryData.length === 0) {
		return {
			heatmapSegments: [],
			heatmapNumBands: showDepths ? 0 : 1,
		};
	}

	const rows = telemetryData;
	const msPerDay = 24 * 60 * 60 * 1000;
	const avgIndex = schema.indexOf('nfk_avg');
	const depthIndices = [
		schema.indexOf('nfk_10cm'),
		schema.indexOf('nfk_30cm'),
		schema.indexOf('nfk_60cm'),
		schema.indexOf('nfk_80cm'),
	].filter(index => index >= 0);

	if (showDepths) {
		const segments = [];

		for (let bandIdx = 0; bandIdx < depthIndices.length; bandIdx++) {
			const dataIndex = depthIndices[bandIdx];
			let nextTs = null;

			for (let i = rows.length - 1; i >= 0; i--) {
				const row = rows[i];
				const ts = row[0];
				const nfk = row[dataIndex];
				if (nfk == null) continue;

				let endTs = nextTs ?? (ts + msPerDay);
				if (showDataGaps && (endTs - ts) > config.dataGapLength) endTs = ts + msPerDay;

				segments.push({ startTs: ts, endTs, nfk, bandIdx });
				nextTs = ts;
			}
		}

		return {
			heatmapSegments: segments,
			heatmapNumBands: depthIndices.length,
		};
	}

	const segments = [];

	if (avgIndex >= 0) {
		for (let i = 0; i < rows.length; i++) {
			const row = rows[i];
			const ts = row[0];
			const nfk = row[avgIndex];
			if (nfk == null) continue;

			let endTs = (i < rows.length - 1) ? rows[i + 1][0] : ts + msPerDay;
			if (showDataGaps && (endTs - ts) > config.dataGapLength) endTs = ts + msPerDay;

			segments.push({ startTs: ts, endTs, nfk, bandIdx: 0 });
		}
	}

	return {
		heatmapSegments: segments,
		heatmapNumBands: 1,
	};
}

export function renderTimelineImage({
	cacheKey,
	startTimestamp,
	endTimestamp,
	colorScheme,
	heatmapSegments,
	heatmapNumBands,
}) {
	if (!heatmapSegments || heatmapSegments.length === 0) {
		return null;
	}
	if (!sharedTimelineCanvas) {
		return null;
	}

	const cachedImageUrl = timelineImageCache.get(cacheKey);
	if (cachedImageUrl) {
		return cachedImageUrl;
	}

	const msPerDay = 24 * 60 * 60 * 1000;
	const width = Math.max(1, Math.ceil((endTimestamp - startTimestamp) / msPerDay) + 1);
	const numBands = Math.max(1, heatmapNumBands || 1);
	const canvas = sharedTimelineCanvas;
	canvas.width = width;
	canvas.height = numBands;

	const ctx = canvas.getContext('2d');
	ctx.setTransform(1, 0, 0, 1, 0, 0);
	ctx.clearRect(0, 0, width, numBands);

	const visibleEndTs = endTimestamp + msPerDay;
	const colorTable = dataModel.get_color_scheme('nfk', colorScheme) || dataModel.get_color_scheme('nfk', 'normal');

	for (let i = 0; i < heatmapSegments.length; i++) {
		const segment = heatmapSegments[i];
		const clippedStartTs = Math.max(segment.startTs, startTimestamp);
		const clippedEndTs = Math.min(segment.endTs, visibleEndTs);
		if (clippedEndTs <= clippedStartTs) continue;

		const startDay = Math.max(0, Math.floor((clippedStartTs - startTimestamp) / msPerDay));
		const endDay = Math.min(width, Math.ceil((clippedEndTs - startTimestamp) / msPerDay));
		const segW = Math.max(1, endDay - startDay);

		ctx.fillStyle = dataModel.get_color(segment.nfk, colorTable);
		ctx.fillRect(startDay, segment.bandIdx, segW, 1);
	}

	const imageUrl = canvas.toDataURL();
	timelineImageCache.set(cacheKey, imageUrl);
	return imageUrl;
}
