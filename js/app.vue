<template>

	<router-view v-slot="{ Component }">
		<keep-alive include="Home">
			<component :is="Component" />
		</keep-alive>
	</router-view>

</template>

<script>

import dataStore from './datastore.js';
import { fetchWMSCapabilities } from './wmsutils.js';
import { state } from './state.js';
import { config } from './config.js';
import { useRoute, useRouter } from 'vue-router';
import { nextTick } from 'vue';


export default {
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