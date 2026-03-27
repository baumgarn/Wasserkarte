<template>
	<div v-if="isOpen" class="popover-menu" @click.stop>

		<template v-for="(item, index) in items" :key="index">

			
			<div
			v-if="item.type === 'select'"
			class="item select"
			:class="{ active: isActive(item) }"
			v-tooltip
			:tooltipcontent="item.tooltip"
			:tooltipdisabled="!item.tooltip"
			tooltipside="left"
			tooltipoffset="10"
			@click="select(item)"
			>
				<div v-if="!item.icon" class="icon"></div>
				<span v-html="item.label" />
			</div>
			
			<div
			v-else-if="item.type === 'action'"
			class="item action"
			v-tooltip
			:tooltipcontent="item.tooltip"
			:tooltipdisabled="!item.tooltip"
			tooltipside="left"
			tooltipoffset="10"
			@click="runAction(item)"
			>
				<div v-if="item.icon" class="icon" :class="item.icon"></div>
				<span v-html="item.label" />
			</div>
		
			<div
				v-else-if="item.type === 'boolean'"
				class="item boolean"
				:class="{ active: state[item.stateProp] }"
				v-tooltip
				:tooltipcontent="item.tooltip"
				:tooltipdisabled="!item.tooltip"
				:tooltiphideonclick="false"
				tooltipside="left"
				tooltipdelay="300"
				tooltipwidth="200"
				tooltipoffset="0"
				@click="toggleBoolean(item)"
			>
				<div v-if="!item.icon" class="icon"></div>
				<span v-html="item.label" />
			</div>

			<div
				v-else-if="item.type === 'header'"
				class="item header"
				v-html="item.label"
			></div>

			<div
				v-else-if="item.type === 'note'"
				class="item note"
				v-html="item.label"
			></div>


			<div
				v-else-if="item.type === 'thinline'"
				class="thinline"
			/>

			<div
				v-else-if="item.type === 'divider'"
				class="divider"
			/>

			<FilterItem
				v-else-if="item.type === 'filteritem'"
				:obj="item.obj"
				type="popovermenuitem"
			/>

		</template>

	</div>
</template>

<script>

import { state } from '@/state.js';
import { nextTick } from 'vue'
import FilterItem from '@/location/filteritem.vue'


export default {
	name: 'PopoverMenu',

	components: { FilterItem },

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
					if (position.top != null) { this.$el.style.top = position.top + 'px';}
					if (position.bottom != null) { this.$el.style.bottom = position.bottom + 'px';}
					if (position.right != null) { this.$el.style.right = position.right + 'px';}
					if (position.left != null) { this.$el.style.left = position.left + 'px';}
					if (position.zIndex != null) {this.$el.style.zIndex = position.zIndex;}
				}

				this.clampHeight();

				setTimeout(() => {
					document.addEventListener('click', this.handleOutsideClick);
					window.addEventListener('resize', this.clampHeight);
				}, 0);
			});
		},

		close() {
			this.isOpen = false;
			document.removeEventListener('click', this.handleOutsideClick);
			window.removeEventListener('resize', this.clampHeight);
			state.popupMenuOpen = false;
		},

		clampHeight() {
			if (!this.$el) return;
			this.$el.style.maxHeight = '';
			const rect = this.$el.getBoundingClientRect();
			const available = window.innerHeight - rect.top - 4;
			if (rect.height > available) {
				this.$el.style.maxHeight = available + 'px';
			}
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
		},
		handleGlobalClose() {
			if (this.isOpen) {
				this.close();
			}
		}
	},

	mounted() {
		window.addEventListener('app:close-popovers', this.handleGlobalClose);
	},

	beforeUnmount() {
		window.removeEventListener('app:close-popovers', this.handleGlobalClose);
		document.removeEventListener('click', this.handleOutsideClick);
		window.removeEventListener('resize', this.clampHeight);
		state.popupMenuOpen = false;
	}
};
</script>

<style lang="stylus" scoped>
// .popover-menu

</style>
