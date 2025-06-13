import '../css/style.css';
import OpenLayersMap from "vue3-openlayers";
import App from '@/app.vue';
import router from '@/router.js';
import { createApp } from 'vue';

export const app = createApp(App);
app.use(OpenLayersMap);
app.use(router);
export const vm = app.mount('#app');
