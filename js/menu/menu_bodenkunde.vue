<template>
	<div class="menuwindow bodenkunde">

		<div class="close iconbutton" v-if="state.isMobile" @click="close"></div>
		
		<div class="menuwindow-content" ref="scrollContainer">
						
			<h3>Hintergrundwissen Bodenkunde</h3>
			
			<div v-if="tocEntries.length" class="inhalt" aria-label="Inhaltsverzeichnis">
				<template v-for="entry in tocEntries" :key="entry.id">
						<a class="inhalt-link" @click="scrollToSection(entry.id)">
							{{ entry.text }}
						</a>
				</template>
			</div>

			<div ref="contentContainer">
	
				<section class="article-section">
					
					<Icon type="pflanze" size="48"/>	
					<h4 class="article-section-header" inhaltname="Nutzbare Feldkapazität">
						Nutzbare Feldkapazität – das pflanzenverfügbare Wasser
					</h4>
					
					<p>
						Die von den Sensoren gemessene <strong>Bodenfeuchte</strong> wird in <strong>Volumen (Vol %)</strong> ausgegeben. Damit ist der prozentuale Anteil von Wasser im gesamten Bodenvolumen gemeint, neben den anderen Bestandteilen des Bodens wie mineralischer und organischer Substanz.
					</p>

					<p>
						Die <strong>nutzbare Feldkapazität (nFK %)</strong> besagt nun wie viel Wasser im Boden die Pflanzen wirklich aufnehmen und nutzen können. Ein Teil des Wassers ist nicht für die Wurzeln der Pflanzen zu erreichen. Wie viel das ist variiert stark je nach <strong>Bodenart</strong> und <strong>Humusgehalt</strong>.
					</p>

					<p>
						Bei 

						<span class="nfkbg" :style="{ backgroundColor: dataModel.get_nfk_color_alpha(100, 0.9) }"><span class="value">100</span> <span class="lightsmall">nFK %</span></span> 
						
						ist die <strong>Feldkapazität</strong> erreicht. Der Boden ist wassergesättigt und kann kein weiteres Wasser gegen die Schwerkraft halten. Das überschüssige Wasser läuft durch den Boden hindurch und versickert im Grundwasser. Pflanzen leiden möglicherweise unter Sauerstoffmangel.
					</p>
					<p>
						Bei 
						
						<span class="nfkbg" :style="{ backgroundColor: dataModel.get_nfk_color_alpha(0, 0.9) }"><span class="value">0</span> <span class="lightsmall">nFK %</span></span> 
						
						ist der <strong>Welkepunkt</strong> erreicht. Es kann sich immer noch Wasser im Boden befinden. Die Restmenge steht den Pflanzen aber nicht mehr zu Verfügung, da das Wasser zu stark an den Boden gebunden ist. Dieses nicht pflanzenverfügbare Wasser wird auch als <strong>Totwasser</strong> bezeichnet.
					</p>
				
					<table class="nfk-table">
						<tbody>
							<tr v-for="label in nfkLabels" :key="label.originalIndex" :style="{ backgroundColor: levelColors[label.originalIndex] }">
								<td class="range" v-html="getNfkRange(label.originalIndex)"></td>
								<td class="unit"><span class="lightsmall">nFK %</span></td>
								<td class="name">{{ label.name }}</td>
							</tr>
						</tbody>
					</table>

					<em class="note nfk-note">
						Trockenheitsstufen angepasst nach <a href="https://www.dwd.de/DE/fachnutzer/landwirtschaft/dokumentationen/allgemein/bodenfeuchte_farbskala_doku.html?nn=16102&lsbId=606604" target="_blank">DWD</a>
					</em>
						
				</section>

				<section class="article-section">
					<h4 class="article-section-header">Bodenarten</h4>
					<p>
						Die Bodenart beschreibt die Mischung und Korngröße der mineralischen Bestandteile eines Bodens. Man unterscheidet die drei Grundarten <strong>Sand</strong>, <strong>Schluff</strong> und <strong>Ton</strong>, während <strong>Lehm</strong> eine Mischung aus diesen ist. Die Korngröße bestimmt die Wasserspeicherfähigkeit des Bodens. Je größer die Körner, desto größer sind auch die Zwischenräume, die sogenannten <strong>Bodenporen</strong>. Große Poren lassen Wasser schnell versickern, kleine Poren halten es dagegen sehr fest. In mittleren Poren wird Wasser gut gespeichert und ist optimal pflanzenverfügbar.
					</p>


							<table class="soil-table">
								<tbody>
									<tr>
										<td :style="{ backgroundColor: dataModel.soilColors.sand}">
											<strong>Sand</strong><br>
											<div class="teilchen">2 mm – 0,063 mm</div>
											Grobe Körner,<br>
											Wasser versickert schnell, geringe Speicherfähigkeit
										</td>
										<td :style="{ backgroundColor: dataModel.soilColors.schluff}">
											<strong>Schluff</strong><br>
											<div class="teilchen">0,063 mm – 0,002 mm</div>
											Mittlere Korngröße,<br>
											gute Wasserspeicherung und Pflanzenverfügbarkeit
										</td>
										<td :style="{ backgroundColor: dataModel.soilColors.ton}">
											<strong>Ton</strong><br>
											<div class="teilchen">< 0,002 mm</div>
											Sehr feine Körner, <br>
											hohe Wasserspeicherung, aber ein Teil ist fest gebunden
										</td>
									</tr>
									<tr>
										<td class="lehm" :style="{ backgroundColor: dataModel.soilColors.lehm}">
											<div><strong>Lehm</strong></div> 
											<div class="teilchen">Mischung aus Sand, Schluff und Ton</div>
											Verschiedene Korngrößen,<br> gute Wasserspeicherung und Pflanzenverfügbarkeit
										</td>
									</tr>

								</tbody>
							</table>


							<p>
								Auf dem <a href="https://www.praxis-agrar.de/fileadmin/Medien/06_Service/00_Sammlung/0244_2397_Bodenartendreieck_interaktiv_web_01.pdf" target="_blank">Bodenartendreieck</a> werden die verschiedenen Abstufungen von Bodenarten anhand ihrer Mischung aus den Anteilen der drei Grundarten Sand, Schluff und Ton dargestellt. Für die Standorte im Fläming sind vor allem sandig-lehmige Böden relevant.
							</p>

							<SoilChart humus-key="h0" title="Unterschiedliche Bodenarten im Vergleich" />

								<p class="small">
									<strong>Feldkapazität</strong> ist die Wasserspeicherkapazität des Bodens. Wasser darüber hinaus kann nicht vom Boden gegen die Schwerkraft gehalten werden und fließt ins Grundwasser ab.
								</p>

								<p class="small">
									<strong>Welkepunkt</strong> ist die Grenze unterhalb der das Wasser nicht mehr für die Wurzeln der Pflanzen erreichbar ist, da es zu stark an den Boden gebunden ist.
								</p>


					
				</section>

				<section class="article-section">
					<h4 class="article-section-header">Humusgehalt</h4>
					<p>
						Humus besteht aus zersetzten organischen Resten und verbessert die Bodenstruktur. Er erhöht die
						Wasserspeicherfähigkeit, stabilisiert das Porengefüge und unterstützt das Bodenleben.
						Vor allem leichte, sandige Böden profitieren stark von höherem Humusgehalt, weil Humus Wasser
						zusätzlich speichern kann. Ein höherer Humusgehalt verschiebt die Feldkapazität und den Welkepunkt des Bodens nach oben.
					</p>

						<SoilChart
							mode="humus"
							:soil-key="state.bodenkundeHumusSoilKey"
							@update:soilKey="updateHumusSoilKey"
							title="Einfluss von Humusgehalt"
						/>

				</section>

				<section class="article-section">
					<h4 class="article-section-header">Wasserhaushalt</h4>
					<p>
						Für eine bessere Vergleichbarkeit werden alle Standorte verschiedenen Wasserhaushalts-Typen zugewiesen. Die Einteilung beschreibt die grundsätzliche Wasserversorgung eines Standorts und hilft dabei, Messwerte zwischen Standorten besser einzuordnen. Gemeint sind damit die standörtlichen Rahmenbedingungen und nicht der aktuelle Feuchtezustand an einem einzelnen Tag.


					</p>
					<div class="type-item-grid">
						<FilterItem
							v-for="item in waterTypeItems"
							:key="item.name"
							:obj="item"
							type="extensive"
						/>
					</div>
				</section>

				<section class="article-section">
					<h4 class="article-section-header">Nutzungsarten</h4>
					<p>
						Die Nutzungsart beschreibt, wie eine Fläche genutzt oder von Vegetation geprägt ist. Sie hilft dabei, Messwerte im Zusammenhang mit Bewuchs, Durchwurzelung und Wasserbedarf besser einzuordnen.
						Sie ist keine direkte Messgröße des Bodens, sondern eine ergänzende Standortinformation. So können ähnliche Bodenfeuchtewerte je nach Nutzungsart unterschiedlich zu bewerten sein, etwa bei Ackerflächen, Grünland oder Gehölzstandorten.
					</p>
					<div class="usage-type-grid">
						<FilterItem
							v-for="item in usageTypeItems"
							:key="item.name"
							:obj="item"
							type="extensive"
						/>
					</div>
					<p><em class="note">
						Angepasst nach <a href="https://www.berlin.de/sen/uvk/umwelt/bodenschutz-und-altlasten/vorsorgender-bodenschutz/informationsgrundlagen-fuer-den-bodenschutz/kartieranleitung/" target="_blank">Bodenkundliche Kartieranleitung Berlin</a>
					</em></p>
				</section>

				<section class="article-section">
					<h4 class="article-section-header">Modellierung</h4>
					<p>
						Die Sensoren auf der Wasserkarte messen für eine bessere Vergleichbarkeit in bis zu vier Tiefen: <strong>10cm</strong>, <strong>30cm</strong>, <strong>60cm</strong> und <strong>80cm</strong>. So lässt sich erkennen, ob nur der Oberboden kurzfristig befeuchtet ist oder ob Wasser auch in tiefere Schichten vorgedrungen ist.
					</p>
					<p>
						Die Modellierung der durchschnittlichen nutzbaren Feldkapazität für jeden Standort erfolgt unter Einbeziehung der Messtiefen des <strong>Oberbodens bis 60cm</strong>, da diese für die meisten Pflanzen am relevantesten sind. Für jede Messtiefe sind Bodenart und Humusgehalt hinterlegt, woraus sich Feldkapazität und Totwasserbereich ergibt. Aus der gemessenen Bodenfeuchte wird so zunächst für jede Messtiefe die nutzbare Feldkapazität berechnet:
					</p>
					<p class="formula">
						nFK = ((Bodenfeuchte - Totwasser) / (Feldkapazität - Totwasser)) * 100
					</p>
					<p>

						Für die Standortmittelwerte werden die je nach Standort unterschiedlich verfügbaren Messtiefen unterschiedlich gewichtet. Beispiel bei Messtiefen <strong>10cm</strong>, <strong>30cm</strong> und <strong>60cm</strong>:
						</p>

						<p class="formula">
							Standortwert = ((1,5 * Wert10) + (2,5 * Wert30) + (2 * Wert60)) / 6
						</p>

						<p>
							Die Standortmittelwerte bilden die Bodenverhältnisse damit also nur näherungsweise ab und dient vor allem der besseren Vergleichbarkeit zwischen Standorten.
					</p>
					<!-- <p>
						Der Quellcode für die Modellierung der nutzbaren Feldkapazität kann <a href="https://github.com/baumgarn/Wasserkarte/blob/main/api/datamodel.php">hier</a> eingesehen werden. Für die unterstützten Bodenarten und Humusgehalte sind Werte für Feldkapazität (FK) und Totwasser (TW) hinterlegt. Damit wird aus der gemessenen Bodenfeuchte die nutzbare Feldkapazität berechnet.
					</p> -->
					<!-- <p>
							nFK = ((Bodenfeuchte - Totwasser) / (Feldkapazität - Totwasser)) * 100
					</p> -->
				</section>

			</div>

		</div>

	</div>

