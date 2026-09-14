import { state } from '@/state.js';
import { postsApi } from '@/posts/api.js';

let loadRequest = null;

export async function loadPosts(force = false) {
	if (state.postsLoaded && !force) return state.posts;
	if (loadRequest) return loadRequest;

	state.postsLoading = true;
	state.postsError = '';
	loadRequest = postsApi.list()
		.then((response) => {
			state.posts = Array.isArray(response.posts) ? response.posts : [];
			state.postsLoaded = true;
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
	state.posts = state.posts.filter((post) => post?.id !== postId);
}
