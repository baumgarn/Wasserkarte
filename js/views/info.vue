<template>

	<div class="sidebar info">

		<div class="scrollcontent">
			<div class="content">
				<div class="infoschicht" :style="'background:'+ dataModel.get_nfk_color_alpha(120, alpha)">
					<h1>
						W<span class="tropfenA" v-if="state.colorScheme == 'normal'">A<div class="tropfenbild"></div></span><span v-else class="tropfenFarbschema">A<div class="tropfenbild" :style="'background:'+dataModel.get_nfk_color(120)"></div></span>SSERKARTE
					</h1>
					Diese Karte befindet sich aktuell in Entwicklung. Inhalt und Funktion kann sich ändern. 
				</div>
				<!-- <div class="infoschicht" :style="'background:'+coloralpha(100)"> -->
				<div class="infoschicht" :style="'background:'+dataModel.get_nfk_color_alpha(70, alpha)">
					<h2>Wie feucht sind unsere Böden wirklich?</h2>
					<p>
						Die Wassermeisterei ist ein Citizen Science Projekt im Hohen Fläming. In der trockensten Region Deutschlands stellen Bürger*innen Bodenfeuchte-Sensoren auf und sammeln Daten um Böden besser zu verstehen und Strategien für eine dürre-resiliente Landschaft zu entwickeln. Erfahre mehr über das Projekt und werde Wassermeister*in auf <a href="http://wassermeisterei.org">wassermeisterei.org</a>

					</p>
				</div>
				<div class="infoschicht" :style="'background:'+dataModel.get_nfk_color_alpha(50, alpha)">
					<h3>Benutzungshinweise</h3>
					<div class="legenditem">
						<div class="legendimage">
							<FauxMarker/>
						</div>
						<div class="legendtext">
							Die Farbkreise zeigen die gemessene Bodenfeuchte in verschiedenen Schichttiefen.<br>
							Klick auf die Standorte öffnet detaillierte Sensordaten.
						</div>
					</div>

					<Legend/>

					<div class="legenditem clickable" @click="openMenu('orte', $event )">
						<div class="legendimage">
							<div class="icon orte"></div>
						</div>
						<div class="legendtext">
							Liste aller Sensorstandorte
						</div>
					</div>
					<div class="legenditem clickable" @click="openMenu('bodenfeuchte', $event )">
						<div class="legendimage">
							<div class="icon bodenfeuchte"></div>
						</div>
						<div class="legendtext">
							Optionen für Bodenfeuchte Kartendarstellung
						</div>
					</div>
					<div class="legenditem clickable" @click="openMenu('karten', $event )">
						<div class="legendimage">
							<div class="icon karten"></div>
						</div>
						<div class="legendtext">
							Karten der Landesvermessung und Geobasisinformation Brandenburg (LGB)
						</div>
					</div>
					<div class="legenditem clickable" @click="openMenu('glossar', $event )">
						<div class="legendimage">
							<div class="icon glossar"></div>
						</div>
						<div class="legendtext">
							Glossar zu Bodenkundethemen
						</div>
					</div>
					<div class="legenditem clickable" @click="openMenu('einstellungen', $event )">
						<div class="legendimage">
							<div class="icon einstellungen"></div>
						</div>
						<div class="legendtext">
							Einstellungen sowie alternative Farbschemata
						</div>
					</div>
					<div class="legenditem">
						<div class="legendimage">
							<div class="icon moreinfo"></div>
						</div>
						<div class="legendtext">
							In der Standortansicht lassen sich hier Iframes sowie QR Codes erstellen
						</div>
					</div>

				</div>
				<div class="infoschicht" :style="'background:'+dataModel.get_nfk_color_alpha(0, alpha)">
					<h3>Umsetzung des Projektes</h3>
					
					<p>
						Die Durchführung des Projektes erfolgt in Zusammenarbeit zwischen dem Verein Lebendiger Lernort Arensnest und
						dem Smart City Modellprojekt der Stadt Bad Belzig (Zukunftsschusterei).
					</p>
					<p>
						Projektleitung Wassermeisterei: Daniel Diehl<br>
						Projektleitung Zukunftsschusterei: Malte Specht<br>
						Design und Programmierung Wasserkarte: Nikolaus Baumgarten<br>
					</p>
						<div class="settings-item">
						<input type="checkbox" id="showinfoonstart" v-model="state.showInfoOnStart">
						<label for="showinfoonstart">Infofenster bei Start anzeigen</label>
					</div>
				</div>
				<div class="infoschicht">
			
					<div class="logos">
						<a href="http://wassermeisterei.org" class="wassermeisterei">
							<img src="/img/wassermeistereilogo.png">
						</a>
						
						<a href="https://zukunftsschusterei.de/" class="zukunftsschusterei">
							<img src="/img/zukunftsschusterei.png">
						</a>

						<a href="https://badbelzig-klimadaten.de/" class="klimadaten">
							<img src="/img/klimadaten.png">
						</a>

					</div>

					<div class="foerderleiste">
						Gefördert durch:<br>
						<img src="/img/foerderleiste.png">
					</div>

					<p class="impressum">
						Inhaltlich Verantwortlich: Daniel Diehl, Lebendiger Lernort Arensnest e.V., Arensnest 3, 14827
						Wiesenburg.

						Diese Webseite verwendet keine Cookies und sammelt keine personenbezogenen Daten.

						Einzelne Nutzereinstellungen werden im lokalen Speicher gesichert.

						Die Nutzung erfolgt ohne Anspruch auf Gewährleistung oder Haftung.

						Für Richtigkeit, Vollständigkeit und Aktualität der Inhalte wird keine Gewähr übernommen.

						Verwendete Open-Source-Software:

						Vue.js, Vue Router, Vue3-OpenLayers, html2pdf.js, qrcode.vue, pako (MIT-Lizenz).

						OpenLayers (BSD-2-Clause-Lizenz).

						D3.js (BSD-3-Clause-Lizenz).

						Einzelne Icons übernommen und modifiziert von Google Material Symbols (Apache 2.0. Lizenz), Noto Emoji (SIL Open Font Lizenz 1.1).

						<a href="/lizenzen/lizenzen.txt" target="_blank">Lizenzinformationen</a>
					</p>

				</div>


			</div>

			<div class="windowbuttons plain">

				<div class="iconbutton close" v-on:click="close()"></div>

			</div>

		</div>

	</div>

