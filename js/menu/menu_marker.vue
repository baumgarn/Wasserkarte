<template>
	<div class="marker menuwindow">
		<div class="menuwindow-header"><h3>Bodenfeuchte</h3></div>
		<div class="menuwindow-content">
			<div
				v-for="item in menuItems"
				:key="item.value"
				:class="['menu-item', item.value.replace(/\s+/g, '_'), { selected: markerStyle === item.value }]"
				@click="selectSensor(item.value)"
			>
				{{ item.label }}
			</div>

			<!-- <div class="settings-group">
			
				<div class="settingslabel">
					Farbschema
				</div>
				
				<ColorSchemeSelect />

				<div class="split">
					<div class="left">Trocken</div>
					<div class="right">Nass</div>
				</div>

			</div> -->

		</div>
	</div>
</template>

<script>
import { displayutil } from '../displayutil.js'
import { state } from '../state.js'
import ColorSchemeSelect from '@/menu/colorscheme_select.vue'

export default {
	name: 'MarkerMenu',
		components: {
		ColorSchemeSelect
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
</style>