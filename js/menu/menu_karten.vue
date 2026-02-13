<template>
	<div class="karten menuwindow" :class="{ open: isOpen }">
		<div class="menuwindow-header" @click="toggleOpen">
			<h3>Geologische Karten</h3>
			<div class="menuwindow-button-row" :class="{ 'has-visible-layer': hasVisibleLayer }">
				<div class="menuwindow-button icon transparent" @click="toggleTransparency" :class="{ active: state.wsmtransparency }">Transparent</div>
				<div class="menuwindow-button icon legende" @click="toggleLegends" :class="{ active: state.wsmlegends }">Legende</div>
			</div>
		</div>
		<div class="menuwindow-content">
			<div class="attribution">
				&copy; Landesvermessung und Geobasisinformation Brandenburg (LGB) 
			</div>
			<template v-for="layer in layers" :key="layer.name">
				<div
					v-if="layer.hasChildLayers"
					class="map-section-header">
					<span class="title">{{ layer.title || layer.name }}</span>
				</div>
				<div
					v-else
					class="menu-item"
					:title="layer.title || layer.name"
					:class="{ 'selected': layer.visible }"
					@click="toggleLayer(layer)">
					<span class="title">{{ layer.title || layer.name }}</span>
				</div>
			</template>
		</div>
	</div>
</template>

<script>

import { state } from '../state.js';

export default {
	name: 'LayerSwitcher',
	setup() {
		return {
			state
		}
	},
	data() {
		return {
			isOpen: false,
		}
	},
	methods: {
	toggleLayer(layer) {
		if (!layer.visible) {
			state.wsmLayers.forEach(l => l.visible = false);
			layer.visible = true;
		} else {
			layer.visible = false;
		}
	},
	toggleOpen() {
		this.isOpen = !this.isOpen;
		window.dispatchEvent(new CustomEvent('menucard:toggled', { detail: this.$el }));
	},
	toggleTransparency () {
		state.wsmtransparency = !state.wsmtransparency;
	},
	toggleLegends() {
		state.wsmlegends = !state.wsmlegends;
	}
	},
	computed: {
		layers() {
			return [...state.wsmLayers];
		},
		hasVisibleLayer() {
			return state.wsmLayers.some(layer => layer.visible);
		}
	},
	created() {
	}
}
</script>

<style lang="stylus" scoped>

.menuwindow.karten
	max-width 440px

.wms-provider
	max-width 100%

.attribution
	margin 5px 5px -15px 5px
	font-size 9.5pt
	opacity 1

.menuwindow-button-row
	opacity 0
	pointer-events none
	transition opacity .05s linear

.menuwindow-button-row.has-visible-layer
	opacity 1
	pointer-events all

.map-section-header
	cursor default
	border-radius 0
	height 32px
	width 100%
	padding 0 6px
	.title
		font-weight bold
		padding-top 6px
		margin-top 2em
		display block
		color var(--menusectionheadercolor)
		text-transform uppercase
		font-size 80%
		font-size 9.5pt
		border-top var(--thinline)

.map-section-header + .map-section-header .title
	margin-top -.1em

.menu-item
	margin-right -.25em

.layer-item label
	display flex
	flex-direction row
	align-items flex-start
	cursor pointer
	span
		margin-left .25em
		margin-top .1em

</style>
