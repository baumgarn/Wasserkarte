import assert from 'node:assert/strict';
import fs from 'node:fs';
import { gunzipSync } from 'node:zlib';

const datastore = fs.readFileSync(new URL('../js/datastore.js', import.meta.url), 'utf8');
const source = Buffer.from(datastore.slice(datastore.indexOf('const DAY_MS ='), datastore.indexOf('const nfkStatsCache =')));
const { calculateNfkStats } = await import(`data:text/javascript;base64,${source.toString('base64')}`);
const day = 86400000;
const rows = Array.from({ length: 14 }, (_, i) => [(i + 1) * day, i < 7 ? 20 : 40]);
const expected = calculateNfkStats(rows, 1);
assert.equal(expected.weekAverage, 40);
assert.equal(expected.trend, 20);
assert.equal(expected.currentDryRun, 14);
assert.deepEqual(calculateNfkStats([...rows, [14 * day + 3600000, 80]], 1), expected);
const gap = rows.filter((_, i) => i !== 10);
assert.equal(calculateNfkStats(gap, 1).weekAverage, null);
assert.equal(calculateNfkStats(gap, 1).currentDryRun, 14);
assert.equal(calculateNfkStats(gap, 1).trend, 20);
for (const explicit of [false, true]) {
	const missing = new Set([3, 10]);
	const samples = explicit ? rows.map((row, i) => missing.has(i) ? [row[0], null] : row) : rows.filter((_, i) => !missing.has(i));
	assert.equal(calculateNfkStats(samples, 1).trend, 20);
}
assert.equal(calculateNfkStats(rows.filter((_, i) => i !== 9 && i !== 10), 1).trend, null);
assert.equal(calculateNfkStats(rows.slice(2), 1).trend, null);
// Older measurements must not move into either calendar window.
assert.equal(calculateNfkStats([[-day, 1000], ...gap], 1).trend, 20);
assert.equal(calculateNfkStats([], 1).weekAverage, null);

// Bridge up to three missing days only between dry measurements.
for (const missing of [1, 2, 3, 4]) {
	for (const explicit of [false, true]) {
		const samples = [[day, 20]];
		if (explicit) for (let i = 1; i <= missing; i++) samples.push([(i + 1) * day, null]);
		samples.push([(missing + 2) * day, 30]);
		const result = calculateNfkStats(samples, 1);
		assert.equal(result.currentDryRun, missing <= 3 ? missing + 2 : 1);
		assert.equal(result.longestDryRun, missing <= 3 ? missing + 2 : 1);
		assert.equal(result.dryPercent, 100);
	}
}
assert.equal(calculateNfkStats([[day, 20], [2 * day, 50], [4 * day, 20]], 1).currentDryRun, 1);
assert.equal(calculateNfkStats([[day, 20], [2 * day, null]], 1).currentDryRun, null);

// Reproduce the appended live reading for Weitzgrund am Apfelbaum.
const id = '560e63e0-38d1-11f1-b3db-f78e84010eed';
const readCache = name => JSON.parse(gunzipSync(fs.readFileSync(new URL(`../api/cache/${name}.json.gz`, import.meta.url))));
const telemetry = readCache('alltelemetry').devices[id];
const devices = readCache('devices').devices;
const device = Array.isArray(devices) ? devices.find(device => device.id === id) : devices[id];
const index = telemetry.schema.indexOf('nfk_avg');
const stats = calculateNfkStats([...telemetry.data, device.telemetrySchema.data[0]], index);
assert.notEqual(stats.weekAverage, null);
assert.notEqual(stats.trend, null);
assert.deepEqual(stats, calculateNfkStats(telemetry.data, index));
console.log('nFK statistics passed, including Weitzgrund:', stats.weekAverage, stats.trend);

const linde = readCache('alltelemetry').devices['b6aea4d0-5277-11f0-a74c-9de59c008e36'];
const lindeStats = calculateNfkStats(linde.data, linde.schema.indexOf('nfk_avg'));
assert.notEqual(lindeStats.trend, null);
console.log('Linde trend:', lindeStats.trend);

// Historical analyses use the selected calendar day and exclude future readings.
const history = [...rows, [15 * day, 80]];
const historical = calculateNfkStats(history, 1, 0, 14 * day + 3600000);
assert.deepEqual(historical, expected);
assert.equal(calculateNfkStats(history, 1).dayValue, 80);
assert.equal(calculateNfkStats(history, 1).currentDryRun, 0);
assert.equal(calculateNfkStats(history, 1, 0, 7 * day).currentDryRun, 7);
assert.equal(calculateNfkStats(history, 1, 0, 7 * day).dayValue, 20);
assert.equal(calculateNfkStats(history, 1, 0, 7 * day).trend, null);
for (const timestamp of [0, 16 * day, 40 * day]) {
	const result = calculateNfkStats(history, 1, 0, timestamp);
	assert.equal(result.dayValue, null);
	assert.equal(result.currentDryRun, null);
}
assert.equal(calculateNfkStats(history, 1, 0, 40 * day).trend, null);
assert.equal(calculateNfkStats(gap, 1, 0, 11 * day).dayValue, null);
console.log('Historical daily analyses passed');

const rain = Array.from({ length: 14 }, (_, i) => [(i + 1) * day, i < 13 ? 20 : 80]);
const rainStats = calculateNfkStats(rain, 1);
assert.equal(rainStats.dayTrend, 60);
assert.equal(rainStats.threeDayTrend, 20);
assert.equal(calculateNfkStats(rain, 1, 0, 13 * day).dayTrend, 0);
assert.equal(calculateNfkStats(rain.filter((_, i) => i !== 12), 1).dayTrend, null);
assert.equal(calculateNfkStats(rain.filter((_, i) => i !== 12), 1).threeDayTrend, 30);
assert.equal(calculateNfkStats(rain.filter((_, i) => i !== 11 && i !== 12), 1).threeDayTrend, null);
console.log('Short-term rain trends passed');
