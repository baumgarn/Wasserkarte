import { state } from '@/state.js';

const endpoint = '/api/media/index.php';

export function draftId() {
	const bytes = crypto.getRandomValues(new Uint8Array(16));
	bytes[6] = (bytes[6] & 15) | 64;
	bytes[8] = (bytes[8] & 63) | 128;
	const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
	return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export const mediaApi = {
	async manageList() {
		const response = await fetch('/api/media/manage.php', { credentials: 'same-origin', cache: 'no-store' });
		const body = await response.json().catch(() => ({}));
		if (!response.ok) throw new Error(body.error || 'Medienübersicht konnte nicht geladen werden.');
		return body;
	},
	async deletionContext(id) {
		const response = await fetch(`/api/media/manage.php?id=${encodeURIComponent(id)}`, { credentials: 'same-origin', cache: 'no-store' });
		const body = await response.json().catch(() => ({}));
		if (!response.ok) throw new Error(body.error || 'Post konnte nicht geprüft werden.');
		if (typeof body.attached !== 'boolean' || !Number.isInteger(body.remainingImages) || body.remainingImages < 0
			|| typeof body.hasText !== 'boolean' || !/^[a-f0-9]{64}$/.test(body.postRevision || '')) {
			throw new Error('Post konnte nicht geprüft werden. Bitte erneut versuchen.');
		}
		return body;
	},
	async delete(id, postRevision = undefined) {
		const response = await fetch('/api/media/manage.php', {
			method: 'DELETE', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.account.csrfToken || '' },
			body: JSON.stringify({ id, postRevision }),
		});
		const body = await response.json().catch(() => ({}));
		if (!response.ok) {
			const error = new Error(body.error || 'Bild konnte nicht gelöscht werden.');
			error.status = response.status;
			throw error;
		}
		return body;
	},
	async list() {
		const response = await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store' });
		const body = await response.json().catch(() => ({}));
		if (!response.ok) throw new Error(body.error || 'Bilder konnten nicht geladen werden.');
		return body;
	},
	upload(file, deviceId, postId, onProgress, signal) {
		return new Promise((resolve, reject) => {
			const xhr = new XMLHttpRequest();
			const abort = () => xhr.abort();
			const finish = (callback, value) => {
				signal?.removeEventListener('abort', abort);
				callback(value);
			};
			xhr.open('POST', endpoint);
			xhr.withCredentials = true;
			xhr.timeout = 120000;
			if (state.account.csrfToken) xhr.setRequestHeader('X-CSRF-Token', state.account.csrfToken);
			xhr.upload.onprogress = (event) => {
				if (event.lengthComputable) onProgress?.(Math.round(event.loaded / event.total * 100));
			};
			xhr.onload = () => {
				let body;
				try { body = JSON.parse(xhr.responseText); } catch { body = {}; }
				if (xhr.status >= 200 && xhr.status < 300 && body.media?.id) finish(resolve, body.media);
				else finish(reject, new Error(body.error || (xhr.status === 413 ? 'Das Bild überschreitet das Uploadlimit des Servers.' : 'Bild konnte nicht hochgeladen werden.')));
			};
			xhr.onerror = () => finish(reject, new Error('Upload fehlgeschlagen. Bitte die Verbindung prüfen.'));
			xhr.ontimeout = () => finish(reject, new Error('Der Upload dauert zu lange. Bitte erneut versuchen.'));
			xhr.onabort = () => finish(reject, new DOMException('Upload abgebrochen.', 'AbortError'));
			if (signal?.aborted) {
				finish(reject, new DOMException('Upload abgebrochen.', 'AbortError'));
				return;
			}
			signal?.addEventListener('abort', abort, { once: true });
			const data = new FormData();
			data.append('file', file);
			data.append('deviceId', deviceId);
			data.append('postId', postId);
			xhr.send(data);
		});
	},
};