</template>

<script>

import { state } from '@/state.js';
import dataStore from '@/datastore.js';
import Legend from '@/map/legend.vue';
import FauxMarker from '@/map/legend_faux_marker.vue';

import { dataModel } from '@/datamodel.js'

export default {
	name: 'Info',
	components: { Legend, FauxMarker},
	props: {
	},
	setup() {
		return { state, dataModel
		}
	},
	data() {
		return {
			alpha: 0.25
		}
	},
	computed: {
	},
	methods: {
		close() {
			state.menuOpen.info = false;
		},
		
		openMenu(key) {
			if (state.menuOpen[key]){
				state.menuOpen[key] = false;

			} else {

				state.menuOpen.orte = false;
				state.menuOpen.bodenfeuchte = false;
				state.menuOpen.karten = false;
				state.menuOpen.glossar = false;
				state.menuOpen.einstellungen = false;
				if (state.isMobile) {
					state.menuOpen.info = false;
				}
				state.menuOpen[key] = true;
			}
		},
	},
	mounted() {
	},
	beforeUnmount() {
	},
};

</script>



<style lang="stylus" scoped>
	h1
		font-size 48px
		margin -12px 0 0
		.tropfenA
			display inline-block
			position relative
			color transparent
			margin 0
			margin-left -.0775em
			margin-right -.05em
			height .8em
			top .3em
			background url(/img/tropfen.png) center / contain no-repeat
		.tropfenFarbschema
			display inline-block
			position relative
			color transparent
			margin 0
			margin-left -.0775em
			margin-right -.05em
			height .8em
			top .3em
			.tropfenbild
				position absolute
				left 0
				top 0
				right 0
				bottom 0
				background white
				mask-image: url(/img/tropfen_schwarz.png);
				mask-size: contain;
				mask-position center;
				mask-repeat: no-repeat;
				-webkit-mask-image: url(/img/tropfen_schwarz.png);
				-webkit-mask-size: contain;
				-webkit-mask-position: contain;
				-webkit-mask-repeat: no-repeat;
				&:after
					background url(/img/tropfen_highlight.png) center / contain no-repeat
					content ''
					position absolute
					left 0
					top 0
					right 0
					bottom 0
	.is-mobile h1
		font-size 36px
	h2
		font-size 16px
		margin 0 0 10px
	h3
		font-size 14px
		margin 0 0 10px
	a
		font-weight bold
	p
		margin 8px 0
	em.hi
		font-style normal
		font-weight 500
	p + p
		margin-top 12px
	.settings-item
		margin 16px 0 10px
	.sidebar.info
		z-index 10000
		background #fff
		line-height 1.4
		height 100vh
	.scrollcontent
		background #fff
		font-size 12px
		padding 0 0 12px
		height 100vh
		max-width 100vw
		.content
			min-height 100%
			display flex
			flex-direction column
	.logos
		margin 24px 0
		display flex
		flex-direction row
		align-items center
		gap 5%
		img
			width 100%
		> *
			flex-grow 0
			flex-shrink 0
		.wassermeisterei
			flex-basis 40%
		.zukunftsschusterei 
			flex-basis 22%
			img
				margin-top -12px
		.klimadaten 
			flex-basis 27%
	
	.impressum
		font-size 10px
		a
			font-weight normal
			
	.foerderleiste
		margin 24px 0 36px
		img
			width 55%
			margin-top 26px

	.infoschicht 
		padding 12px 24px

	.legenditem
		display flex
		flex-direction row
		align-items center
		&:last-of-type
			margin-bottom 2px
		&.clickable
			cursor pointer
		.legendimage
			flex-basis 36px
			flex-grow 0
			flex-shrink 0
			position relative
			min-height 20px
			margin-right 8px
			display flex
			align-items center
			justify-content center
		.legendtext
			flex-grow 1
		.icon
			width 36px !important
			height 36px !important
		.icon.moreinfo
			width 28px !important
			height 28px !important
</style>

