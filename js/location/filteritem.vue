<template>
	<div class="filteritem" :class="[{ active: includeActive, exclude: excludeActive, hover: isHover}, type]"
	     @mouseleave="mouseLeave" @mouseenter="mouseEnter" @click="click">
		
		<div class="bg"></div>

		<template v-if="type=='statusbaritem'">
			
			<FilterIcon :obj :size="36"/>
			
			<div class="name">
				{{ obj.name }}
			</div>
			
		</template>
		
		<template v-else-if="type=='small'">

			<FilterIcon :obj :size="22"/>
			
			<div class="name">
				{{ obj.name }}
			</div>
			
		</template>

		<template v-else-if="type=='menuitem'">

			<FilterIcon :obj :size="22"/>
			
			<div class="name">
				{{ obj.name }}
			</div>

			<!-- <div class="count">
				{{ obj.count ? obj.count : 0 }}
			</div> -->
			
		</template>
		
		<template v-else-if="type=='pill'">
			
			<FilterIcon :obj :size="24"/>
			
			<div class="name">
				{{ obj.name }}
			</div>
			
		</template>

		
		
	</div>
</template>

<script>
import FilterIcon from '@/location/filtericon.vue';
import { state } from '@/state.js';

export default {
	props: {
		obj: {
			type: Object,
			required: true
		},
		type: {
			type: String,
			default: "pill"
		}
	},
	components: {
		FilterIcon
	},
	data() {
		return {
		};
	},
	watch: {
	},
	methods: {
		mouseEnter() {
			state.hoverFilter = this.obj;
		},
		mouseLeave() {
			state.hoverFilter = null;
		},
		click(event) {
			if (!this.includeActive) {
				if (! event.shiftKey) {
					state.includeFilter = [];
				}
				state.includeFilter.push(this.obj);
			} else {
				state.includeFilter = state.includeFilter.filter(item => item.name !== this.obj.name);
				state.hoverFilter = null;
			}
			if (!this.excludeActive) {
				if (event.altKey) {
					if (! event.shiftKey) {
						state.excludeFilter = [];
					}
					state.excludeFilter.push(this.obj);
					state.includeFilter = state.includeFilter.filter(item => item.name !== this.obj.name);
				}
			} else {
				state.excludeFilter = state.excludeFilter.filter(item => item.name !== this.obj.name);
				state.hoverFilter = null;
			}
			if (state.isMobile) {
				state.selectedDevice = null;
			}
		},
	},
	computed: {
		includeActive() {
			let included = false;
			state.includeFilter.forEach(item => {
				if (item.name == this.obj.name) {
					included = true;
				}
			});
			return included;
		},
		excludeActive() {
			let excluded = false;
			state.excludeFilter.forEach(element => {
				if (element.name == this.obj.name) {
					excluded = true;
				}
			});
			return excluded;
		},
		isActive() {
			return (state.activeFilter?.name == this.obj.name);
		},
		isHover() {
			return (state.hoverFilter?.name == this.obj.name);
		}
	}
};
</script>

<style lang="stylus" scoped>
.filteritem
	display inline-flex
	align-items center
	justify-content center
	border-radius 20px
	height 30px
	padding 0
	border 1px solid #00000011
	// border-bottom 1px solid #ddd
	box-shadow 0 1px 2px rgba(0,0,0,.1)
	padding-left 2px
	font-size 9pt
	font-weight bold
	cursor pointer
	position relative
	overflow hidden
	user-select none
	.bg
		position absolute
		inset 0
		z-index 0
	.name
		margin-right 8px
		margin-left 3px
		z-index 1
	.filtericon
		z-index 1
	&:hover .bg
		background var(--activecolorgreybrighter)
		opacity .6
	&.active
	&.active:hover
	&.exclude
	&.exclude:hover
		border 1px solid #00000022
		.bg
			background var(--activecolorgrey)
			opacity .7
	&.bordercolor
		padding 2px
	.innerborder
		display inline-flex
		align-items center
		border-radius 20px
		height 32px
		padding 0 10px
		border 2px solid var(--bordercolor)
.name
	white-space nowrap

.filteritem.statusbaritem
	display inline-flex
	border none
	box-shadow none
	pointer-events none
	height 42px
	padding-right 0
	padding-left 0
	// background red
	.bg
		display none
	.filtericon
		width 36px
		height 36px
		position relative
		margin 3px
		margin-right .2em
		filter: drop-shadow(0 1px 1px rgba(0,0,0,.125));

.filteritem.small
	display flex
	border none
	border-radius none
	box-shadow none
	font-weight normal
	justify-content flex-start
	border none
	height 20px
	flex-basis 50%
	flex-shrink 0
	flex-grow 0
	.name
		flex-grow 1
		flex-shrink 1
		font-size 8pt
		margin 0
		text-align left
		opacity .55
		overflow hidden
		text-overflow ellipsis
	.filtericon
		width 18px
		height 18px
		flex-basis 18px
		flex-shrink 0
		flex-grow 0
		margin 0
		margin-left 1px
		margin-right 2px
		filter: drop-shadow(0 1px 1px rgba(0,0,0,.125));
	&.active
	&.exclude
		.bg
			display none
.filteritem.menuitem
	border none
	border-radius 3px
	border-radius 14px
	height 28px
	box-shadow none
	font-size 11pt
	font-weight normal
	justify-content flex-start
	.filtericon
		width 22px
		height 22px
		margin 0
		margin-left 1px
		margin-right 2px
		filter: drop-shadow(0 1px 1px rgba(0,0,0,.125));
	.name
		flex-grow 1
	.count
		padding-right 8px
		font-size 9pt
		opacity .4
	&.active
	&.active:hover
	&.exclude
	&.exclude:hover
		border none
		.bg
			opacity 1
	// &:hover .bg
		// background transparent
	&:hover .bg
		background transparent
	&.active:hover .bg
		background var(--activecolorgrey)

</style>