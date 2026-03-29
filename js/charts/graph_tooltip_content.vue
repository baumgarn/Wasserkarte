<template>

	<div class="schichten-uebersicht">
		<div class="schichten-container" > 
				<div v-if="hoverData && hoverData.ts" class="latestdate">
					{{ displayutil.formatDateShort(hoverData.ts) }}
					<span v-if="dataAggregation != '1d'" class="time">{{ displayutil.formatDateTime(hoverData.ts) }}</span>
				</div>
			<div class="schichten">
				
				<div class="schicht" v-for="depth in depths" :key="depth">

				
						<div class="depth">
							<div class="line" 
							:style="getDepthLineStyle(depth)"></div>
							<div class="value">
								{{ depth }} cm
							</div>
						</div>

						
						<div class="bodenfeuchte" >

							<span class="value">{{ getVolValue('Bodenfeuchte_'+depth+'cm') }}</span>
							<span class="unit">%</span>

						</div>


				</div>
			</div>
			
		</div>


	</div>
</template>

<script>
import { displayutil } from '../displayutil.js'
import { config } from '../config.js'
import { state } from '../state.js'

export default {
	name: 'SchichtenUebersicht',
	data() {
		return {
			rowHeight: 80,
			rowMargin: 0,
			linePathRefs: [],
			heatmapImages: [],
			hoverValidRange: false,
			lastData: [],
			filteredSensors: [],
			displayutil
		}
	},
	props: {
		sensors: {
			type: Object,
			default: () => {},
		},
		device: {
			required: true,
			type: Object,
		},
		hoverData: {
			type: Object,
			required: false
		},
	},
	computed: {
		depths() {
			let ds = [];
			if (this.hoverData['Bodenfeuchte_10cm']) {ds.push(10);}
			if (this.hoverData['Bodenfeuchte_30cm']) {ds.push(30);}
			if (this.hoverData['Bodenfeuchte_60cm']) {ds.push(60);}
			if (this.hoverData['Bodenfeuchte_80cm']) {ds.push(80);}
			return ds;
		},
		dataAggregation () {
			return state.dataAggregation;
		},
		
	},
	methods: {
		getDepthLineStyle(depth) {
			var s =	 "background:" + displayutil.getDepthColor(depth);
			return s;
		},
		formatNumber(num) {
			return parseFloat(num).toFixed(1).replace('.', ',');
		},
		getVolValue(key) {
			let vol = this.hoverData[key];
			return this.formatNumber(vol);
		},
	
	},
	mounted() {
	},
	beforeUnmount() {
	},

}
</script>

<style lang="stylus" scoped>

.schichten-uebersicht
	margin 0 0 
	position relative

.schichten-container
	position relative

.schichten
	position: relative;
	overflow: hidden;

.schichten-header
.schicht
	display flex
	flex-direction row
	align-items center
	gap 12px
	font-size 8pt
	> *
		flex 1
		padding 0
		text-align right
		white-space nowrap
	.depth
		opacity 1
		text-align left
		display flex
		flex-direction row
		align-items center
		.line
			height 2px
			flex-basis 18px
			flex-grow 0
			flex-shrink 0
			margin-right 3px
			background #444
		.value
			font-size 8pt
			opacity .8
			font-weight normal
	.value
		font-size 9pt
	.unit
		font-weight normal
		display inline-block
		margin-left .15em
		margin-left .25em
		font-size 8pt
		opacity .8
		.unittype
			font-size 7pt
			display inline-block
			margin-right .25em
	.bodenfeuchteName
		flex 2
		text-align left
		text-align right
		display block
		text-overflow ellipsis
		overflow hidden
		padding-left 15px
		padding-right 12px
	.bodenfeuchteNFK .value
		font-weight normal
		opacity .8
		font-size 9pt

.latestdate
	padding 3px 0
	font-size 8pt
	opacity .8
	text-align center
	text-align right
	font-weight bold
	font-weight normal


</style>