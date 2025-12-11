<template>
	
	<div class="statusbarouter">

		<div class="statusbar" v-if="hasIncludeFilter || hasExcludeFilter">

			<div class="filterbar" v-if="hasIncludeFilter || hasExcludeFilter">

				<div class="label before"><span class="count">{{filteredDevicesCount}}</span> 
					{{filteredDevicesCount == 1 ? 'Standort' : 'Standorte'}}&nbsp;
				</div>
					
				
				<template v-if="hasIncludeFilter">
					
					<!-- <div class="label">mit</div> -->
					<div class="include filteritems">
						<FilterItem v-for="item in includeFilter" :obj="item" type="statusbaritem"/>
					</div>

				</template>
				
				<template v-if="hasExcludeFilter">

					<div class="label not">ohne</div>
					<div class="exclude filteritems">
						<FilterItem v-for="item in excludeFilter" :obj="item" type="statusbaritem"/>
					</div>

				</template>

				<div class="iconbutton close" @click="clearFilter"></div>

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
	methods: {
		clearFilter() {
			state.includeFilter = [];
			state.excludeFilter = [];
		}
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
	// border 1px solid red
	flex-grow 1
	flex-shrink 1
	min-width 0
.statusbar
	display flex
	align-items center
	justify-content center
	white-space nowrap
	min-width: 0
	flex-shrink 1
	flex-wrap wrap
	background #ddd
	background #ddd
	box-shadow 0 2px 1px rgba(0,0,0,.1)
	font-size 9pt
	border-top none
	overflow hidden
	background #44444444
	padding 0 6px
	// border-bottom-left-radius 12px
	// border-bottom-right-radius 12px
	// border-top-left-radius 8px
	// border-top-right-radius 8px
	// min-width 350px
	// margin-top 4px
	// border-radius 22px
	// border-radius 8px
.filterbar
	height calc( var(--menubariconsize) + 1px)
	border-top 1px solid #bbb
	position relative
	border-bottom 1px solid #ccc
	padding 0
	padding-right 24px
	padding-left 12px
	min-width 320px
	background var(--menuinactivebg)
	// background var(--activecolorgrey)
	display inline-flex
	align-items center
	// border-bottom-left-radius 12px
	// border-bottom-right-radius 12px
	// border-top-left-radius 8px
	// border-top-right-radius 8px
	justify-content flex-start
	margin 0 .2em
.filteritems
	display inline-flex
	align-items center
	// flex-wrap wrap
	gap 2px
	margin 0 4px
// .label.not
// 	border-bottom 1px solid #00000033
// 	padding-bottom 0
// 	margin-bottom -1px
.label
	display inline-block
	height 24px
	margin 0 .1em
	line-height 24px
.count
	font-weight bold
	display inline-block
	width 15px
	font-size 11pt
	vertical-align baseline
	margin-right .2em
	margin-top -.1em
	text-align right
.label.before
	min-width 85px
	// background red
	// font-size 140%
	// display inline-block
	// margin-top -4pxö

.iconbutton.close
	position absolute
	right 4px
	top 8px
	background-size 110%
	opacity .4
	&:hover
		opacity .8
</style>

<style lang="stylus">
.is-mobile .statusbarouter
	flex-basis 100vw

@media (max-width: 1200px) 
	.sidebaropen .topbar .statusbarouter
		position fixed
		top calc(var(--menubariconsize) + 8px)
		left 0px
		right 600px

</style>