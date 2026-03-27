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

					<Icon type="pflanze" :size="22" class="markerstyle-icon" />

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

					<Icon type="tropfen-flat" :size="22" class="markerstyle-icon" />

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

					<Icon :obj="dataModel.usage_table.MW" :size="22" />

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

					<Icon :obj="dataModel.bewaessert_obj" :size="22" />

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

					<Icon :obj="dataModel.soil_table.Ss" :size="22" />

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

					<Icon :obj="dataModel.humus_table.h3" :size="22" />

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
import Icon from '@/ui/Icon.vue';

export default {
	name: 'MarkerMenu',
	components: {
		FauxMarker,
		Icon
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

	.iconspace .markerstyle-icon
		display inline-block
		position relative
		opacity .5
		margin-left -4px

	.menuitem.Bodenfeuchte_vol .markerstyle-icon
		opacity .85

	.menuitem.Bodenfeuchte_nfk .markerstyle-icon
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
