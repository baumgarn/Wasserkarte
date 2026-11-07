<template>
	<div v-if="ids.length && (initialLoading || error || visibleIds.length)" class="post-media" @keydown.stop>
		<p v-if="initialLoading" class="media-note" role="status">Bilder werden geladen …</p>
		<div v-else-if="video" class="post-video" @click.stop>
			<video v-if="!failed[video.id]" :key="video.id" :src="video.variants.display.url" :poster="video.variants.thumbnail.url" controls playsinline preload="metadata" @error="failed[video.id] = true"></video>
			<p v-else class="media-unavailable">Dieses Video ist nicht verfügbar.</p>
		</div>
		<div v-else class="media-strip">
			<template v-for="(id, index) in visibleIds" :key="id">
				<div v-if="media[id] && !failed[id]" class="media-tile" :style="{ '--image-ratio': media[id].variants.thumbnail.width / media[id].variants.thumbnail.height }" role="button" tabindex="0" :aria-label="`Bild ${index + 1} von ${visibleIds.length} öffnen`" @click.stop="open(index)" @keydown.enter.prevent="open(index)" @keydown.space.prevent="open(index)">
					<img :src="media[id].variants.thumbnail.url" :width="media[id].variants.thumbnail.width" :height="media[id].variants.thumbnail.height" alt="" loading="lazy" @error="failed[id] = true">
				</div>
				<div v-else class="media-unavailable">Bild {{ index + 1 }} nicht verfügbar</div>
			</template>
		</div>
		<p v-if="error" class="media-note">{{ error }} <span class="media-retry" role="button" tabindex="0" @click.stop="reload" @keydown.enter.prevent="reload" @keydown.space.prevent="reload">Erneut laden</span></p>
		<Teleport to="body">
			<div v-if="active !== null" ref="viewer" class="media-viewer" role="dialog" aria-modal="true" :aria-label="`Bild ${active + 1} von ${visibleIds.length}`" tabindex="-1" @click.self="close">
				<div class="viewer-close" role="button" tabindex="0" aria-label="Bildansicht schließen" @click="close" @keydown.enter.prevent="close" @keydown.space.prevent="close"><Icon type="close" :size="36" /></div>
				<figure>
					<div class="viewer-image" @wheel.prevent="zoomImage">
						<img v-if="activeMedia && !displayFailed" :key="activeMedia.id" class="viewer-image-action" :style="{ transform: `scale(${zoom})`, transformOrigin: zoomOrigin }" :src="activeMedia.variants.display.url" alt="Bild zum Post" role="button" tabindex="0" :aria-label="active === visibleIds.length - 1 ? 'Bildansicht schließen' : 'Nächstes Bild anzeigen'" @click="advanceFromImage" @keydown.enter.prevent="advanceFromImage" @keydown.space.prevent="advanceFromImage" @error="displayFailed = true">
						<p v-else>Dieses Medium ist nicht verfügbar.</p>
					</div>
					<div v-if="visibleIds.length > 1" class="viewer-navigation">
						<div class="viewer-control viewer-previous" role="button" tabindex="0" aria-label="Vorheriges Bild" @click="navigate(-1)" @keydown.enter.prevent="navigate(-1)" @keydown.space.prevent="navigate(-1)"><Icon type="arrow-left" :size="16" /></div>
						<figcaption>{{ active + 1 }} / {{ visibleIds.length }}</figcaption>
						<div class="viewer-control viewer-next" role="button" tabindex="0" aria-label="Nächstes Bild" @click="navigate(1)" @keydown.enter.prevent="navigate(1)" @keydown.space.prevent="navigate(1)"><Icon type="arrow-right" :size="16" /></div>
					</div>
				</figure>
			</div>
		</Teleport>
	</div>
</template>

<script>
import { state } from '@/state.js';
import { loadMedia } from '@/media/store.js';
import Icon from '@/ui/Icon.vue';

