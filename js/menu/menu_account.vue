<template>
	<div class="menuwindow account">

		<div class="menuwindow-header">
			<h3 v-if="!state.account.authenticated">Login</h3>
			<h3 v-else="!state.account.authenticated">{{ displayName }}</h3>
		</div>

		<div class="menuwindow-content">
			<div v-if="state.account.loading" class="notice">Sitzung wird geprüft …</div>

			<form v-else-if="!state.account.authenticated" class="account-form" @submit.prevent="login">
				<label>
					<span>E-Mail-Adresse</span>
					<input v-model.trim="email" type="email" autocomplete="username" required>
				</label>
				<label>
					<span>Passwort</span>
					<input v-model="password" type="password" autocomplete="current-password" required>
				</label>
				<label class="remember-me">
					<input v-model="rememberMe" type="checkbox">
					<span>Eingeloggt bleiben</span>
				</label>
				<p v-if="error" class="error">{{ error }}</p>
				<button class="" type="submit" :disabled="submitting">
					{{ submitting ? 'Anmeldung läuft …' : 'Anmelden' }}
				</button>
			</form>

			<div v-else class="account-summary">
				<div class="account-type">{{ thingsboardAccountType }}</div>
				<button class="" type="button" @click="state.accountDetailsOpen = true">Konto</button>
				<button v-if="isAdmin" class="" type="button" @click="state.accountsOpen = true">Accounts</button>
				<button class="" type="button" @click="logout">Abmelden</button>
			</div>
		</div>

	</div>

</template>

<script>

import { state } from '../state.js' 
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
				CUSTOMER_USER: 'Customer User',
				SYS_ADMIN: 'System Administrator',
			};
			return labels[authority] || 'ThingsBoard-Benutzer';
		},
		isAdmin() {
			const authority = state.account.user?.thingsboardAuthority;
			return authority === 'TENANT_ADMIN' || authority === 'SYS_ADMIN';
		},
	},
	props: {
	},
	methods: {
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
.account-summary
	display flex
	flex-direction column
	gap 10px
	padding 6px

.account-form label
	display flex
	flex-direction column
	gap 3px
	font-size 9pt

.account-form input
	padding 7px
	border 1px solid #00000033
	border-radius 4px
	font inherit

.account-form .remember-me
	flex-direction row
	align-items center
	gap 6px

.error
	margin 0
	color #b52323
	font-size 9pt

.account-type
	font-size 9pt
	color var(--menusectionheadercolor)

// .settings
// 	user-select none

.notice
	opacity .5

.split
	margin-top -2px
	display flex
	justify-content space-between
	font-size 10px
	// margin-bottom 12px
	.right
		text-align right

</style>
