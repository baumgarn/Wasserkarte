<template>
	<div class="management-panel management-window-size-mid account-permissions">
		<p class="management-section-title account-name">{{ userName }}</p>
		<form class="management-form" @submit.prevent="save">
			<label class="management-field">
				<span>Berechtigungsstufe</span>
				<select v-model="role" :disabled="loading || saving" @change="previewRole">
					<option value="none">Keine Rechte</option>
					<option value="wassermeister">Wassermeister*in</option>
				</select>
			</label>
			<div class="management-field">
				<LocationSelect :model-value="locations" @update:model-value="setLocations" />
			</div>
			<p v-if="error" class="management-note management-error">{{ error }}</p>
			<div class="management-actions spacebetween">
				<button :class="{ success: saved }" type="submit" :disabled="loading || saving">{{ saving ? 'Speichert …' : saved ? 'Gespeichert' : 'Speichern' }}</button>
				<button class="danger" type="button" :disabled="loading || saving" @click="$emit('delete-account', user)">Account löschen</button>
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
		return {
			role: this.user.role === 'wassermeister' ? 'wassermeister' : 'none',
			savedRole: this.user.role === 'wassermeister' ? 'wassermeister' : 'none',
			locations: Array.isArray(this.user.locations) ? [...this.user.locations] : [],
			saved: false,
			loading: false,
			saving: false,
			error: '',
		};
	},
	computed: {
		userName() {
			return [this.user.firstName, this.user.lastName].filter(Boolean).join(' ') || this.user.email;
		},
	},
	methods: {
		async save() {
			this.error = '';
			this.saved = false;
			this.saving = true;
			try {
				await managementAuth.updateUserPermissions(this.user.id, this.role, this.locations);
				this.user.role = this.role;
				this.user.locations = [...this.locations];
				this.user.locationCount = this.locations.length;
				this.savedRole = this.role;
				this.saved = true;
				this.$emit('saved');
			} catch (error) {
				this.user.role = this.savedRole;
				this.error = error.message || 'Berechtigungen konnten nicht gespeichert werden.';
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
.account-name
	margin 0 0 14px
	font-weight 500

.account-permissions select
	min-width 180px

</style>