</template>

<script>

import { state } from '../state.js' 
import { dataModel } from '@/datamodel.js'
import SoilChart from '@/charts/soilchart.vue'
import SoilChartOld from '@/charts/soilchartold.vue'
import Icon from '@/ui/icon.vue'
import FilterItem from '@/location/filteritem.vue'

export default {
	name: 'Bodenkunde',
	components: {
		SoilChart, SoilChartOld, Icon, FilterItem
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
		waterTypeItems() {
			return [
				dataModel.grundwasser_obj,
				dataModel.bewaessert_obj,
				dataModel.regenabhängig_obj
			];
		},
		usageTypeItems() {
			return Object.values(dataModel.usage_table);
		},
	},
	props: {
	},
	data() {
		return {
			tocEntries: []
		};
	},
	methods: {
		getNfkRange(index) {
			const labels = dataModel.nfk_labels;
			if (index === 0) {
				return '&lt; <span class="value">0</span>';
			}
			if (index === labels.length - 1) {
				return '&gt; <span class="value">' + labels[index - 1].value + '</span>';
			}
			return '<span class="value">' + labels[index - 1].value + '</span> - <span class="value">' + labels[index].value + '</span>';
		},
		close() {
			state.menuOpen.bodenkunde = false;
		},
		refreshTableOfContents() {
			const contentContainer = this.$refs.contentContainer;
			if (!contentContainer) {
				this.tocEntries = [];
				return;
			}

			const headings = Array.from(contentContainer.querySelectorAll('.article-section-header'));
			this.tocEntries = headings.map((heading, index) => {
				const id = `bodenkunde-section-${index + 1}`;
				const tocText = heading.getAttribute('inhaltname') || heading.textContent.trim();
				heading.id = id;
				return {
					id,
					text: tocText
				};
			});
		},
		scrollToSection(id) {
				const scrollContainer = this.$refs.scrollContainer;
				const contentContainer = this.$refs.contentContainer;
			const target = contentContainer?.querySelector(`#${id}`);

			if (!scrollContainer || !target) {
				return;
			}

			const containerRect = scrollContainer.getBoundingClientRect();
			const targetRect = target.getBoundingClientRect();
			const nextTop = scrollContainer.scrollTop + (targetRect.top - containerRect.top) +1;

			scrollContainer.scrollTo({
				top: Math.max(0, nextTop),
					behavior: 'smooth'
				});
			},
			updateHumusSoilKey(soilKey) {
				state.bodenkundeHumusSoilKey = soilKey;
			},
		},
	watch: {
	
	},
	mounted() {
		this.$nextTick(() => {
			this.refreshTableOfContents();
		});
	}
}
</script>

