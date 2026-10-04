import { state } from '@/state.js';

const endpoint = '/api/posts/index.php';

async function request(method = 'GET', payload = null, force = false) {
	const headers = new Headers();
	if (payload !== null) headers.set('Content-Type', 'application/json');
	if (method !== 'GET' && state.account.csrfToken) headers.set('X-CSRF-Token', state.account.csrfToken);

	const response = await fetch(endpoint, {
		method,
		credentials: 'same-origin',
		cache: method === 'GET' && force ? 'no-store' : 'default',
		headers,
		body: payload === null ? null : JSON.stringify(payload),
	});
	const body = await response.json().catch(() => ({}));
	if (!response.ok) throw new Error(body.error || `HTTP ${response.status}`);
	return body;
}

export const postsApi = {
	list(force = false) {
		return request('GET', null, force);
	},
	create(deviceId, content, timestamp, mediaIds = [], id = undefined) {
		return request('POST', { deviceId, content, timestamp, mediaIds, id });
	},
	update(id, content, timestamp, mediaIds = undefined) {
		return request('PATCH', { id, content, timestamp, mediaIds });
	},
	delete(id) {
		return request('DELETE', { id });
	},
};
