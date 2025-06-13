<template>
	
	<div class="infoarea">
		<div class="arrow"></div>
		<div class="text">{{ text }}</div>
	</div>

</template>

<script>
import { fromLonLat } from 'ol/proj.js';

export default {
	name: 'MapInfoArrow',
	props: {
		device: {
			type: Object,
			required: true
		},
		text: {
			type: String,
			required: true
		}
	},
	computed: {
		hasLatLong() {
			return this.device?.attributes?.latitude && this.device?.attributes?.longitude;
		},
		position() {
			return fromLonLat([this.device.attributes.longitude, this.device.attributes.latitude]);
		},
	},
	mounted() {
		this.$nextTick(() => {
			setTimeout(() => {
				const overlayEl = this.$refs.overlay?.$el;
				console.log('info ol-overlay $el:', overlayEl);
				
				const overlayContainer = overlayEl?.closest('.ol-overlay-container');
				console.log('info ol-overlay container:', overlayContainer);

				if (overlayContainer) {
					overlayContainer.style.zIndex = '9999';
				}
			}, 100);
		}
	);
}
};
</script>

<style lang="stylus" scoped>

*
	--bgcolor: #00000055

.map-info-arrow-overlay
	pointer-events none

.info-arrow-container
	display flex
	flex-direction column
	align-items center
	color #333
	max-width 210px
	margin-top 18px
	text-align center
	transform: translateX(-50%);
	filter: drop-shadow(0 2px 1px rgba(0,0,0,.2));
	pointer-events none

.arrow
	width 0
	height 0
	border-left 8px solid transparent
	border-right 8px solid transparent
	border-bottom 12px solid var(--bgcolor)
	filter: drop-shadow(0 -3px 1px rgba(0,0,0,.1));

.text
	color #fff
	background var(--bgcolor)
	font-size 14px
	padding 4px 6px
	line-height 1.4
	border-radius 8px


</style>