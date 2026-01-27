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
			isOpen: false
		};
	},

	methods: {
		open(position) {
			this.isOpen = true;
			state.popupMenuOpen = true;
			nextTick(() => {

				if (position) {
					this.$el.style.bottom = position.bottom + 'px';
					this.$el.style.right = position.right + 'px';

					if (position.zIndex) {
						this.$el.style.zIndex = position.zIndex;
					}
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
	right -4px
	bottom -12px

.item
	padding 8px 12px
	cursor pointer

// .item.action
	// font-weight 500

</style>