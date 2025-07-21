<template>
	<div class="colorscheme" :style="{ height: height + 'px' }">
		<img v-if="dataUrl" :src="dataUrl" :height="height" alt="Color Scheme" />
	</div>
	</template>
	
	<script>
	import { ref, watch, onMounted } from 'vue';
	import { dataModel } from '@/datamodel.js';
	
	export default {
	name: 'ColorScheme',
	props: {
		colorScheme: {
			type: Array,
			required: true,
		},
		width: {
			type: Number,
			required: true,
		},
		height: {
			type: Number,
			required: true,
		},
	},
	setup(props) {
		const dataUrl = ref(null);
	
		const drawToDataUrl = () => {
		const canvas = document.createElement('canvas');
		canvas.width = props.width;
		canvas.height = props.height;
		const ctx = canvas.getContext('2d');
	
		const padleft = 5;
	
		for (let x = 0; x <= canvas.width; x++) {
			let nfk = (x / canvas.width) * (120 + padleft) - padleft;
			let c = dataModel.get_color(nfk, props.colorScheme);
			ctx.fillStyle = c;
			ctx.fillRect(x, 0, 1, canvas.height);
		}
	
		dataUrl.value = canvas.toDataURL();
		};
	
		onMounted(drawToDataUrl);
	
		watch(
		() => [props.colorScheme, props.width, props.height],
		() => {
			drawToDataUrl();
		},
		{ deep: true }
		);
	
		return { dataUrl };
	},
	};
	</script>
	
<style scoped lang="stylus">
	.colorscheme
		cursor: pointer;
		position: relative;
		img
			width 100%
	
		
</style>