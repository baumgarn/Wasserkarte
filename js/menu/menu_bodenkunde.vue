<template>
	<div class="menuwindow glossar">

		<div class="menuwindow-header">
			<h3>Hintergrundwissen Bodenkunde</h3>
		</div>
		
		<div class="menuwindow-content" ref="scrollContainer">
			
						<div v-if="tocEntries.length" class="inhalt" aria-label="Inhaltsverzeichnis">
							<!-- <ul class="article-inhalt-list"> -->
								<template v-for="entry in tocEntries" :key="entry.id">
									<!-- <li> -->
										<a class="inhalt-link" @click="scrollToSection(entry.id)">
											{{ entry.text }}
										</a>
									<!-- </li> -->
								</template>
							<!-- </ul> -->
						</div>
			
			<div ref="contentContainer">
	
			<section class="article-section">
				
				<Icon type="pflanze" size="42"/>	
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

						<em class="note">
							Trockenheitsstufen frei nach <a href="https://www.dwd.de/DE/fachnutzer/landwirtschaft/dokumentationen/allgemein/bodenfeuchte_farbskala_doku.html?nn=16102&lsbId=606604" target="_blank">DWD</a>
						</em>
					
			</section>

			<section class="article-section">
				<Icon type="soil" size="42"/>	
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
										hohe Wasserspeicherung, aber ein Teil fest gebunden
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
							Auf dem <a href="https://www.praxis-agrar.de/fileadmin/Medien/06_Service/00_Sammlung/0244_2397_Bodenartendreieck_interaktiv_web_01.pdf" target="_blank">Bodenartendreieck</a> werden die verschiedenen Abstufungen von Bodenarten anhand ihrer Mischung aus den Anteilen der drei Grundarten Sand, Schluff und Ton dargestellt. Auf der Wasserkarte sind für den Fläming vor allem Sandböden mit Lehmanteil relevant. Die folgende Grafik zeigt die unterschiedlichen Wasserspeicherkapazitäten und Eigenschaften der Böden im Fläming: 
						</p>

						<SoilChart humus-key="h0" class="soilchart-block" />

						<p>
							Die <strong>Feldkapazität</strong> ist die gesamte Wasserspeicherkapazität eines Bodens. Wasser darüber hinaus kann nicht vom Boden gegen die Schwerkraft gehalten werden und fließt ins Grundwasser ab. Dieses Wasser nennt man auch <strong>Sickerwasser</strong>.
						</p>
						<p>
							Das Wasser unterhalb des <strong>Welkepunktes</strong>, das sogenannte <strong>Totwasser</strong>, ist zu fest an den Boden gebunden und nicht mehr für die Wurzeln der Pflanzen erreichbar.
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
			</section>

			<section class="article-section">
				<h4 class="article-section-header">Wasserhaushalt</h4>
				<p>
					Für eine bessere Vergleichbarkeit werden verschiedenen Standortarten unterschieden:
				</p>
					<div class="splitview aligncenter">
						<Icon type="grundwasser" :shadow="true" />
						<p>
							<Strong>Grundwassernah</Strong><br> Standorte mit höherer Wasserversorgung aufgrund Grundwassernähe.

						</p>
					</div>
					<div class="splitview aligncenter">
						<Icon type="bewaessert" :shadow="true" />
						<p>
							<Strong>Bewässert</Strong><br>Regelmäßig bewässerte Standorte.

						</p>
					</div>
					<div class="splitview aligncenter">
						<Icon type="regenabhaengig" :shadow="true" />
						<p>
							<Strong>Regenabhängig</Strong><br>Standorte ohne Bewässerung und ohne größeren Grundwassereinfluss.

						</p>
					</div>
			</section>

			<section class="article-section">
				<h4 class="article-section-header">Modellierung</h4>
				<p>
					Die Sensoren auf der Wasserkarte messen für eine bessere Vergleichbarkeit in bis zu vier Schichttiefen: <strong>10cm</strong>, <strong>30cm</strong>, <strong>60cm</strong> und <strong>80cm</strong>. So lässt sich erkennen, ob nur der
					Oberboden kurzfristig befeuchtet ist oder ob Wasser auch in tiefere Schichten vorgedrungen
					ist.
				</p>
				<p>
					Die Modellierung der durchschnittlichen nutzbaren Feldkapazität für jeden Standort erfolgt unter einbeziehung der Schichttiefen des <strong>Oberbodens bis 60cm</strong>, da diese für die meisten Pflanzen am relevantesten sind. Es wird ein Annäherungswert aus den je nach Standorten unterschiedlichen Messtiefen gebildet.
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

export default {
	name: 'Bodenkunde',
	components: {
		SoilChart, SoilChartOld, Icon
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
// .menuwindow.glossar
// 	position fixed
// 	left 0
// 	top var(--menubariconsize)
// 	margin-top 1px
// 	bottom 0
// 	pointer-events none
// 	display flex
// 	flex-direction column
// 	z-index 2
// 	filter drop-shadow(2px 3px 2px #00000022)
// 	> *
// 		pointer-events all


.menuwindow.glossar
	width 440px
	width 600px
	--paragraphwidth 500px

.article-section
	margin 16px 0
	padding 4px 4px
	margin-right 24px
	font-size 9pt
	line-height 1.45
	> .icon:first-of-type
		margin-top 8px

.article-section + .article-section
	border-top var(--thinline)

.inhalt
	margin 4px 4px -16px
	font-size 9pt
	display flex
	flex-wrap wrap
	// .inhalt-list
		// margin 8px 0 0
		// padding-left 8px
		// li
			// margin-left 6px
			// padding-left 6px

		
.inhalt-link:after
	content '•'
	display inline-block
	margin 0 3px
	opacity .6
	pointer-events none
	white-space nowrap
.inhalt-link:last-child:after
	display none

.inhalt-link
	margin .1em 0
	white-space nowrap
	font-weight normal
	color var(--menusectionheadercolor)
	cursor pointer
	&:hover
		text-decoration underline

.article-section-header
	scroll-margin-top 8px

.article-inhalt-link:hover
	text-decoration underline

.article-section-header
	font-size 11.5pt
	margin 0 0
	color var(--menusectionheadercolorlight)
	padding 7px 0 0
	text-transform none
	display flex
	align-items flex-end
	// flex-direction column


.article-section h4
	border-top none

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


.nfkbg
	display inline-block
	padding .1em .4em
	padding .15em .6em
	margin -.2em .15em
	border-radius 999px
	filter: drop-shadow(0 1px 2px rgba(0,0,0,.22));

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
	margin 16px 0 24px
	font-size 8.5pt
	max-width var(--paragraphwidth)
	tr
		padding 0
	td
		border-top 1px solid #ddd
		padding 2px 0
		margin 0
		padding-right 16px

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
		filter: drop-shadow(0 1px 2px rgba(0,0,0,.14));
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

table.nfk-table
	overflow hidden
	border-radius 3px
	border-bottom none
	margin-bottom 8px
	filter: drop-shadow(0 1px 2px rgba(0,0,0,.22));
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
	
em.note
	font-size 8.5pt
	font-style normal
	color #00000088

code
	display inline-block
	background #eee
	padding .2em .5em
	border-radius 4px

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
		
</style>
