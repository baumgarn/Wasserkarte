<template>
	<div class="menuwindow account">

		<div class="menuwindow-header">
			<h3 v-if="!state.account.authenticated">Login</h3>
			<h3 v-else="!state.account.authenticated">{{ displayName }}</h3>
		</div>

		<div class="menuwindow-content">

			<div v-if="state.account.loading" class="management-note management-notice">Sitzung wird geprüft …</div>

			<form v-else-if="!state.account.authenticated" class="management-form account-form" @submit.prevent="login">
				<label class="management-field">
					<span>E-Mail-Adresse</span>
					<input v-model.trim="email" type="email" autocomplete="username" required>
				</label>
				<label class="management-field">
					<span>Passwort</span>
					<input v-model="password" type="password" autocomplete="current-password" required>
				</label>
				<div class="settings-item">
					<input id="remember-me" v-model="rememberMe" type="checkbox">
					<label for="remember-me">Eingeloggt bleiben</label>
				</div>
				<p v-if="error" class="management-note management-error">{{ error }}</p>
				<button class="" type="submit" :disabled="submitting">
					{{ submitting ? 'Anmeldung läuft …' : 'Anmelden' }}
				</button>
			</form>

			<div v-else class="account-summary">
				<div class="account-type">{{ thingsboardAccountType }}</div>
				<div class="account-actions">
					<button :class="{ active: state.accountDetailsOpen }" type="button" @click="toggleAccountModal('accountDetailsOpen')">Konto</button>
					<button class="" type="button" @click="logout">Abmelden</button>
					<button v-if="isAdmin" :class="{ active: state.accountsOpen }" type="button" @click="toggleAccountModal('accountsOpen')">Accounts</button>
				</div>
				<div v-if="isWassermeister" class="account-locations-divider"></div>
				<div v-if="isWassermeister" class="account-locations">
					<div
						v-for="device in assignedDevices"
						:key="device.id"
						class="menuitem"
						:class="{ selected: state.selectedDevice === device.name }"
						@click="selectDevice(device)">
						<ColorDot :device />
						<div class="title">{{ device.attributes?.Anzeigename || device.name }}</div>
					</div>
				</div>
			</div>
		</div>

	</div>

</template>

<script>

import { state, closeAllModals, toggleModal } from '../state.js'
import { managementAuth } from '@/management/auth.js'
import ColorDot from '@/menu/colordot.vue'

export default {
	name: 'AccountMenu',
	components: {
		ColorDot,
	},
	setup() {
		return {state};
	},
	data() {
		return {
			email: '',
			password: '',
			rememberMe: false,
			error: '',
			submitting: false,
		}
	},
	computed: {
		displayName() {
			const user = state.account.user;
			if (!user) return '';
			return [user.firstName, user.lastName].filter(Boolean).join(' ') || user.email;
		},
		thingsboardAccountType() {
			const authority = state.account.user?.thingsboardAuthority;
			const labels = {
				TENANT_ADMIN: 'Tenant Administrator',
				SYS_ADMIN: 'System Administrator',
			};
			if (labels[authority]) return labels[authority];
			if (state.account.user?.wasserkarteRole === 'super_wassermeister') return 'Super Wassermeister*in';
			return state.account.user?.wasserkarteRole === 'wassermeister' ? 'Wassermeister*in' : 'Keine Rechte';
		},
		isAdmin() {
			const authority = state.account.user?.thingsboardAuthority;
			return authority === 'TENANT_ADMIN' || authority === 'SYS_ADMIN';
		},
		isWassermeister() {
			return ['wassermeister', 'super_wassermeister'].includes(state.account.user?.wasserkarteRole);
		},
		assignedDevices() {
			const locationIds = state.account.user?.wasserkarteLocations;
			if (!Array.isArray(locationIds)) return [];
			const assignedIds = new Set(locationIds);
			return state.devices
				.filter(device => assignedIds.has(device.id))
				.sort((a, b) => (a.attributes?.Anzeigename || a.name).localeCompare(b.attributes?.Anzeigename || b.name, 'de'));
		},
	},
	props: {
	},
	methods: {
		toggleAccountModal(modalStateKey) {
			toggleModal(modalStateKey);
		},
		selectDevice(device) {
			state.selectedDevice = device?.name || null;
			window.dispatchEvent(new CustomEvent('sidebar:open', { detail: device }));
			window.dispatchEvent(new CustomEvent('device-selected', { detail: device }));
		},
		async login() {
			this.error = '';
			this.submitting = true;
			try {
				await managementAuth.login(this.email, this.password, this.rememberMe);
				this.password = '';
			} catch (error) {
				this.error = error.message || 'Anmeldung fehlgeschlagen.';
			} finally {
				this.submitting = false;
			}
		},
		async logout() {
			this.error = '';
		try {
				await managementAuth.logout();
				closeAllModals();
			} catch (error) {
				this.error = error.message || 'Abmeldung fehlgeschlagen.';
			}
		},
	},
	watch: {
	
	},
	mounted() {
	}
}
</script>

<style lang="stylus" scoped>

.menuwindow.account
	width 275px

.account-form
	padding 6px

.account-summary
	display flex
	flex-direction column
	gap 10px
	padding 6px

.account-type
	font-size 9pt
	font-weight bold
	color var(--menusectionheadercolor)

.account-actions
	display flex
	flex-direction column
	gap 8px

.account-actions button
	flex 0 0 auto

.account-locations-divider
	border-top var(--thinline)
	margin 2px 0

.account-locations
	display flex
	flex-direction column
	gap 0
	margin -4px 0

.account-locations .menuitem
	display flex
	align-items center
	padding-right 2px

.account-locations .title
	min-width 0
	flex-grow 1
	flex-shrink 1
	overflow hidden
	white-space nowrap
	text-overflow ellipsis
	font-weight normal

// .settings
// 	user-select none

.split
	margin-top -2px
	display flex
	justify-content space-between
	font-size 10px
	// margin-bottom 12px
	.right
		text-align right

</style>
