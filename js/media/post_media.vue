<template>
	<div v-if="ids.length && (loading || error || visibleIds.length)" class="post-media" @click.stop @keydown.stop>
		<p v-if="loading" class="media-note" role="status">Bilder werden geladen …</p>
		<div v-else class="media-grid" :class="{ 'media-grid-single': visibleIds.length === 1 }">
			<template v-for="(id, index) in visibleIds" :key="id">
				<div v-if="media[id] && !failed[id]" class="media-tile" role="button" tabindex="0" :aria-label="`Bild ${index + 1} von ${visibleIds.length} öffnen`" @click="open(index)" @keydown.enter.prevent="open(index)" @keydown.space.prevent="open(index)">
					<img :src="media[id].variants.thumbnail.url" :width="media[id].variants.thumbnail.width" :height="media[id].variants.thumbnail.height" alt="" loading="lazy" @error="failed[id] = true">
				</div>
				<div v-else class="media-unavailable">Bild {{ index + 1 }} nicht verfügbar</div>
			</template>
		</div>
		<p v-if="error" class="media-note">{{ error }} <span class="media-retry" role="button" tabindex="0" @click="reload" @keydown.enter.prevent="reload" @keydown.space.prevent="reload">Erneut laden</span></p>
		<Teleport to="body">
			<div v-if="active !== null" ref="viewer" class="media-viewer" role="dialog" aria-modal="true" :aria-label="`Bild ${active + 1} von ${visibleIds.length}`" tabindex="-1" @click.self="close">
				<div class="viewer-close" role="button" tabindex="0" aria-label="Bildansicht schließen" @click="close" @keydown.enter.prevent="close" @keydown.space.prevent="close"><Icon type="close" :size="36" /></div>
				<figure>
					<img v-if="activeMedia && !displayFailed" :key="activeMedia.id" :class="{ 'viewer-image-navigable': visibleIds.length > 1 }" :src="activeMedia.variants.display.url" alt="Bild zum Post" :role="visibleIds.length > 1 ? 'button' : null" :tabindex="visibleIds.length > 1 ? 0 : null" :aria-label="visibleIds.length > 1 ? 'Nächstes Bild anzeigen' : null" @click="visibleIds.length > 1 && navigate(1)" @keydown.enter.prevent="visibleIds.length > 1 && navigate(1)" @keydown.space.prevent="visibleIds.length > 1 && navigate(1)" @error="displayFailed = true">
					<p v-else>Dieses Bild ist nicht verfügbar.</p>
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
	data: () => ({ active: null, failed: {}, displayFailed: false, previousFocus: null, previousOverflow: '' }),
	computed: {
		media: () => state.media,
		loading: () => state.mediaLoading,
		error: () => state.mediaError,
		visibleIds() { return state.mediaLoaded && !this.loading && !this.error ? this.ids.filter(id => this.media[id]) : this.ids; },
		activeMedia() { return this.media[this.visibleIds[this.active]]; },
	},
	watch: { visibleIds() { if (this.active !== null) this.close(); } },
	mounted() { loadMedia().catch(() => {}); },
	beforeUnmount() { if (this.active !== null) this.close(); },
	methods: {
		reload() { this.failed = {}; loadMedia(true).catch(() => {}); },
		async open(index) {
			this.previousFocus = document.activeElement;
			this.previousOverflow = document.body.style.overflow;
			document.body.style.overflow = 'hidden';
			this.active = index;
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
			this.displayFailed = false;
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
	container-type: inline-size; 
}
.media-grid { 
	display: grid; 
	grid-template-columns: repeat(2, minmax(0, 1fr)); 
	gap: 6px; 
}
.media-grid-single { 
	grid-template-columns: minmax(0, 300px); 
}
.media-tile { 
	display: block; 
	padding: 0; 
	min-width: 0; 
	width: 100%; 
	height: auto; 
	border: 0; 
	border-radius: 4px; 
	overflow: hidden; 
	background: #eee; 
	cursor: pointer; 
	aspect-ratio: 4 / 3; 
}
.media-tile img { 
	display: block; 
	width: 100%; 
	height: 100%; 
	object-fit: cover; 
}
.media-grid-single .media-tile { 
	aspect-ratio: auto; 
}
.media-grid-single img { 
	height: auto; 
}
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
@container (min-width: 650px) { 
	.media-grid:not(.media-grid-single) { 
		grid-template-columns: repeat(4, minmax(0, 1fr)); 
	} 
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
	padding: 36px 16px 36px; 
	box-sizing: border-box; 
}
.media-viewer figure { 
	margin: 0; 
	text-align: center; 
	min-width: 0; 
}
.media-viewer figure img { 
	display: block; 
	max-width: 100%; 
	max-height: calc(100dvh - 72px); 
	width: auto; 
	height: auto; 
	object-fit: contain; 
	margin: auto; 
}
.media-viewer figure img.viewer-image-navigable {
	cursor: pointer;
}
.media-viewer figure img.viewer-image-navigable:focus-visible {
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
	margin-top: 10px;
}
.viewer-navigation figcaption {
	min-width: 48px;
	font-size: 10pt;
}
</style>
