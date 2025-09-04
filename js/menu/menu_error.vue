<template>
	<div class="devices menuwindow" :class="{ open: isOpen, sideview }">
		<div class="menuwindow-header" @click="toggleOpen">
			<h3>
				<span>Fehlermeldungen</span>
				<!-- <span class="count">{{ faultyDevices.length }}</span> -->
			</h3>
		</div>
		<div class="menuwindow-content">

			<div v-for="device in faultyDevices" :key="device.name" class="menu-item" :class="[{ 
						selected: selectedDevice === device.name,
						red: (!nowarning && (timeSinceLastTelemetry(device.id) >= 48 || noLocationAttributes(device) || noSoilAttributes(device)) ),
					}]" @click="selectDevice(device)">

				<div class="title">
					{{ device.attributes?.Anzeigename || device.name }}
				</div>

				<div class="latestdate message" v-if="daysSinceLastTelemetry(device.id) >= 10000">
					Keine Telemetrie
				</div>

				<div class="latestdate message" v-else-if="timeSinceLastTelemetry(device.id) >= 48">
					Keine Telemetrie seit {{daysSinceLastTelemetry(device.id)}} {{daysSinceLastTelemetry(device.id) == 1 ? 'Tag' : 'Tagen'}}
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
		timeSinceLastTelemetry(deviceId) {
			return dataStore.timeSinceLastTelemetry(deviceId)
		},
		daysSinceLastTelemetry(deviceId) {
			const hours = dataStore.timeSinceLastTelemetry(deviceId);
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
	.menuwindow.devices
		width 320px
	.menu-item
		display: flex
		flex-direction column
	.red
		color: #ea4545ee
	.title
		overflow hidden
		white-space nowrap
		text-overflow ellipsis
	.message
		font-size 11px
	
	
</style> 
