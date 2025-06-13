<template>
	<div class="fullview embed">

		<div class="windowrow">

			<MenuDevices :sideview="true" :nowarning="true" stateProperty="selectedDeviceEmbed"/>

			<div class="windowcol">
				
				<div class="fullviewheader">
					<div v-if="device" class="row">
						<label for="width">Breite:</label>
						<input
							id="width"
							class="sizeinput"
							type="text"
							v-model="width"
							@input="sanitizeInput('iframeWidth')"
							placeholder=""
						/>

						<label for="height">Höhe:</label>
						<input
							id="height"
							type="text"
							class="sizeinput"
							v-model="height"
							@input="sanitizeInput('iframeHeight')"
							placeholder=""
						/>
					    <button @click="adjustIframeHeight">Höhe Einpassen</button>

						<div class="iframecode">
							{{ iframeCode }}
						</div>	
					</div>

				</div>

				<div class="scrollcontent">

					<div class="notice" v-if="device && !isLocalhost">
						Die Darstellung des Iframes befindet sich aktuell in Entwicklung und ist Gegenstand von Veränderung.
					</div>

					<div v-if="device" v-html="iframeCode" class="iframepreview"></div>

				</div>
			</div>
			
		</div>

		<div class="windowbuttons plain">
			<div class="iconbutton close" v-on:click="close()"></div>
		</div>


	</div>
</template>

<script>
import { state } from '@/state.js';
import dataStore from '@/datastore.js';
import MenuDevices from '@/menu/menu_devices.vue';


export default {
	components: { MenuDevices },
	props: {
		// name: {
		// 	type: String,
		// 	required: false
		// }
	},
	data() {
		return {
		};
	},
	computed: {
		width: {
			get() {
				return state.iframeWidth;
			},
			set(value) {
				state.iframeWidth = value;
			}
		},
		height: {
			get() {
				return state.iframeHeight;
			},
			set(value) {
				state.iframeHeight = value;
			}
		},
		url() {
			return `${window.location.origin}/iframe/${this.device?.name}`;
		},
		selectedDeviceEmbed() {
			console.log(state.selectedDeviceEmbed)
			return state.selectedDeviceEmbed;
		},
		device() {
			console.log(state.selectedDeviceEmbed)
			return dataStore.getDeviceByName(state.selectedDeviceEmbed);
		},
		iframeCode() {
		return `<iframe src="${this.url}" width="${this.width}" height="${this.height}" frameborder="0" allowfullscreen></iframe>`;
		},
		isLocalhost() {
			return state.localhost;
		}
	},
	methods: {
		close() {
			this.$router.push(`/`);
		},
		sanitizeInput(field) {
			// Allow only numbers and percentages
			const value = this[field];
			const sanitizedValue = value.replace(/[^0-9%]/g, ''); // Remove invalid characters
			this[field] = sanitizedValue;

			// Ensure valid percentage format
			if (sanitizedValue.includes('%') && !/^\d+%$/.test(sanitizedValue)) {
				this[field] = sanitizedValue.replace('%', '') + '%';
			}
		},
		adjustIframeHeight() {
			const iframe = this.$el.querySelector('.iframepreview iframe');
			iframe.style.height = '0px';
			
			const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
			const scrollFrame = iframeDoc.querySelector('#app .fullview');
			console.log(scrollFrame)
			const style = iframeDoc.defaultView.getComputedStyle(scrollFrame);
			const contentEl = scrollFrame.querySelector('.location');
			const contentHeight = contentEl.offsetHeight + 60;
			
			iframe.style.height = '';
			
			this.height = contentHeight;
		},
		
	},
	mounted() {
	},
	beforeUnmount() {
	}
};
</script>

<style lang="stylus" scoped>

	.row
		align-items center

	.sizeinput
		width 50px
		margin 0 .5em
		vertical-align baseline

	.notice
		font-size 9.5pt
		text-align center
		opacity .45
		margin -1em 0 1.75em

	.fullviewheader
		flex-direction column
		margin 0
		padding .5em 
	.iframecode
		width 600px
		border 1px solid #aaa
		background white
		height 4em
		overflow-y auto
		padding .25em
		margin 0 .5em
		// margin .5em
	.scrollcontent
		padding 2px 0 12px
		margin 0
		align-items center
		justify-content flex-start
		display flex
		flex-direction column
		// background #fff
		padding 36px
		 &::before
			content ''
			flex-grow 1
		&::after
			content ''
			flex-grow 1
	.iframepreview
		display flex
		align-items center
		width 100%
		justify-content center
</style>
