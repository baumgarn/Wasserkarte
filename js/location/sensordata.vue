<script>
import { state } from '../state.js';

export default {
	props: {
		device: {
			required: true,
			type: Object,
		},
		showTitle: {
			default: true,
			type: Boolean,
		},
	},
	data() {
		return {
			isExpanded: false
		}
	},
	computed: {
		telemetryData() {
			const telemetry = this.device.telemetry || {};
			return Object.fromEntries(
				Object.entries(telemetry).sort(([keyA], [keyB]) => keyA.localeCompare(keyB))
			);
		},
		attributes() {
			const attributes = this.device.attributes || {};
			return Object.fromEntries(
				Object.entries(attributes).sort(([keyA], [keyB]) => keyA.localeCompare(keyB))
			);
		},
	},
	methods: {
		formatNumber(floatString) {
			return parseFloat(floatString).toFixed(1).replace('.', ',');
		},
	}
};



</script>

<template>
	<div class="sensordata">
		<div class="expand-button" :class="{ isexpanded: isExpanded }" @click="isExpanded = !isExpanded">
			<span class="arrow">▶</span>
			Attribute und Telemetrie
		</div>

		<div v-show="isExpanded">
			<h3>Attribute</h3>
			<table>
				<tr v-for="(value, key) in attributes" :key="key">
					<td class="label">{{ key }}</td>
					<td class="value">{{ value }}</td>
				</tr>
			</table>

			<h3>Telemetrie</h3>
			<table>
				<tr v-for="(values, key) in telemetryData" :key="key">
					<td class="label">{{ key }}</td>
					<td class="value">{{ values[0].value }}</td>
				</tr>
			</table>
		</div>
	</div>
</template>

<style lang="stylus" scoped>
	.sensordata
		width 100%
		max-width 100%
		font-size 10pt
		margin 1em 0 0
		h3
			font-size 10pt
			margin 1em 0 .25em
		table
			font-size 9pt
			width 100%
			border-collapse collapse
			td
				opacity .5
				vertical-align top
				border-bottom 1px solid #00000011
			tr:first-child td
				border-top 1px solid #00000011
			td.value
				text-align right
				opacity 1
			td.timestamp
				text-align left
				padding-left .1em
			.value
				font-weight bold
				font-size 110%

	.expand-button
		width 100%
		text-align left
		background none
		border none
		cursor pointer
		display flex
		align-items center
		margin 1em 0 0
	.arrow
		display inline-block
		width 1em
		margin-right .2em
		height 1em
		font-size 90%
		position relative
		top 0
	.isexpanded
		.arrow
			transform rotate(90deg)
			top .1em
</style>
