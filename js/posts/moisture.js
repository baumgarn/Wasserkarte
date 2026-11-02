import { state } from '@/state.js';
import dataStore from '@/datastore.js';
import { dataModel } from '@/datamodel.js';

export function postMoistureLayers(post) {
			if (!state.telemetryLoaded) return [];
			const device = state.devices.find(item => item?.id === post.deviceId);
			if (!device) return [];
			const telemetry = dataStore.fetchTelemetryCache(device.id);
			const index = dataStore.get_telemetry_index_binary(telemetry.data, dataStore.floorToMidnight(Number(post.timestamp)));
			const row = telemetry.data[index];
			if (!row) return [];
			return [10, 30, 60, 80].flatMap(depth => {
				const value = row[telemetry.schema.indexOf(`Bodenfeuchte_${depth}cm`)];
				if (!Number.isFinite(value) || value < -5 || value > 100) return [];
				const nfk = dataModel.vol_to_nfk(device, value);
				return [{ depth, value, nfk, color: Number.isFinite(nfk) ? dataModel.get_nfk_color(nfk) : '#ccc' }];
			});
		}

export function moistureBackground(layers) {
	return `linear-gradient(to bottom, ${layers.map((layer, i) => `${layer.color} ${i / layers.length * 100}% ${(i + 1) / layers.length * 100}%`).join(', ')})`;
}
