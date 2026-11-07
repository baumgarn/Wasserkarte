<template>
	<div v-if="posts.length && frameWidth && latestTimestamp > startTimestamp" class="post-timeline" @pointermove.stop @touchstart.stop @touchmove.stop :style="{ width: frameWidth + 'px' }">
		<div
			v-for="entry in entries"
			:key="entry.post.id"
			class="post-marker"
			:class="{ 'overlap-left': entry.overlapLeft, 'overlap-right': entry.overlapRight }"
			:style="{ left: entry.x + 'px' }"
			@pointerenter="$event.pointerType === 'mouse' && openPreview(entry.post)"
			@pointerleave="leavePreview"
			@click="openPreview(entry.post, true)">
			<PostCategoryIcon :category="entry.post.category">
				<Icon v-if="entry.media" :type="entry.media.type === 'video' ? 'post-video' : 'post-imag'" :size="12" />
				<Icon v-else type="post-text" :size="18" />
			</PostCategoryIcon>
		</div>
		<Transition name="post-preview-fade">
		<div v-if="active" ref="preview" class="post-preview" @wheel.stop @pointerleave="leavePreview" :style="{ left: Math.max(0, Math.min(active.x - previewWidth / 2, frameWidth - previewWidth)) + 'px', maxWidth: Math.min(300, frameWidth) + 'px' }">
			<div v-for="entry in activePosts" :key="entry.post.id" class="post-preview-item" :class="{ hovered: formatDate(entry.post.timestamp) === hoveredDate }" @mouseenter="hoverPreview(entry.post)" @click="$emit('select', entry.post)">
				<div class="post-preview-header">
					<span class="post-preview-date">{{ formatDate(entry.post.timestamp) }}</span>
				</div>
				<p v-if="entry.post.content"><PostCategoryIcon :category="entry.post.category" inline />{{ entry.post.content }}</p>
				<div v-if="entry.media.length" class="post-preview-gallery">
					<img v-for="media in entry.media" :key="media.id" :src="media.variants.thumbnail.url" alt="" @load="updatePreviewPlacement">
				</div>
			</div>
		</div>
		</Transition>
	</div>
</template>

<script>
import { formatPostDate } from '@/posts/format.js';
import { state } from '@/state.js';
import Icon from '@/ui/Icon.vue';
import PostCategoryIcon from '@/posts/post_category_icon.vue';

let activePreview = null;

export default {
	name: 'PostTimeline',
	components: { Icon, PostCategoryIcon },
	emits: ['select', 'hover'],
	props: {
		device: { type: Object, required: true },
		startTimestamp: { type: Number, required: true },
		latestTimestamp: { type: Number, required: true },
		chartWidth: { type: Number, required: true },
		frameWidth: { type: Number, required: true },
		scrollLeft: { type: Number, default: 0 },
	},
	data: () => ({ activeId: null, previewPinned: false, hoveredDate: null, previewWidth: 0 }),
	computed: {
		posts() {
			return state.posts.filter(post => post?.deviceId === this.device.id);
		},
		entries() {
			const duration = this.latestTimestamp - this.startTimestamp;
			if (duration <= 0) return [];
			const entries = [];
			for (const post of [...this.posts].sort((a, b) => Number(a.timestamp) - Number(b.timestamp))) {
				const x = (Number(post.timestamp) - this.startTimestamp) / duration * this.chartWidth - this.scrollLeft;
				if (!Number.isFinite(x) || x < 0 || x > this.frameWidth) continue;
				const previous = entries[entries.length - 1];
				const overlaps = previous != null && x - previous.x < 18;
				const samePosition = x === previous?.x;
				const showOverlap = overlaps && !samePosition && this.formatDate(post.timestamp) !== this.formatDate(previous.post.timestamp);
				if (showOverlap) previous.overlapRight = true;
				const overlapLeft = showOverlap || (samePosition && previous.overlapLeft);
				const group = overlaps ? previous.posts : [];
				group.push(post);
				entries.push({ post, media: this.postMedia(post), x, posts: group, overlapLeft, overlapRight: false });
			}
			return entries;
		},
		activePosts() {
			if (!this.active) return [];
			const date = this.formatDate(this.active.post.timestamp);
			return this.posts.filter(post => this.formatDate(post.timestamp) === date || this.active.posts.includes(post))
				.sort((a, b) => Number(a.timestamp) - Number(b.timestamp))
				.map(post => ({ post, media: (post.mediaIds || []).map(id => state.media[id]).filter(media => media?.variants?.thumbnail?.url) }));
		},
		active() { return this.entries.find(entry => entry.post.id === this.activeId); },
	},
	watch: {
		active(entry) {
			if (!entry && this.activeId !== null) this.closePreview();
		},
		activePosts() {
			this.$nextTick(this.updatePreviewPlacement);
		},
	},
	beforeUnmount() {
		if (this.activeId !== null) this.closePreview();
	},
	methods: {
		updatePreviewPlacement() {
			const preview = this.$refs.preview;
			if (!this.active || !preview) return;
			this.previewWidth = preview.offsetWidth;
			const location = this.$el.closest('.location');
			const locationTop = location ? location.getBoundingClientRect().top : -window.scrollY;
			const previewBottom = this.$el.getBoundingClientRect().top - parseFloat(getComputedStyle(preview).bottom);
			preview.style.maxHeight = Math.max(0, previewBottom - locationTop) + 'px';
			preview.scrollTop = preview.scrollHeight;
		},
		postMedia(post) {
			return (post.mediaIds || []).map(id => state.media[id]).find(Boolean);
		},
		openPreview(post, pinned = false) {
			if (!pinned && activePreview?.previewPinned) return;
			if (activePreview !== this) {
				activePreview?.closePreview();
				activePreview = this;
				document.addEventListener('pointerdown', this.closeOutside, true);
			}
			this.activeId = post.id;
			this.previewPinned = pinned;
			this.hoverPreview(post);
		},
		hoverPreview(post) {
			this.hoveredDate = this.formatDate(post.timestamp);
			this.$emit('hover', Number(post.timestamp));
		},
		isPreviewTarget(target) {
			const marker = target?.closest?.('.post-marker');
			return this.$refs.preview?.contains(target) || (marker && this.$el.contains(marker));
		},
		leavePreview(event) {
			if (event.pointerType === 'mouse' && this.activeId !== null && !this.previewPinned && !this.isPreviewTarget(event.relatedTarget)) {
				this.closePreview();
			}
		},
		closeOutside(event) {
			if (!this.isPreviewTarget(event.target)) this.closePreview();
		},
		closePreview() {
			document.removeEventListener('pointerdown', this.closeOutside, true);
			if (activePreview === this) activePreview = null;
			this.activeId = null;
			this.previewPinned = false;
			this.hoveredDate = null;
			this.$emit('hover', null);
		},
		formatDate(timestamp) {
			return formatPostDate(timestamp);
		},
	},
};
</script>

<style lang="stylus" scoped>
.post-preview :deep(.icon.post-category-inline)
	margin-right 2px
	&.type-post-bewaessert
		top 1px
</style>
