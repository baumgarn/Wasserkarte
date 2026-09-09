<template>
	<div class="management-panel account-permissions">
		<p class="management-section-title account-name">{{ userName }}</p>
		<form class="management-form" @submit.prevent="save">
			<label class="management-field">
				<span>Berechtigungsstufe</span>
				<select v-model="role" :disabled="loading || saving">
					<option value="none">Keine Rechte</option>
					<option value="wassermeister">Wassermeister*in</option>
				</select>
			</label>
			<p v-if="error" class="management-note management-error">{{ error }}</p>
			<p v-if="success" class="management-note management-success">Gespeichert.</p>
			<button type="submit" :disabled="loading || saving">{{ saving ? 'Speichert …' : 'Speichern' }}</button>
		</form>
	</div>
</template>

<script>
import { managementAuth } from '@/management/auth.js';

export default {
	name: 'AccountPermissions',
	props: {
		user: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			role: 'none',
			loading: true,
			saving: false,
			error: '',
			success: false,
		};
	},
	computed: {
		userName() {
			return [this.user.firstName, this.user.lastName].filter(Boolean).join(' ') || this.user.email;
		},
	},
	methods: {
		async load() {
			this.loading = true;
			this.error = '';
			try {
				const response = await managementAuth.getUserPermissions(this.user.id);
				this.role = response.role === 'wassermeister' ? 'wassermeister' : 'none';
			} catch (error) {
				this.error = error.message || 'Berechtigungen konnten nicht geladen werden.';
			} finally {
				this.loading = false;
			}
		},
		async save() {
			this.error = '';
			this.success = false;
			this.saving = true;
			try {
				await managementAuth.updateUserPermissions(this.user.id, this.role);
				this.user.role = this.role;
				this.success = true;
			} catch (error) {
				this.error = error.message || 'Berechtigungen konnten nicht gespeichert werden.';
			} finally {
				this.saving = false;
			}
		},
	},
	mounted() {
		this.load();
	},
};
</script>

<style lang="stylus" scoped>
.account-name
	margin 0 0 14px
	font-weight 500

.account-permissions select
	min-width 180px
</style>
