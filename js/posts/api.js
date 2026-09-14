import { state } from '@/state.js';

const endpoint = '/api/posts.php';

async function request(method = 'GET', payload = null) {
	const headers = new Headers();
	if (payload !== null) headers.set('Content-Type', 'application/json');
	if (method !== 'GET' && state.account.csrfToken) headers.set('X-CSRF-Token', state.account.csrfToken);

	const response = await fetch(endpoint, {
		method,
		credentials: 'same-origin',
		headers,
		body: payload === null ? null : JSON.stringify(payload),
	});
	const body = await response.json().catch(() => ({}));
	if (!response.ok) throw new Error(body.error || `HTTP ${response.status}`);
	return body;
}

export const postsApi = {
	list() {
		return request();
	},
	create(deviceId, content, timestamp) {
		return request('POST', { deviceId, content, timestamp });
	},
	update(id, content, timestamp) {
		return request('PATCH', { id, content, timestamp });
	},
	delete(id) {
		return request('DELETE', { id });
	},
};
