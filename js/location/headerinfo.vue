<template>

	<div class="headerinfo">



		<div class="info">

			<div class="soil" v-if="usageName || soilName || humusName" >
				
				<span v-if="usageName" class="usagename" >
					{{ usageName }}
				</span>
				<span class="separator"> – </span>
				<span v-if="soilName" class="soiltype" >
					{{ soilName }}
				</span>
				<span class="separator"> – </span>
				<span v-if="humusName" class="humustype">
					{{ humusName }}
				</span>

			</div>
		
			<div v-if="device.attributes.Beschreibung" class="description">
				{{ device.attributes.Beschreibung }}
			</div>

		</div>
		
	</div>
</template>

<script>
	import { displayutil } from '@/displayutil.js'
	import { dataModel } from '@/datamodel.js'

	export default {
		name: 'HeaderInfo',
		props: {device: Object},
		computed: {
			usageName() {
				return dataModel.get_usage_name(this.device);
			},
			soilName() {
				return dataModel.get_soil_name(this.device);
			},
			soilColor() {
				return dataModel.get_soil_color(this.device);
			},
			humusName() {
				return dataModel.get_humus_name(this.device);
			},
			humusColor() {
				return dataModel.get_humus_color(this.device);
			},

		}

	}

</script>

<style lang="stylus" scoped>

	.headerinfo
		padding 0
		margin-left var(--sidebartextmargin)
		margin-bottom .6em
		margin-bottom 1em
		margin-right 0
		flex-grow 0
		flex-shrink 0
		.soil
		.description
			font-size 10pt
			margin .4em 0 .3em
		.soil
			font-weight bold
		.separator
			opacity .4
		.soiltype
		.humustype
			display inline-block
			// padding .1em .5em

</style>