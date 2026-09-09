<template>
	<div class="management-panel accounts">
		<div v-if="loading" class="management-note management-notice">Accounts werden geladen …</div>
		<p v-else-if="error" class="management-note management-error">{{ error }}</p>
		<div v-else class="accounts-list">
			<table v-if="users.length" class="accounts-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>E-Mail-Adresse</th>
						<th>Berechtigung</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="user in users" :key="user.id" class="account-row" tabindex="0" @click="$emit('permissions', user)" @keydown.enter="$emit('permissions', user)">
						<td class="account-name" :title="userName(user)">{{ userName(user) }}</td>
						<td class="account-email" :title="user.email">{{ user.email }}</td>
						<td class="account-authority">{{ permissionLabel(user.role) }}</td>
					</tr>
				</tbody>
			</table>
			<p v-else class="management-note management-notice">Keine Accounts gefunden.</p>
		</div>
		<div class="management-actions accounts-actions">
			<button type="button" @click="$emit('create')">Neuer Account</button>
		</div>
	</div>
</template>

<script>
import { managementAuth } from '@/management/auth.js';

export default {
	name: 'Accounts',
	emits: ['create', 'permissions'],
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
		permissionLabel(role) {
			return role === 'wassermeister' ? 'Wassermeister*in' : 'Keine Rechte';
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
	display flex
	flex-direction column
	gap 10px

.accounts-list
	flex-grow 1
	flex-shrink 1
	overflow auto
	align-self flex-start

.accounts-table
	width 600px
	max-width calc(100vw - 32px)
	table-layout fixed
	border-collapse collapse
	font-size 9pt

.accounts-table th
	padding 6px
	border-bottom 1px solid #00000022
	text-align left
	font-size 8pt
	font-weight 500
	color var(--menusectionheadercolor)

.accounts-actions
	padding-top 4px
	flex-grow 0
	flex-shrink 0

.account-row
	cursor pointer
	background #f4f4f4
	transition background linear .2s
	&:hover
	&:focus
		background: #e0e0e0
		outline none

.account-row td
	padding 6px
	overflow hidden
	text-overflow ellipsis
	white-space nowrap

.account-name
	max-width 180px

.account-email
	max-width 260px

.account-authority
	max-width 180px

.account-name
	font-weight 500

// .account-email
	// color #555

.account-authority
	color var(--menusectionheadercolor)
	white-space nowrap

</style>
