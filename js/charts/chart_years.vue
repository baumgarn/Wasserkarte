<script>
import dataStore from '@/datastore.js';
import { dataModel } from '@/dataModel.js';
import { state } from '@/state.js';
import DateAxis from '@/charts/dateaxis.vue';
import PostTimeline from '@/posts/post_timeline.vue';

const DAY_MS = 86400000;

export default {
	name: 'ChartYears',
	components: { DateAxis, PostTimeline },
	emits: ['hover', 'select-post'],
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
				years.push({ year, days, cells, startTimestamp: new Date(year, 0, 1).getTime(), latestTimestamp: new Date(year + 1, 0, 1).getTime() - 1 });
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
		hoverPost(timestamp) {
			const year = timestamp === null ? null : this.years.find(year => timestamp >= year.startTimestamp && timestamp <= year.latestTimestamp);
			this.hover = year ? {
				year: year.year,
				ts: timestamp,
				x: (timestamp - year.startTimestamp) / (year.latestTimestamp - year.startTimestamp) * 100,
			} : null;
			this.$emit('hover', timestamp);
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
			<PostTimeline :device :startTimestamp="year.startTimestamp" :latestTimestamp="year.latestTimestamp" :chartWidth="frameWidth" :frameWidth @hover="hoverPost" @select="$emit('select-post', $event)" />
			<svg :viewBox="`0 0 ${year.days} 1`" preserveAspectRatio="none">
				<rect v-for="cell in year.cells" :key="cell.x" :x="cell.x" y="0" width="1" height="1" :fill="cell.color">
				</rect>
			</svg>
			<div class="label">{{ year.year }}</div>
			<template v-if="hover?.year === year.year">
				<div class="year-hoverline" :style="{ left: hover.x + '%' }"></div>
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
.year :deep(.post-marker)
	top 0
.year :deep(.post-preview)
	bottom 0
	&.below
		top 18px
		bottom auto
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
</style>
