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
			isExpanded: true
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
	<div class="debuginfo">
		<!-- <div class="expand-button" :class="{ isexpanded: isExpanded }" @click="isExpanded = !isExpanded">
			<span class="arrow">▶</span>
			Geräteattribute
		</div> -->

		<!-- <div v-show="isExpanded" class="expanded"> -->
			<h3>Gerät</h3>
			<table>
				<tr>
					<td class="label">name</td>
					<td class="value">{{ device.name }}</td>
				</tr>
				<tr>
					<td class="label">id</td>
					<td class="value">{{ device.id }}</td>
				</tr>
				<tr>
					<td class="label">label</td>
					<td class="value">{{ device.label }}</td>
				</tr>
			</table>

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

			<template v-if="device.telemetrySchema && device.telemetrySchema.schema">

				<h3>Telemetrie Schema</h3>
				<table>
					<tr>
						<td class="label">schema</td>
						<td class="value">
							<span v-for="(field, index) in device.telemetrySchema.schema" :key="index">
								{{ field }}<br>
							</span>
						</td>
					</tr>
					<tr>
						<td class="label">data</td>
						<td class="value">
							<span v-for="(value, index) in device.telemetrySchema.data[0]" :key="index">
								{{ value }}<br>
							</span>
						</td>
					</tr>
				</table>
			</template>
<!-- 
		</div> -->
	</div>
</template>

<style lang="stylus" scoped>
	.debuginfo
		width 100%
		max-width 100%
		font-size 10pt
		margin 3em 0 2em
		h3
			font-size 9pt
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
		.expanded
			margin-bottom 24px

	.expand-button
		width 100%
		text-align left
		background none
		border none
		cursor pointer
		display flex
		align-items center
		margin 3em 0 0
		font-size 9pt
	.arrow
		display inline-block
		width 1em
		margin-right .2em
		height 1em
		font-size 90%
		position relative
		opacity .4
		top 0
	.isexpanded
		.arrow
			transform rotate(90deg)
			top .1em
</style>
