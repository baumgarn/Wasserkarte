<template>
	<div class="devices menuwindow" :class="{ open: isOpen, sideview }">
		<div class="menuwindow-header" @click="toggleOpen" v-if="!sideview">
			<h3>
				<span>Standorte</span>
				<span class="count" v-if="!sideview">{{ deviceItems.length }}</span>
			</h3>
		</div>
		<div class="menuwindow-content">

			<div v-for="device in deviceItems" :key="device.name" class="menu-item" 
				:class="[{selected: selectedDevice === device.name,}]" @click="selectDevice(device)"
				>

				<ColorDot :device />

				<div class="title">
					{{ device.attributes?.Anzeigename || device.name }}
				</div>

			</div>
		</div>
	</div>
</template>

<script>
import { state } from '@/state.js';
import dataStore from '@/datastore.js';
import { dataModel } from '@/datamodel.js'
import ColorDot from '@/menu/colordot.vue';

export default {
	name: 'GeraeteMenu',
	setup() {
		return {state}
	},
	components: {ColorDot},
	props: {
		sideview: {
			type: Boolean,
			default: false
		},
		nowarning: {
			type: Boolean,
			default: false
		},
		stateProperty: {
			type: String,
			required: false,
			default: "selectedDevice"
		}
	},
	data() {
		return {
			isOpen: false
		}
	},
	computed: {
		devices() {
			return state.devices;
		},
		selectedDevice() {
			return state[this.stateProperty];
		},
		deviceItems() {
			return [...this.deviceTitles].sort((a, b) => {
				let a_name = a.attributes?.Anzeigename || a.name;
				let b_name = b.attributes?.Anzeigename || b.name;
				return a_name.localeCompare(b_name);
			});
		},
		deviceTitles() {
			return this.devices;
		}
	},
	methods: {
		selectDevice(device) {
			state[this.stateProperty] = device?.name || null;
			window.dispatchEvent(new CustomEvent('sidebar:open', { detail: device }));
			window.dispatchEvent(new CustomEvent('device-selected', { detail: device }));
		},
		setSelectedDevice(device) {
			state[this.stateProperty] = device?.name || null;
		},
		nfk(device) {
			const nfk = dataModel.nfk(device);
			if (isNaN(nfk)) return '–'
			return parseFloat(nfk.toFixed(0));
		},
		nfk_color(device) {
			return dataModel.get_nfk_color(this.nfk(device));
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

	.sideview
		flex-grow 0
		flex-shrink 0
		position relative
		padding-right 6px
		margin-right 32px
		margin-right 0
		flex-basis 300px !important
		background var(--windowbg)
		border-radius 0
	.sideview:after
		content ''
		position absolute
		right -7px
		top 6px
		bottom 6px
	.sideview .menuwindow-header
		margin-bottom 3px
	.sideview .menuwindow-header h3:before
		content ''
		height 28px
		width 28px
		margin-bottom -7px
		margin-right 5px 
		margin-left -10px 
		display inline-block
		opacity .85
		background url('/img/sensor.png') no-repeat center center / 100%
	.red
		color: red
	.menu-item
		display: flex
	.menu-title
		flex-grow 1
	.count
		font-weight normal
		display inline-block
		margin-left .5em
		opacity	.45
		font-size 11pt
	.menu-item
		display flex
		padding-right 2px
	.title
		text-overflow ellipsis
		overflow hidden
		white-space nowrap
		flex-grow 1
		font-weight: normal

</style> 
