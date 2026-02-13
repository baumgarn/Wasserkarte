<template>
  <div v-if="hasVisibleLayers && state.wsmlegends" class="legend-window">
    <div class="legend-inner">
      <div v-for="layer in visibleLayers" :key="layer.name" class="legend-item">
        <h4> {{ layer.title }}</h4>
		<div v-if="layer.abstract" class="abstract">{{ layer.abstract }}</div>
		<div v-if="layer.attribution" class="attribution">&copy; {{ layer.attribution }}</div>
        <img v-if="layer.legendUrl" :src="layer.legendUrl" :alt="`Legende für ${layer.title}`" />
      </div>
    </div>
	<div class="windowbuttons plain">
		<div class="iconbutton close" v-on:click="close()"></div>
	</div>
  </div>
</template>

<script>

import { state } from '../state.js';

export default {
  name: 'LegendDisplay',

  data() {
	return {
	}
  },
  computed: {
    visibleLayers() {
      return Object.values(this.wsmLayers).filter(layer => {return ((layer.legendUrl || layer.abstract) && layer.visible)});
    },
    hasVisibleLayers() {
      return this.visibleLayers.length > 0;
    },
	wsmLayers() {
		return state.wsmLayers
	},
	state() {
		return state
	}
  },
  methods: {
    close() {
    	state.wsmlegends = false;
    }
  }
}
</script>
<style lang="stylus" scoped>
	.legend-window
		position fixed
		top 0
		right 0
		z-index 1001
		width auto
		height auto
		max-height 100vh
		max-width 600px
		display block
		overflow hidden
		background #fff
		pointer-events all
		box-shadow 0 0 2px 2px #00000018
		border 2px solid #00000011
	.abstract
	.attribution
		font-size 9.5pt
		margin .5em 0
	.attribution
		opacity .45
	.legend-inner
		max-height 100vh
		max-width 600px
		padding 0 1.5em
		overflow auto
	.legend-item
		margin-bottom: 15px;
	.legend-item h4
		margin-bottom: 5px;
		margin-right 2em


	
</style>
