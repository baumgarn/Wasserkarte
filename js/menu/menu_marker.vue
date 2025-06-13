<template>
	<div class="marker menuwindow">
		<div class="menuwindow-header"><h3>Bodenfeuchte</h3></div>
		<div class="menuwindow-content">
			<div
				v-for="title in menuItems"
				:key="title"
				:class="['menu-item', title.replace(/\s+/g, '_'), { selected: markerStyle === title }]"
				@click="selectSensor(title)"
				>
				{{ getDisplayTitle(title) }}
			</div>
		</div>
	</div>
</template>

<script>

import { displayutil } from '../displayutil.js'
import { state } from '../state.js'

export default {
	name: 'MarkerMenu',
	data() {
		return {
		}
	},
	computed: {
		menuItems() {
			return ['Bodenfeuchte Farbkreis','Bodenfeuchte_10cm','Bodenfeuchte_30cm','Bodenfeuchte_60cm','Bodenfeuchte_80cm']
		},
		markerStyle: {
			get() {
				return state.markerStyle
			},
			set(value) {
				state.markerStyle = value
			}
		},
	},
	props: {
	},
	methods: {
		selectSensor(title) {
			this.markerStyle = title
		},
		getDisplayTitle(title) {
			return displayutil.title(title)
		},
		getDisplayUnit(title) {
			return displayutil.unit(title)
		},
	},
	watch: {
		markerStyle(newSensor, oldSensor) {
		}
	},
	mounted() {
	}
}
</script>

<style lang="stylus" scoped>

</style>