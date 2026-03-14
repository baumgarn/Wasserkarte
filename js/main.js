import '../css/style.css';
import OpenLayersMap from "vue3-openlayers";
import App from '@/app.vue';
import router from '@/router.js';
import { createApp } from 'vue';
import { tooltipDirective } from '@/tooltip.js';

export const app = createApp(App);
app.use(OpenLayersMap);
app.use(router);
app.directive('tooltip', tooltipDirective);




app.directive('click-hide-tooltip', {
	mounted(el) {
		el.addEventListener('click', () => {
			const target = el.closest('.hastooltip') ?? el;
			target.classList.add('was-clicked');
			setTimeout(() => target.classList.remove('was-clicked'), 1000);
		});
	}
});

export const vm = app.mount('#app');
