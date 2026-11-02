<template>
	<div v-if="posts.length && frameWidth && latestTimestamp > startTimestamp" class="post-timeline" @mousemove.stop @touchmove.stop :style="{ width: frameWidth + 'px' }" @mouseleave="clearHover">
		<div v-for="entry in entries" :key="entry.post.id" class="post-marker" :style="{ left: entry.x + 'px', zIndex: activeId === entry.post.id ? 1 : 0 }" @mouseenter="hoverPost(entry.post)" @mouseleave="leavePost" @click="$emit('select', entry.post)">
			<span v-if="entry.posts.length > 1" class="post-count">{{ entry.posts.length }}</span>
			<Icon v-else-if="entry.media" :type="entry.media.type === 'video' ? 'post-video' : 'post-imag'" :size="12" :opacity="0.95" />
			<Icon v-else type="post-text" :size="14" />
		</div>
		<Transition name="post-preview-fade">
		<div v-if="active" ref="preview" class="post-preview" :class="{ below: previewBelow }" @mouseleave="leavePost" :style="{ left: Math.max(0, Math.min(active.x - 120, frameWidth - Math.min(240, frameWidth))) + 'px', width: Math.min(240, frameWidth) + 'px' }">
			<div v-for="entry in activePosts" :key="entry.post.id" class="post-preview-item" @mouseenter="$emit('hover', Number(entry.post.timestamp))" @click="$emit('select', entry.post)">
				<div class="post-preview-header">
					<span class="post-preview-author">{{ entry.post.authorName || 'Unbekannt' }}</span>
					<span class="post-preview-date">{{ formatDate(entry.post.timestamp) }}</span>
				</div>
				<p v-if="entry.post.content">{{ entry.post.content }}</p>
				<div v-if="entry.media.length" class="post-preview-gallery" :class="{ 'multiple': entry.media.length > 1 }">
					<img v-for="media in entry.media" :key="media.id" :src="media.variants.thumbnail.url" alt="" @load="updatePreviewPlacement">
				</div>
			</div>
		</div>
		</Transition>
	</div>
</template>

<script>
import { state } from '@/state.js';
import Icon from '@/ui/Icon.vue';

export default {
	name: 'PostTimeline',
	components: { Icon },
	emits: ['select', 'hover'],
	props: {
		device: { type: Object, required: true },
		startTimestamp: { type: Number, required: true },
		latestTimestamp: { type: Number, required: true },
		chartWidth: { type: Number, required: true },
		frameWidth: { type: Number, required: true },
		scrollLeft: { type: Number, default: 0 },
	},
	data: () => ({ activeId: null, previewBelow: false }),
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
				if (previous && x - previous.x < 18) previous.posts.push(post);
				else entries.push({ post, media: this.postMedia(post), x, posts: [post] });
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
		activePosts() {
			this.$nextTick(this.updatePreviewPlacement);
		},
	},
	beforeUnmount() {
		if (this.activeId !== null) this.clearHover();
	},
	methods: {
		updatePreviewPlacement() {
			const preview = this.$refs.preview;
			if (!this.active || !preview) return;
			const markerTop = this.$el.getBoundingClientRect().top - 13;
			this.previewBelow = preview.offsetHeight + 8 > markerTop;
		},
		postMedia(post) {
			return (post.mediaIds || []).map(id => state.media[id]).find(Boolean);
		},
		hoverPost(post) {
			this.activeId = post.id;
			this.$emit('hover', Number(post.timestamp));
		},
		leavePost(event) {
			const next = event.relatedTarget;
			if (next instanceof Element && this.$el.contains(next) && next.closest('.post-marker, .post-preview')) return;
			this.clearHover();
		},
		clearHover() {
			this.activeId = null;
			this.$emit('hover', null);
		},
		formatDate(timestamp) {
			return new Date(Number(timestamp)).toLocaleDateString('de-DE');
		},
	},
};
</script>

<style lang="stylus" scoped>
.post-timeline
	position relative
	height 0
	overflow visible
	box-sizing border-box
	z-index 101
	max-width 100%
.post-marker
	pointer-events auto
	position absolute
	top -13px
	width 18px
	height 18px
	transform translateX(-50%)
	display grid
	place-items center
	background #444
	color white
	cursor pointer
	img
		width 100%
		height 100%
		object-fit cover
	&:before
		content ''
		position absolute
		top 100%
		left 0
		width 100%
		height 9px
	&:after
		display none
		content ''
		position absolute
		top 100%
		left calc(50% - 3px)
		border-left 3px solid transparent
		border-right 3px solid transparent
		border-top 6px solid #444
	&:hover
		background #888
		&:after
			border-top-color #888
.post-count
	font-size 10px
	line-height 1
	color inherit
	pointer-events none
.post-preview
	pointer-events auto
	position absolute
	bottom 13px
	z-index 20
	box-sizing border-box
	padding 10px
	max-height 60vh
	overflow-y auto
	background white
	box-shadow 0 2px 12px #0003
	&.below
		bottom auto
		top 5px
	cursor pointer
	img
		display block
		width 100%
		max-height 150px
		object-fit cover
		margin-top 6px
	p
		margin 6px 0 0
		font-size 9pt
		white-space pre-wrap
		overflow-wrap anywhere
		display -webkit-box
		-webkit-line-clamp 5
		-webkit-box-orient vertical
		overflow hidden
.post-preview-gallery
	display grid
	gap 4px
	margin-top 6px
	img
		min-width 0
		margin-top 0
	&.multiple
		grid-template-columns repeat(2, minmax(0, 1fr))
		img
			aspect-ratio 1
.post-preview-fade-enter-active
.post-preview-fade-leave-active
	transition opacity 100ms ease
.post-preview-fade-enter-from
.post-preview-fade-leave-to
	opacity 0
.post-preview-fade-leave-active
	pointer-events none
.post-preview-item
	padding 8px 0
	&:first-child
		padding-top 0
	&:last-child
		padding-bottom 0
	& + &
		border-top 1px solid #00000022
.post-preview-header
	display flex
	align-items baseline
	justify-content space-between
	gap 8px
	font-size 8pt
	color #777
.post-preview-author
	min-width 0
	overflow hidden
	text-overflow ellipsis
	white-space nowrap
.post-preview-date
	flex-shrink 0
	white-space nowrap
	font-size 8pt
	color #777
</style>
