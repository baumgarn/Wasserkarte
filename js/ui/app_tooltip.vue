<template>
	<teleport to="body">
		<div
			ref="tooltipRef"
			class="app-tooltip"
			:class="[{ visible: tooltipState.visible, ready: tooltipState.ready, rich: tooltipState.isHtml }, `placement-${tooltipState.placement}`, `align-${tooltipState.align}`]"
			:style="tooltipStyle"
			aria-hidden="true"
		>
			<div class="app-tooltip-body">
				<div v-if="tooltipState.isHtml" v-html="tooltipState.contentHtml"></div>
				<template v-else>{{ tooltipState.content }}</template>
			</div>
			<div v-if="tooltipState.placement !== 'cursor'" class="app-tooltip-arrow"></div>
		</div>
	</teleport>
</template>

<script>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { tooltipState, setTooltipElement } from '@/tooltip.js';

export default {
	name: 'AppTooltip',
	setup() {
		const tooltipRef = ref(null);

		const tooltipStyle = computed(() => ({
			left: `${tooltipState.x}px`,
			top: `${tooltipState.y}px`,
			maxWidth: `${tooltipState.maxWidth}px`,
			'--app-tooltip-arrow-x': `${tooltipState.arrowX}px`,
			'--app-tooltip-arrow-y': `${tooltipState.arrowY}px`,
			'--app-tooltip-arrow-half-width': `${Math.max(2, Math.round(tooltipState.arrowWidth / 2))}px`,
			'--app-tooltip-arrow-height': `${tooltipState.arrowHeight}px`,
		}));

		onMounted(() => {
			setTooltipElement(tooltipRef.value);
		});

		onBeforeUnmount(() => {
			setTooltipElement(null);
		});

		return {
			tooltipRef,
			tooltipState,
			tooltipStyle,
		};
	},
};
</script>

<style scoped lang="stylus">
.app-tooltip
	position fixed
	left 0
	top 0
	--app-tooltip-radius 3px
	--app-tooltip-pad-y 4px
	--app-tooltip-pad-x 6px
	width max-content
	max-width 280px
	pointer-events none
	user-select none
	z-index 10000
	opacity 0
	visibility hidden
	transition opacity .12s linear, visibility 0s linear .12s
	&.visible
		visibility visible
		transition-delay 0s
	&.ready
		opacity 1
	&.placement-cursor
		padding 0

.app-tooltip-body
	max-width inherit
	white-space pre-wrap
	overflow-wrap break-word
	word-break normal
	text-align left
	color white
	background var(--infobg)
	backdrop-filter blur(10px)
	-webkit-backdrop-filter blur(10px)
	font-size 9pt
	line-height 1.3
	padding var(--app-tooltip-pad-y) var(--app-tooltip-pad-x)
	border-radius var(--app-tooltip-radius)
	box-shadow 0 2px 8px rgba(0,0,0,.2)

.app-tooltip.rich .app-tooltip-body
	white-space normal

.app-tooltip-arrow
	position absolute
	background var(--infobg)
	backdrop-filter blur(10px)
	-webkit-backdrop-filter blur(10px)
	pointer-events none

.app-tooltip.placement-top
	padding-bottom var(--app-tooltip-arrow-height, 8px)
	.app-tooltip-arrow
		left var(--app-tooltip-arrow-x, 50%)
		bottom 0
		width calc(var(--app-tooltip-arrow-half-width, 5px) * 2)
		height var(--app-tooltip-arrow-height, 8px)
		transform translateX(-50%)
		clip-path polygon(50% 100%, 0 0, 100% 0)

.app-tooltip.placement-bottom
	padding-top var(--app-tooltip-arrow-height, 8px)
	.app-tooltip-arrow
		left var(--app-tooltip-arrow-x, 50%)
		top 0
		width calc(var(--app-tooltip-arrow-half-width, 5px) * 2)
		height var(--app-tooltip-arrow-height, 8px)
		transform translateX(-50%)
		clip-path polygon(50% 0, 0 100%, 100% 100%)

.app-tooltip.placement-left
	padding-right var(--app-tooltip-arrow-height, 8px)
	.app-tooltip-arrow
		right 0
		top var(--app-tooltip-arrow-y, 50%)
		width var(--app-tooltip-arrow-height, 8px)
		height calc(var(--app-tooltip-arrow-half-width, 5px) * 2)
		transform translateY(-50%)
		clip-path polygon(100% 50%, 0 0, 0 100%)

.app-tooltip.placement-right
	padding-left var(--app-tooltip-arrow-height, 8px)
	.app-tooltip-arrow
		left 0
		top var(--app-tooltip-arrow-y, 50%)
		width var(--app-tooltip-arrow-height, 8px)
		height calc(var(--app-tooltip-arrow-half-width, 5px) * 2)
		transform translateY(-50%)
		clip-path polygon(0 50%, 100% 0, 100% 100%)

.app-tooltip.placement-cursor .app-tooltip-arrow
	display none
</style>
