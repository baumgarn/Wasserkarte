<template>
	<div v-if="device" class="sidebar" :class="{ 'fullwidth': sidebarFullView }">
		
		<div class="windowrow">
			<MenuDevices :sideview="true" v-if="sidebarFullView"/>
			<div class="scrollcontent">
				<LocationView :device="device" />
			</div>
			<WindowButtons :device="device" :close="close"/>
		</div>
			
	</div>
</template>


<script>
import { state } from '@/state.js';
import dataStore from '@/datastore.js';
import LocationView from '@/location/_location.vue';
import WindowButtons from '@/ui/windowbuttons.vue';
import MenuDevices from '@/menu/menu_devices.vue';


export default {
	components: { WindowButtons, LocationView, MenuDevices},
	props: {
	},
	data() {
		return {
		}
	},
	computed: {
		device() {
			return dataStore.getDeviceByName(state.selectedDevice);
		},
		sidebarFullView() {
			return state.sidebarFullView;
		},
		selectedDevice() {
			return state.selectedDevice;
		},
	},
	methods: {
		close() {
			state.selectedDevice = null;
			state.sidebarFullView = false;
		},
	},
	mounted() {
	},
	watch: {
		device() {
			this.$nextTick(() => {
				if (this.$el && typeof this.$el.querySelector === 'function') {
					const scrollContent = this.$el.querySelector('.scrollcontent');
					if (scrollContent) {
					scrollContent.scrollTop = 0;
					}
				}
				if (state.selectedDevice != null) {
					state.menuOpen.info = false;
				}
			});

		}
	}
};
</script>



<style lang="stylus" scoped>
</style>
