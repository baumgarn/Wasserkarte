<template>
	<ol-overlay 
		ref="overlay"
		:key="device.id.id"
		:position="position(device)"
		>

		<div class="device-popup" :class="{'popupbelow':popupBelow}"
			:style="{'--offset': offsetY + 'px', '--nfk_color': nfk_color }" >
			<div class="popupcontent">
				<div class="popupcontentinner">

					<div class="popupheader">
						{{ device.attributes.Anzeigename || device.name }}
					</div>

					<div class="nfklabel">
						<div class="name">
							{{ nfk_label }}
						</div>
						<div class="nameoverlay">
							{{ nfk_label }}
						</div>
					</div>

					<div v-if="bodenfeuchteSensors.length > 0">
						<div class="dataheader">
							<div class="left">Tiefe</div>
							<div class="right">Bodenfeuchte</div>
						</div>
						<div class="datarow" v-if="telemetry.Bodenfeuchte_10cm" :style="'background:'+dataModel.get_vol_color(device, getLastValue(telemetry.Bodenfeuchte_10cm))">
							<div class="depth">10 cm</div>
							<div class="value">{{ formatNumber(getLastValue(telemetry.Bodenfeuchte_10cm)) }}<span class="unit"> <span class="smaller">Vol</span> %</span></div>
						</div>
						<div class="datarow" v-if="telemetry.Bodenfeuchte_30cm" :style="'background:'+dataModel.get_vol_color(device, getLastValue(telemetry.Bodenfeuchte_30cm))">
							<div class="depth">30 cm</div>
							<div class="value">{{ formatNumber(getLastValue(telemetry.Bodenfeuchte_30cm)) }}<span class="unit"> <span class="smaller">Vol</span> %</span></div>
						</div>
						<div class="datarow" v-if="telemetry.Bodenfeuchte_60cm" :style="'background:'+dataModel.get_vol_color(device, getLastValue(telemetry.Bodenfeuchte_60cm))">
							<div class="depth">60 cm</div>
							<div class="value">{{ formatNumber(getLastValue(telemetry.Bodenfeuchte_60cm)) }}<span class="unit"> <span class="smaller">Vol</span> %</span></div>
						</div>
						<div class="datarow" v-if="telemetry.Bodenfeuchte_80cm" :style="'background:'+dataModel.get_vol_color(device, getLastValue(telemetry.Bodenfeuchte_80cm))">
							<div class="depth">80 cm</div>
							<div class="value">{{ formatNumber(getLastValue(telemetry.Bodenfeuchte_80cm)) }}<span class="unit"> <span class="smaller">Vol</span> %</span></div>
						</div>
					</div>
					
					<!-- <div v-if="state.showHelp" class="helpnotes">
						Klick für detaillierte Sensordaten
					</div> -->

				</div>
			</div>
			

		</div>


	</ol-overlay>

</template>

<script>
import { fromLonLat } from 'ol/proj.js';
import { config } from '../config.js';
import { state } from '../state.js';
import { displayutil } from '../displayutil.js';
import { dataModel } from '../dataModel.js';

