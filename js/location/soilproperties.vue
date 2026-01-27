<template>

	<div class="filteritems" v-if="device" >

		<FilterItem v-if="usageObj" :obj="usageObj"/>
		
		<FilterItem v-if="bewaessertObj" :obj="bewaessertObj"/>
		
		<FilterItem v-if="grundwasserObj" :obj="grundwasserObj"/>

		<FilterItem v-if="regenabhängigObj" :obj="regenabhängigObj"/>
		
		<FilterItem v-if="soilObj" :obj="soilObj"/>

		<FilterItem v-if="humusObj" :obj="humusObj"/>

	</div>

				

</template>

<script>
	import { displayutil } from '@/displayutil.js'
	import { dataModel } from '@/datamodel.js'
	import { state } from '@/state.js'
	import dataStore from '@/datastore.js'
	import {config} from '@/config.js'
	
	import FilterItem from '@/location/filteritem.vue';

	export default {
		name: 'SoilProperties',
		components: {FilterItem},
		props: {device: Object},
		setup() {
			return {state};
		},
		data() {
			return {
			};
		},
		computed: {
			usageObj() {
				return dataModel.get_usage_obj(this.device);
			},
			soilObj() {
				return dataModel.get_soil_obj(this.device);
			},
			humusObj() {
				return dataModel.get_humus_obj(this.device);
			},
			bewaessertObj() {
				if (this.device.attributes.Bewässerung) {
					return dataModel.bewaessert_obj;
				}
			},
			grundwasserObj() {
				if (this.device.attributes.Grundwasser) {
					return dataModel.grundwasser_obj;
				}
			},
			regenabhängigObj() {
				if (this.device.filterKeywords.indexOf(dataModel.regenabhängig_obj.name)>-1) {
					return dataModel.regenabhängig_obj;
				}
			},
		},
		methods: {
		},
		watch: {
			
		}

	}

</script>

<style lang="stylus" scoped>

	.filteritems
		display flex
		flex-wrap wrap
		font-weight bold
		gap 6px 2px
		margin 15px -50px 8px -11px

</style>

<style lang="stylus" >
	.context-iframe .location .filteritems
	.context-single .location .filteritems
	.is-mobile .location .filteritems
		margin-right 0
</style>