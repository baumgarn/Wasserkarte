
<template>

	<div class="wrapper" :class="{ showtooltips: state.tooltips, sidebaropen: state.sidebarOpen }" >

		<div class="mapareawrapper">

			<Map />
			
			<div class="ui">
				
				<div class="topbar" :style="state.sidebarOpen ? { right: '600px' } : {}">

					<MenuBar />
					
					<div v-if="state.isMobile && !state.menuOpen.info" class="infobutton" @click="state.menuOpen.info = true"></div>

					<StatusBar />
					
				</div>

				<div class="leftui">

					<div class="menuwindows">	

						<GeraeteMenu v-if="state.menuOpen.orte"/>
						<ErrorMenu v-if="state.menuOpen.error"/>
						<MarkerMenu v-if="state.menuOpen.bodenfeuchte"/>
						<FilterMenu v-if="state.menuOpen.filter"/>
						<ColorschemeMenu v-if="state.menuOpen.colorscheme"/>
						<BodenkundeMenu v-if="state.menuOpen.bodenkunde"/>
						<SettingsMenu v-if="state.menuOpen.einstellungen"/>
						<KartenMenu v-if="state.menuOpen.karten"/>
					</div>
										
				</div>
				
				<a v-if="!state.isMobile" href="http://badbelzig-klimadaten.de" class="klimadaten desktop"><img src="/img/klimadaten.png" ></a>

				<a v-else-if="state.isMobile" href="http://badbelzig-klimadaten.de" class="klimadaten mobile"><img src="/img/klimadaten.png" ></a>
				
			</div>

		</div>

		<!-- <BodenkundeMenu v-if="state.menuOpen.bodenkunde"/> -->

		<TimelineWrapper />


		
		<TableView v-if="state.menuOpen.standorttabelle"/>
	</div>

	<div class="rightui">

		<Info v-if="state.menuOpen.info"/>
		

		<Sidebar />
		
		<LayerLegends />

	</div>

	<!-- <div class="loadingoverlay" v-if="!loaded">

	</div> -->

</template>

<script>
import { ref, computed } from 'vue';
import { nextTick } from 'vue';
import TableView from '@/table/tableview.vue';
import Sidebar from '@/views/sidebar.vue';
import Info from '@/views/info.vue';
import MenuBar from '@/menu/menu_bar.vue';
import Legend from '@/map/legend.vue';
import MarkerMenu from '@/menu/menu_marker.vue';
import FilterMenu from '@/menu/menu_filter.vue';
import BodenkundeMenu from '@/menu/menu_bodenkunde.vue';
import KartenMenu from '@/menu/menu_karten.vue';
import ErrorMenu from '@/menu/menu_error.vue';
import LayerLegends from '@/menu/layer_legends.vue';
import SettingsMenu from '@/menu/menu_settings.vue';
import ColorschemeMenu from '@/menu/menu_colorscheme.vue';
import ColorschemeGradient from '@/menu/colorscheme_gradient.vue';
import SoilMenu from '@/menu/menu_soil.vue';
import GeraeteMenu from '@/menu/menu_devices.vue';
import StatusBar from '@/map/statusbar.vue';
import { state } from '@/state.js';
import { dataModel } from '@/datamodel.js';
import { config } from '@/config.js';
import Map from '@/map/map.vue';
import TimelineWrapper from '@/map/timeline_wrapper.vue';

export default {
	name: 'Home',
	setup() {
		return {state, dataModel}
	},
	data(){
		return {
			// loaded: false
		}
	}, 
	components: {
		Sidebar,
		TableView,
		KartenMenu,
		MenuBar,
		LayerLegends,
		Legend,
		MarkerMenu,
		FilterMenu,
		BodenkundeMenu,
		ErrorMenu,
		SettingsMenu,
		GeraeteMenu,
		ColorschemeMenu,
		ColorschemeGradient,
		SoilMenu,
		TimelineWrapper,
		StatusBar,
		Map,
		Info
	},
	computed: {
		telemetryLoaded() {
			return state.telemetryLoaded;
		},
	},
	watch: {
	}
};
</script>

<style lang="stylus" scoped>



			
		
	.klimadaten
		user-select none
		background white
		display flex
		padding 0 12px
		align-items center
		justify-content center
		box-shadow 0 0 4px #00000044
		img
			height 80%

	// .klimadaten.mobile
		// height 30px
		// align-self center
		// position relative
		
	.klimadaten.mobile
	.klimadaten.desktop
		position absolute
		left 0
		bottom 0
		height 32px
		margin 16px

	.klimadaten.mobile
		height 32px
		margin 0 8px 48px

	.infobutton
		margin 6px
		font-size 10px
		display flex
		align-items center
		justify-content center
		flex-shrink 0
		background white
		height 36px
		width 36px
		padding 3px
		border-radius 50%
		filter drop-shadow(2px 3px 2px #00000022)
		cursor pointer
		background #fff url(/img/info.png) center / 80% no-repeat

</style>