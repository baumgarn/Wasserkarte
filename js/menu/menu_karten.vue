<template>
	<div class="karten menuwindow" :class="{ open: isOpen }">
		<div class="menuwindow-header" @click="toggleOpen">
			<h3>Karten</h3>
			<div class="menuwindow-button-row">
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
					class="menu-item map-section-header">
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
	// margin .6em 0 
	max-width 100%
	// background red
.attribution
	margin 5px 5px -20px 5px
	font-size 9.5pt
	opacity .5

.map-section-header
	cursor default
	border-radius 0
	.title
		font-weight bold
		padding-top .6em
		margin-top 2em
		display block
		color #587eccbb
		color #1c5de1bb
		// color #df8a69
		// color #888
		font-size 80%
		font-size 9.5pt
		// border-top 2px solid #587ecc77
		border-top 2px solid #1c5de144
		// border-top 2px solid #df8a69aa



.map-section-header + .map-section-header .title
	margin-top -.1em


.menu-item
	margin-right -.25em

.wms-provider h4 {
	// margin-bottom: 5px;
	display none
	border-bottom 1px solid #ccc
	margin-left -.25em
	margin-right -.5em
	margin-bottom 0
	margin-top 0
	max-width 100%
	padding 0

	font-weight: bold;
}
.layer-item label
	display flex
	flex-direction row
	align-items flex-start
	cursor pointer
	span
		margin-left .25em
		margin-top .1em
</style>
