<template>
	<div class="colorschemes menuwindow" :class="{ open: isOpen }">
		<div class="menuwindow-header" @click="toggleOpen"><h3>Farbschema</h3></div>
		<div class="menuwindow-content">
			<div class="colorscheme-items">
				<div 
					v-for="key in colorSchemeKeys" 
					:key="key" 
					class="colorscheme-item" 
					:class="[{ selected: state.colorScheme === key }]"
					@click="selectColorScheme(key)">

					<ColorScheme :colorScheme="colorSchemes[key]"/>

				</div>
			</div>
		</div>
	</div>
</template>

<script>

import { dataModel } from '@/datamodel.js'
import { state } from '@/state.js'
import ColorScheme from '@/menu/colorscheme_item.vue'

export default {
	name: 'ColorschemeMenu',
	components: {
		ColorScheme
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
			console.log(dataModel.color_schemes.nfk)
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
			console.log(key)
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

.colorscheme-item
	cursor pointer
	width 100%
	height 32px
	margin 0 0
	position relative
	&.selected::after
		content ''
		display block
		position absolute
		left 0
		top 0
		right 0
		bottom 0


</style>