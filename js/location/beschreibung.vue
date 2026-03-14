<template>
	<div
		v-if="beschreibung"
		ref="box"
		class="beschreibung_container"
		:class="{
			expanded: expanded,
			clickable: (state.expanded || hasOverflow) && !forceExpanded
		}"
		@click="toggle"
	>
		{{ beschreibung }}
	</div>
</template>

<script>
import { nextTick } from 'vue'
import { state } from '@/state.js'

export default {
	props: {
		device: {
			type: Object,
			required: true
		},
		alwaysExpanded: {
			type: Boolean,
			default: false
		}
	},

	data() {
		return {
			box: null,
			hasOverflow: false,
			forceExpanded: false,
			state
		}
	},

	computed: {
		beschreibung() {
			return this.device.attributes.Beschreibung
		},
		expanded() {
			return this.alwaysExpanded || state.expanded
		}
	},

	methods: {
		updateForceExpanded() {
			this.forceExpanded = window.matchMedia('(max-width: 900px)').matches

			if (this.forceExpanded) {
				this.state.expanded = true
			}
		},

		measure() {
			if (!this.$refs.box) return

			if (this.state.expanded || this.forceExpanded) {
				this.hasOverflow = false
				return
			}

			this.hasOverflow =
				(this.$refs.box.scrollWidth - this.$refs.box.clientWidth) > 1
		},

		async toggle() {
			if (this.forceExpanded) return

			if (this.state.expanded || this.hasOverflow) {
				this.state.expanded = !this.state.expanded
				await nextTick()
				this.measure()
			}
		}
	},

	async mounted() {
		this.updateForceExpanded()
		window.addEventListener('resize', this.updateForceExpanded)

		await nextTick()
		this.measure()

		window.addEventListener('resize', this.measure, { passive: true })
	},

	beforeUnmount() {
		window.removeEventListener('resize', this.updateForceExpanded)
		window.removeEventListener('resize', this.measure)
	},

	watch: {
		beschreibung: {
			async handler() {
				await nextTick()
				this.measure()
			}
		},

		'state.expanded': {
			async handler() {
				await nextTick()
				this.measure()
			}
		}
	}
}
</script>

<style lang="stylus" scoped>
.beschreibung_container
	max-width 100%
	white-space nowrap
	overflow hidden
	text-overflow ellipsis
	cursor default
	min-width 0
	font-size 9pt
	margin 8px 0 17px

	&.clickable
		cursor pointer

	&.expanded
		white-space normal
		overflow visible
		text-overflow clip

@media (max-width: 900px)
	.beschreibung_container
		white-space normal
		overflow visible
		text-overflow clip
		cursor default !important
</style>