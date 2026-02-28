<template>
	<div v-if="isOpen" class="popover-menu" @click.stop>

		<template v-for="(item, index) in items" :key="index">

			<div
				v-if="item.type === 'select'"
				class="item"
				:class="{ active: isActive(item) }"
				@click="select(item)"
				v-html="item.label"
			/>

			<div
				v-else-if="item.type === 'action'"
				class="item action"
				@click="runAction(item)"
				v-html="item.label"
			/>
		
			<div
				v-else-if="item.type === 'header'"
				class="item header"
				v-html="item.label"
			/>

			<div
				v-else-if="item.type === 'boolean'"
				class="item boolean"
				:class="{ active: state[item.stateProp] }"
				@click="toggleBoolean(item)"
			>
				<div class="icon"></div>
				<span v-html="item.label" />
			</div>

			<div
				v-else-if="item.type === 'divider'"
				class="divider"
			/>


		</template>

	</div>
</template>

<script>

import { state } from '@/state.js';
import { nextTick } from 'vue'


export default {
	name: 'PopoverMenu',

	props: {
		items: {
			type: Array,
			required: true
		}
	},

	data() {
		return {
			isOpen: false,
			state
		};
	},

	methods: {
		open(position) {
			if (this.isOpen) {
				this.close();
				return;
			}
			this.isOpen = true;
			state.popupMenuOpen = true;
			nextTick(() => {

				if (position) {
					if (position.top) { this.$el.style.top = position.top + 'px';}
					if (position.bottom) { this.$el.style.bottom = position.bottom + 'px';}
					if (position.right) { this.$el.style.right = position.right + 'px';}
					if (position.left) { this.$el.style.left = position.left + 'px';}
					if (position.zIndex) {this.$el.style.zIndex = position.zIndex;}
				}

				setTimeout(() => {
					document.addEventListener('click', this.handleOutsideClick);
				}, 0);
			});
		},

		close() {
			this.isOpen = false;
			document.removeEventListener('click', this.handleOutsideClick);
			state.popupMenuOpen = false;
		},

		handleOutsideClick(e) {

			if (!this.$el.contains(e.target)) {
				this.close();
			}

		},

		isActive(item) {
			return (
				item.type === 'select' &&
				state[item.stateProp] === item.value
			);
		},

		select(item) {
			state[item.stateProp] = item.value;
		},

		toggleBoolean(item) {
			state[item.stateProp] = !state[item.stateProp];
		},

		runAction(item) {
			if (typeof item.action === 'function') {
				item.action(state);
			}
			this.close();
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
</style>