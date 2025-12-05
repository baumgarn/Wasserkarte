<template>
	
	<div class="statusbarouter">

		<div class="statusbar" v-if="hasIncludeFilter || hasExcludeFilter">

			<div class="filterbar" v-if="hasIncludeFilter || hasExcludeFilter">

				<div class="label">{{filteredDevicesCount}} {{filteredDevicesCount == 1 ? 'Standort' : 'Standorte'}}&nbsp;</div>
				
				<template v-if="hasIncludeFilter">
					
					<div class="label">mit</div>
					<div class="include filteritems">
						<FilterItem v-for="item in includeFilter" :obj="item"/>
					</div>

				</template>
				
				<template v-if="hasExcludeFilter">

					<div class="label">ohne</div>
					<div class="exclude filteritems">
						<FilterItem v-for="item in excludeFilter" :obj="item"/>
					</div>

				</template>

			</div>
		</div>
	</div>

</template>

<script>
import {state} from '@/state.js';
import FilterItem from '@/location/filteritem.vue';

export default {
	name: 'StatusBar',
	components: {
		FilterItem
	},
	props: {
	},
	computed: {
		includeFilter() {
			return state.includeFilter;
		},
		excludeFilter() {
			return state.excludeFilter;
		},
		hasIncludeFilter() {
			return (state.includeFilter.length > 0);
		},
		hasExcludeFilter() {
			return (state.excludeFilter.length > 0);
		},
		filteredDevices() {
			return state.filteredDevices
		},
		filteredDevicesCount() {
			return state.filteredDevices.length;
		},
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
	border 1px solid red
	flex-grow 1
	flex-shrink 1
	min-width 0
	overflow hidden
.statusbar
	display flex
	align-items center
	justify-content center
	height var(--menubariconsize)
	padding 0 24px
	white-space nowrap
	min-width: 0
	flex-shrink 1
	flex-wrap wrap
	background #eeeeeeaa
	background #ddd
	box-shadow 0 0 2px 2px #00000022
	font-size 9pt
	border-top none
	overflow hidden
.filterbar
	display inline-flex
	align-items center
	margin 0 .2em
.filteritems
	display inline-flex
	align-items center
	// flex-wrap wrap
	gap 2px
	margin 0 4px
.label.red
	color var(--warningred)

</style>

<style lang="stylus">
.is-mobile .statusbarouter
	flex-basis 100vw

@media (max-width: 1200px) 
	.sidebaropen .topbar .statusbarouter
		position fixed
		top var(--menubariconsize)
		left 0px
		right 600px

</style>