export default {
	name: 'PostMedia',
	components: { Icon },
	props: { ids: { type: Array, default: () => [] } },
	data: () => ({ active: null, failed: {}, displayFailed: false, zoom: 1, zoomOrigin: 'center', previousFocus: null, previousOverflow: '' }),
	computed: {
		media: () => state.media,
		initialLoading: () => state.mediaLoading && !state.mediaLoaded,
		error: () => state.mediaError,
		visibleIds() { return state.mediaLoaded ? this.ids.filter(id => this.media[id]) : this.ids; },
		video() { const item = this.media[this.visibleIds[0]]; return item?.type === 'video' ? item : null; },
		activeMedia() { return this.media[this.visibleIds[this.active]]; },
	},
	watch: {
		visibleIds(ids, previousIds) {
			if (this.active !== null && (ids.length !== previousIds.length || ids.some((id, index) => id !== previousIds[index]))) {
				this.close();
			}
		},
	},
	mounted() { loadMedia().catch(() => {}); },
	beforeUnmount() { if (this.active !== null) this.close(); },
	methods: {
		reload() { this.failed = {}; loadMedia(true).catch(() => {}); },
		async open(index) {
			this.previousFocus = document.activeElement;
			this.previousOverflow = document.body.style.overflow;
			document.body.style.overflow = 'hidden';
			this.active = index;
			this.zoom = 1;
			this.displayFailed = false;
			window.addEventListener('keydown', this.handleKeydown, true);
			await this.$nextTick();
			this.$refs.viewer?.querySelector('[role="button"]')?.focus();
		},
		close() {
			window.removeEventListener('keydown', this.handleKeydown, true);
			document.body.style.overflow = this.previousOverflow;
			this.active = null;
			this.previousFocus?.focus();
		},
		navigate(delta) {
			this.active = (this.active + delta + this.visibleIds.length) % this.visibleIds.length;
			this.zoom = 1;
			this.displayFailed = false;
		},
		zoomImage(event) {
			if (!this.activeMedia || this.displayFailed || !event.deltaY) return;
			const delta = event.deltaMode === 0 ? event.deltaY : Math.sign(event.deltaY) * 100;
			const nextZoom = Math.max(1, Math.min(2, this.zoom * Math.exp(-delta * 0.002)));
			if (this.zoom === 1 && nextZoom > 1) {
				const bounds = event.currentTarget.getBoundingClientRect();
				this.zoomOrigin = `${event.clientX - bounds.left}px ${event.clientY - bounds.top}px`;
			}
			this.zoom = nextZoom;
		},
		advanceFromImage() {
			if (this.active === this.visibleIds.length - 1) this.close();
			else this.navigate(1);
		},
		handleKeydown(event) {
			if (this.active === null) return;
			if (['Escape', 'ArrowLeft', 'ArrowRight', 'Tab'].includes(event.key)) {
				event.preventDefault();
				event.stopImmediatePropagation();
			}
			if (event.key === 'Escape') this.close();
			if (event.key === 'ArrowLeft') this.navigate(-1);
			if (event.key === 'ArrowRight') this.navigate(1);
			if (event.key === 'Tab') {
				const controls = [...this.$refs.viewer.querySelectorAll('[role="button"]')];
				const current = controls.indexOf(document.activeElement);
				controls[(current + (event.shiftKey ? -1 : 1) + controls.length) % controls.length].focus();
			}
		},
	},
};
</script>

<style lang="stylus" scoped>
.post-media { 
	margin-top: 10px; 
}
.media-strip
	display flex
	flex-wrap wrap
	gap 4px
.post-video video
	display block
	width 100%
	height auto
	border-radius 0

.media-tile
	display inline-block
	width calc(var(--post-image-height, 300px) * var(--image-ratio))
	max-width 100%
	vertical-align top
	border-radius 0
	overflow hidden
	cursor pointer
	img
		display block
		width 100%
		height auto
.media-tile:focus-visible { 
	outline: 2px solid #186d84; 
	outline-offset: 2px; 
}
.media-unavailable { 
	display: grid; 
	place-items: center; 
	min-height: 80px; 
	padding: 8px; 
	background: #f2f2f2; 
	color: #666; 
	font-size: 9pt; 
}
.media-note { 
	font-size: 9pt; 
	color: #666; 
}
.media-retry {
	color: #186d84;
	cursor: pointer;
	text-decoration: underline;
}
.media-retry:focus-visible {
	outline: 2px solid #186d84;
	outline-offset: 2px;
}
.media-viewer { 
	position: fixed; 
	inset: 0; 
	z-index: 1000; 
	display: flex; 
	align-items: center; 
	justify-content: center; 
	background: #fff; 
	color: #111; 
	padding 36px 16px 4px
	box-sizing: border-box; 
}
.media-viewer figure
	display grid
	grid-template-rows minmax(0, 1fr) 36px
	gap 10px
	width 100%
	height 100%
	margin 0
	text-align center
	min-width 0
	min-height 0

.viewer-image
	overflow hidden
	display grid
	place-items center
	min-width 0
	min-height 0

.media-viewer figure img
	display block
	width 100%
	height 100%
	min-width 0
	min-height 0
	object-fit contain
.media-viewer figure img.viewer-image-action {
	cursor: pointer;
}
.media-viewer figure img.viewer-image-action:focus-visible {
	outline: 2px solid #186d84;
	outline-offset: 3px;
}
.viewer-control { 
	display: grid;
	place-items: center;
	min-width: 0; 
	width: 36px; 
	height: 36px; 
	padding: 0; 
	cursor: pointer; 
	opacity .2
}
.viewer-control:hover { 
	opacity .4
}
.viewer-control:focus-visible {
	outline: 2px solid #186d84;
	outline-offset: 2px;
}
.viewer-close 
	position: absolute; 
	top: 0; 
	left: 0; 
	opacity 1
	cursor pointer
	&:hover
		opacity .4
.viewer-navigation {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
}
.viewer-navigation figcaption {
	min-width: 48px;
	font-size: 10pt;
}
</style>
