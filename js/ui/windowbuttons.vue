<template>

	<div class="windowbuttons">
		<div class="windowbuttonsinner">
			<div class="iconbutton close" v-on:click="closefunc()"></div>
			<!-- <div v-if="showswitch" class="iconbutton switchfullwindow" v-on:click="switchSidebarWidth()"></div> -->
			<div class="iconbutton more" ref="toggleMore" v-on:click="openMore()"></div>
			<PopoverMenu ref="moreRef" :items="moreItems" @select="handleMoreMenu" />
		</div>
	</div>

</template>

<script>

import { state } from '@/state.js';
import PopoverMenu from '@/ui/popover_menu.vue';

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
		showswitch() {
			return this.$route.name != 'standort' && this.$route.name != 'embed' && this.$route.name != 'iframe' && this.$route.name != 'qrcode';
		},
		moreItems() {
			var items = [];
			if (this.$route.name != 'embed') {
				items.push({ label: 'Einbetten', value: 'embed' },);
			}
			if (this.$route.name != 'qrcode') {
				items.push({ label: 'QR Code', value: 'qrcode' },);
			}
			// if (this.$route.name != 'standort') {
			// 	items.push({ label: 'Einzelansicht', value: 'standort' });
			// }
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
		handleMoreMenu(item) {
			if (item.value === 'standort') {
				this.$router.push(`/standort/${this.device.name}`);
			} else if (item.value === 'embed') {
				state.selectedDeviceEmbed = state.selectedDevice;
				this.$router.push(`/einbetten/`);
			} else if (item.value === 'iframe') {
				this.$router.push(`/iframe/${this.device.name}`);
			} else if (item.value === 'qrcode') {
				state.devicesMultiselect = [];
				state.devicesMultiselect.push(state.selectedDevice);
				this.$router.push(`/qrcode/`);
			}
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
