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
					Trockenstress
				</h4>

				<div class="glossar-item-content">
					Standorte mit einer nutzbaren Feldkapazität von unter 30% werden in der Statusanzeige der Wasserkarte als Trockenstress einbezogen. 
				</div>

			</div>

			<div class="glossar-item">

				<h4 class="glossar-item-header">
					Welkepunkt
				</h4>

				<div class="glossar-item-content">
					Nicht alles Wasser im Boden können Pflanzen aufnehmen. Ein Teil ist so fest an die Erde gebunden, dass Wurzeln es nicht mehr erreichen – dieses Wasser nennt man Totwasser. Der Welkepunkt ist die Grenze, ab der Pflanzen zu welken beginnen, weil sie kein nutzbares Wasser mehr finden.
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
	},
	props: {
	},
	methods: {
	
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
		padding-right 24px
		padding-right 12px


	
</style>