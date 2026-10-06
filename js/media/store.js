import { state } from '@/state.js';
import { mediaApi } from '@/media/api.js';

let loadRequest = null;
let managerRequest = null;
let loadChanges = null;
let managerChanges = null;

export function loadMedia(force = false) {
	if (loadRequest) return force ? loadRequest.catch(() => {}).then(() => loadMedia(true)) : loadRequest;
	if (state.mediaLoaded && !force) return Promise.resolve(state.media);
	state.mediaLoading = true;
	state.mediaError = '';
	loadChanges = new Map();
	loadRequest = mediaApi.list().then((response) => {
		state.media = Object.fromEntries((response.media || []).map((item) => [item.id, item]));
		for (const [id, item] of loadChanges) {
			if (item) state.media[id] = item;
			else delete state.media[id];
		}
		state.mediaLoaded = true;
		state.postsLocalMode = response.localMode === true;
		return state.media;
	}).catch((error) => {
		state.mediaError = error.message;
		throw error;
	}).finally(() => {
		state.mediaLoading = false;
		loadRequest = null;
		loadChanges = null;
	});
	return loadRequest;
}

export function upsertMedia(item) {
	if (!item?.id) return;
	state.media[item.id] = item;
	loadChanges?.set(item.id, item);
	const user = state.account.user;
	const managedItem = {
		...item,
		authorUserId: user?.id,
		authorName: [user?.firstName, user?.lastName].filter(Boolean).join(' ') || user?.email || '',
		canDelete: true,
		attached: false,
	};
	state.mediaManagerItems = [...state.mediaManagerItems.filter(entry => entry.id !== item.id), managedItem];
	managerChanges?.set(item.id, managedItem);
	state.mediaManagerLoaded = false;
}

export function removeMedia(id) {
	if (!id) return;
	delete state.media[id];
	loadChanges?.set(id, null);
	removeManagedMedia(id);
}

export function loadManagedMedia(force = false) {
	if (managerRequest) return force ? managerRequest.catch(() => {}).then(() => loadManagedMedia(true)) : managerRequest;
	if (state.mediaManagerLoaded && !force) return Promise.resolve(state.mediaManagerItems);
	state.mediaManagerLoading = true;
	state.mediaManagerError = '';
	managerChanges = new Map();
	managerRequest = mediaApi.manageList().then((response) => {
		const items = new Map((response.media || []).map(item => [item.id, item]));
		for (const [id, item] of managerChanges) {
			if (item) items.set(id, item);
			else items.delete(id);
		}
		state.mediaManagerItems = [...items.values()];
		state.mediaManagerAllUsers = response.allUsers === true;
		state.mediaManagerLocalMode = response.localMode === true;
		state.mediaManagerLoaded = true;
		return state.mediaManagerItems;
	}).catch((error) => {
		state.mediaManagerError = error.message || 'Bilder konnten nicht geladen werden.';
		throw error;
	}).finally(() => {
		state.mediaManagerLoading = false;
		managerRequest = null;
		managerChanges = null;
	});
	return managerRequest;
}

export function removeManagedMedia(id) {
	state.mediaManagerItems = state.mediaManagerItems.filter((item) => item.id !== id);
	managerChanges?.set(id, null);
}