<style lang="stylus" scoped>

.menuwindow.bodenkunde
	width 440px
	width 600px
	--paragraphwidth 500px

.close
	position absolute
	top 8px
	right 12px

.menuwindow-header
	padding 6px calc(4% + 4px)

.is-mobile .menuwindow.bodenkunde
	top 0

.menuwindow.bodenkunde
	position fixed
	top calc( var(--menubariconsize) + 1px)
	left 0
	right 0
	bottom 0 !important
	max-width 100vw !important
	z-index 1000
	.menuwindow-header
		padding-right 10px

.menuwindow-content
	padding 6px 4% 2em

h3
	margin-top 8px
	font-size 12pt
	margin-bottom 8px

.article-section
	margin 16px 0
	padding 0
	max-width var(--paragraphwidth)
	font-size 9pt
	line-height 1.45
	margin-right 12px 

.article-section + .article-section
	border-top var(--thinline)
	padding-top 2em
	margin-top 3em

.inhalt
	margin 0 0 18px
	font-size 9pt
	display flex
	flex-wrap wrap
	.inhalt-link
		margin .1em 0
		white-space nowrap
		font-weight normal
		color var(--menusectionheadercolor)
		cursor pointer
		font-weight bold
		&:hover
			text-decoration underline		
	.inhalt-link:after
		content '•'
		display inline-block
		margin 0 3px
		opacity .6
		pointer-events none
		white-space nowrap
	.inhalt-link:last-child:after
		display none


