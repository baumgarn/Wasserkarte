
<template>
	<div v-if="isOpen" class="popover-menu" :style="{ top: `${position.top}px`, right: `${position.right}px` }">
		<div class="items">
			<div v-for="(item, index) in items" :key="index" class="item" @click="selectItem(item)">
					{{ item.label }}
			</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'PopoverMenu',
	props: {
		items: {
			type: Array,
			required: true
		},
		position: {
			type: Object,
			default: () => ({ top: 0, right: 0 })
		}
	},
	emits: ['select'],
	data() {
		return {
			isOpen: false
		}
	},
	methods: {
		open(position) {
			this.isOpen = true
			this.position.top = position.top;
			this.position.right = position.right;
			window.setTimeout(() => {
				document.addEventListener('click', this.handleOutsideClick);
			}, 1);
		},
		close() {
			this.isOpen = false
			document.removeEventListener('click', this.handleOutsideClick);
		},
		selectItem(item) {
			this.$emit('select', item)
			this.close()
		},
		handleOutsideClick(event) {
			if (!this.$el.contains(event.target)) {
				this.close();
			}
		}
	},
	mounted() {
	},
	beforeUnmount() {
	},
}
</script>

<style scoped lang="stylus">
.popover-menu
	position fixed
	.item
		font-size 13px
		&:hover
			background: var(--activecolorgrey)
</style>