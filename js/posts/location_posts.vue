<template>
	<section class="location-posts">
		<div class="location-posts-header">
			<button v-if="canCreateEntry" type="button" @click="$emit('create')">Neuen Post erstellen</button>
		</div>
		<p v-if="loading" class="management-note management-notice">Einträge werden geladen …</p>
		<p v-else-if="error" class="management-note management-error">{{ error }}</p>
		<div v-else-if="posts.length" class="location-posts-list">
			<PostItem v-for="post in posts" :key="post.id" :post context="location" @edit="$emit('edit', $event)" @delete="$emit('delete', $event)" />
		</div>
	</section>
</template>

<script>
import { state } from '@/state.js';
import PostItem from '@/posts/post_item.vue';
import { loadPosts } from '@/posts/store.js';

export default {
	name: 'LocationPosts',
	components: { PostItem },
	emits: ['create', 'edit', 'delete'],
	props: {
		device: {
			type: Object,
			required: true,
		},
	},
	computed: {
		posts() {
			return state.posts.filter((post) => post?.deviceId === this.device.id);
		},
		loading() {
			return state.postsLoading;
		},
		error() {
			return state.postsError;
		},
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
	mounted() {
		loadPosts().catch(() => {});
	},
};
</script>

<style lang="stylus" scoped>
.location-posts
	margin 16px var(--sidebartextmargin) 8px

.location-posts-header
	display flex
	align-items center
	justify-content space-between
	gap 12px

.location-posts-header h3
	margin 0
	font-size 11pt

.location-posts-list
	margin 12px 0
	min-width 0
	max-width 100%
	border-top var(--thinline)
	border-bottom var(--thinline)


</style>
