<template>
	<div class="accounts">
		<div v-if="loading" class="notice">Accounts werden geladen …</div>
		<p v-else-if="error" class="error">{{ error }}</p>
		<div v-else class="accounts-list">
			<div v-for="user in users" :key="user.id" class="account-row">
				<div class="account-name">{{ userName(user) }}</div>
				<div class="account-email">{{ user.email }}</div>
				<div class="account-authority">{{ authorityLabel(user.authority) }}</div>
			</div>
			<p v-if="users.length === 0" class="notice">Keine Accounts gefunden.</p>
		</div>
		<div class="accounts-actions">
			<button type="button" @click="$emit('create')">Neuer Account</button>
		</div>
	</div>
</template>

<script>
import { managementAuth } from '@/management/auth.js';

export default {
	name: 'Accounts',
	emits: ['create'],
	data() {
		return {
			loading: true,
			error: '',
			users: [],
		};
	},
	methods: {
		userName(user) {
			return [user.firstName, user.lastName].filter(Boolean).join(' ') || 'Ohne Namen';
		},
		authorityLabel(authority) {
			return {
				TENANT_ADMIN: 'Tenant Administrator',
				SYS_ADMIN: 'System Administrator',
				CUSTOMER_USER: 'Customer User',
			}[authority] || authority || 'Unbekannt';
		},
		async loadUsers() {
			this.loading = true;
			this.error = '';
			try {
				this.users = await managementAuth.listUsers();
			} catch (error) {
				this.error = error.message || 'Accounts konnten nicht geladen werden.';
			} finally {
				this.loading = false;
			}
		},
	},
	mounted() {
		this.loadUsers();
	},
};
</script>

<style lang="stylus" scoped>
.accounts
	padding 10px

.accounts-list
	display grid
	gap 1px

.account-row
	display grid
	grid-template-columns minmax(120px, 1fr) minmax(160px, 1.4fr) auto
	gap 14px
	align-items center
	padding 9px
	background #f4f4f4

.account-name
	font-weight 500

.account-email
	font-size 9pt
	color #555

.account-authority
	font-size 8.5pt
	color var(--menusectionheadercolor)
	white-space nowrap

.notice
	margin 0
	opacity .65

.error
	margin 0
	color #b52323

.accounts-actions
	margin-top 12px
</style>
