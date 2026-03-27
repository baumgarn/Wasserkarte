<template>

	<div class="legend" >

		<div  class="gradientframe">

			<!-- <div class="split"> -->
				
				<!-- <div class="startlabel">
					<span class="value">0<span class="smaller">nFK</span>%</span>
				</div>
				
				<div class="endlabel">
					<span class="value">120<span class="smaller">nFK</span>%</span>
				</div> -->

			<!-- </div> -->
			
			<div class="split">
				
				<div class="startlabel">
					<span class="label">Trocken</span> 
				</div>
				
				<div class="endlabel">
					<span class="label">Nass</span>
				</div>
											
			</div>
			
			<ColorSchemeGradient class="gradient" :colorScheme="dataModel.get_color_scheme('nfk')" :width="300" :height="18"/>

			
			
		</div>

	</div>

	
	</template>

	<script>
	import { state } from '@/state.js';
	import { dataModel } from '@/datamodel.js';
	import FauxMarker from '@/map/legend_faux_marker.vue';
	import ColorSchemeGradient from '@/menu/colorscheme_gradient.vue';

	export default {
		setup() {
			return {state, dataModel}
		},
		components: { 
			ColorSchemeGradient, FauxMarker },
	};
	</script>

<style lang="stylus" scoped>

	.legend
		width 100%
		font-size 12px
		position relative
		display flex
		flex-direction column
		padding 0
		margin 12px 0 14px
		gap 0
	.gradientframe
		display flex
		flex-direction column
		width 100%
		box-shadow 0 2px 0px #00000022
		.split
			display flex
		.startlabel
		.endlabel
			flex-grow 1
		.startlabel
			text-align left
		.endlabel
			text-align right
		.label
			margin 0 6px
			margin 0 0
			font-size 10px
			opacity .8
		.value
			font-size 10px
			opacity .8
			display inline-block
			font-weight normal
			.smaller
				display inline-block
				margin 0 2px
				font-size 9px
		.gradient
			position relative
			width 100% !important
			margin -1px 0 0
			pointer-events none
	

</style>
