<template>
	<div class="error menuwindow" :class="{ open: isOpen, sideview }">
		<div class="menuwindow-header" @click="toggleOpen">
			<h3>
				<span>Fehlermeldungen</span>
				<!-- <span class="count">{{ faultyDevices.length }}</span> -->
			</h3>
		</div>
		<div class="menuwindow-content">

			<div v-if="telemetryLoaded" v-for="device in faultyDevices" :key="device.name" class="menuitem" :class="[{ 
						selected: selectedDevice === device.name,
					}]" @click="selectDevice(device)">

				<div class="title">
					{{ device.attributes?.Anzeigename || device.name }}
				</div>

				<div class="latestdate message" v-if="hoursSinceLastTelemetry(device.id) == -1">
					Keine Telemetrie
				</div>

				<div class="latestdate message" v-else-if="hoursSinceLastTelemetry(device.id) >= 48">
					Keine Telemetrie seit <span class="days">{{daysSinceLastTelemetry(device.id)}}</span> {{daysSinceLastTelemetry(device.id) == 1 ? 'Tag' : 'Tagen'}}
				</div>
					
				<div v-if="noLocationAttributes(device)" class="nolocationattributes message">
					Geolocation fehlt
				</div>
				
				<div v-if="noSoilAttributes(device)" class="nosoilattributes message">
					Bodenattribute fehlen
				</div>

			</div>
		</div>
	</div>
</template>

<script>
import { state } from '@/state.js';
import { displayutil } from '../displayutil.js'

import dataStore from '@/datastore.js';
import ColorDot from '@/menu/colordot.vue';

export default {
	name: 'GeraeteMenu',
	setup() {
		return {state,displayutil}
	},
	components: {ColorDot},
	props: {
	},
	data() {
		return {
			isOpen: false
		}
	},
	computed: {
		selectedDevice() {
			return state.selectedDevice;
		},
		deviceItems() {
			return [...this.deviceTitles].sort((a, b) => {
				let a_name = a.attributes?.Anzeigename || a.name;
				let b_name = b.attributes?.Anzeigename || b.name;
				return a_name.localeCompare(b_name);
			});
		},
		faultyDevices() {
			return state.faultyDevices;
		},
		deviceTitles() {
			return this.devices;
		},
		telemetryLoaded() {
			return state.telemetryLoaded;
		}
	},
	methods: {
		selectDevice(device) {
			state.selectedDevice = device?.name || null;
			window.dispatchEvent(new CustomEvent('sidebar:open', { detail: device }));
			window.dispatchEvent(new CustomEvent('device-selected', { detail: device }));
			// window.dispatchEvent(new CustomEvent('panToDevice'));
		},
		setSelectedDevice(device) {
			state.selectedDevice = device?.name || null;
		},
		toggleOpen() {
			this.isOpen = !this.isOpen;
			window.dispatchEvent(new CustomEvent('menucard:toggled', { detail: this.$el }));
		},
		noLocationAttributes(device) {
			return ( ! device.attributes?.longitude || ! device.attributes?.latitude )  
		},
		noSoilAttributes(device) {
			return ( ! device.attributes?.Bodenart || ! device.attributes?.Humusgehalt )  
		},
		hoursSinceLastTelemetry(deviceId) {
			return dataStore.hoursSinceLastTelemetry(deviceId)
		},
		daysSinceLastTelemetry(deviceId) {
			const hours = dataStore.hoursSinceLastTelemetry(deviceId);
			const days = Math.floor(hours / 24);
			return days;
		},

	
	},
	mounted() {
	},
	beforeUnmount() {
	}
}
</script>

<style scoped lang="stylus">
	.menuwindow.error
		min-width 275px

	.menuitem
		display: flex
		flex-direction column
		color: #ea4545ee
	.title
		overflow hidden
		white-space nowrap
		text-overflow ellipsis
	.message
		font-size 11px
	
	
</style> 