export default {
	name: "MapPopup",
	setup() {
		return {state};
	},
	data() {
		return {
			displayutil,
			dataModel,
			popupBelow: false,
		}
	},
	props: {
		device: {
			type: Object,
			required: false
		},
	},
	computed: {
		telemetry() {
			return this.device.telemetry || {};
		},
		bodenfeuchteSensors() {
			return Object.entries(this.telemetry)
				.filter(([key]) => key.startsWith("Bodenfeuchte"))
				.map(([key, data]) => ({ key, data }));
		},
		attributes() {
			return this.device.attributes || {};
		},
		otherSensors() {
			return this.sensors.filter(
				(sensor) =>
					!sensor.sensore_title.includes("BF") &&
					!sensor.sensore_title.includes("BT")
			);
		},
		getLastValue() {
			return (array) => array ? array[array.length - 1].value : null;
		},
		offsetY() {
			if (state.markerStyle == 'Bodenfeuchte Farbkreis') {
				return 20
			} else if (state.markerStyle == 'Bodenfeuchte Alle') {
				return 6
			} else {
				return 16
			}
		},
		nfk() {
			const nfk = dataModel.nfk(this.device);
			if (isNaN(nfk)) return '–'
			return parseFloat(nfk.toFixed(0));
		},
		nfk_liter() {
			if (isNaN(this.nfk)) return '–'
			let l =  Math.max(0, this.wassergehalt_oberboden - this.TW_liter);
			return parseFloat(l.toFixed(0));
		},
		nfk_label() {
			return dataModel.get_nfk_label(this.nfk);
		},
		nfk_color() {
			return dataModel.get_nfk_color(this.nfk);
		},
	},
	watch: {
		device() {
			this.$nextTick(() => {
				this.updatePopupPosition();
			});
		}
	},
	methods: {
		position(device) {
			return fromLonLat([device.attributes?.longitude, device.attributes?.latitude])
		},
		formatNumber(floatString) {
			return parseFloat(floatString).toFixed(1).replace('.', ',');
		},
		updatePopupPosition() {
			const overlayEl = this.$refs.overlay?.$el;
			if (!overlayEl) return;

			this.$nextTick(() => {
				// setTimeout(() => {
					const popupEl = overlayEl.querySelector('.popupcontent');
					if (!popupEl) return;

					const rect = popupEl.getBoundingClientRect();
					const popupHeight = rect.height;
					
					if (popupHeight === 0) {
						return;
					}
					
					const spaceAbove = rect.top;
					if (spaceAbove < 0) {
						this.popupBelow = true;
					}
					// this.popupBelow = spaceAbove < popupHeight + 20;
					// console.log(spaceAbove)
				// }, 50); // delay 50ms
			});
		},
	},
	mounted() {
		const overlayContainer = this.$refs.overlay?.$el?.closest('.ol-overlay-container');
		if (overlayContainer) {
			overlayContainer.style.zIndex = '10000';
		}
		this.updatePopupPosition();
	}
	
};
</script>

<style lang="stylus" scoped>


.device-popup
	position absolute
	height 0
	width 0
	// left -100px
	background white
	pointer-events none

.popupcontent
	position: absolute;
	padding: 0;
	left: 50%;
	transform: translateX(-50%);
	border-radius: 10px;
	text-align: center;
	box-shadow 0 1px 2px 2px #00000013
	bottom calc(var(--offset) + 6px)

.popupbelow .popupcontent
	bottom auto
	top calc(var(--offset) + 6px)


body.sensor-Bodenfeuchte .popupcontent
	bottom 10px

.popupcontent::after
	content: '';
	position: absolute;
	top: 100%;
	left: 50%;
	margin-left: -10px;
	border-width: 12px 10px 0 10px;
	border-style: solid;
	border-color: white transparent transparent transparent;
	filter: drop-shadow(0 2px 1px rgba(0,0,0,.2));

.popupbelow .popupcontent::after
	top auto
	bottom 100%
	border-width: 0 10px 12px 10px;
	border-color: transparent transparent white transparent;
	filter: drop-shadow(0 -2px 1px rgba(0,0,0,.2));

.popupcontentinner
	border-radius: 10px;
	background #fff
	overflow hidden
	width 175px
	border 2px solid white
	padding 0 0
	text-align center
	.popupheader
		margin 0
		font-weight bold
		font-size 100%
		font-size 9pt
	.nfklabel
		font-weight bold
		font-size 9pt
		margin 2px 8px 2px
		letter-spacing 0.03em;
		position relative
		.name
			color var(--nfk_color)
			position relative
		.nameoverlay
			position absolute
			left 0
			top 0
			right 0
			opacity .15
	.dataheader
		font-size 8pt
		display flex
		opacity .55
		> *
			flex-grow 1
		.left
			text-align left
		.right
			text-align right
	.popupheader
		padding 1px 0
	.dataheader
	.soiltype
	.datarow
		padding 1px 6px
	.depth
	.unit
		font-weight normal
	.datarow
		display flex
		flex-direction row
		align-items center
		justify-content space-between
		width 100%
		.depth
			text-align left
			flex-basis 50%
			font-size 8pt
			opacity .55
		.value
			flex-basis 50%
			text-align right
			font-weight bold
			font-size 10pt
		.unit
			font-size 9pt
			font-weight normal
			opacity .55
			margin-left .3em
			.smaller
				font-size 8pt
	.helpnotes
		padding 2px 0 1px
		font-size 8pt
		opacity .55

.popupOverlay
	z-index 100
</style>