.article-section-header
	scroll-margin-top 8px

.article-inhalt-link:hover
	text-decoration underline

.article-section-header
	font-size 13pt
	margin 0 0
	color #000
	padding 7px 0 0
	text-transform none
	display flex
	align-items flex-end


.article-section h4
	border-top none

.small
	font-size 8pt

p
	margin 1.3em 0
	max-width var(--paragraphwidth)

h4 .icon
	margin-right 6px

h4 + p
	margin-top 1em

a
	font-weight bold
	color var(--menusectionheadercolor)
	&:hover
		opacity .8


em.note
	font-size 7.5pt
	font-style normal
	color #00000088

code
	display inline-block
	background #eee
	padding .2em .5em
	border-radius 4px


.nfkbg
	display inline-block
	padding .1em .4em
	padding .15em .6em
	margin -.2em .15em
	border-radius 999px
	// filter: drop-shadow(0 1px 2px rgba(0,0,0,.22));

.lightsmall
	font-size 90%

.article-note
	font-style italic
	color #444
	margin-bottom 0

.formula
	margin 0
	padding 4px 8px
	display inline-block
	border-radius 3px
	letter-spacing .025em
	overflow hidden
	background var(--activecolorgreybrighter)

.article-list
	margin 10px 0 0
	padding-left 18px
	li
		margin 0 0 6px

