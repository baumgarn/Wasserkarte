<template>
	<div
		v-if="beschreibung"
		ref="box"
		class="beschreibung_container"
		:class="{
			expanded: state.expanded,
			clickable: (state.expanded || hasOverflow) && !forceExpanded
		}"
		@click="toggle"
	>
		{{ beschreibung }}
	</div>
</template>

<script setup>
	import { ref, onMounted, onBeforeUnmount, watch, nextTick, computed } from 'vue'
	import { state } from '@/state.js'

	const props = defineProps({
		beschreibung: { type: String, default: '' }
	})

	const box = ref(null)
	const hasOverflow = ref(false)

	// Track if we are in "forced expanded" mode via matchMedia
	const forceExpanded = ref(false)

	function updateForceExpanded() {
		forceExpanded.value = window.matchMedia('(max-width: 900px)').matches
		if (forceExpanded.value) {
			state.expanded = true
		}
	}

	function measure() {
		if (!box.value) return
		if (state.expanded || forceExpanded.value) {
			hasOverflow.value = false
			return
		}
		hasOverflow.value = (box.value.scrollWidth - box.value.clientWidth) > 1
	}

	function toggle() {
		// On small screens, always expanded → no toggle
		if (forceExpanded.value) return
		if (state.expanded || hasOverflow.value) {
			state.expanded = !state.expanded
			nextTick(measure)
		}
	}

	onMounted(async () => {
		updateForceExpanded()
		window.addEventListener('resize', updateForceExpanded)
		await nextTick()
		measure()
		window.addEventListener('resize', measure, { passive: true })
	})

	onBeforeUnmount(() => {
		window.removeEventListener('resize', updateForceExpanded)
		window.removeEventListener('resize', measure)
	})

	watch(() => props.beschreibung, async () => {
		await nextTick()
		measure()
	})
	watch(() => state.expanded, async () => {
		await nextTick()
		measure()
	})
</script>

<style lang="stylus" scoped>
	.beschreibung_container
		max-width 100%
		white-space nowrap
		overflow hidden
		text-overflow ellipsis
		cursor default
		min-width 0

		&.clickable
			cursor pointer

		&.expanded
			white-space normal
			overflow visible
			text-overflow clip

	/* Always expanded on small screens */
	@media (max-width: 900px)
		.beschreibung_container
			white-space normal
			overflow visible
			text-overflow clip
			cursor default !important
</style>