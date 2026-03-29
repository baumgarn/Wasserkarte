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
				<Icon v-if="item.obj" class="item-obj-icon" :obj="item.obj" :size="20" shadow="true" />
				<div v-else-if="item.icon" class="popovericon" :class="item.icon"></div>
				<div v-else class="popovericon"></div>
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
				<Icon v-if="item.obj" class="item-obj-icon" :obj="item.obj" :size="20" shadow="true" />
				<div v-else-if="item.icon" class="popovericon" :class="item.icon"></div>
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
				<Icon v-if="item.obj" class="item-obj-icon" :obj="item.obj" :size="20" />
				<div v-else-if="item.icon" class="popovericon" :class="item.icon"></div>
				<div v-else class="popovericon"></div>
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
import Icon from '@/ui/Icon.vue'


export default {
	name: 'PopoverMenu',

	components: { FilterItem, Icon },

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

				this.$el.style.top = '';
				this.$el.style.bottom = '';
				this.$el.style.left = '';
				this.$el.style.right = '';

				if (position) {
					if (position.top != null) { this.$el.style.top = position.top + 'px';}
					if (position.bottom != null) { this.$el.style.bottom = position.bottom + 'px';}
					if (position.right != null) { this.$el.style.right = position.right + 'px';}
					if (position.left != null) { this.$el.style.left = position.left + 'px';}
					if (position.zIndex != null) {this.$el.style.zIndex = position.zIndex;}
					if (position.width != null) {this.$el.style.width = position.width;}
				}

				this.clampHeight();

				setTimeout(() => {
					document.addEventListener('mousedown', this.handleOutsideClick);
					window.addEventListener('resize', this.clampHeight);
				}, 0);
			});
		},

		close() {
			this.isOpen = false;
			document.removeEventListener('mousedown', this.handleOutsideClick);
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
			if (item.type !== 'select') {
				return false;
			}

			if (typeof item.active === 'function') {
				return Boolean(item.active(item, state));
			}

			if (typeof item.active === 'boolean') {
				return item.active;
			}

			return item.stateProp != null && state[item.stateProp] === item.value;
		},

		select(item) {
			if (typeof item.onSelect === 'function') {
				item.onSelect(item, state);
			} else if (item.stateProp != null) {
				state[item.stateProp] = item.value;
			}

			if (item.closeOnSelect) {
				this.close();
			}
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
.popover-menu
	.item-obj-icon
		margin-right 4px
		margin-left -8px
		flex-shrink 0

	.select.active .item-obj-icon
		opacity 1
		background-size contain !important


.popover-menu
	display inline-flex
	flex-direction column
	width max-content
	background #ffffffcc
	backdrop-filter: blur(20px);
	border 1px solid rgba(0,0,0,0.2)
	border-radius 8px
	box-shadow 0 2px 8px rgba(0, 0, 0, 0.2)
	z-index 1000
	overflow hidden
	position absolute
	overflow-y auto
	.items
		display flex
		flex-direction column
		margin 0
	.item
		width auto
		padding 6px 12px
		background none
		border none
		text-align right
		cursor pointer
		font-size 9pt
		font-weight normal
		white-space nowrap
		color #000000cc
		user-select none
		display flex
		align-items center
		&:hover
			background: var(--activecolorgreybrighter)
		&.active
			color #000000
			background: var(--activecolorgrey)
			pointer-events none
	.item.boolean.active
		pointer-events all
	.item.header
		pointer-events none
		font-weight bold
		padding-bottom 4px
		padding-left 5px
	.divider
		padding 0
		margin 0
		height 3px
		background rgba(0,0,0,0.1)
	.thinline
		padding 0
		margin 0
		height 1px
		background rgba(0,0,0,0.1)
	.popovericon
		width 16px
		height 16px
		margin-right 4px
		margin-left -6px
		background-position center center
		background-repeat no-repeat
		background-size 100% 100%
	.note
		pointer-events none
	.note + .note
		margin-top -.5em
	.boolean.active .popovericon
		opacity .8
		background-image url('/img/check.png')
		background-size 100% 100%
	.select.active .popovericon
		opacity .7
		background-image url('/img/radio_fill.png')
		background-size 40% 40%





</style>
