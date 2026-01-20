<template>
	
	<div class="statusbarouter">

		<div class="statusbar">

			<FilterBar />
			<Interpretation />

		</div>
	</div>

</template>

<script>
import {state} from '@/state.js';
import FilterBar from '@/map/statusbar_filter.vue';
import Interpretation from '@/map/statusbar_interpretation.vue';

export default {
	name: 'StatusBar',
	components: {
		FilterBar,
		Interpretation
	},
	props: {
	},
	computed: {
	},
	methods: {
	},
	mounted() {
	}
};
</script>

<style lang="stylus" scoped>
.statusbarouter
	display flex
	align-items center
	justify-content center
	flex-grow 1
	flex-shrink 1
	min-width 0
	pointer-events none
.statusbar
	display flex
	flex-direction column
	align-items center
	justify-content center
	white-space nowrap
	min-width: 0
	flex-shrink 1
	flex-wrap wrap
	margin-top 8px
	font-size 9pt
	border-top none
	padding 0 6px
	filter drop-shadow(0 3px 2px #00000022)
	> *
		pointer-events all

</style>

<style lang="stylus">
.is-mobile .statusbarouter
	flex-basis 100vw

@media (max-width: 1250px) 
	.sidebaropen .topbar .statusbarouter
		position fixed
		top calc(var(--menubariconsize) + 8px)
		left 0px
		right 600px

@media (min-width: 1250px) 
	.wrapper:not(.sidebaropen) .topbar .statusbarouter
		position fixed
		left 0px
		right 0px

.is-mobile .statusbarouter
	.statusbar
		margin-top 0 

</style>