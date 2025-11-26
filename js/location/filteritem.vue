<template>
	<div class="filteritem" :class="{ hasicon: icon }">
		
		<div v-if="icon" class="icon" :style="{ backgroundImage: `url(${icon})` }"></div>

		<div class="name">
			{{ obj.name }}
		</div>
	</div>
</template>

<script>
import { IconFactory } from '@/location/iconfactory.js';

export default {
	props: {
		obj: {
			type: Object,
			required: true
		}
	},
	computed: {
		icon() {
			if (!this.obj) return null;

			// If obj has an image filename, return its path
			if (this.obj.img) {
				return '/img/' + this.obj.img; // Can be a relative path or full URL
			}

			// If obj has a soilIcon descriptor, generate it via IconFactory
			if (this.obj.soilIcon) {
				// soilIcon should be an array of instructions like [['sand',1], ['lehm',0.3]]
				return IconFactory.getSoilIcon(this.obj.soilIcon);
			}

			return null;
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
	height 40px
	padding 0 8px
	background #fff
	box-shadow 0 2px 1px rgba(0,0,0,.05)
	&.hasicon
		padding-left 2px
		padding-right 8px
.icon
	width 34px
	height 34px
	background-size contain
	background-position center
	background-repeat no-repeat
	margin-right 3px

.name
	white-space nowrap
</style>