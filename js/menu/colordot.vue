<template>
	<div class="colordotcontainer">

		<div class="colordot" :style="'background-color:' + 	nfk_color">
		</div>

	</div>
</template>

<script>
import { state } from '@/state.js'
import { dataModel } from '@/datamodel.js'
import dataStore from '@/datastore.js';

export default {
	name: 'ColorDot',
	setup() {
		return { state, dataModel };
	},
	props: {
		device: Object,
	},
	data() {
		return {
		};
	},
	mounted() {
	},
	computed: {
		nfk() {
			const nfk = dataModel.nfk(this.device);
			if (isNaN(nfk)) return '–'
			return parseFloat(nfk.toFixed(0));
		},
		nfk_color() {
			return dataModel.get_nfk_color(this.nfk);
		},
	},
	watch: {
	},
	methods: {
	}
};
</script>

<style lang="stylus" scoped>
	.colordotcontainer
		display inline-flex
		align-items center
		justify-content center
		width 30px
		position relative
		margin-left -7px
		margin-top -4px
		margin-right 0
		margin-bottom -4px
		flex-shrink 0
		flex-grow 0
	.colordot
		position absolute
		// left 0 
		// top -2px
		width 16px
		height 16px
		// border 1px solid #00000011
		border-radius 50%
		background #e4e4e4

	// .warning
	// .nolocationattributes
	// .noSoilAttributes
	// 	display block
	// 	position absolute
	// 	width 18px
	// 	height 18px
	// 	background url('/img/warning.png') no-repeat center center / 100%
	// 	background-color #aaa
	// 	margin 0
	// .warning
	// 	background url('/img/warning.png') no-repeat center center / 100%
	// .nolocationattributes
	// 	background url('/img/nolocblack.png') no-repeat center center / 100%
</style>