<template>

<div class="modalouter">

	<div class="modalwindow info">
	<div class="modalinner">
		<p class="wassermeisterei">
			<a href="http://wassermeisterei.org">
				<img src="/img/logonetzwerk.png" >
			</a>
		</p>

		<div class="text">
			<p>
				Die Wassermeisterei ist ein Citizen Science Projekt im Hohen Fläming. In der trockensten Region Deutschlands stellen Bürger*innen Bodenfeuchte-Sensoren auf und sammeln Daten um Böden besser zu verstehen und Strategien für eine dürre-resiliente Landschaft zu entwickeln. Ziel ist die Errichtung von mindestens 100 Messstationen. Diese in Entwicklung befindliche Datenkarte dient der Visualisierung der gesammelten Daten. Mehr über das Projekt auf <a href="http://wassermeisterei.org">wassermeisterei.org</a>
			</p>
			
			<div class="credits">
				
			

				<p>
					<em>Projektleitung Wassermeisterei</em><br>
					Daniel Diehl
				</p>

				<p>
					<em>Design & Programmierung Datenkarte</em><br>
					Nikolaus Baumgarten
				</p>

				<p>
					<em>Mit freundlicher Unterstützung von</em><br>
					Zukunftsschusterei Smart City Modellprojekt Bad Belzig & Wiesenburg
				</p>

				<p>
					Kontakt: <a href="mailto:post@wassermeisterei.org">post@wassermeisterei.org</a>
				</p>

			</div>
			
		<p class="light verantwortlich">
				Inhaltlich Verantwortlich: Daniel Diehl, Lebendiger Lernort Arensnest e.V., Arensnest 3, 14827 Wiesenburg
			</p>

			<p class="light disclaimer">

				Diese Webseite verwendet keine Cookies und sammelt keine personenbezogenen Daten.
				
				Die Nutzung erfolgt ohne Anspruch auf Gewährleistung oder Haftung.

				Für Richtigkeit, Vollständigkeit und Aktualität der Inhalte wird keine Gewähr übernommen.

				Verwendete Open-Source-Software:
				
				Vue.js, Vue Router, Vue3-OpenLayers, html2pdf.js, qrcode.vue (MIT-Lizenz).

				OpenLayers (BSD-2-Clause-Lizenz).
				
				D3.js (BSD-3-Clause-Lizenz).
				
				Einzelne Icons übernommen und modifiziert von Google Material Symbols (Apache 2.0. Lizenz).

			</p>	
			
	
			
			<p class="logosunten">
				<img src="/img/zukunftsschusterei.png" class="zukunftsschusterei">
				<img src="/img/foerderleiste.png" class="foerderleiste">
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

export default {
	name: 'Info',
	components: { },
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
		handleOutsideClick(event) {
			if (!this.$el.querySelector('.modalwindow')?.contains(event.target)) {
				this.close();
			}
		}
	},
	mounted() {
		window.setTimeout(() => {
			document.addEventListener('click', this.handleOutsideClick);
		}, 1);
	},
	beforeUnmount() {
		document.removeEventListener('click', this.handleOutsideClick);
	},
};

</script>



<style lang="stylus" scoped>
	.info
		font-size 11pt
		padding-bottom 0
	p
		margin 1.4em 0
	em
		font-style normal
		font-weight 600
		color var(--logogruen)
		opacity .8
	.light
		color rgba(0,0,0,.45)
		font-size 11px
		font-size 9.5pt
		// margin .7em 0
		a
			text-decoration underline
			color unset
			font-weight normal
			&:hover
				color rgba(0,0,0,.8)
	a
		color var(--logorot)
		text-decoration none
		font-weight 600
		opacity .9
		&:hover
			text-decoration underline
	.wassermeisterei
		margin 1.5em 0 -.5em
		img
			max-width 60%
			min-width 350px
			margin-left -.25em
	.logosunten
		margin-top .8em
		margin-bottom 0
		display flex
		overflow-x hidden
		align-items center
		justify-content flex-end
		.foerderleiste
			width 75%
			margin-right -4em
		.zukunftsschusterei
			height 70px
			display none


</style>
