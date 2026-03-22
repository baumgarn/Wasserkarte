<template>
	<div class="menuwindow glossar">

		<div class="menuwindow-header">
			<h3>Bodenkunde</h3>
		</div>

		<div class="menuwindow-content">

			<div class="article-section">
				<h4 class="article-section-header headericon bodenfeuchte">Die gemessene Bodenfeuchte</h4>
				<p>
					Die Bodenfeuchte ist das Wasser im Boden. Das Wasser füllt die kleinen Hohlräume zwischen den festen Bestandteilen des Bodens. Die von den Sensoren gemessene Bodenfeuchte wird in <strong>Volumen (Vol %)</strong> angegeben, also der Wasseranteil im gesamten Bodenvolumen neben den anderen Bestandteilen wie mineralischer und organischer Substanz.
				</p>
				<p>
					Die Bodenfeuchte-Sensoren auf der Wasserkarte messen für eine bessere Vergleichbarkeit in bis zu vier festen Schichttiefen: <strong>10cm</strong>, <strong>30cm</strong>, <strong>60cm</strong> und <strong>80cm</strong>. So lässt sich erkennen, ob nur der
					Oberboden kurzfristig befeuchtet ist oder ob Wasser auch in tiefere Schichten vorgedrungen
					ist.
				</p>
			</div>

			<section class="article-section">
				<h4 class="article-section-header headericon pflanzenverfuegbar">Das pflanzenverfügbare Wasser – die nutzbare Feldkapazität (nFK %)</h4>
				<p>
 					Die nutzbare Feldkapazität besagt wie viel Wasser im Boden die Pflanzen wirklich aufnehmen und nutzen können. Ein Teil des Wassers ist nicht für die Wurzeln der Pflanzen zu erreichen. Wie viel das ist, hängt stark von Bodenart und Humusgehalt ab.
				</p>
				<p>
					Bei 

					<span class="nfkbg" :style="{ backgroundColor: dataModel.get_nfk_color_alpha(100, 0.9) }">100 <span class="lightsmall">nFK %</span></span> 
					
					ist die <strong>Feldkapazität</strong> erreicht, der Boden ist wassergesättigt. Das überschüssige Wasser läuft durch den Boden hindurch und versickert im Grundwasser.
				</p>
				<p>
					Bei 
					
					<span class="nfkbg" :style="{ backgroundColor: dataModel.get_nfk_color_alpha(0, 0.9) }">0 <span class="lightsmall">nFK %</span></span> 
					
					ist der <strong>Welkepunkt</strong> erreicht, die Restmenge an Wasser steht den Pflanzen nicht mehr zu Verfügung, sie ist zu stark an den Boden gebunden. Das nicht pflanzenverfügbare Wasser wird auch als Totwasser bezeichnet.
				</p>
				
				<p>
					Die Nutzbare Feldkapazität wird auf der Wasserkarte rechnerisch aus der gemessenen <strong>Bodenfeuchte</strong> und den Standorteigenschaften <strong>Bodenart</strong> und <strong>Humusgehalt</strong> modelliert. Die farbliche Codierung der Elemente auf der Wasserkarte wird aus der berechneten nutzbare Feldkapazität generiert.
				</p>

				<table class="nfk-table">
					<tbody>
						<tr v-for="label in nfkLabels" :key="label.originalIndex" :style="{ backgroundColor: levelColors[label.originalIndex] }">
							<td class="range">{{ getNfkRange(label.originalIndex) }}</td>
							<td class="unit"><span class="lightsmall">nFK %</span></td>
							<td class="name">{{ label.name }}</td>
						</tr>
					</tbody>
				</table>
				<p>
					Die Interpretation in verschiedene Trockenheitsstufen erfolgt frei nach der Einteilung des <a href="https://www.dwd.de/DE/fachnutzer/landwirtschaft/dokumentationen/allgemein/bodenfeuchte_farbskala_doku.html?nn=16102&lsbId=606604" target="_blank">Deutschen Wetterdienst</a>. Es handelt sich lediglich um eine Annäherung, da unterschiedliche Pflanzenarten sehr unterschiedliche Anforderungen an die Bodenfeuchte haben können.
				</p>

			</section>

			<section class="article-section">
				<h4 class="article-section-header">Bodenarten</h4>
				<p>
					Die Bodenart beschreibt die Mischung und Korngröße der mineralischen Bestandteile eines
					Bodens. Grundsätzlich sind vor allem Sand, Schluff und Ton wichtig. Lehm ist keine eigene
					Korngröße, sondern eine günstige Mischung aus diesen Anteilen.
				</p>

				<table class="soil-table">
					<tbody>
						<tr>
							<td>Sand</td>
							<td>grobe Körner, viele Grobporen</td>
							<td>Wasser versickert schnell, geringe Speicherfähigkeit</td>
						</tr>
						<tr>
							<td>Schluff</td>
							<td>mittlere Korngröße, viele Mittelporen</td>
							<td>oft gute Wasserspeicherung und gute Pflanzenverfügbarkeit</td>
						</tr>
						<tr>
							<td>Ton</td>
							<td>sehr feine Körner, viele Feinporen</td>
							<td>hohe Wasserspeicherung, aber ein Teil ist fest gebunden</td>
						</tr>
						<tr>
							<td>Lehm</td>
							<td>Mischung aus Sand, Schluff und Ton</td>
							<td>meist günstiges Verhältnis aus Speicherung, Durchlüftung und Verfügbarkeit</td>
						</tr>
					</tbody>
				</table>

				<p>
					Auf der Karte für den Fläming werden vor allem lehmbeeinflusste Sandböden und sandige Lehme
					dargestellt. Diese Böden sind für die Region typisch, weil sie zwischen eher trockenen
					Sandstandorten und besser wasserhaltenden Lehmböden liegen.
				</p>

				<table class="soil-table">
					<tbody>
						<tr>
							<td>sandiger Sand</td>
							<td>sehr sandig und durchlässig</td>
							<td>trocknet schnell aus, geringe Wasserspeicherung</td>
						</tr>
						<tr>
							<td>schwach lehmiger Sand</td>
							<td>Sand mit geringem Lehmanteil</td>
							<td>speichert Wasser etwas besser als reiner Sand</td>
						</tr>
						<tr>
							<td>mittel lehmiger Sand</td>
							<td>deutlich stärker lehmbeeinflusst</td>
							<td>oft günstiger für pflanzenverfügbares Wasser</td>
						</tr>
						<tr>
							<td>sandiger Lehm</td>
							<td>lehmiger Boden mit spürbarem Sandanteil</td>
							<td>höhere Wasserspeicherung bei meist guter Verfügbarkeit</td>
						</tr>
					</tbody>
				</table>

				<p class="article-note">
					Ein Boden mit hoher Gesamtfeuchte ist nicht automatisch gut versorgt: Entscheidend ist, wie viel
					davon in den für Pflanzen nutzbaren Poren steckt.
				</p>
			</section>

			<section class="article-section">
				<h4 class="article-section-header">Humusgehalt</h4>
				<p>
					Humus besteht aus zersetzten organischen Resten und verbessert die Bodenstruktur. Er erhöht die
					Wasserspeicherfähigkeit, stabilisiert das Porengefüge und unterstützt das Bodenleben.
				</p>
				<p>
					Vor allem leichte, sandige Böden profitieren stark von höherem Humusgehalt, weil Humus Wasser
					zusätzlich speichern kann. Im hier verwendeten Modell werden Humusklassen von <code>h0-1</code> bis <code>h4</code>
					berücksichtigt. Höherer Humusgehalt verändert dabei sowohl die Feldkapazität als auch das
					Totwasser der jeweiligen Bodenschicht.
				</p>
			</section>

			<section class="article-section">
				<h4 class="article-section-header">Wasserhaushalt</h4>
				<p>
					Für eine bessere Vergleichbarkeit werden verschiedenen Standortarten unterschieden:
				</p>
				<ul class="article-list">
					<li><Strong>Grundwassernah</Strong>: Standorte mit höherer Wasserversorgung aufgrund von Grundwassernähe.</li>
					<li><Strong>Bewässert</Strong>: regelmäßig bewässerte Standorte.</li>
					<li><Strong>Regenabhängig</Strong>: Standorte ohne Bewässerung und ohne nennenswerten Grundwassereinfluss.</li>
				</ul>
			</section>

			<section class="article-section">
				<h4 class="article-section-header">Modellierung</h4>
				<p>
					Die Berechnung erfolgt in <code>datamodel.php</code>.
					Dort sind für jede unterstützte Bodenart und Humusklasse Werte für Feldkapazität (FK) und
					Totwasser (TW) hinterlegt. Für jede gemessene Tiefe wird daraus die nutzbare Feldkapazität
					berechnet.
				</p>
				<p class="formula">
					nFK = ((Bodenfeuchte - Totwasser) / (Feldkapazität - Totwasser)) * 100
				</p>
				<p>
					Die Modellierung der durschnittlichen nFK % und Vol % Werte für jeden Standort erfolgt unter einbeziehung der Schichttiefen 10cm bis 60cm da diese für den Großteil der Pflanzen am relevantesten sind. Es wird ein Annäherungswert aus den verfügbaren Schichttiefen gebildet.
				</p>
			</section>

		</div>

	</div>

