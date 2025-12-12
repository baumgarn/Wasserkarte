<template>
	<div class="marker menuwindow">
		<div class="menuwindow-header"><h3>Standort Darstellung</h3></div>
		<div class="menuwindow-content">
			<div
				v-for="item in menuItems"
				:key="item.value"
				:class="['menu-item', item.value.replace(/\s+/g, '_'), { selected: markerStyle === item.value }]"
				@click="selectSensor(item.value)"
			>
				{{ item.label }}
			</div>

		</div>
	</div>
</template>

<script>
import { displayutil } from '@/displayutil.js'
import { state } from '@/state.js'
import { dataModel } from '@/datamodel.js'
import FilterItem from '@/location/filteritem.vue'
import ColorSchemeSelect from '@/menu/colorscheme_select.vue'

export default {
	name: 'MarkerMenu',
		components: {
		ColorSchemeSelect,
		FilterItem,
	},
	setup() {
		return {dataModel}
	},
	data() {
		return {}
	},
	computed: {
		menuItems() {
			return [
				{ label: 'Farbkreis Schichten', value: 'Bodenfeuchte_Farbkreis' },
				{ label: 'Ø Nutzbare Feldkapazität %', value: 'Bodenfeuchte_nfk' },
				{ label: 'Ø Volumen %', value: 'Bodenfeuchte_vol' },
				// { label: '10cm Volumen %', value: 'Bodenfeuchte_10cm' },
				// { label: '30cm Volumen %', value: 'Bodenfeuchte_30cm' },
				// { label: '60cm Volumen %', value: 'Bodenfeuchte_60cm' },
				// { label: '80cm Volumen %', value: 'Bodenfeuchte_80cm' }
			]
		},
		markerStyle: {
			get() {
				return state.markerStyle
			},
			set(value) {
				state.markerStyle = value
			}
		}
	},
	methods: {
		selectSensor(value) {
			this.markerStyle = value
		}
	}
}
</script>

<style lang="stylus" scoped>
	// .marker.menuwindow
	// 	width 314px !important

	.filteritems
		display flex
		flex-direction column
		gap 0
		margin 0
	h4
		margin 0 0 .5em
		font-weight bold
		padding-top .6em
		margin-top 2em
		display block
		color #488eddee
		font-size 80%
		font-size 9.5pt
		text-transform uppercase
		border-top 1px solid #488eddaa

</style>