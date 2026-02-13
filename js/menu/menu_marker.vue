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

					Trockenheit Schichten

				</div>
			</div>

			<div
				class="menu-item bodenfeuchte Bodenfeuchte_nfk"
				:class="{ selected: markerStyle === 'Bodenfeuchte_nfk' }"
				@click="selectSensor('Bodenfeuchte_nfk')"
			>

				<div class="iconspace">

					<div class="icon nfk"></div>

					<!-- <div class="map-marker einzeln">
						<div class="schicht" :style="'background:'+dataModel.get_nfk_color(80)">
							<div class="value">nFK</div>
						</div>
					</div> -->

				</div>
				<div class="label">

					Nutzbare Feldkapazität %

				</div>
				
			</div>

			<div
				class="menu-item bodenfeuchte Bodenfeuchte_vol"
				:class="{ selected: markerStyle === 'Bodenfeuchte_vol' }"
				@click="selectSensor('Bodenfeuchte_vol')"
			>

				<div class="iconspace">

					<div class="icon vol"></div>
					<!-- <div class="map-marker einzeln">
						<div class="schicht" :style="'background:'+dataModel.get_nfk_color(120100)">
							<div class="value">Vol</div>
						</div>
					</div> -->

				</div>
				<div class="label">

					Volumen %

				</div>
				
			</div>

			<h4>Eigenschaften</h4>

			<div
				class="menu-item eigenschaften nutzungsart"
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
				class="menu-item eigenschaften wasserhaushalt"
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
				class="menu-item eigenschaften bodenart"
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
				class="menu-item eigenschaften humusgehalt"
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
		height 28px
		// height 40px
		// border-radius 20px
		// padding-left 2px
		.iconspace
			margin-right 4px
			// margin-left -2px
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

	.menu-item.Bodenfeuchte_Farbkreis 
		.iconspace
			height 22px
			position relative
		.map-marker.faux
			left -10px
			position absolute
			transform scale(0.6)

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
				margin-left -6px
				// transform scale(0.9)
			filter: drop-shadow(0 1px 1px rgba(0,0,0,.125));

	// .menu-item.smaller
	// 	height 30px
	// 	display flex
	// 	align-items center
	// 	.iconspace

</style>