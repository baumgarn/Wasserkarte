<template>
	<div class="devices menuwindow" :class="{ open: isOpen, sideview }" tabindex="0" @keydown="onKeydown">
		<div class="menuwindow-header" @click="toggleOpen" v-if="!sideview">
			<h3>
				<span>Standorte</span>
				<span class="count" v-if="!sideview">{{ deviceItems.length }}</span>
			</h3>
		</div>
		<div class="menuwindow-content">

			<div
				v-for="(device, index) in deviceItems"
				:key="device.name"
				ref="items"
				class="menu-item"
				:class="{ selected: selectedIndex === index }"
				@click="selectDevice(device)"
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
			isOpen: false,
			selectedIndex: -1
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
			this.$nextTick(() => {
				const el = this.$refs.items?.[this.selectedIndex];
				el?.scrollIntoView({ block: 'nearest' });
			});
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
		onKeydown(e) {
			if (!this.deviceItems.length) return;

			switch (e.key) {
				case 'ArrowDown':
					e.preventDefault();
					this.selectedIndex =
						(this.selectedIndex + 1) % this.deviceItems.length;
					this.selectDevice(this.deviceItems[this.selectedIndex]);
					break;

				case 'ArrowUp':
					e.preventDefault();
					this.selectedIndex =
						(this.selectedIndex - 1 + this.deviceItems.length) %
						this.deviceItems.length;
					this.selectDevice(this.deviceItems[this.selectedIndex]);
					break;

				case 'Enter':
					e.preventDefault();
					if (this.selectedIndex >= 0) {
						this.selectDevice(this.deviceItems[this.selectedIndex]);
					}
					break;
			}
		},
	},
	mounted() {
		this.$el.focus();
	},
	beforeUnmount() {
	},
	watch: {
		selectedDevice: {
			immediate: true,
			handler(name) {
				this.selectedIndex = this.deviceItems.findIndex(
					d => d.name === name
				);
			}
		}
	},
}
</script>

<style scoped lang="stylus">
	.menuwindow.devices
		width 320px
	.menuwindow.devices:focus
		outline none
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
