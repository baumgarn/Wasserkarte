<template>
	<div class="fullview iframe">

			<LocationView :device="device" context="iframe" />

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
			let d = dataStore.getDeviceByName(this.name);
			return d;
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
	// padding .5em 0 0
	overflow-y auto
	position absolute
	left 0
	top 0
	right 0
	bottom 0
	border var(--separatorline)
</style>
