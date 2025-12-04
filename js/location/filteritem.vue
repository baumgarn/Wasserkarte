<template>
	<div class="filteritem" :class="{ active: includeActive, exclude: excludeActive, hover: isHover }"
	     @mouseleave="mouseLeave" @mouseenter="mouseEnter" @click="click">
		
		<div class="bg"></div>

		<FilterIcon :obj :size="30"/>
		
		<div class="name">
			{{ obj.name }}
		</div>
		
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
	border 1px solid #ddd
	border-radius 20px
	height 36px
	padding 0
	// background #fff
	box-shadow 0 2px 1px rgba(0,0,0,.05)
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
	&.hover .bg
		background var(--activecolorgreybrighter)
	&.active
	&.active.hover
		border 1px solid #bbb
		.bg
			background var(--activecolorgrey)
	&.exclude
	&.exclude.hover
		border 1px solid #bbb
		.bg
			background var(--activecolorgrey)
			// background #ea4545ee
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
</style>