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

					<div class="colorscheme-name">{{ colorSchemes[key].name }}</div>
					<ColorScheme :colorScheme="colorSchemes[key].colors"/>

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

.colorscheme-item
	cursor pointer
	width 100%
	min-height 32px
	margin 0 0
	position relative
	display flex
	flex-direction column
	gap 4px
	padding 4px 0
	&.selected::after
		content ''
		display block
		position absolute
		left 0
		top 0
		right 0
		bottom 0

.colorscheme-name
	font-size 12px


</style>
