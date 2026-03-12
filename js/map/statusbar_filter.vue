<template>

	<div class="filterbar" :class="{ intableview }" v-if="hasIncludeFilter || hasExcludeFilter">

			<template v-if="hasIncludeFilter">
				
				<div class="include filteritems">
					<template v-if="intableview">
						<FilterItem v-for="item in includeFilter" :obj="item" type="statusbaritemsmall"/>
					</template>
					<template v-else>
						<FilterItem v-for="item in includeFilter" :obj="item" type="statusbaritem"/>
					</template>
				</div>
				
			</template>
			
			<template v-if="hasExcludeFilter">
				
				<div class="label ohne">ohne</div>
				<div class="exclude filteritems">
					<template v-if="intableview">
						<FilterItem v-for="item in excludeFilter" :obj="item" type="statusbaritemsmall"/>
					</template>
					<template v-else>
						<FilterItem v-for="item in excludeFilter" :obj="item" type="statusbaritem"/>
					</template>

				</div>
				
			</template>
			
			<!-- <div v-if="!intableview" class="label before">
				{{filteredDevicesCount}}
				{{filteredDevicesCount == 1 ? 'Standort' : 'Standorte'}}&nbsp;
			</div> -->


		<div v-if="hasIncludeFilter || hasExcludeFilter" class="iconbutton close" @click="clearFilter"></div>

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
		intableview: Boolean,
		narrow: Boolean,
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
.filterbar
	height 42px
	position relative
	padding 0
	padding-right 20px
	min-width 250px
	font-size 9pt
	border-radius 21px
	background var(--menuinactivebg)
	background #fff
	display inline-flex
	align-items center
	justify-content flex-start
	margin 0

.filterbar.intableview
	min-width unset
	height 30px
	border 1px solid #00000020
	filter: drop-shadow(0.5px 1px 1px rgba(0,0,0,.075));
	padding-right 24px
	.label
		margin-left 0 
		margin-right 6px
	.iconbutton.close
		background-size 91%
		opacity .4
		top 2px
		right 2px
		&:hover
			opacity .8

.filteritems
	display inline-flex
	align-items center
	height 42px
	justify-content center
	position relative
	gap 0
	margin 0
.label.ohne
	margin 0 4px 0 10px
.label
	display inline-flex
	height 100%
	align-items center
	justify-content center
	margin 0
	opacity .55
.count
	font-weight bold
	display inline-block
	width 15px
	font-size 11pt
	vertical-align baseline
	margin-right .2em
	margin-top -.1em
	text-align right

.iconbutton.close
	position absolute
	right 8px
	top 9px
	background-size 95%
	opacity .5
	&:hover
		opacity .8
</style>

<style lang="stylus">

</style>