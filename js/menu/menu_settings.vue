<template>
	<div class="menuwindow settings">

		<div class="menuwindow-header">
			<h3>Einstellungen</h3>
		</div>

		<div class="menuwindow-content">

			<div class="settings-group">
			
				<div class="settingslabel">
					Farbschema
				</div>
				
				<ColorSchemeSelect />

				<div class="split">
					<div class="left">Trocken</div>
					<div class="right">Nass</div>
				</div>

			</div>

			<div class="settings-group">
			
				<div class="settings-item">
					<input type="checkbox" id="faultyvalues" v-model="state.focusMode">
					<label for="faultyvalues">Monochrome Karte</label>
				</div>

				<div class="settings-item">
					<input type="checkbox" id="showdatagaps" v-model="state.showDataGaps">
					<label for="showdatagaps">Datenlücken in Graphen</label>
				</div>
				
				<div class="settings-item">
					<input type="checkbox" id="locationwarnings" v-model="state.showErrors">
					<label for="locationwarnings">Fehlermeldungen</label>
				</div>

				<div class="settings-item">
					<input type="checkbox" id="showinfoonstart" v-model="state.debugAttributes">
					<label for="showinfoonstart">Debug Attribute</label>
				</div>

			</div>
			
		</div>

	</div>

</template>

<script>

import { state } from '../state.js' 
import ColorSchemeSelect from '@/menu/colorscheme_select.vue'

export default {
	name: 'MarkerMenu',
	components: {
		ColorSchemeSelect
	},
	setup() {
		return {state};
	},
	data() {
		return {
		}
	},
	computed: {
	},
	props: {
	},
	methods: {
	
	},
	watch: {
	
	},
	mounted() {
	}
}
</script>

<style lang="stylus" scoped>

.menuwindow.settings
	width 320px

// .settings
// 	user-select none

.notice
	opacity .5

.split
	margin-top -2px
	display flex
	justify-content space-between
	font-size 10px
	// margin-bottom 12px
	.right
		text-align right

</style>