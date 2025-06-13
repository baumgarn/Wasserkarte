<template>
	<div class="fullview single">

		<LocationView :device="device" context="single" />

	</div>

</template>

<script>
import { state } from '@/state.js';
import dataStore from '@/datastore.js';
import LocationView from '@/location/_location.vue';

export default {
	components: { LocationView },
	props: {
		name: {
			type: String,
			required: false
		}
	},
	data() {
		return {
		}
	},
	computed: {
		device() {
			return dataStore.getDeviceByName(this.name);
		},
	},
	methods: {
		close() {
			this.$router.push(`/`);
		},
	}
};

</script>

<style lang="stylus" scoped>
.iframe
	padding .5vw 2vw 2vw
	overflow-y auto
	position absolute
	left 0
	top 0
	right 0
	bottom 0
</style>
