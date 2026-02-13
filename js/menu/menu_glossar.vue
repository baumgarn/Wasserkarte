<template>
	<div class="menuwindow glossar">

		<div class="menuwindow-header">
			<h3>Bodenkunde Glossar</h3>
		</div>

		<div class="menuwindow-content">


			<div class="glossar-item">

				<h4 class="glossar-item-header">
					Bodenfeuchte 
				</h4>

				<div class="glossar-item-content">
					ist das Wasser im Boden. Der Boden ist wie ein Schwamm – er kann Wasser aufsaugen und speichern. Das Wasser füllt die kleinen Hohlräume zwischen den festen Bestandteilen des Bodens.
				</div>

			</div>
			
			<div class="glossar-item">

				<h4 class="glossar-item-header">
					Bodenfeuchte in Volumen %
				</h4>

				<div class="glossar-item-content">
					zeigt den prozentualen Anteil von Wasser im gesamten Bodenvolumen. Dieser kann nie 100% sein, da sich nur die Hohlräume, die sogenannten Bodenporen, zwischen den mineralischen Bodenteilchen mit Wasser füllen können.
				</div>

			</div>
			<div class="glossar-item">

				<h4 class="glossar-item-header">
					Bodenporen 
				</h4>

				<div class="glossar-item-content">

					sind winzige Hohlräume im Boden, gefüllt mit Wasser oder Luft. Große Poren lassen Wasser schnell durchsickern, kleine halten es zu fest. In den mittleren Poren steckt das Wasser, das Pflanzen nutzen können.

					<table>
						<tbody>

							<tr>
								<td>Grobporen</td><td>> 50μm</td><td>Wasser läuft hindurch</td>
							</tr>
							<tr>
								<td>Mittelporen</td><td>2-50μm</td><td>Pflanzenverfügbarer Wasserspeicher</td>
							</tr>
							<tr>
								<td>Feinporen</td><td>< 2μm</td><td>Wasser zu fest an Boden gebunden</td>
							</tr>

						</tbody>
					</table>
				</div>

			</div>

			<div class="glossar-item">

				<h4 class="glossar-item-header">
					Gesamtkapazität 
				</h4>

				<div class="glossar-item-content">
					Die Gesamtkapazität beschreibt, wie viel Wasser eine bestimmte Bodenart maximal speichern kann. Das ist die Wassermenge, die nach einem Regen im Boden bleibt, wenn alles überschüssige Wasser abgeflossen ist.
				</div>

			</div>

			<div class="glossar-item">

				<h4 class="glossar-item-header">
					Humus
				</h4>

				<div class="glossar-item-content">
					bezeichnet abgestorbene Pflanzen- und Tierreste im Boden, die durch Mikroorganismen zersetzt wurden. Dadurch machen sie den Boden fruchtbar, speichern Wasser und Nährstoffe und sorgen dafür, dass der Boden gesund und lebendig bleibt.
				</div>

			</div>

			<div class="glossar-item">

				<h4 class="glossar-item-header">
					Nutzbare Feldkapazität (nFK %)
				</h4>

				<div class="glossar-item-content">
					bezeichnet den Anteil des pflanzenverfügbaren Wassers in Prozent. Bei 100% nFK ist der Boden wassergesättigt. Das überschüssige Wasser läuft durch den Boden hindurch und versickert im Grundwasser. Bei 0% nFK ist der Welkepunkt erreicht, die Restmenge an Wasser steht den Pflanzen nicht mehr zu Verfügung, sie ist zu stark an den Boden gebunden. 
				</div>

			</div>

			<div class="glossar-item">

				<h4 class="glossar-item-header">
					Pflanzenverfügbar
				</h4>

				<div class="glossar-item-content">
					besagt wie viel Wasser im Boden die Pflanzen wirklich aufnehmen und nutzen können. Ein Teil des Wassers ist nicht für die Wurzeln der Pflanzen zu erreichen. Wie viel das ist, hängt stark von Bodenart und Humusgehalt ab. Der pflanzenverfügbare Wasseranteil des Bodens wird als nutzbare Feldkapazität bezeichnet.
				</div>

			</div>

			<div class="glossar-item">

				<h4 class="glossar-item-header">
					Trockenheitsstufen
				</h4>

				<div class="glossar-item-content">
					Die Interpretation der nutzbaren Feldkapazität (nFK %) in verschiedene Trockenheitsstufen erfolgt frei nach der Einteilung des <a href="https://www.dwd.de/DE/fachnutzer/landwirtschaft/dokumentationen/allgemein/bodenfeuchte_farbskala_doku.html?nn=16102&lsbId=606604" target="_blank">Deutschen Wetterdienst</a>. Es handelt sich lediglich um eine Annäherung, da unterschiedliche Pflanzenarten sehr unterschiedliche Anforderungen an die Bodenfeuchte haben können.
					<table class="nfk-table">
						<tbody>
							<tr v-for="label in nfkLabels" :key="label.originalIndex" :style="{ backgroundColor: levelColors[label.originalIndex] }">
								<td class="range">{{ getNfkRange(label.originalIndex) }}</td>
								<td class="name">{{ label.name }}</td>
							</tr>
						</tbody>
					</table>

				</div>

			</div>

			<div class="glossar-item">

				<h4 class="glossar-item-header">
					Trockenstress
				</h4>

				<div class="glossar-item-content">
					Standorte mit einer nutzbaren Feldkapazität von unter 30% werden in der Statusanzeige der Wasserkarte als Trockenstress einbezogen. Die Statusanzeige soll eine Tendenz sichtbar machen, ist aber nicht als objektive Messung zu verstehen, da sie von der sich im Laufe der Zeit verändernden Anzahl und Auswahl der Sensorstandorte abhängt.
				</div>

			</div>

			<div class="glossar-item">

				<h4 class="glossar-item-header">
					Welkepunkt
				</h4>

				<div class="glossar-item-content">
					Nicht alles Wasser im Boden können Pflanzen aufnehmen. Ein Teil ist so fest an die Erde gebunden, dass Wurzeln es nicht mehr erreichen – dieses Wasser nennt man Totwasser. Der Welkepunkt ist die untere Grenze der nutzbaren Feldkapazität (nFK < 0 %), ab der Pflanzen zu welken beginnen, weil sie kein nutzbares Wasser mehr finden.
				</div>

			</div>
			

		</div>

	</div>

</template>

<script>

import { state } from '../state.js' 
import { dataModel } from '@/datamodel.js'

export default {
	name: 'Glossar',
	components: {
		
	},
	setup() {
		return {state, dataModel};
	},
	data() {
		return {
			alpha: 0.4
		}
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
				return '< 0 %';
			}
			if (index === labels.length - 1) {
				return '> ' + labels[index - 1].value + ' %';
			}
			return labels[index - 1].value + ' - ' + labels[index].value + ' %';
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



.glossar-item
	margin 0 0 16px
	padding 4px 4px
	font-size 12px

.glossar-item-header
	font-size 9.5pt
	margin 0 0 8px
	padding 6px 0 0

.glossar-item:first-of-type .glossar-item-header
	border-top none
	margin-top 0
.glossar-item:first-of-type
	padding-top 0

table
	border-spacing 0
	border-bottom 1px solid #ddd
	margin 12px 0 0
	tr
		padding 0
	td
		border-top 1px solid #ddd
		padding 3px 0
		margin 0
		padding-right 12px

table.nfk-table
	border-bottom none
	tr
		td
			border-top none
		td.range
			// width 80px
			text-align right
			padding-right 16px
			padding-left 8px
		td.name
			text-align left


	
</style>