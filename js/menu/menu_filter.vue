<template>
	<div class="filter menuwindow">
		<div class="menuwindow-header"><h3>Standort Filter</h3></div>
		<div class="menuwindow-content">
			
			<!-- <div class="filter"> -->

				<div class="filteritems">

				<h4>Wasserhaushalt</h4>

				<FilterItem :obj="dataModel.regenabhängig_obj" type="menuitem"/>
				<FilterItem :obj="dataModel.bewaessert_obj" type="menuitem"/>
				<FilterItem :obj="dataModel.grundwasser_obj" type="menuitem"/>
				<!-- </div> -->

				<h4>Bodenart</h4>

				<!-- <div class="filteritems"> -->
					<FilterItem v-for="item in dataModel.soil_table" :obj="item" type="menuitem"/>
				<!-- </div> -->
				
				<h4>Humusgehalt</h4>

				<!-- <div class="filteritems"> -->
					<FilterItem v-for="item in dataModel.humus_table" :obj="item" type="menuitem"/>
				<!-- </div> -->
			
				<h4>Nutzungsart</h4>
				<!-- <div class="filteritems"> -->
					<FilterItem v-for="item in dataModel.usage_table" :obj="item" type="menuitem"/>
				</div>

			</div>

		<!-- </div> -->
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
	.menuwindow.filter
		min-width 225px !important

	// 	width 314px !important

	.filteritems
		display flex
		flex-direction column
		gap 0
		margin 0
		h4:first-of-type
			border-top none
			margin-top 0


</style>