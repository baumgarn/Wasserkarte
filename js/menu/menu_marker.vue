<template>
	<div class="marker menuwindow">
		<div class="menuwindow-header"><h3>Standort Darstellung</h3></div>
		<div class="menuwindow-content">

			<h4>
				Bodenfeuchte
			</h4>

			<div
				class="menuitem bodenfeuchte Bodenfeuchte_Farbkreis"
				:class="{ selected: markerStyle === 'Bodenfeuchte_Farbkreis' }"
				@click="selectSensor('Bodenfeuchte_Farbkreis')"
			>
				<div class="iconspace">

					<FauxMarker />

				</div>
				<div class="label">

					Trockenheit Schichten

				</div>
			</div>

			<div
				class="menuitem bodenfeuchte Bodenfeuchte_nfk"
				:class="{ selected: markerStyle === 'Bodenfeuchte_nfk' }"
				@click="selectSensor('Bodenfeuchte_nfk')"
			>

				<div class="iconspace">

					<div class="icon nfk"></div>

				</div>
				<div class="label">

					Nutzbare Feldkapazität %

				</div>
				
			</div>

			<div
				class="menuitem bodenfeuchte Bodenfeuchte_vol"
				:class="{ selected: markerStyle === 'Bodenfeuchte_vol' }"
				@click="selectSensor('Bodenfeuchte_vol')"
			>

				<div class="iconspace">

					<div class="icon vol"></div>

				</div>
				<div class="label">

					Volumen %

				</div>
				
			</div>

			<h4>Eigenschaften</h4>

			<div
				class="menuitem eigenschaften nutzungsart"
				:class="{ selected: markerStyle === 'nutzungsart' }"
				@click="selectSensor('nutzungsart')"
			>

				<div class="iconspace">

					<FilterIcon :obj="dataModel.usage_table.MW" :size="22" />

				</div>
				<div class="label">

					Nutzungsart

				</div>
				
			</div>

			<div
				class="menuitem eigenschaften wasserhaushalt"
				:class="{ selected: markerStyle === 'wasserhaushalt' }"
				@click="selectSensor('wasserhaushalt')"
			>
				<div class="iconspace">

					<FilterIcon :obj="dataModel.bewaessert_obj" :size="22" />

				</div>
				<div class="label">

					Wasserhaushalt

				</div>
			</div>

			<div
				class="menuitem eigenschaften bodenart"
				:class="{ selected: markerStyle === 'bodenart' }"
				@click="selectSensor('bodenart')"
			>
				<div class="iconspace">

					<FilterIcon :obj="dataModel.soil_table.Ss" :size="22" />

				</div>
				<div class="label">

					Bodenart

				</div>
			</div>

			<div
				class="menuitem eigenschaften humusgehalt"
				:class="{ selected: markerStyle === 'humusgehalt' }"
				@click="selectSensor('humusgehalt')"
			>
				<div class="iconspace">

					<FilterIcon :obj="dataModel.humus_table.h3" :size="22" />

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
	
	.menuitem
		display flex
		align-items center
		height 28px
		.iconspace
			margin-right 4px
			flex-basis 22px
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
					opacity .7
					// transform scale(.85)

	.iconspace .icon
		content ''
		display inline-block
		position relative
		width 22px
		flex-basis 22px
		height 22px
		flex-grow 0
		flex-shrink 0
		opacity .5
		margin-left -4px
		background-size contain
		background-position center
		background-repeat no-repeat
		&.vol
			opacity .85
			background-image url(/img/tropfen_flat.png)
			// background-size 60% 90%
		&.nfk 
			background-image url(/img/plant.svg)
			opacity .8

	.menuitem.Bodenfeuchte_Farbkreis 
		.iconspace
			height 22px
			position relative
		.map-marker.faux
			left -10px
			position absolute
			transform scale(0.6)

	.menuitem.Bodenfeuchte_nfk
	.menuitem.Bodenfeuchte_vol
		.map-marker .schicht
			margin 0 -4px
			position relative
			left 2px
			transform scale(0.8)

	.menuitem.eigenschaften
		.iconspace
			.filtericon
				margin-left -6px
				// transform scale(0.9)
			filter: drop-shadow(0 1px 1px rgba(0,0,0,.125));

	// .menuitem.smaller
	// 	height 30px
	// 	display flex
	// 	align-items center
	// 	.iconspace

</style>