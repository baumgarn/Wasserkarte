<template>
	<div class="marker menuwindow">
		<div class="menuwindow-header"><h3>Standort Darstellung</h3></div>
		<div class="menuwindow-content">

			<h4>
				Bodenfeuchte
			</h4>

			<div
				class="menu-item bodenfeuchte Bodenfeuchte_Farbkreis"
				:class="{ selected: markerStyle === 'Bodenfeuchte_Farbkreis' }"
				@click="selectSensor('Bodenfeuchte_Farbkreis')"
			>
				<div class="iconspace">

					<FauxMarker />

				</div>
				<div class="label">

					Farbkreis Schichten

				</div>
			</div>

			<div
				class="menu-item bodenfeuchte Bodenfeuchte_nfk"
				:class="{ selected: markerStyle === 'Bodenfeuchte_nfk' }"
				@click="selectSensor('Bodenfeuchte_nfk')"
			>

				<div class="iconspace">

					<div class="map-marker einzeln">
						<div class="schicht" :style="'background:'+dataModel.get_nfk_color(70)">
							<div class="value">nFK<span class="unit">%</span></div>
						</div>
					</div>

				</div>
				<div class="label">

					Pflanzenverfügbar

				</div>
				
			</div>

			<div
				class="menu-item bodenfeuchte Bodenfeuchte_vol"
				:class="{ selected: markerStyle === 'Bodenfeuchte_vol' }"
				@click="selectSensor('Bodenfeuchte_vol')"
			>

				<div class="iconspace">

					<div class="map-marker einzeln">
						<div class="schicht" :style="'background:'+dataModel.get_nfk_color(120)">
							<div class="value">Vol<span class="unit">%</span></div>
						</div>
					</div>

				</div>
				<div class="label">

					Volumen

				</div>
				
			</div>

			<h4>Eigenschaften</h4>

			<div
				class="menu-item eigenschaften nutzungsart"
				:class="{ selected: markerStyle === 'nutzungsart' }"
				@click="selectSensor('nutzungsart')"
			>

				<div class="iconspace">

					<FilterIcon :obj="dataModel.usage_table.MW" :size="36" />

				</div>
				<div class="label">

					Nutzungsart

				</div>
				
			</div>

			<div
				class="menu-item eigenschaften wasserhaushalt"
				:class="{ selected: markerStyle === 'wasserhaushalt' }"
				@click="selectSensor('wasserhaushalt')"
			>
				<div class="iconspace">

					<FilterIcon :obj="dataModel.bewaessert_obj" :size="36" />

				</div>
				<div class="label">

					Wasserhaushalt

				</div>
			</div>

			<div
				class="menu-item eigenschaften bodenart"
				:class="{ selected: markerStyle === 'bodenart' }"
				@click="selectSensor('bodenart')"
			>
				<div class="iconspace">

					<FilterIcon :obj="dataModel.soil_table.Ss" :size="36" />

				</div>
				<div class="label">

					Bodenart

				</div>
			</div>

			<div
				class="menu-item eigenschaften humusgehalt"
				:class="{ selected: markerStyle === 'humusgehalt' }"
				@click="selectSensor('humusgehalt')"
			>
				<div class="iconspace">

					<FilterIcon :obj="dataModel.humus_table.h3" :size="36" />

				</div>
				<div class="label">

					Humusgehalt

				</div>
			</div>

		</div>
	</div>
</template>

<script>
import { state } from '@/state.js'
import FauxMarker from '@/map/legend_faux_marker.vue';
import { dataModel } from '@/dataModel.js';
import FilterIcon from '@/location/filtericon.vue';

export default {
	name: 'MarkerMenu',
	components: {
		FauxMarker,
		FilterIcon
	},
	setup() {
		return {dataModel}
	},
	data() {
		return {}
	},
	computed: {
		markerStyle: {
			get() {
				return state.markerStyle
			},
			set(value) {
				state.markerStyle = value
			}
		}
	},
	methods: {
		selectSensor(value) {
			this.markerStyle = value
		}
	}
}
</script>

<style lang="stylus" scoped>
	.filteritems
		display flex
		flex-direction column
		gap 0
		margin 0
	// h4
	// 	margin 0 0 .5em
	// 	font-weight bold
	// 	padding-top .6em
	// 	margin-top 2em
	// 	display block
	// 	// color #488eddee
	// 	font-size 80%
	// 	font-size 9.5pt
	// 	text-transform uppercase
		// border-top 1px solid #488eddaa
	
	.menu-item
		display flex
		align-items center
		height 40px
		border-radius 20px
		padding-left 2px
		.iconspace
			margin-right 4px
			margin-left -2px
			flex-basis 40px
			flex-grow 0
			flex-shrink 0
			display flex
			align-items center
			justify-content center
			position relative
			.map-marker
				position relative
				transform none
				.schicht
					padding-left 0
					padding-right 0
					width 46px
					display flex
					align-items center
					justify-content center
				.value
					transform scale(0.8)

	.menu-item.Bodenfeuchte_Farbkreis 
		.map-marker.faux
			transform scale(0.9)

	.menu-item.Bodenfeuchte_nfk
	.menu-item.Bodenfeuchte_vol
		.map-marker .schicht
			margin 0 -4px
			position relative
			left 2px
			transform scale(0.8)

	.menu-item.eigenschaften
		.iconspace
			.filtericon
				transform scale(0.9)
			filter: drop-shadow(0 1px 1px rgba(0,0,0,.125));

	// .menu-item.smaller
	// 	height 30px
	// 	display flex
	// 	align-items center
	// 	.iconspace

</style>