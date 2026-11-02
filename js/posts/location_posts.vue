<template>
	<section class="location-posts">
		<div class="location-posts-header">
			<button v-if="canCreateEntry" type="button" @click="$emit('create')"><Icon type="postbw" :size="20" />Neuer Post</button>
		</div>
		<p v-if="error" class="management-note management-error">{{ error }}</p>
		<div v-if="posts.length" class="location-posts-list">
			<PostItem ref="postItems" v-for="post in posts" :key="post.id" :post context="location" @edit="$emit('edit', $event)" @delete="$emit('delete', $event)" />
		</div>
	</section>
</template>

<script>
import { state } from '@/state.js';
import PostItem from '@/posts/post_item.vue';
import Icon from '@/ui/Icon.vue';
import { loadPosts } from '@/posts/store.js';

export default {
	name: 'LocationPosts',
	components: { PostItem, Icon },
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
	methods: {
		scrollToPost(id) {
			const item = this.$refs.postItems?.find(item => item.post.id === id);
			item?.$el.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
