import { state } from '@/state.js';
import { postsApi } from '@/posts/api.js';
import { loadMedia, removeMedia } from '@/media/store.js';

let loadRequest = null;

function seenKey() {
	const userId = state.account.authenticated && state.account.user?.id;
	return userId ? `postsSeenAt:${userId}` : 'postsSeenAt:guest';
}

export function restorePostsSeen() {
	state.postsSeenAt = 0;
	try {
		const value = Number(localStorage.getItem(seenKey()));
		if (Number.isFinite(value) && value > 0) state.postsSeenAt = value;
	} catch {}
}

export function markPostsSeen() {
	const key = seenKey();
	if (!key || !state.menuOpen.posts || !state.postsLoaded || state.postsLoading || state.postsError) return;
	state.postsSeenAt = state.posts.reduce((latest, post) => Math.max(latest, Number(post.createdAt) || 0), state.postsSeenAt);
	try {
		localStorage.setItem(key, String(state.postsSeenAt));
	} catch {}
}

export async function loadPosts(force = false) {
	if (state.postsLoaded && !force) return state.posts;
	if (loadRequest) return loadRequest;

	state.postsLoading = true;
	state.postsError = '';
	loadRequest = postsApi.list(force)
		.then((response) => {
			state.postsLocalMode = response.localMode === true;
			state.posts = (Array.isArray(response.posts) ? response.posts : [])
				.filter(post => state.postsLocalMode || !post.environment);
			state.postsLoaded = true;
			loadMedia(force).catch(() => {});
			return state.posts;
		})
		.catch((error) => {
			state.postsError = error.message || 'Einträge konnten nicht geladen werden.';
			throw error;
		})
		.finally(() => {
			state.postsLoading = false;
			loadRequest = null;
		});

	return loadRequest;
}

export function upsertPost(post) {
	if (!post?.id) return;
	state.posts = [...state.posts.filter((item) => item?.id !== post.id), post]
		.sort((a, b) => Number(b.timestamp) - Number(a.timestamp));
	state.postsLoaded = true;
}

export function removePost(postId) {
	for (const item of state.mediaManagerItems) {
		if (item.postId === postId) removeMedia(item.id);
	}
	state.posts = state.posts.filter((post) => post?.id !== postId);
}
