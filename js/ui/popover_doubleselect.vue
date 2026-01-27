<template>

	<div v-if="isOpen" class="popover-menu doubleselect">
		
		<div class="items">
			<div
				v-for="item in items"
				:key="'first-' + item.value"
				:class="['item', { active: isActive(item.value, stateProperty) }]"
				@click="selectItem(item.value, stateProperty)"
				v-html="item.label"
			></div>
		</div>

		<div v-if="secondItems.length" class="items second-group">
			<div
				v-for="item in secondItems"
				:key="'second-' + item.value"
				:class="['item', { active: isActive(item.value, secondStateProperty) }]"
				@click="selectItem(item.value, secondStateProperty)"
				v-html="item.label"
			></div>
		</div>
		
	</div>

</template>

<script>
import { state } from '@/state.js';

export default {
	name: 'PopoverDaterange',
	props: {
		position: {
			type: Object,
			default: () => ({ top: 0, right: 0 })
		},
		items: {
			type: Array,
			required: true
		},
		secondItems: {
			type: Array,
			required: true
		},
		stateProperty: {
			type: String,
			required: true
		},
		secondStateProperty: {
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
		selectItem(value, property) {
			state[property] = value;
		},
		isActive(value, property) {
			return state[property] === value;
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

<style lang="stylus" scoped>

.popover-menu
	position absolute
	right -4px
	bottom -12px

</style>