</template>

<script>

import { state } from '../state.js' 
import { dataModel } from '@/datamodel.js'

export default {
	name: 'Bodenkunde',
	components: {
		
	},
	setup() {
		return {state, dataModel};
	},
	computed: {
		nfkLabels() {
			return dataModel.nfk_labels.map((label, index) => ({
				...label,
				originalIndex: index
			})).reverse();
		},
		levelColors() {
			const labels = dataModel.nfk_labels;
			return labels.map((label, index) => {
				// first entry stays at 0
				if (index === 0) {
					return dataModel.get_nfk_color(0);
				}
				// other entry midway between nfk level bounds
				const prevValue = labels[index - 1].value;
				const midpoint = prevValue + (label.value - prevValue) / 2;
				return dataModel.get_nfk_color_alpha(midpoint, .9);
			});
		},
	},
	props: {
	},
	methods: {
		getNfkRange(index) {
			const labels = dataModel.nfk_labels;
			if (index === 0) {
				return '< 0';
			}
			if (index === labels.length - 1) {
				return '> ' + labels[index - 1].value + '';
			}
			return labels[index - 1].value + ' - ' + labels[index].value + '';
		},
	},
	watch: {
	
	},
	mounted() {
	}
}
</script>

<style lang="stylus" scoped>

.menuwindow.glossar
	width 440px
	width 600px

