import { state } from '../state.js';

const endpoint = '/api/management.php';

async function request(action, options = {}) {
	const headers = new Headers(options.headers || {});
	if (action !== 'login' && state.account.csrfToken) {
		headers.set('X-CSRF-Token', state.account.csrfToken);
	}
	const response = await fetch(`${endpoint}?action=${action}`, {
		credentials: 'same-origin',
		...options,
		headers,
	});
	const body = await response.json().catch(() => ({}));
	if (!response.ok) {
		throw new Error(body.error || `HTTP ${response.status}`);
	}
	return body;
}

function applySession(session) {
	state.account.authenticated = session.authenticated === true;
	state.account.user = session.user || null;
	state.account.permissions = session.permissions || {};
	state.account.csrfToken = session.csrfToken || null;
}

export const managementAuth = {
	async restoreSession() {
		state.account.loading = true;
		try {
			applySession(await request('me'));
		} catch (error) {
			applySession({ authenticated: false });
		} finally {
			state.account.loading = false;
		}
	},

	async login(email, password, remember = false) {
		const session = await request('login', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ email, password, remember }),
		});
		applySession(session);
		return session;
	},

	async logout() {
		await request('logout', { method: 'POST' });
		applySession({ authenticated: false });
	},

	async updateProfile(firstName, lastName) {
		const session = await request('profile', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ firstName, lastName }),
		});
		applySession(session);
	},

	async changePassword(currentPassword, newPassword) {
		return request('password', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ currentPassword, newPassword }),
		});
	},

	async listUsers() {
		const response = await request('users');
		return Array.isArray(response.users) ? response.users : [];
	},

	async createUser(firstName, lastName, email) {
		return request('create-user', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ firstName, lastName, email }),
		});
	},

	async getUserPermissions(userId) {
		return request(`user-permissions&id=${encodeURIComponent(userId)}`);
	},

	async updateUserPermissions(userId, role, locations) {
		return request('user-permissions', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ id: userId, role, locations }),
		});
	},

	async deleteUser(userId) {
		return request('delete-user', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ id: userId }),
		});
	},

	async activateAccount(activateToken, password) {
		return request('activate', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ activateToken, password }),
		});
	},
};
