<template>
	<div class="standortanalyse">
		<div class="soil-table">
			<section v-for="group in propertyGroups" :key="group.title">
				<div class="property-grid">
					<div v-for="item in group.items" :key="item.label" v-tooltip="{ content: item.title, disabled: true, delay: 1000, maxWidth: 'trigger', align: 'left', alignOffset: -8, arrowPosition: 22 }">
						<div class="label" :class="{ nowrap: item.label.endsWith('Trockenphase') }">
							<template v-if="item.label.endsWith('Trockenphase')">
								<span class="full-label">{{ item.label }}</span>
								<span class="short-label">{{ item.label === 'Längste Trockenphase' ? 'Längste Trocken' : 'Aktuell Trocken' }}</span>
							</template>
							<template v-else>{{ item.label }}</template>
						</div>
						<div class="value"><span v-if="item.color" class="nfk-dot" :style="{ backgroundColor: item.color }"></span><span v-if="item.direction" class="trend-triangle" :class="item.direction" :style="{ color: item.trendColor }"></span>{{ item.value }}</div>
					</div>
				</div>
			</section>
		</div>
		<section class="measurement-period">{{ measurementPeriod }}</section>
	</div>
</template>

<script>
import dataStore from '@/datastore.js';
import { state } from '@/state.js';
import { dataModel } from '@/dataModel.js';

