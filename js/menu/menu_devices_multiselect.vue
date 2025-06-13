<template>
    <div class="devices menuwindow" :class="{sideview }">
       
		 <div class="menuwindow-header">
			<div class="info">
				Mehrere Standorte mit gedrückter<br>Shift oder Ctrl Taste auswählen
			</div>
        </div>

        <div class="menuwindow-content">
            <div
                v-for="(device, index) in deviceItems"
                :key="device.name"
                :class="[
                    'menu-item', 
                    device.name.replace(/\s+/g, '_'), 
                    { 
                        selected: isDeviceSelected(device),
                        hasselectedbelow: isSelectedBelow(index)
                    }
                ]"
                @click="handleDeviceClick(device, index, $event)"
				@contextmenu="preventContextMenu(device, index, $event)"
				 >
                <span class="title">{{ device.attributes?.Anzeigename || device.name }}</span>
            </div>
        </div>

    </div>
</template>

<script>
import { state } from '../state.js';
import dataStore from '../datastore.js';

export default {
    name: 'GeraeteMenu',
    props: {
        sideview: {
            type: Boolean,
            default: false
        }
    },
    data() {
        return {
            isOpen: false,
            lastSelectedIndex: null // Track the last selected device index for Shift key selection
        };
    },
    computed: {
        devices() {
            return state.devices;
        },
        selectedDevices() {
            return state.devicesMultiselect || [];
        },
        deviceItems() {
            return [...this.deviceTitles].sort((a, b) => {
                let a_name = a.attributes?.Anzeigename || a.name;
                let b_name = b.attributes?.Anzeigename || b.name;
                return a_name.localeCompare(b_name);
            });
        },
        faultyDevices() {
            let faultyDevices = [];
            for (const device of this.deviceItems) {
                if (this.timeSinceLastTelemetry(device.id.id) >= 24) {
                    faultyDevices.push(device);
                }
            }
            return faultyDevices;
        },
        deviceTitles() {
            return this.devices;
        }
    },
    methods: {
        isDeviceSelected(device) {
            return this.selectedDevices.includes(device.name);
        },
        isSelectedBelow(index) {
            // Check if the current device is selected
            if (!this.isDeviceSelected(this.deviceItems[index])) {
                return false;
            }

            // Check if the device directly below is selected
            const nextDevice = this.deviceItems[index + 1];
            return nextDevice ? this.isDeviceSelected(nextDevice) : false;
        },
		preventContextMenu(device, index, event) {
			event.preventDefault();
			this.handleDeviceClick(device, index, event)
			return false
		},
        handleDeviceClick(device, index, event) {
            if (event.ctrlKey || event.metaKey) {
                // Toggle selection with Ctrl/Command key
                if (this.isDeviceSelected(device)) {
                    // Prevent unselecting the last remaining device
                    if (state.devicesMultiselect.length > 1) {
                        state.devicesMultiselect = state.devicesMultiselect.filter(
                            (selected) => selected !== device.name
                        );
                    }
                } else {
                    state.devicesMultiselect.push(device.name);
                }
            } else if (event.shiftKey && this.lastSelectedIndex !== null) {
                // Select range with Shift key
                const start = Math.min(this.lastSelectedIndex, index);
                const end = Math.max(this.lastSelectedIndex, index);
                const range = this.deviceItems.slice(start, end + 1).map((d) => d.name);

                state.devicesMultiselect = Array.from(
                    new Set([...state.devicesMultiselect, ...range])
                );
            } else {
                // Single selection (no modifier key)
                state.devicesMultiselect = [device.name];
            }

            // Sort the selected devices array to match the menu order
            state.devicesMultiselect = this.deviceItems
                .map((d) => d.name)
                .filter((name) => state.devicesMultiselect.includes(name));

            // Update the last selected index
            this.lastSelectedIndex = index;
        },
        selectDevice(device) {
            state.selectedDevice = device?.name || null;
            window.dispatchEvent(new CustomEvent('sidebar:open', { detail: device }));
            window.dispatchEvent(new CustomEvent('device-selected', { detail: device }));
        },
        timeSinceLastTelemetry(deviceId) {
            return dataStore.timeSinceLastTelemetry(deviceId);
        }
    },
    mounted() {
    },
    beforeUnmount() {
    }
};
</script>


<style scoped lang="stylus">
	// .menuwindow.devices
	// 	max-width 275px
	.menuwindow-header
		flex-grow 0
		flex-shrink 0
		height auto
		padding 0 10px
		.info
			font-size 12px
			margin .8em 0
			// opacity .6
	.sideview
		flex-grow 0
		flex-shrink 0
		position relative
		padding-right 6px
		margin-right 6px
		margin-right 0
		border-radius 0
		flex-basis 300px !important
	// .sideview:after
	// 	content ''
	// 	position absolute
	// 	right -7px
	// 	top 6px
	// 	bottom 6px
	// 	border-left var(--separatorline)
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
	.warning
		color: red
	.menu-item
		display: flex
		user-select none
	.menu-title
		flex-grow 1
	.menu-warning	
		font-weight normal
		color #ff0000
		font-size 11pt
		opacity	.9
		border-radius 1em
		&:after
			content: ''
			display inline-block
			width 1em
			height 1em
			background url('/img/error.png') no-repeat center center / 100%
			position relative
			top 0.1em
			margin -.05em 0

			margin-right 0.2em
			margin-left 0.2em
	.title
		text-overflow ellipsis
		overflow hidden
		white-space nowrap
		font-weight: normal
	.warning span
		color red !important
		opacity .8
	.count
		font-weight normal
		display inline-block
		margin-left .5em
		opacity	.45
		font-size 11pt
</style>
