<template>
	<div class="management-panel management-window-size-mid account-permissions">
		<header class="account-permissions-header">
			<p class="management-section-title account-name">{{ userName }}</p>
		</header>
		<div class="management-actions account-header-actions">
			<button type="button" :disabled="loading || saving || sendingMail" @click="sendAccountMail">{{ sendingMail ? 'Sendet …' : mailSent ? 'Email gesendet' : 'Passwort-Email erneut senden' }}</button>
			<button type="button" class="warning" :disabled="loading || saving || sendingMail" @click="$emit('delete-account', user)">Account löschen</button>
		</div>
		<form class="management-form" @submit.prevent="save">
			<label class="management-field">
				<span>Berechtigungsstufe</span>
				<select v-model="role" :disabled="loading || saving" @change="previewRole">
					<option value="none">Keine Rechte</option>
					<option value="wassermeister">Wassermeister*in</option>
					<option value="super_wassermeister">Super Wassermeister*in</option>
				</select>
			</label>
			<div class="management-field">
				<LocationSelect :model-value="locations" :save-selection="saveLocations" :disabled="loading || saving" @update:model-value="setLocations" />
			</div>
			<p v-if="error" class="management-note management-error">{{ error }}</p>
			<div class="management-actions account-permissions-actions">
				<button :class="{ success: saved }" type="submit" :disabled="loading || saving || !hasChanges">{{ saving ? 'Speichert …' : saved ? 'Gespeichert' : 'Speichern' }}</button>
			</div>
		</form>
	</div>
</template>

<script>
import { managementAuth } from '@/management/auth.js';
import LocationSelect from '@/management/location_select.vue';

export default {
	name: 'AccountPermissions',
	emits: ['delete-account', 'saved'],
	components: { LocationSelect },
	props: {
		user: {
			type: Object,
			required: true,
		},
	},
	data() {
		const role = ['wassermeister', 'super_wassermeister'].includes(this.user.role) ? this.user.role : 'none';
		return {
			role,
			savedRole: role,
			locations: Array.isArray(this.user.locations) ? [...this.user.locations] : [],
			savedLocations: Array.isArray(this.user.locations) ? [...this.user.locations] : [],
			saved: false,
			loading: false,
			saving: false,
			sendingMail: false,
			mailSent: false,
			error: '',
		};
	},
	computed: {
		hasChanges() {
			const saved = new Set(this.savedLocations);
			const current = new Set(this.locations);
			return this.role !== this.savedRole || saved.size !== current.size || [...current].some(id => !saved.has(id));
		},
		userName() {
			return [this.user.firstName, this.user.lastName].filter(Boolean).join(' ') || this.user.email;
		},
	},
	methods: {
		async sendAccountMail() {
			if (this.loading || this.saving || this.sendingMail) return;
			this.sendingMail = true;
			this.mailSent = false;
			this.error = '';
			try {
				await managementAuth.sendAccountMail(this.user.id);
				this.mailSent = true;
			} catch (error) {
				this.error = error.message || 'Email konnte nicht gesendet werden.';
			} finally {
				this.sendingMail = false;
			}
		},
		async save() {
			if (this.loading || this.saving || !this.hasChanges) return;
			this.error = '';
			this.saved = false;
			this.saving = true;
			try {
				await managementAuth.updateUserPermissions(this.user.id, this.role, this.locations);
				this.user.role = this.role;
				this.user.locations = [...this.locations];
				this.user.locationCount = this.locations.length;
				this.savedRole = this.role;
				this.savedLocations = [...this.locations];
				this.saved = true;
				this.$emit('saved');
			} catch (error) {
				this.user.role = this.savedRole;
				this.error = error.message || 'Berechtigungen konnten nicht gespeichert werden.';
			} finally {
				this.saving = false;
			}
		},
		async saveLocations(locations) {
			if (this.loading || this.saving) throw new Error('Berechtigungen werden bereits gespeichert.');
			this.saving = true;
			this.error = '';
			try {
				// Eine noch ungespeicherte Rollenänderung nicht nebenbei übernehmen.
				await managementAuth.updateUserPermissions(this.user.id, this.savedRole, locations);
				this.locations = [...locations];
				this.savedLocations = [...locations];
				this.user.locations = [...locations];
				this.user.locationCount = locations.length;
			} finally {
				this.saving = false;
			}
		},
		previewRole() {
			this.saved = false;
			this.user.role = this.role;
		},
		setLocations(locations) {
			this.locations = locations;
			this.saved = false;
		},
	},
	beforeUnmount() {
		this.user.role = this.savedRole;
	},
};
</script>

<style lang="stylus" scoped>
.account-permissions-header
	display flex
	align-items center
	gap 8px
	margin-bottom 14px

.account-name
	flex 1
	min-width 0
	margin 0
	overflow hidden
	text-overflow ellipsis
	white-space nowrap
	font-weight 500

.account-header-actions
	justify-content flex-start
	flex-wrap wrap
	margin-bottom 14px

.account-permissions-actions
	justify-content flex-start

.account-permissions select
	min-width 180px

</style>
