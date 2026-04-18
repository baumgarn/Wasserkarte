<template>

	<div class="qrcodecontent">

		<div class="qrcodeinner">

			<img src="/img/schildbg_162x222.png" class="schildbg">

			<div class="textoben">
				<div class="titel">{{ device?.attributes.Anzeigename || device?.name }}</div>
				<div class="aufruf">
					Scanne den QR-Code für Echtzeitdaten.
				</div>
			</div>

			<div class="qrcodeelement">
				<qrcode-vue :value="url" size="400" level="M" foreground="#000" background="transparent" render-as="svg" />
			</div>

			<div class="textunten">
				badbelzig-klimadaten.de
			</div>

		</div>

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
		color #000
		position relative
		font-family Ubuntu

	.schildbg
		position absolute
		left 0
		top 0
		width 100%
		

	.qrcodeinner
		position absolute
		left 0
		top 0
		bottom 0
		right 0
	
		// border .25em solid var(--logorot)

	.qrcodeheader
		flex-grow 1
		
	.headercontent
		margin 1.5em 1.5em 1em
		display flex
		flex-direction column
		align-items center
		justify-content center	

	.textoben
	.textunten
		position absolute
		left 0
		right 0
		font-size 13pt
		text-align center
		font-weight 400

	.textoben
		top 34.7%
		.titel
			font-weight bold
			margin-bottom .15em

	.textunten
		top 76%

	.qrcodeelement 
		position absolute
		left 27%
		right 27%
		top 35.5%
		left 30%
		right 30%
		top 34%
		svg
			width 100% !important

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

</style>
