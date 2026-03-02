<template>

	<div class="windowbuttons">
		<div class="windowbuttonsinner">
			<div class="iconbutton close" v-on:click="closefunc()"></div>
			<div class="tooltipwrapper hastooltip" v-click-hide-tooltip>

				<div v-if="showswitch" class="iconbutton switchfullwindow" v-on:click="switchSidebarWidth()"></div>
				
				<div class="tooltip left">
					<template v-if="sidebarFullView">
						Schmale Ansicht
					</template>
					<template v-else>
						Weite Ansicht
					</template>
				</div>
			</div>

			<div class="iconbutton more" ref="toggleMore" v-on:click="openMore()"></div>
			<PopoverMenu ref="moreRef" :items="moreItems" />
		</div>
	</div>

</template>

<script>

import { state } from '@/state.js';
import dataStore from '@/dataStore.js';
import PopoverMenu from '@/ui/popovermenu.vue';

export default {
	components: { PopoverMenu },
	props: {
		device: {
			type: Object,
			required: true
		},
		close: {
			type: Function,
			required: false
		}
	},
	data() {
		return {
		}
	},
	computed: {
		sidebarFullView() {
			return state.sidebarFullView;
		},
		showswitch() {
			return this.$route.name != 'standort' && this.$route.name != 'embed' && this.$route.name != 'iframe' && this.$route.name != 'qrcode';
		},
		moreItems() {
			var items = [];
			const router = this.$router;
			const device = this.device;
			if (this.$route.name != 'embed') {
				items.push({ 
					type: 'action', 
					label: 'Einbetten', 
					action: (s) => {
						s.selectedDeviceEmbed = s.selectedDevice;
						router.push('/einbetten/');
				}});
			}
			if (this.$route.name != 'qrcode') {
				items.push({ 
					type: 'action', 
					label: 'QR Code', 
					action: (s) => {
						s.devicesMultiselect = [];
						s.devicesMultiselect.push(s.selectedDevice);
						router.push('/qrcode/');
				}});
			}
			if (this.$route.name != 'qrcode' && this.$route.name != 'embed') {
				items.push({ 
					type: 'action', 
					label: 'API', 
					action: (s) => {
						let apiurl = dataStore.getApiUrl(device.id, 'all', s.dataAggregation);
						window.open(apiurl, '_blank');
				}});
			}
			return items;
		},
	
	},
	methods: {
		closefunc() {
			if (this.close) {
				this.close();
			} else {
				this.$router.push(`/`);
				state.selectedDevice = null;
			}
		},
		switchSidebarWidth() {
			state.sidebarFullView = !state.sidebarFullView;
		},
		openMore() {
			const button = this.$refs.toggleMore;
			const rect = button.getBoundingClientRect();
			var position = {
				top: rect.top -4,
				right: 4,
			};
			this.$refs.moreRef.open(position);
		},
	},
	mounted() {
		const parent = this.$el.parentElement;
		if (!parent) return;

		const scrollContent = parent.querySelector('.scrollcontent');
		if (scrollContent) {
			this.$el.addEventListener('wheel', (event) => {
				scrollContent.scrollTop += event.deltaY;
			}, { passive: true });
		}
	},
};
</script>



<style lang="stylus" scoped>

	
</style>
