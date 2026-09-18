<template>
	<div class="menuwindow posts">
		<div class="menuwindow-header">
			<h3>Posts</h3>
		</div>
		<div class="menuwindow-content">
			<p v-if="loading" class="management-note management-notice">Einträge werden geladen …</p>
			<p v-else-if="error" class="management-note management-error">{{ error }}</p>
			<div v-else-if="posts.length" class="posts-list">
				<PostItem v-for="post in posts" :key="post.id" :post context="allposts" @open-location="openLocation" @edit="$emit('edit', $event)" @delete="$emit('delete', $event)" />
			</div>
			<p v-else class="management-note management-notice">Noch keine Einträge vorhanden.</p>
		</div>
	</div>
</template>

<script>
import { state } from '@/state.js';
import { loadPosts } from '@/posts/store.js';
import PostItem from '@/posts/post_item.vue';

export default {
	name: 'PostsMenu',
	emits: ['edit', 'delete'],
	components: { PostItem },
	setup() {
		return { state };
	},
	computed: {
		posts() {
			return state.posts;
		},
		loading() {
			return state.postsLoading;
		},
		error() {
			return state.postsError;
		},
	},
	methods: {
		openLocation(post) {
			const device = state.devices.find((item) => item?.id === post?.deviceId);
			if (!device) return;

			state.selectedDevice = device.name;
			window.dispatchEvent(new CustomEvent('sidebar:open', { detail: device }));
			window.dispatchEvent(new CustomEvent('device-selected', { detail: device }));
		},
	},
	mounted() {
		loadPosts().catch(() => {});
	},
};
</script>

<style lang="stylus" scoped>
.menuwindow.posts
	width 400px

// .posts-list
	// display flex
	// flex-direct
	// min-width 0
	// max-width 100%
</style>
