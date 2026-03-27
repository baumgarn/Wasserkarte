<template>

	<div class="colorscheme-select">

		<div class="current-colorscheme">
			Farbschema: <span class="current-colorscheme-name">{{ selectedColorSchemeName }}</span>
		</div>

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

				<!-- <div class="colorscheme-label">{{ colorSchemes[key].name }}</div> -->
				<ColorSchemeGradient :width="220" :height="16" :colorScheme="colorSchemes[key].colors" />
				<!-- <ColorSchemeItem :width="220" :height="20" :colorScheme="colorSchemes[key].colors" /> -->

			</div>
		</div>

	</div>
	
</template>

<script>

import { dataModel } from '@/datamodel.js'
import { state } from '@/state.js'
import ColorSchemeGradient from '@/menu/colorscheme_gradient.vue'

export default {
	name: 'ColorschemeMenu',
	components: {
		ColorSchemeGradient,
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
		selectedColorSchemeName() {
			return dataModel.get_color_scheme_name('nfk');
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

.colorscheme-select
	display flex
	flex-direction column
	gap 6px

// .current-colorscheme
	// font-size 12px

// .current-colorscheme-name
	// font-weight 600

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
	display flex
	flex-direction column
	gap 4px
	padding 0
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

.colorscheme-label
	font-size 12px


</style>
