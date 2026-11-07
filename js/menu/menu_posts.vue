<template>
	<div class="menuwindow posts">
		<div class="menuwindow-header">
			<h3>Posts</h3>
		</div>
		<div class="menuwindow-content">
			<p v-if="error" class="management-note management-error">{{ error }}</p>
			<div v-if="posts.length" class="menu-posts-list">
				<PostItem
					v-for="post in posts"
					:key="post.id"
					:post
					context="allposts"
					@pointerenter="$event.pointerType === 'mouse' && (state.timelineDate = Number(post.timestamp))"
					@pointerleave="$event.pointerType === 'mouse' && (state.timelineDate = null)"
					@open-location="openLocation"
					@edit="$emit('edit', $event)"
					@delete="$emit('delete', $event)" />
			</div>
			<p v-else-if="state.postsLoaded && !error" class="management-note management-notice">Noch keine Einträge vorhanden.</p>
		</div>
	</div>
</template>

<script>
import { state } from '@/state.js';
import { loadPosts, markPostsSeen } from '@/posts/store.js';
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
	watch: {
		posts: { handler: markPostsSeen, flush: 'post' },
		loading: { handler: markPostsSeen, flush: 'post' },
	},
	mounted() {
		loadPosts().then(() => this.$nextTick(markPostsSeen)).catch(() => {});
	},
};
</script>

<style lang="stylus" scoped>
.menuwindow.posts
	width 400px
	// width 275px

</style>
