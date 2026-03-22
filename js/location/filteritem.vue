<template>
	<div class="filteritem" :class="[{ active: includeActive, exclude: excludeActive, hover: isHover}, type, filterNameClass]"
	     @mouseleave="mouseLeave" @mouseenter="mouseEnter" @click="click">
		
		<div class="bg"></div>

		<template v-if="type=='statusbaritem'">
			
			<FilterIcon :obj :size="32" :exclude="excludeActive"/>
			
			<div class="name">
				<span v-if="excludeActive" class="notname">Nicht</span>
				{{ obj.name }}
			</div>

			<div class="iconbutton light close" @click="close">
			</div>
			
		</template>

		<template v-else-if="type=='statusbaritemsmall'">
			
			<FilterIcon :obj :size="24" :exclude="excludeActive"/>
			
			<div class="name">
				<span v-if="excludeActive" class="notname">Nicht</span>
				{{ obj.name }}
			</div>

			<div class="iconbutton light close" @click="close">
			</div>
			
		</template>
		
		<template v-else-if="type=='small'">

			<FilterIcon :obj :size="22"/>
			
			<div class="name">
				{{ obj.name }}
			</div>
			
		</template>
		
		<template v-else-if="type=='menuitem'">
			
			<FilterIcon :obj :size="22" :exclude="excludeActive" />
			
			<div class="name">
				{{ obj.name }}
			</div>

			<div class="count">
				{{ obj.count ? obj.count : 0 }}
			</div>

		</template>

		<template v-else-if="type=='popovermenuitem'" :exclude="excludeActive">

			<FilterIcon :obj :size="20"/>
			
			<div class="name">
				{{ obj.name }}
			</div>

			<!-- <div class="count">
				{{ obj.count ? obj.count : 0 }}
			</div>
			 -->
		</template>
		
		<template v-else-if="type=='table'">
			
			<FilterIcon :obj :size="22"/>
			
			<div class="name">
				{{ obj.name }}
			</div>
			
		</template>
		
		<template v-else-if="type=='tablecompact'">
			
			<FilterIcon :obj :size="22"/>
			
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

function toFilterNameClass(name) {
	if (!name) return null;

	return 'filter-' + String(name)
		.toLowerCase()
		.replace(/ä/g, 'ae')
		.replace(/ö/g, 'oe')
		.replace(/ü/g, 'ue')
		.replace(/ß/g, 'ss')
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');
}

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
			if (this.type == 'statusbaritem' || this.type == 'statusbaritemsmall') return;

			if (!this.includeActive) {
				if (! event.shiftKey) {
					state.includeFilter = [];
					state.excludeFilter = [];
				}
				state.includeFilter.push(this.obj);
			} else {
				state.includeFilter = state.includeFilter.filter(item => item.name !== this.obj.name);
				state.hoverFilter = null;
			}
			if (!this.excludeActive) {
				if (event.altKey) {
					if (! event.shiftKey) {
						state.includeFilter = [];
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
			event.stopPropagation();
		},
		close(event) {
			state.includeFilter = state.includeFilter.filter(item => item.name !== this.obj.name);
			state.excludeFilter = state.excludeFilter.filter(item => item.name !== this.obj.name);
		}
	},
	computed: {
		filterName() {
			return this.obj && this.obj.name ? this.obj.name : null;
		},
		filterNameClass() {
			return toFilterNameClass(this.filterName);
		},
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
		not() {
			return this.excludeActive;
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
	background #fff
	.bg
		position absolute
		inset 0
		z-index 0
	.name
		margin-right 8px
		margin-left 3px
		z-index 1
	.notname
		opacity .4
		margin-right .15em
		// color var(--warningred)
		text-transform uppercase
		// font-size 80%
		// display none
		// opacity .6
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

.filteritem.pill.exclude
	border 1px solid #00000011
	.bg
		display none

.name
	white-space nowrap

.filteritem.statusbaritem
	display inline-flex
	border 1px solid #00000011 !important
	box-shadow none
	height 38px
	cursor default
	padding-left 0
	.name
		font-size 10pt
		opacity .9
		margin-right 6px
	.bg
		display none
	.close
		opacity .25
		background-size 110%
		margin-right 5px
		&:hover
			opacity .8
	.filtericon
		position relative
		margin 2px
		margin-right 6px
		filter: drop-shadow(0 1px 1px rgba(0,0,0,.125));

.filteritem.statusbaritemsmall.filter-bookmarks .name
	margin-left -.05em
.filteritem.statusbaritem.filter-bookmarks .name
	margin-left -.2em


.filteritem.statusbaritemsmall
	height 30px
	border-radius none
	border none !important
	// border 1px solid #00000011
	cursor default
	box-shadow 0 2px 3px rgba(0,0,0,.14)
	padding-right 0
	padding-left 0
	outline 1px solid rgba(0,0,0,.075)
	.name 
		margin-right 4px
	.bg
		display none
	.filtericon
		width 24px
		height 24px
		position relative
		margin 3px
	.close
		opacity .25
		width 24px
		height 24px
		background-size 100%
		margin-right 2px
		&:hover
			opacity .8
		// filter: drop-shadow(0 1px 1px rgba(0,0,0,.125));

.filteritem.small
	display flex
	border none
	border-radius none
	font-weight normal
	justify-content flex-start
	border none
	height 20px
	flex-basis 50%
	flex-shrink 0
	flex-grow 0
	background #fff
	box-shadow none 
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
.filteritem.popovermenuitem
	display flex
	border none
	border-radius 0
	height auto
	height 28px
	box-shadow none
	background transparent
	font-size 9pt
	font-weight normal
	justify-content flex-start
	padding 4px 12px 4px 4px
	.count
		z-index 1
		opacity .45
		font-size 8.5pt
		margin-right -.4em
		display none
	.filtericon
		margin 0
		margin-left 0
		margin-right 1px
		flex-shrink 0
		filter: drop-shadow(0 1px 1px rgba(0,0,0,.125));
	.name
		flex-grow 1
	&.active
	&.active:hover
	&.exclude
	&.exclude:hover
		border none
		.bg
			background var(--activecolorgrey)
			opacity 1

.filteritem.popovermenuitem
	&:hover .bg
		background var(--activecolorgreybrighter)
		opacity .8
	&.active:hover .bg
		background var(--activecolorgrey)
		opacity 1

.filteritem.menuitem
	border-radius 14px
	&:hover 
		.bg
			background #fff
	.filtericon
		margin-left -1px

.filteritem.table
.filteritem.tablecompact
	width 100%
	height 100%
	border-radius unset
	box-shadow none
	border none
	margin 0
	font-weight normal
	font-size unset
	cursor pointer
	background transparent
	justify-content flex-start

.filteritem.table
	&.active
	&:hover
	&.active:hover
		border none
	.bg
		opacity 0
	&:hover .bg
		transition opacity linear .1s
		background var(--activecolorgreybrighter)
		opacity 1
	// &.active .bg
		// opacity 0
	.filtericon
		flex-basis 22px 
		flex-shrink 0

.filteritem.tablecompact
	margin 0
	padding 0
	justify-content center
	.filtericon
		filter brightness(1)
		transition filter linear .15s
	&:hover .filtericon
		filter brightness(.85)
	&.active
	&:hover
	&.active:hover
		border none
		.bg
			display none !important


</style>