.article-intro
	margin 0 0 20px
	padding 4px 4px 0
	font-size 12px
	line-height 1.45

.article-section
	margin 0 0 18px
	padding 4px 4px
	font-size 12px
	line-height 1.45

.article-section-header
	font-size 10pt
	margin 0 0
	padding 7px 0 0
	text-transform none
	display flex
	align-items flex-end
	// flex-direction column

.headericon:before
	content: ''
	display block
	width 32px
	height 32px
	top 3px
	margin-right 6px
	background-size contain
	background-position center
	background-repeat no-repeat
	position relative

.headericon.bodenfeuchte:before
	background-image url(/img/tropfen_flat.png)
	background-size 60% 90%
	margin-right -5px
	opacity .9
	left -6px
.headericon.pflanzenverfuegbar:before
	background-image url(/img/plant.svg)
	opacity .7


.article-section:first-child h4
	border-top none
	margin-top -10px

p
	margin 1.3em 0

h4 + p
	margin-top 1em

a
	font-weight bold

.nfkbg
	display inline-block
	padding .1em .5em
	margin -.1em .1em
	border-radius 6px

.lightsmall
	font-size 90%

.article-note
	font-style italic
	color #444
	margin-bottom 0

.formula
	font-family monospace
	background #f5f5f5
	padding 6px 8px
	border-radius 3px

.article-list
	margin 10px 0 0
	padding-left 18px
	li
		margin 0 0 6px

table
	border-spacing 0
	border-bottom 1px solid #ddd
	margin 20px 0
	tr
		padding 0
	td
		border-top 1px solid #ddd
		padding 3px 0
		margin 0
		padding-right 16px

table.soil-table
	td:first-child
		white-space nowrap
		font-weight 600

table.nfk-table
	overflow hidden
	border-radius 4px
	border-bottom none
	td:first-child
		padding-left 10px 
	tr
		td
			border-top none
		td.range
			text-align right
		td.name
			text-align left


	
</style>
