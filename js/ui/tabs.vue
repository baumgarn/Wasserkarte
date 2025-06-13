<template>
	<div class="selectgroup">
		<div 
		v-for="item in items" 
		:key="item.label" 
		:class="[
			'item',
			{ 
				active: isActive(item.value),
			}
			]"
		@click="click(item.value)"
		>
		{{ item.label }}
		</div>
	</div>
</template>

<script>

import { click } from 'ol/events/condition';
import { state } from '@/state.js';

export default {
	name: 'SelectGroup',
	props: {
		items: {
			type: Array,
			required: true
		},
		stateProperty: {
			type: String,
		}
	},
	emits: ['select'],
	methods: {
		click(value) {
			state[this.stateProperty] = value;
		},
		isActive(value) {
			return state[this.stateProperty] === value;
		},
	},
	mounted() {
	},
	beforeUnmount() {
	},
}
</script>

<style scoped lang="stylus">
	
	.selectgroup
		display: flex
		margin: 0
		.item
			padding: 0 18px 0
			border-bottom none
			flex-basis 0
			flex-grow 1
			flex-shrink 0
			font-size 10px
			cursor: pointer
			text-align: center
			font-weight bold
			text-transform uppercase
			letter-spacing .05em
			color #00000099
			position relative
			display flex
			align-items center
			&:hover
				background: var(--activecolorgreybrighter)
			&.active	
				color #000000ee
				background: var(--activecolorgrey)
			&.active	
				&:after
					content ''
					position absolute
					left 0	
					right 0
					bottom 0
			&.active:after
				border-bottom 2px solid #00000077
		.item:last-child
			margin-right 0

	@media (max-width: 475px)
		.selectgroup
			.item
				font-size 10px
				padding 0 8px 0
	@media (max-width: 400px)
		.selectgroup
			.item
				font-size 9px
	// 	.itemrow
	// 		gap .2em
	// 		flex-direction column
	// 	.selectgroup
	// 		.item
	// 			padding: .3em .5em
</style>