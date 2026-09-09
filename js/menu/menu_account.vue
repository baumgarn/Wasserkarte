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
				<button :class="{ active: state.accountDetailsOpen }" type="button" @click="toggleAccountModal('accountDetailsOpen')">Konto</button>
				<button v-if="isAdmin" :class="{ active: state.accountsOpen }" type="button" @click="toggleAccountModal('accountsOpen')">Accounts</button>
				<button class="" type="button" @click="logout">Abmelden</button>
			</div>
		</div>

	</div>

</template>

<script>

import { state, closeAllModals, toggleModal } from '../state.js'
import { dataModel } from '@/datamodel.js'
import { managementAuth } from '@/management/auth.js'

export default {
	name: 'AccountMenu',
	components: {
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
			return state.account.user?.wasserkarteRole === 'wassermeister' ? 'Wassermeister*in' : 'Keine Rechte';
		},
		isAdmin() {
			const authority = state.account.user?.thingsboardAuthority;
			return authority === 'TENANT_ADMIN' || authority === 'SYS_ADMIN';
		},
	},
	props: {
	},
	methods: {
		toggleAccountModal(modalStateKey) {
			toggleModal(modalStateKey);
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
	color var(--menusectionheadercolor)

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
