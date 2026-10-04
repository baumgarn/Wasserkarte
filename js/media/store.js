import { state } from '@/state.js';
import { mediaApi } from '@/media/api.js';

let loadRequest = null;

export function loadMedia(force = false) {
	if (loadRequest) return force ? loadRequest.catch(() => {}).then(() => loadMedia(true)) : loadRequest;
	if (state.mediaLoaded && !force) return Promise.resolve(state.media);
	state.mediaLoading = true;
	state.mediaError = '';
	loadRequest = mediaApi.list().then((response) => {
		state.media = Object.fromEntries((response.media || []).map((item) => [item.id, item]));
		state.mediaLoaded = true;
		state.postsLocalMode = response.localMode === true;
		return state.media;
	}).catch((error) => {
		state.mediaError = error.message;
		throw error;
	}).finally(() => {
		state.mediaLoading = false;
		loadRequest = null;
	});
	return loadRequest;
}

export function upsertMedia(item) {
	if (item?.id) state.media[item.id] = item;
}
