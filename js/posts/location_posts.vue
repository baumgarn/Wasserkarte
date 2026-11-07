<template>
	<section class="location-posts">
		<div class="location-posts-header">
			<button v-if="canCreateEntry" type="button" @click="$emit('create')"><Icon type="plus-square" :size="14" />Neuer Post</button>
			<slot name="header-end" />
		</div>
		<p v-if="error" class="management-note management-error">{{ error }}</p>
		<div v-if="posts.length" class="location-posts-list">
			<PostItem ref="postItems" v-for="post in posts" :key="post.id" :post context="location" @mouseenter="$emit('hover', Number(post.timestamp))" @mouseleave="$emit('hover', null)" @edit="$emit('edit', $event)" @delete="$emit('delete', $event)" />
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
	emits: ['create', 'edit', 'delete', 'hover'],
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
.location-posts-header
	position relative
	&:has(> *)
		height 26px
	> button
		height 100%
</style>
