<template>
	<section class="location-posts">
		<button v-if="canCreateEntry" type="button" @click="$emit('create')">Neuen Post erstellen</button>
	</section>
</template>

<script>
import { state } from '@/state.js';

export default {
	name: 'LocationPosts',
	emits: ['create'],
	props: {
		device: {
			type: Object,
			required: true,
		},
	},
	computed: {
		isAdmin() {
			const authority = state.account.user?.thingsboardAuthority;
			return authority === 'TENANT_ADMIN' || authority === 'SYS_ADMIN';
		},
		role() {
			return state.account.user?.wasserkarteRole || 'none';
		},
		canCreateEntry() {
			if (!state.account.authenticated) return false;
			if (this.isAdmin || this.role === 'super_wassermeister') return true;
			return this.role === 'wassermeister'
				&& Array.isArray(state.account.user?.wasserkarteLocations)
				&& state.account.user.wasserkarteLocations.includes(this.device.id);
		},
	},
};
</script>

<style lang="stylus" scoped>
.location-posts
	margin 8px 0

</style>
