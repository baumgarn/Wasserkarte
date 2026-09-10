<template>
	<div class="management-panel management-window-size-mid delete-account">
		<p class="management-note management-error">Möchtest du den Account von {{ userName }} wirklich löschen?</p>
		<p class="management-note management-notice">Der Account und seine Wasserkarte-Berechtigungen werden dauerhaft entfernt.</p>
		<p v-if="error" class="management-note management-error">{{ error }}</p>
		<div class="management-actions spacebetween">
			<button type="button" :disabled="deleting" @click="$emit('close')">Abbrechen</button>
			<button class="danger" type="button" :disabled="deleting" @click="deleteAccount">{{ deleting ? 'Löscht …' : 'Account endgültig löschen' }}</button>
		</div>
	</div>
</template>

<script>
import { managementAuth } from '@/management/auth.js';

export default {
	name: 'DeleteAccount',
	emits: ['close', 'deleted'],
	props: {
		user: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			deleting: false,
			error: '',
		};
	},
	computed: {
		userName() {
			return [this.user.firstName, this.user.lastName].filter(Boolean).join(' ') || this.user.email;
		},
	},
	methods: {
		async deleteAccount() {
			this.error = '';
			this.deleting = true;
			try {
				await managementAuth.deleteUser(this.user.id);
				this.$emit('deleted');
			} catch (error) {
				this.error = error.message || 'Account konnte nicht gelöscht werden.';
			} finally {
				this.deleting = false;
			}
		},
	},
};
</script>

<style lang="stylus" scoped>
.delete-account
	display grid
	gap 12px
</style>
