<template>
	<div class="fullview qrcode">

		<div class="windowrow">

			<MenuDevicesMultiselect :sideview="true" />

			<div class="windowcol">
				
				<div class="fullviewheader">

					
					<SelectGroup
					:items="[	{ label: 'Schild', value: 'schild' },
								{ label: 'QR Code', value: 'qrcode' },]"
					 	v-model="qrcodeStyle" />
						 
					<div class="info">
						Schilder und mehrere QR Codes werden als PDF gespeichert.<br>
						Technisch bedingt ist der PDF Seiteninhalt eine Rastergrafik.<br> 
						Einzelne QR Codes werden als SVG gespeichert. 
					</div>

					<button @click="download">Speichern</button>
				</div>

				<div class="scrollcontent">

					<div class="pdfelement scaled" ref="pdfelement">
						<template  v-for="(name, index) in devices">
							
							<a :href="url(name)" target="_blank">

								<div class="page" :style="{ width: pagewidth + 'mm', height: pageheight + 'mm' }">
									<QrCodeContent v-if="qrcodeStyle=='qrcode'" :name="name" />
									<QrCodeSchildContent v-else-if="qrcodeStyle=='schild'" :name="name" />
								</div>

							</a>

						</template>

					</div>

				</div>
			</div>
			
			<div class="windowbuttons plain">
				<div class="iconbutton close" v-on:click="close()"></div>
			</div>

		</div>
	</div>
</template>

<script>
import html2pdf from 'html2pdf.js';
import html2canvas from 'html2canvas';
import { jsPDF } from 'jspdf';
import { state } from '@/state.js';
import dataStore from '@/datastore.js';
import SelectGroup from '@/ui/selectgroup.vue';
import QrCodeSchildContent from '@/views/qrcode_schildcontent.vue';
import QrCodeContent from '@/views/qrcode_qrcodecontent.vue';
import MenuDevicesMultiselect from '@/menu/menu_devices_multiselect.vue';


export default {
	components: { QrCodeContent, QrCodeSchildContent, MenuDevicesMultiselect, SelectGroup },
	data() {
		return {
			schildwidth: 148,
			schildheight: 210,
			qrcodewidth: 100,
			qrcodeheight: 100,
			scale: 1,
			qrcodeStyle : 'schild',
		};
	},
	computed: {
		pagewidth() {
			return (this.qrcodeStyle == 'schild') ? this.schildwidth : this.qrcodewidth;
		} ,
		pageheight() {
			return (this.qrcodeStyle == 'schild') ? this.schildheight : this.qrcodeheight;
		} ,
		devices() {
			return state.devicesMultiselect
		},
	
		scaleVariable() {
			return this.scale;
		}
	},
	methods: {
		url(name) {
			return `${window.location.origin}/standort/${name}`;
		},
		close() {
			this.$router.push(`/`);
		},
		download() {
			if (this.devices.length == 1 && this.qrcodeStyle == 'qrcode') {
				this.downloadSvg();
			} else {
				this.downloadPdf();
			}
		},
		downloadSvg() {
			const svg = document.querySelector('.qrcodesvg');
			if (!svg) {
				console.error('No SVG element found.');
				return;
			}

			const clone = svg.cloneNode(true);

			if (!clone.getAttribute('xmlns')) {
				clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
			}

			const svgData = new XMLSerializer().serializeToString(clone);
			const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
			const url = URL.createObjectURL(blob);

			const link = document.createElement('a');
			link.href = url;
			link.download = `qrcode_${this.devices[0]}.svg`
			document.body.appendChild(link);
			link.click();

			document.body.removeChild(link);
			URL.revokeObjectURL(url);
		},
		async downloadPdf() {
			
			const element = this.$refs.pdfelement;
			const sections = Array.from(element.querySelectorAll('.page'));
			
			if (this.devices.length > 1) {
				var filename = 'schilder_wassermeisterei.pdf'
			} else {
				var filename = `schild_${this.devices[0]}.pdf`
			}
			
			const opt = {
				margin: 0,
				filename: filename,
				image: { type: 'png' },
				html2canvas: { scale: this.qrcodeStyle == 'schild' ? 3 : 2 },
				jsPDF: { unit: 'mm', format: [this.pagewidth, this.pageheight], orientation: 'portrait' }
			};

			const pdf = new jsPDF(opt.jsPDF);

			for (let i = 0; i < sections.length; i++) {
				const clone = sections[i].cloneNode(true);

				// Wrap clone in a container so html2canvas renders correctly
				const container = document.createElement('div');
				container.style.width = `${this.pagewidth}mm`;
				container.style.height = `${this.pageheight}mm`;
				container.style.overflow = 'hidden';
				container.appendChild(clone);

				document.body.appendChild(container); // Needed for rendering

				const canvas = await html2canvas(container, opt.html2canvas);
				const imgData = canvas.toDataURL('image/png');

				if (i > 0) pdf.addPage();
				pdf.addImage(imgData, 'PNG', 0, 0, this.pagewidth, this.pageheight);

				document.body.removeChild(container);
			}

			pdf.save(filename);
			

		},
		updateScale() {
			const scrollcontent = this.$refs.scrollcontent;
			if (scrollcontent) {
				const availableHeight = scrollcontent.offsetHeight;
				const pageHeight = this.pageheight * 37.795; // Convert cm to pixels (1 cm ≈ 37.795 px)
				this.scale = (availableHeight * 0.9) / pageHeight; // Scale to 90% of available height
				this.setScaleVariable(); // Apply updated scale
			}
		},
		setScaleVariable() {
			document.documentElement.style.setProperty('--scale', this.scale);
		}
	},
	mounted() {
		this.updateScale();
		this.setScaleVariable();
		window.addEventListener('resize', this.updateScale);
	},
	beforeUnmount() {
		window.removeEventListener('resize', this.updateScale);
	}
};
</script>

<style lang="stylus" scoped>
.inner
	display flex
	flex-direction column
	align-items center
	justify-content center
	min-height 100%


.scrollcontent
	padding 24px 0
	.pdfelement 
		width 100%
		display flex
		justify-content center
		align-items center
		flex-direction column
		position relative
		min-height calc( 100%)
		.page
			box-shadow 0 1px 1px 0 #00000011
		.page:first-child
			margin-top 2em
		.url
			opacity .45
			margin-top .25em
			margin-bottom 2em



.fullviewheader
	flex-grow 0
	display flex
	flex-direction row
	gap 24px
	align-items center
	justify-content center

button
	font-size 120%
	padding 6px 12px
	cursor pointer

.info
	font-size 12px

</style>