table
	border-spacing 0
	border-bottom 1px solid #ddd
	margin 32px 0
	font-size 8pt
	// max-width var(--paragraphwidth)
	tr
		padding 0
	td
		border-top 1px solid #ddd
		padding 2px 0
		margin 0
		padding-right 16px

// NFK TABLE

table.nfk-table
	overflow hidden
	border-radius 6px
	border-bottom none
	margin-bottom 5px
	background #fff
	box-shadow 0 1px 4px 0 rgba(0,0,0,.22)
	td:first-child
		padding-left 18px 
		padding-right 8px
	tr
		td
			padding-right 27px 
			border-top none
		td.range
			text-align right
			color #000000bb
		td.unit
			color #000000bb
		td.name
			text-align left

table.nfk-table
.nfk-note
	width 58%
	min-width 250px
	margin-right calc(21% )
	margin-left calc(21% )
	margin-left auto
	margin-right auto
.nfk-note
	text-align right
	display block
	margin-bottom 32px 
	padding-right 2px

// SOIL TABLE			

table.soil-table
	border-bottom none
	margin-bottom 0
	tr	
		width 100%
		display: flex
		gap: 8px;
		margin-bottom 8px
		> *
			flex-grow 1
	td
	th
		width 33.333%
	td
		vertical-align top
		text-align center
		padding 8px 4px
		border none
		border-radius 8px
		overflow hidden
		box-shadow 0 1px 2px 0 rgba(0,0,0,.22)
		.teilchen
			margin 4px
			opacity .55
			// font-style italic
	td.lehm
		margin 0 calc(16.66% + 8px )
		margin 0 calc(21% )
		div
			margin-bottom 4px


table.soil-table tr:first-child td
	text-align center

table.soil-table td.row-label
	width 12%
	min-width 72px

.soilchart-block
	margin 18px 0 14px

.splitview
	display flex
	flex-direction row
	gap 8px
	margin 1.3em 0
	align-items flex-start
	justify-content flex-start
	> .icon
		margin 0
		// margin-left 4px
	&.aligncenter
		align-items center
	table
		flex-shrink 0
		flex-grow 0
	table
	p
		margin-top 0
		margin-bottom 0

.type-item-grid
	display grid
	gap 0
	margin-top 14px

.usage-type-grid
	display grid
	gap 0
	margin-top 14px

</style>
