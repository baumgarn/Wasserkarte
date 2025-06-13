<template>

	<div class="qrcodecontent">

		<qrcode-vue class="qrcodesvg" :value="url" size="200" level="M" foreground="#000" render-as="svg" />

	</div>

</template>

<script>

import { state } from '@/state.js';
import dataStore from '@/datastore.js';
import QrcodeVue from 'qrcode.vue'

export default {
	components: { QrcodeVue },
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
		url() {
			return `${window.location.origin}/standort/${this.name}`;
		}
	},
	methods: {
	}
};

</script>

<style lang="stylus" scoped>
	

	.qrcodecontent
		background white
		height 100%
		margin auto
		display flex
		align-items center
		justify-content center
		color #008c46
		position relative

	.qrcodeheader
		flex-grow 1
		
	.headercontent
		margin 1.5em 1.5em 1em
		display flex
		flex-direction column
		align-items center
		justify-content center	

	svg
		position absolute
		left 0
		top 0
		bottom 0
		right 0
		width 100% !important
		height 100% !important
		

	h1
		text-align left
		font-size 11pt
		width 100%
		color #008c46
	.kreis
		width 33%
		margin 0 auto .75em
	.logo
		width 100%
	.netzwerk
		width 99.25%
		margin 1em 0 .1em
	.text
		font-size 11pt
		width 100%
		color #008c46
		margin-bottom 1em
		margin-top -.1em
	.url
		font-size 11pt
		margin-bottom 1em
		color #008c46
		font-weight bold
	// .text
	// h1
	// 	color #444


</style>
