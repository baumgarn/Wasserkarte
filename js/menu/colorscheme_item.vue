<template>
	<div
		class="colorscheme">
		<div class="colors">
			<div
				v-for="(colorItem, index) in colorScheme"
				:key="index"
				class="color"
				:style="{ background: colorItem.color }"
			></div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'ColorScheme',
	props: {
		colorScheme: {
			type: Array,
			required: true
		}
	}
}
</script>

<style lang="stylus" scoped>

.colorscheme
	cursor pointer
	width 100%
	height 100%
	margin 0 0
	position relative

.colors
	height 100%
	display flex
	overflow hidden

.color
	flex-grow 1

</style>