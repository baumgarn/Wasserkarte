<template>
	<div class="chart-range" ref="container">
		
		<div class="range-bar">
			<div 
				class="range-indicator" 
				:style="{ left: startHandlePosition + '%', width: rangeWidth + '%' }"
				@mousedown="startRangeDrag"
			></div>
			<div 
				class="handle start-handle" 
				:style="{ left: startHandlePosition + '%' }"
				@mousedown="(e) => startDrag(e, 'start')"
			></div>
			<div 
				class="handle end-handle" 
				:style="{ left: endHandlePosition + '%' }"
				@mousedown="(e) => startDrag(e, 'end')"
			></div>
		</div>
	</div>
</template>


<script>

import { state } from '../state.js';

export default {
	props: {
		// Initial values
		dataPresent: {
			type: Boolean,
			default: false
		},
		graphScale: {
			type: Number,
			default: 1.0 // 1.0 means full graph is visible
		},
		graphPosition: {
			type: Number,
			default: 0	// Changed from 0.5 to 0
		}
	},
	data() {
		return {
			startHandlePos: this.graphPosition, // Internal representation as a fraction
			endHandlePos: this.graphPosition + this.graphScale, // Internal representation as a fraction
			scale: this.graphScale,
			position: this.graphPosition,
			isDraggingHandle: false,
			isDraggingRange: false,
			startDragX: 0,
			activeHandle: null, // 'start', 'end', or null
			containerWidth: 0,
			internalUpdate: false, // Flag to track internal updates
		};
	},
	computed: {
		// Calculate the visual positions of the handles
		startHandlePosition() {
			return Math.max(0, this.startHandlePos * 100);
		},
		endHandlePosition() {
			return Math.min(100, this.endHandlePos * 100);
		},
		// Width of the visible range indicator
		rangeWidth() {
			return (this.endHandlePos - this.startHandlePos) * 100;
		},
		sidebarFullWidth() {
			return state.sidebarFullWidth;
		}
	},
	watch: {
		sidebarFullWidth() {
			this.updateContainerWidth();
		},
		graphPosition(newPosition) {
			if (!this.internalUpdate) {
				this.position = newPosition;
				this.startHandlePos = newPosition;
				this.endHandlePos = newPosition + this.scale;
			}
		},
		graphScale(newScale) {
			if (!this.internalUpdate) {
				this.scale = newScale;
				this.endHandlePos = this.position + newScale;
			}
		}
	},
	methods: {
		startDrag(event, handle) {
			this.isDraggingHandle = true;
			this.activeHandle = handle;
			this.startDragX = event.clientX;
			
			// Add event listeners for drag and release
			window.addEventListener('mousemove', this.handleDrag);
			window.addEventListener('mouseup', this.endDrag);
		},
		
		startRangeDrag(event) {
			if (!this.isDraggingHandle) {
				this.isDraggingRange = true;
				this.startDragX = event.clientX;
				
				// Add event listeners for drag and release
				window.addEventListener('mousemove', this.handleRangeDrag);
				window.addEventListener('mouseup', this.endRangeDrag);
			}
		},
		
		handleDrag(event) {
			if (!this.isDraggingHandle) return;
			
			const deltaX = event.clientX - this.startDragX;
			const deltaPercent = deltaX / this.containerWidth;
			this.startDragX = event.clientX;
			
			if (this.activeHandle === 'start') {
				const newStartPos = Math.max(0, this.startHandlePos + deltaPercent);
				const newEndPos = this.endHandlePos;
				
				if (newEndPos - newStartPos >= 0.05) { // Minimum 5% width
					this.startHandlePos = newStartPos;
					this.updateValues(newStartPos, newEndPos - newStartPos);
				}
			} else if (this.activeHandle === 'end') {
				const newStartPos = this.startHandlePos;
				const newEndPos = Math.min(1, this.endHandlePos + deltaPercent);
				
				if (newEndPos - newStartPos >= 0.05) { // Minimum 5% width
					this.endHandlePos = newEndPos;
					this.updateValues(newStartPos, newEndPos - newStartPos);
				}
			}
		},
		
		handleRangeDrag(event) {
			if (!this.isDraggingRange) return;
			
			const deltaX = event.clientX - this.startDragX;
			const deltaPercent = deltaX / this.containerWidth;
			this.startDragX = event.clientX;
			
			let newStartPos = this.startHandlePos + deltaPercent;
			let newEndPos = this.endHandlePos + deltaPercent;
			
			// Ensure the range does not shrink and stays within bounds
			if (newStartPos < 0) {
				newStartPos = 0;
				newEndPos = this.endHandlePos - this.startHandlePos; // Maintain width
			} else if (newEndPos > 1) {
				newEndPos = 1;
				newStartPos = 1 - (this.endHandlePos - this.startHandlePos); // Maintain width
			}
			
			this.startHandlePos = newStartPos;
			this.endHandlePos = newEndPos;
			this.updateValues(newStartPos, newEndPos - newStartPos);
		},
		
		updateValues(newPosition, newScale) {
			this.internalUpdate = true; // Set flag before emitting
			this.position = newPosition;
			this.scale = newScale;
			
			this.$emit('range-update', {
				scale: this.scale,
				position: this.position,
			});
			
			// setTimeout(() => {
			// 	this.internalUpdate = false; // Reset flag after next tick
			// }, 100);

			this.$nextTick(() => {
				this.internalUpdate = false; // Reset flag after next tick
			});
		},
		
		endDrag() {
			this.isDraggingHandle = false;
			this.activeHandle = null;
			window.removeEventListener('mousemove', this.handleDrag);
			window.removeEventListener('mouseup', this.endDrag);
		},
		
		endRangeDrag() {
			this.isDraggingRange = false;
			window.removeEventListener('mousemove', this.handleRangeDrag);
			window.removeEventListener('mouseup', this.endRangeDrag);
		},
		
		updateContainerWidth() {
			if (this.$refs.container) {
				this.containerWidth = this.$refs.container.offsetWidth;
			}
		},

		handleScroll(event) {
			// Implementation of handleScroll method
		},
	},
	mounted() {
		this.updateContainerWidth();
		window.addEventListener('resize', this.updateContainerWidth);
		// window.addEventListener('sidebar:switchfullwindow', this.updateContainerWidth);
		this.$refs.container.addEventListener('scroll', this.handleScroll);
	},
	beforeUnmount() {
		window.removeEventListener('resize', this.updateContainerWidth);
		// window.removeEventListener('sidebar:switchfullwindow', this.updateContainerWidth);
		this.$refs.container.removeEventListener('scroll', this.handleScroll);
	}
};
</script>



<style lang="stylus" scoped>

.chart-range
	--barheight .2em
	--barheight 12px
	padding: 0
	margin .1em 0
	user-select: none
	filter var(--dropshadowfilter)

.range-bar
	margin 0
	position: relative
	height: var(--barheight)
	background-color: #fff
	border-radius: calc(var(--barheight) / 2)
	background-color: #00000022

.range-indicator
	position: absolute
	height: 100%
	background-color: #bbb
	border-radius: calc(var(--barheight) / 2)
	background-color: var(--activecolordarker)
	cursor: grab
	&:active
		cursor: grabbing

.handle
	position: absolute
	width: var(--barheight)
	height: var(--barheight)
	background-color: #666
	border-radius: 50%
	background-color: #aaa
	background-color: #00000066
	background-color: #666
	top: 50%
	cursor: ew-resize
	&.start-handle
		transform: translate(0, -50%)
	&.end-handle
		transform: translate(-100%, -50%)

</style>