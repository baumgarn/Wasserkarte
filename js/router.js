import { createRouter, createWebHistory } from 'vue-router'
import Home from './views/home.vue'
import LocationEmbed from './views/embed.vue'
import LocationSingle from './views/single.vue'
import LocationQrCode from './views/qrcode_generate.vue'
import LocationIFrame from './views/iframe.vue'

export default createRouter({
	history: createWebHistory(),
	routes: [
		{ path: '/', name: 'home', component: Home },
		{ path: '/standort/:name', name: 'standort', component: LocationSingle, props: true },
		{ path: '/einbetten/', name: 'embed', component: LocationEmbed, props: true },
		{ path: '/qrcode/', name: 'qrcode', component: LocationQrCode, props: true },
		{ path: '/iframe/:name', name: 'iframe', component: LocationIFrame, props: true },
	]
})