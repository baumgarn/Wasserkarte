<template>

	<div class="sidebar info">

		<div class="scrollcontent">
			<div class="content">
				<div class="contentoben">


					<p class="wassermeisterei">
						<a href="http://wassermeisterei.org" class="logolink">
							<img src="/img/logonetzwerk.png">
						</a>
					</p>

					<p class="beschreibung">
						Die Wassermeisterei ist ein Citizen Science Projekt im Hohen Fläming. In der trockensten Region
						Deutschlands stellen Bürger*innen Bodenfeuchte-Sensoren auf und sammeln Daten um Böden besser zu
						verstehen und Strategien für eine dürre-resiliente Landschaft zu entwickeln. Geplant ist die
						Errichtung von 100 Messstationen. Die aktuell in Entwicklung befindliche Datenkarte zeigt alle
						Sensorstandorte und visualisiert die gesammelten Daten.
					</p>
					
					<p class="beschreibung">
						Die Farbkreise stellen die Bodenfeuchte in verschiedenen Schichttiefen dar.<br>
						Ein Klick auf die Standorte öffnet detaillierte Sensordaten.
					</p>
					
					<!-- <Legend /> -->

					<div class="credits">



						<p>
							<em>Erfahre mehr über das Projekt und werde Wassermeister*in auf</em><br><a class="redlink"
								href="http://wassermeisterei.org">wassermeisterei.org</a><br>
						</p>

						<p>
							<em>Kontakt</em><br>
							<a class="redlink" href="mailto:post@wassermeisterei.org">post@wassermeisterei.org</a>
						</p>

						<p>
							<em>Projektleitung Wassermeisterei</em><br>
							Daniel Diehl<br>
						</p>

						<p>
							<em>Design & Programmierung Datenkarte</em><br>
							Nikolaus Baumgarten
						</p>

						<p>
							<em>Mit freundlicher Unterstützung von</em><br>
							Zukunftsschusterei Smart City Modellprojekt Bad Belzig & Wiesenburg
						</p>



					</div>


				</div>
				<div class="contentunten">

					<div class="logos">

						<a href="https://zukunftsschusterei.de/" class="zukunftsschusterei">
							<img src="/img/zukunftsschusterei.png">
						</a>

						<a href="https://badbelzig-klimadaten.de/" class="klimadaten">
							<img src="/img/klimadaten.png">
						</a>

					</div>

					<div class="foerderleiste">
						Gefördert durch:
						<img src="/img/foerderleiste.png">
					</div>

					<p class="light verantwortlich">
						Inhaltlich Verantwortlich: Daniel Diehl, Lebendiger Lernort Arensnest e.V., Arensnest 3, 14827
						Wiesenburg
					</p>

					<p class="light disclaimer">

						Diese Webseite verwendet keine Cookies und sammelt keine personenbezogenen Daten.

						Einzelne Nutzereinstellungen werden im lokalen Speicher gesichert.

						Die Nutzung erfolgt ohne Anspruch auf Gewährleistung oder Haftung.

						Für Richtigkeit, Vollständigkeit und Aktualität der Inhalte wird keine Gewähr übernommen.

						Verwendete Open-Source-Software:

						Vue.js, Vue Router, Vue3-OpenLayers, html2pdf.js, qrcode.vue (MIT-Lizenz).

						OpenLayers (BSD-2-Clause-Lizenz).

						D3.js (BSD-3-Clause-Lizenz).

						Einzelne Icons übernommen und modifiziert von Google Material Symbols (Apache 2.0. Lizenz).

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

export default {
	name: 'Info',
	components: { Legend},
	props: {
	},
	data() {
		return {
		}
	},
	computed: {
	},
	methods: {
		close() {
			state.menuOpen.info = false;
		},
	},
	mounted() {
	},
	beforeUnmount() {
	},
};

</script>



<style lang="stylus" scoped>
	.sidebar.info
		z-index 10000
		background #fff
		line-height 1.4
	.scrollcontent
		background #fff
		font-size 10pt
		margin-top -8px
		padding 0 24px 12px
		height 100%
		max-width 100vw
		.content
			min-height 100%
			display flex
			flex-direction column
			// .contentoben
				// flex 1
	.beschreibung 
		color #333
		line-height 1.5
		font-size 10pt
		
	p
		margin 1.4em 0
	em
		font-style normal
		font-weight 600
		color var(--logogruen)
		opacity .8
		font-size 10pt
	.light
		color rgba(0,0,0,.5)
		font-size 11px
		font-size 8pt
		margin 0
		a
			text-decoration underline
			color unset
			font-weight normal
			&:hover
				color rgba(0,0,0,.8)
	a
		text-decoration none
		font-weight 600
		&:hover
			text-decoration underline
	.redlink
		font-size 11pt
		color var(--logorot)
		opacity .9
	a.logolink
		opacity 1
	.wassermeisterei
		margin 1.5em 0 -1em
		img
			width 60%
			max-width 100%
			margin-left -.25em
	
	.logos
		margin 24px 0
		display flex
		flex-direction row
		> *
			flex-basis 38%
		.zukunftsschusterei img
			height 75px
		.klimadaten img
			height 45px
			margin-top 25px
	

			
	.foerderleiste
		margin 24px 0 36px
		img
			width 100%
			margin-top 26px

	@media (max-width 500px)
		.wassermeisterei img
			width 100%
</style>


<style lang="stylus" scoped>
	.is-mobile .info .logos
		> *
			flex-basis 50%
		.zukunftsschusterei img
			height 60px
		.klimadaten img
			height 36px
			margin-top 20px
</style>