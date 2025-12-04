
<template>

	<div class="wrapper" :class="{ showtooltips: state.tooltips }" >

		<Map />

		<Timeline />
		
		<div class="ui">
			
			<div class="topbar" :style="state.sidebarOpen ? { right: '600px' } : {}">

				<MenuBar />
				
				<a v-if="state.isMobile" href="http://badbelzig-klimadaten.de" class="klimadaten mobile"><img src="/img/klimadaten.png" ></a>

				<div v-if="state.isMobile && !state.menuOpen.info" class="infobutton" @click="state.menuOpen.info = true"></div>

				<StatusBar />

			</div>

			<!-- <Legend v-if="state.showHelp"/> -->

			<div class="leftui">


				<div class="menuwindows">	

					<GeraeteMenu v-if="state.menuOpen.orte"/>
					<ErrorMenu v-if="state.menuOpen.error"/>
					<MarkerMenu v-if="state.menuOpen.bodenfeuchte"/>
					<ColorschemeMenu v-if="state.menuOpen.colorscheme"/>
					<GlossarMenu v-if="state.menuOpen.glossar"/>
					<SettingsMenu v-if="state.menuOpen.einstellungen"/>
					<KartenMenu v-if="state.menuOpen.karten"/>
				</div>
				
				<div class="bottombar">
					<!-- <ColorschemeGradient style="width:100%" class="gradient" :colorScheme="dataModel.color_schemes.nfk[state.colorScheme]" :width="1200" :height="44"/> -->

				</div>

			</div>



			<a v-if="!state.isMobile" href="http://badbelzig-klimadaten.de" class="klimadaten desktop"><img src="/img/klimadaten.png" ></a>


			<div class="rightui">

				<Info v-if="state.menuOpen.info"/>
				
				<Sidebar />
				
				<LayerLegends />

			</div>

			<div class="loadingoverlay" v-if="!telemetryLoaded">

			</div>
			
		</div>
		
	</div>

</template>

<script>
import { ref, computed } from 'vue';
import Sidebar from '@/views/sidebar.vue';
import Info from '@/views/info.vue';
import MenuBar from '@/menu/menu_bar.vue';
import Legend from '@/map/legend.vue';
import MarkerMenu from '@/menu/menu_marker.vue';
import GlossarMenu from '@/menu/menu_glossar.vue';
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
import Timeline from '@/map/timeline.vue';

export default {
	setup() {
		return {state, dataModel}
	},
	components: {
		Sidebar,
		KartenMenu,
		MenuBar,
		LayerLegends,
		Legend,
		MarkerMenu,
		GlossarMenu,
		ErrorMenu,
		SettingsMenu,
		GeraeteMenu,
		ColorschemeMenu,
		ColorschemeGradient,
		SoilMenu,
		Timeline,
		StatusBar,
		Map,
		Info
	},
	computed: {
		telemetryLoaded() {
			return state.telemetryLoaded;
		},
	},
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

	.klimadaten.mobile
		align-self center
		position relative
		height 30px
		
	.klimadaten.desktop
		position fixed
		left 0
		bottom 0
		bottom var(--timelineheight);
		height 36px
		margin 16px

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

	.loadingoverlay
		background #fff
		position fixed
		left 0
		top 0
		right 0
		bottom 0
		z-index 10000

</style>