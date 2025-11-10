<template>

	<div class="colorscheme-items">

		<div
			v-for="key in colorSchemeKeys"
			:key="key"
			class="colorscheme-item"
			:class="{ selected: state.colorScheme === key }"
			tabindex="0"
			role="radio"
			:aria-checked="state.colorScheme === key"
			@click="selectColorScheme(key)"
			@keydown.enter.space.prevent="selectColorScheme(key)">

			<ColorSchemeGradient :width="220" :height="16" :colorScheme="colorSchemes[key]" />
			<!-- <ColorSchemeItem :width="220" :height="20" :colorScheme="colorSchemes[key]" /> -->

		</div>
		
	</div>
	
</template>

<script>

import { dataModel } from '@/datamodel.js'
import { state } from '@/state.js'
import ColorScheme from '@/menu/colorscheme_item.vue'
import ColorSchemeGradient from '@/menu/colorscheme_gradient.vue'
import ColorSchemeItem from '@/menu/colorscheme_item.vue'

export default {
	name: 'ColorschemeMenu',
	components: {
		ColorScheme,
		ColorSchemeGradient,
		ColorSchemeItem,
	},
	setup() {
		return {state};
	},
	data() {
		return {
		}
	},
	computed: {
		colorSchemes() {
			return dataModel.color_schemes.nfk;
		},
		colorSchemeKeys() {
			return Object.keys(this.colorSchemes);
		},
		
	},
	props: {
	},
	methods: {
		selectColorScheme(key) {
			state.colorScheme = key
		},
	},
	watch: {
		colorScheme () {
			
		}
	},
	mounted() {
	}
}
</script>

<style lang="stylus" scoped>

.colorscheme-items
	display flex
	flex-direction column
	padding 0
	margin-bottom 4px
	gap 1px

.colorscheme-item
	cursor pointer
	width 100%
	position relative
	&.selected::after
		content ''
		display block
		position absolute
		left -8px
		top 0
		right -8px
		opacity .4
		bottom 0
		background url(/img/dreieck_rechts.png) no-repeat left center / 8px 12px, url(/img/dreieck_links.png) no-repeat right center / 8px 12px


</style>