<template>
	<div class="soil menuwindow" :class="{ open: isOpen }">
		<div class="menuwindow-header"><h3>Böden</h3></div>
		<div class="menuwindow-content">
			<div
				v-for="title in menuItems"
				:key="title"
				:class="['menuitem', title.replace(/\s+/g, '_'), { selected: selectedSoil === title }]"
				@click="selectSoil(title)"
				>
				{{ getDisplayTitle(title) }}
			</div>
		</div>
	</div>
</template>

<script>
import { displayutil } from '../displayutil.js'
// import dataStore from './datastore.js'
import {state} from '../state.js'

export default {
	name: 'SoilMenu',
	data() {
		return {
			isOpen: false
		}
	},
	computed: {
		menuItems() {
			// Add a safety check
			return this.soilTypes?.length ? ['Alle',...this.soilTypes] : []
		},
		selectedSoil: {
			get() {
				return state.selectedSoil || 'Alle' // Default value
			},
			set(value) {
				state.selectedSoil = value
			}
		},
		soilTypes() {
			return state.uniqueSoilTypes;
		}
	},
	methods: {
		selectSoil(title) {
			console.log(title)
			this.selectedSoil = title
			this.$emit('soil-selected', title)
		},
		setSelectedSoil(title) {
			this.selectedSoil = title
		},
		getDisplayTitle(title) {
			return displayutil.title(title)
		},
		getDisplayUnit(title) {
			return displayutil.unit(title)
		},
	}
}
</script>