<script>
import dataStore from '@/datastore.js';
import { dataModel } from '@/dataModel.js';
import { state } from '@/state.js';
import DateAxis from '@/charts/dateaxis.vue';
import GraphTooltip from '@/charts/graph_tooltip.vue';
import { displayutil } from '@/displayutil.js';

const DAY_MS = 86400000;

export default {
	name: 'ChartYears',
	components: { DateAxis, GraphTooltip },
	setup() {
		return { displayutil };
	},
	emits: ['hover'],
	props: {
		device: { type: Object, required: true },
		frameWidth: { type: Number, required: true },
	},
	data() {
		return { telemetry: { schema: [], data: [] }, hover: null };
	},
	computed: {
		rowHeight() {
			return state.sidebarFullView ? 125 : 115;
		},
		years() {
			const tsIndex = this.telemetry.schema.indexOf('ts');
			const valueIndex = this.telemetry.schema.indexOf('nfk_avg');
			if (tsIndex < 0 || valueIndex < 0) return [];
			const rows = this.telemetry.data;
			if (!rows.length) return [];
			// Track the active scale when generating the cell colors.
			state.colorScheme;
			const firstYear = new Date(rows[0][tsIndex]).getFullYear();
			const lastYear = new Date(rows[rows.length - 1][tsIndex]).getFullYear();
			const years = [];
			for (let year = lastYear; year >= firstYear; year--) {
				const start = Date.UTC(year, 0, 1);
				const days = (Date.UTC(year + 1, 0, 1) - start) / DAY_MS;
				const cells = [];
				for (const row of rows) {
					const date = new Date(row[tsIndex]);
					const value = row[valueIndex];
					if (date.getFullYear() !== year || !Number.isFinite(value)) continue;
					cells.push({
						ts: row[tsIndex],
						value,
						x: (Date.UTC(year, date.getMonth(), date.getDate()) - start) / DAY_MS,
						color: dataModel.get_nfk_color(value),
					});
				}
				years.push({ year, days, cells });
			}
			return years;
		},
	},
	methods: {
		hoverYear(event, year) {
			if (!year.cells.length) {
				this.clearHover();
				return;
			}
			const rect = event.currentTarget.getBoundingClientRect();
			const pointer = event.touches?.[0] ?? event;
			const fraction = Math.max(0, Math.min(0.999999, (pointer.clientX - rect.left) / rect.width));
			const day = fraction * year.days;
			const closest = year.cells.reduce((nearest, cell) =>
				Math.abs(cell.x - day) < Math.abs(nearest.x - day) ? cell : nearest
			);
			this.hover = {
				year: year.year,
				ts: closest.ts,
				value: closest.value,
				x: closest.x / year.days * 100,
			};
			this.$emit('hover', closest.ts);
		},
		clearHover() {
			this.hover = null;
			this.$emit('hover', null);
		},
	},
	beforeUnmount() {
		this.clearHover();
	},
	watch: {
		'device.id': {
			async handler(id) {
				this.clearHover();
				this.telemetry = { schema: [], data: [] };
				const telemetry = await dataStore.fetchTelemetryData(id, 'all', '1d');
				if (id === this.device.id) this.telemetry = telemetry;
			},
			immediate: true,
		},
	},
};
</script>

<template>
	<div class="chart-years chartouterframe">
		<div class="chartheader">
			<h3>Bodenfeuchte</h3>
		</div>
		<div class="years-frame">
		<div v-for="year in years" :key="year.year" class="year" :style="{ height: rowHeight + 'px' }" @mousemove="hoverYear($event, year)" @mouseleave="clearHover" @touchstart="hoverYear($event, year)" @touchmove="hoverYear($event, year)" @touchend="clearHover" @touchcancel="clearHover">
			<svg :viewBox="`0 0 ${year.days} 1`" preserveAspectRatio="none">
				<rect v-for="cell in year.cells" :key="cell.x" :x="cell.x" y="0" width="1" height="1" :fill="cell.color">
				</rect>
			</svg>
			<div class="label">{{ year.year }}</div>
			<template v-if="hover?.year === year.year">
				<div class="year-hoverline" :style="{ left: hover.x + '%' }"></div>
				<GraphTooltip class="year-tooltip" :device :hoverData="hover" :mouseOverChart="true">
					<div class="tooltip-date">{{ displayutil.formatDateShort(hover.ts) }}</div>
					<div class="tooltip-value">Ø nFK <span class="value">{{ Math.round(hover.value) }}</span><span class="unit">%</span></div>
				</GraphTooltip>
			</template>
		</div>
		</div>
		<DateAxis v-if="years.length" :chartWidth="frameWidth" :frameWidth :startTimestamp="new Date(years[0].year, 0, 1).getTime()" :numberOfDays="years[0].days" :monthsOnly="true" />
	</div>
</template>

<style lang="stylus" scoped>
.chart-years
	width 100%
.chart-time
	margin 0
	border-top 1px solid transparent
.years-frame
	overflow hidden
	border-radius var(--chartborderradius)
	border var(--chartborderstyle)
	filter var(--chartdropshadowfilter)
.year
	position relative
	background #fff
	& + .year
		border-top 1px solid #00000022
	svg
		display block
		width 100%
		height 100%
		shape-rendering crispEdges
.label
	position absolute
	top calc(10% - 6px)
	left calc(.5vw - 2px)
	font-size 11px
	font-weight bold
	color #0009
	pointer-events none
.year-hoverline
	position absolute
	top 0
	bottom 0
	border-left 1px dotted #000
	pointer-events none
.year-tooltip
	min-width 0
	width 80px
.tooltip-date
	padding 3px 0
	font-size 8pt
	opacity .8
	text-align right
.tooltip-value
	font-size 8pt
	text-align right
	.value
		font-size 9pt
	.unit
		margin-left .25em
		opacity .8
</style>
