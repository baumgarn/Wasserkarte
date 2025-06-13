<template>
	<div ref="container" class="measured-container">
		<slot :width="width" :height="height" />
	</div>
</template>

<script>
import { ref, onMounted, onBeforeUnmount } from 'vue';

export default {
	name: 'MeasuredContainer',
	setup() {
		const container = ref(null);
		const width = ref(0);
		const height = ref(0);

		let resizeObserver = null;

		const measure = () => {
			if (container.value) {
				const rect = container.value.getBoundingClientRect();
				width.value = rect.width;
				height.value = rect.height;
			}
		};

		onMounted(() => {
			measure();
			resizeObserver = new ResizeObserver(measure);
			if (container.value) {
				resizeObserver.observe(container.value);
			}
		});

		onBeforeUnmount(() => {
			if (resizeObserver && container.value) {
				resizeObserver.unobserve(container.value);
				resizeObserver.disconnect();
			}
		});

		return {
			container,
			width,
			height,
		};
	},
};
</script>

<style scoped>
.measured-container {
	width: 100%;
	height: 100%;
}
</style>