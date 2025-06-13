<template>
	<div v-if="isOpen" class="popover-menu">
		<div class="items">
			<div
				v-for="item in items"
				:key="item.value"
				:class="['item', { active: isActive(item.value) }]"
				@click="selectItem(item.value)"
				v-html="item.label"
			>
			</div>
		</div>
	</div>
</template>

<script>
import { state } from '@/state.js';

export default {
	name: 'PopoverSelect',
	props: {
		items: {
			type: Array,
			required: true
		},
		position: {
			type: Object,
			default: () => ({ top: 0, right: 0 })
		},
		stateProperty: {
			type: String,
			required: true
		}
	},
	data() {
		return {
			isOpen: false
		};
	},
	methods: {
		open(position) {
			this.isOpen = true;
			this.position.bottom = position.bottom;
			this.position.right = position.right;
			window.setTimeout(() => {
				document.addEventListener('click', this.handleOutsideClick);
			}, 1);
		},
		close() {
			this.isOpen = false;
			document.removeEventListener('click', this.handleOutsideClick);
		},
		selectItem(value) {
			state[this.stateProperty] = value;
			// This component does NOT auto-close on select
		},
		isActive(value) {
			return state[this.stateProperty] === value;
		},
		handleOutsideClick(event) {
			if (!this.$el.contains(event.target)) {
				this.close();
			}
		}
	},
	beforeUnmount() {
		document.removeEventListener('click', this.handleOutsideClick);
	}
};
</script>

<style scoped lang="stylus">
.popover-menu
	position absolute
	right -4px
	bottom -12px
// 	background var(--uibrighter)
// 	background #fff
// 	border 2px solid #bcbcbc
// 	border-radius 8px
// 	box-shadow 0 2px 8px rgba(0, 0, 0, 0.1)
// 	z-index 1000
// 	overflow hidden

// .items
// 	display flex
// 	flex-direction column
// 	margin 0

// .item
// 	width 100%
// 	padding 5px 16px
// 	background none
// 	border none
// 	text-align right
// 	cursor pointer
// 	font-size 9pt
// 	font-weight normal
// 	white-space nowrap
// 	color #000000cc
// 	&:hover
// 		background: var(--activecolorgreybrighter)
// 	&.active
// 		color #000000
// 		background: var(--activecolorgrey)
// 		pointer-events none

</style>