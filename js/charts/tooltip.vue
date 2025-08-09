<template>
	<div v-if="hoverData"
		class="tooltip" 
		:class="{mouseOverChart}"
		:style="{ 
			left: `${x}px`, 
			top: `${y}px` 
		}">
		
		<ToolTipContent :sensors :device :hoverData />
	</div>
</template>

<script>

import ToolTipContent from '@/charts/tooltip_content.vue';

export default {
	components: {ToolTipContent},
	name: 'Tooltip',
	data() {
		return {
			x: 0,
			y: 0,

		}
	},
	props: {
		sensors: {
			type: Array,
			default: () => [],
		},
		device: {
			required: true,
			type: Object,
		},
		hoverData: {
			type: Object,
			required: false
		},
		mouseOverChart: {
			type: Boolean
		}
	},
	mounted() {
		document.addEventListener('mousemove', this.updatePosition)
	},
	beforeDestroy() {
		document.removeEventListener('mousemove', this.updatePosition)
	},
	methods: {
		updatePosition(event) {
			const tooltip = this.$el;
			const tooltipWidth = tooltip.offsetWidth;
			const tooltipHeight = tooltip.offsetHeight;
			const cursorPaddingX = 6;
			const cursorPaddingY = 6;
			const edgePadding = 6;

			// Get the parent container's bounding box
			const parent = this.$el.parentElement;
			if (!parent) {
				return;
			}
			const parentRect = parent.getBoundingClientRect();
			

			const parentLeft = parentRect.left;
			const parentTop = parentRect.top;
			const parentWidth = parentRect.width;
			const parentHeight = parentRect.height;

			// Calculate tooltip position relative to the parent container
			let xPos = event.clientX - parentLeft - tooltipWidth - cursorPaddingX;
			let yPos = event.clientY - parentTop + cursorPaddingY;

			// Ensure the tooltip stays within the parent's boundaries
			if (xPos < edgePadding) {
				xPos = edgePadding;
			}

			if (xPos + tooltipWidth > parentWidth - edgePadding) {
				xPos = parentWidth - tooltipWidth - edgePadding;
			}

			if (yPos + tooltipHeight > parentHeight - edgePadding) {
				yPos = parentHeight - tooltipHeight - edgePadding;
			}

			if (yPos < edgePadding) {
				yPos = edgePadding;
			}

			this.x = xPos;
			this.y = yPos;
		}
	}
}
</script>

<style lang="stylus" scoped>
.tooltip 
	position: absolute;
	display: block;
	background-color: white;
	border-radius: 3px;
	min-width 110px
	padding: 2px 6px;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
	pointer-events: none;
	z-index: 1000;
	opacity 0
	transition opacity .1s linear

.tooltip.mouseOverChart 
	opacity 1

</style>