export default {
	name: 'LocationDetailsAnalyse',
	setup() {
		return { dataModel };
	},
	props: { device: Object },
	computed: {
		measurementRange() {
			return dataStore.getDeviceMeasurementRange(this.device);
		},
		measurementPeriod() {
			const { first, last } = this.measurementRange;
			if (!first || !last) return '–';
			const differentYears = new Date(first).getFullYear() !== new Date(last).getFullYear();
			return `${this.formatDate(first, differentYears)} – ${this.formatDate(last)} (${this.measurementDuration})`;
		},
		measurementDuration() {
			const { first, last } = this.measurementRange;
			if (!first || !last) return '–';
			const start = new Date(first);
			const end = new Date(last);
			let years = end.getFullYear() - start.getFullYear();
			let months = end.getMonth() - start.getMonth();
			let days = end.getDate() - start.getDate();
			if (days < 0) {
				months--;
				const previousMonth = new Date(end.getFullYear(), end.getMonth(), 0);
				days += previousMonth.getDate();
			}
			if (months < 0) {
				years--;
				months += 12;
			}
			if (years) {
				const duration = Number((years + (months + days / 30.4375) / 12).toFixed(1));
				return `${duration.toLocaleString('de-DE')} ${duration === 1 ? 'Jahr' : 'Jahre'}`;
			}
			if (months) {
				const duration = Number((months + days / 30.4375).toFixed(1));
				return `${duration.toLocaleString('de-DE')} ${duration === 1 ? 'Monat' : 'Monate'}`;
			}
			return `${days} ${days === 1 ? 'Tag' : 'Tage'}`;
		},
		propertyGroups() {
			const stats = this.nfkStats;
			const daily = this.dailyNfkStats;
			const vol = this.dailyVolStats;
			const days = value => value === null || value === 0 ? '–' : `${value} ${value === 1 ? 'Tag' : 'Tage'}`;
			return [
				{ title: 'Tageswerte', items: [
					{ label: 'nFK des Tages', value: daily.dayValue === null ? '–' : `${Math.round(daily.dayValue)} %`, color: daily.dayValue === null ? null : dataModel.get_nfk_color(daily.dayValue) },
					{ label: 'Vol des Tages', value: vol.dayValue === null ? '–' : `${vol.dayValue.toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %` },
					this.trendItem('Vol zu Vortag', vol.dayTrend, 'Änderung der Bodenfeuchte gegenüber dem Vortag in Vol.-Prozentpunkten', 1),
				] },
				{ title: 'Gesamt', items: [
					{ label: 'Ø nFK Gesamt', value: this.nfkColor ? `${this.nfkAverage} %` : '–', color: this.nfkColor },
					{ label: 'Ø Vol Gesamt', value: this.volStats.average === null ? '–' : `${this.volStats.average.toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %` },
					this.trendItem('Vol zu Vorwoche', vol.weekChange, 'Änderung der Bodenfeuchte gegenüber sieben Tagen zuvor in Vol.-Prozentpunkten', 1),
				] },
				{ title: 'Trockenheit', items: [
					{ label: 'Trockene Tage', value: stats.dryPercent === null ? '–' : `${Math.round(stats.dryPercent)} %`, title: 'Anteil der gemessenen Tage unter 50 % nFK' },
					{ label: 'Längste Trockenphase', value: days(stats.longestDryRun) },
					{ label: 'Aktuelle Trockenphase', value: daily.currentDryRun === null ? '–' : daily.currentDryRun ? `${daily.currentDryRun} ${daily.currentDryRun === 1 ? 'Tag' : 'Tage'}` : '–' },
				] },

			];
		},
		nfkStats() {
			return dataStore.getDeviceNfkStats(this.device);
		},
		dailyNfkStats() {
			return dataStore.getDeviceNfkStats(this.device, state.timelineDate);
		},
		dailyVolStats() {
			return dataStore.getDeviceNfkStats(this.device, state.timelineDate, 'vol_avg');
		},
		volStats() {
			return dataStore.getDeviceNfkStats(this.device, null, 'vol_avg');
		},
		nfkColor() {
			const value = dataStore.getDeviceNfkAverage(this.device);
			return typeof value === 'number' && !Number.isNaN(value) ? dataModel.get_nfk_color(value) : null;
		},
		nfkAverage() {
			const value = dataStore.getDeviceNfkAverage(this.device);
			return typeof value === 'number' && !Number.isNaN(value) ? value.toFixed(0) : '–';
		},

	},
	methods: {
		formatDate(timestamp, includeYear = true) {
			if (!timestamp) return '–';
			const dateValue = new Date(timestamp);
			const month = new Intl.DateTimeFormat('de-DE', { month: 'short' }).format(dateValue).replace(/\.$/, '');
			return `${dateValue.getDate()}. ${month}${includeYear ? ` ${dateValue.getFullYear()}` : ''}`;
		},
		trendItem(label, trend, title, decimals = 0) {
			const value = trend === null ? null : Number(trend.toFixed(decimals));
			return {
				label, title,
				value: value === null ? '–' : `${value > 0 ? '+ ' : value < 0 ? '− ' : ''}${Math.abs(value).toLocaleString('de-DE', { minimumFractionDigits: decimals, maximumFractionDigits: decimals })} %`,
				color: value === 0 ? '#ccc' : null,
				direction: value ? (value > 0 ? 'up' : 'down') : null,
				trendColor: dataModel.get_nfk_color(value < 0 ? 0 : 120),
			};
		},

	},
};
</script>

<style lang="stylus" scoped>
.measurement-period
	font-size 8pt
	color #000
	opacity 1
	font-weight normal
	font-weight normal

.standortanalyse
	cursor pointer
	width 100%
	margin-bottom 10px

.soil-table
	display grid
	grid-template-rows repeat(3, minmax(0, 1fr))
	height var(--location-table-height)

section
	min-height 0
	padding-top 5px
	border-top var(--thinnerline)

.property-grid
	display grid
	grid-template-columns repeat(3, minmax(0, 1fr))
	gap 8px 12px

.label.nowrap
	white-space nowrap

.value
	font-size 9pt
	font-weight bold

.nfk-dot
	display inline-block
	width 12px
	height 12px
	border-radius 50%
	margin-right 5px
	vertical-align middle

.trend-triangle
	display inline-block
	width 0
	height 0
	border-left 6px solid transparent
	border-right 6px solid transparent
	margin-right 5px
	vertical-align middle
	&.up
		border-bottom 10px solid currentColor
	&.down
		border-top 10px solid currentColor
.short-label
	display none

@media (max-width: 499.98px)
	.full-label
		display none
	.short-label
		display inline
</style>
