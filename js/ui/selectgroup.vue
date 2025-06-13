<template>
	<div class="selectgroup">
		<div 
			v-for="item in items" 
			:key="item.label" 
			:class="['item', { active: item.value === modelValue }]"
			@click="select(item.value)"
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
			required: true,
		},
		modelValue: {
			required: true,
		},
	},
	emits: ['update:modelValue'],
	methods: {
		select(value) {
			this.$emit('update:modelValue', value);
		},
	},
};

</script>

<style scoped lang="stylus">

	
	
	.selectgroup
		display: flex
		margin: 0
		border-radius 1em
		overflow hidden
		background var(--uibrighter)
		background #b9d0eb88
		background #00000019
		filter drop-shadow(0 2px 2px #00000022)
		.item
			padding: 5px 12px
			border: none
			flex-basis 0
			flex-grow 1
			flex-shrink 0
			white-space nowrap
			font-size 10pt
			cursor: pointer
			text-align: center
			&.active	
				color #000000
				background: var(--activecolordarker)
		.item:first-child
			padding-left 16px
		.item:last-child
			padding-right 16px
		.item + .item
			border-left: var(--buttonborder)


	@media (max-width: 600px)
		.itemrow
			gap .2em
			flex-direction column
		.selectgroup
			.item
				padding: .3em .5em
</style>