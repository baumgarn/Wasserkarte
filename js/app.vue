<template>

	<router-view v-slot="{ Component }">
		<keep-alive include="Home">
			<component :is="Component" />
		</keep-alive>
	</router-view>
	<AppTooltip />

</template>

<script>

import dataStore from './datastore.js';
import { fetchWMSCapabilities } from './wmsutils.js';
import { closeAllMenuWindowsAndSidebar, state } from './state.js';
import { config } from './config.js';
import { useRoute, useRouter } from 'vue-router';
import { onBeforeUnmount } from 'vue';
import AppTooltip from '@/ui/app_tooltip.vue';


export default {
	components: {
		AppTooltip,
	},
	setup() {
		const route = useRoute();
		const router = useRouter();


		dataStore.fetchDevicesData();
		fetchWMSCapabilities(config.wmsUrls);

		const handleResize = () => {
			state.windowWidth = window.innerWidth;
			state.windowHeight = window.innerHeight;
			if (state.windowWidth <= 767) {
				document.body.classList.add("is-mobile");
				state.isMobile = true;
			} else {
				document.body.classList.remove("is-mobile");
				state.isMobile = false;
			}
		};

		window.addEventListener('resize', handleResize);
		handleResize();

		const handleGlobalKeydown = (event) => {
			if (event.key !== 'Escape') return;
			if (route.name === 'embed' || route.name === 'qrcode') {
				router.push('/');
				return;
			}
			closeAllMenuWindowsAndSidebar();
		};

		window.addEventListener('keydown', handleGlobalKeydown);
		onBeforeUnmount(() => {
			window.removeEventListener('resize', handleResize);
			window.removeEventListener('keydown', handleGlobalKeydown);
		});

		if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
			state.localhost = true;
		} else {
			state.localhost = false;
		}
		
		router.isReady().then(() => {
			if (route.name === 'home' && state.windowWidth > 1000 && state.showInfoOnStart) {
				state.menuOpen.info = true;
			}
		});
	},
	computed: {
		devices() {
			return state.devices;
		},
	},
};
</script>

<style lang="stylus" scoped>
</style>
