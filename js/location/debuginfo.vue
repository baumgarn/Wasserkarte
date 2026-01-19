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
		formatTs(ts) {
			const d = new Date(ts);
			const pad = n => String(n).padStart(2, '0');

			return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()}, ${pad(d.getHours())}:${pad(d.getMinutes())}`;
		}
	}
};



</script>

<template>
	<div class="debuginfo">

		<h3>Gerät</h3>

		<div class="datagroup">
			<div class="datarow">
				<div class="label">label</div>
				<div class="value">{{ device.label }}</div>
			</div>
			<div class="datarow">
				<div class="label">name</div>
				<div class="value">{{ device.name }}</div>
			</div>
			<div class="datarow">
				<div class="label">id</div>
				<div class="value">{{ device.id }}</div>
			</div>
			<!-- <div class="datarow">
				<div class="label">index</div>
				<div class="value">{{ device.index }}</div>
			</div> -->
		</div>

		<h3>Telemetrie</h3>
		<div class="datagroup">
			<div class="datarow" :class="key" v-for="(values, key) in telemetryData" :key="key">
				<div class="label">{{ key }}</div>
				<div class="ts">{{ formatTs(values[0].ts) }}</div>
				<div class="value">{{ values[0].value }}</div>
			</div>
		</div>
		
		<h3>Attribute</h3>
		<div class="datagroup">
			<div class="datarow" v-for="(value, key) in attributes" :key="key">
				<div class="label">{{ key }}</div>
				<div class="value">{{ value }}</div>
			</div>
		</div>

		<!-- <template v-if="device.telemetrySchema && device.telemetrySchema.schema">

			<h3>Telemetrie Schema</h3>
			<div class="datagroup">
				<div class="datarow">
					<div class="label">schema</div>
					<div class="value">
						<span v-for="(field, index) in device.telemetrySchema.schema" :key="index">
							{{ field }}<br>
						</span>
					</div>
				</div>
				<div class="datarow">
					<div class="label">data</div>
					<div class="value">
						<span v-for="(value, index) in device.telemetrySchema.data[0]" :key="index">
							{{ value }}<br>
						</span>
					</div>
				</div>
			</div>

		</template> -->

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
		.datagroup
			font-size 9pt
			width 100%
			margin-bottom 2em
			.datarow
				display flex
				justify-content space-between
				border-bottom 1px solid #00000011
				> *
					flex-grow 1
					flex-shrink 1
			.label
				flex-basis 30%
			.value
				flex-basis 40%
				font-weight bold
				text-align right
			.ts	
				flex-basis 30%
				text-align right
			.false_value_note 
				flex-wrap wrap
	
</style>
