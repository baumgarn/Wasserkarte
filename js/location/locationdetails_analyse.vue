<template>
	<div class="standortanalyse">
		<div class="soil-table">
			<section v-for="group in propertyGroups" :key="group.title">
				<div class="property-grid">
					<div v-for="item in group.items" :key="item.class" :class="item.class" v-tooltip="{ content: item.title, disabled: true, delay: 1000, maxWidth: 'trigger', align: 'left', alignOffset: -8, arrowPosition: 22 }">
						<div class="label">
							<template v-if="item.label.endsWith('Trockenphase')">
								<span class="full-label">{{ item.label }}</span>
								<span class="short-label">{{ item.label === 'Längste Trockenphase' ? 'Längste Trocken' : 'Aktuell Trocken' }}</span>
							</template>
							<template v-else>{{ item.label }}</template>
						</div>
						<div class="value"><span v-if="item.color" class="nfk-dot" :style="{ backgroundColor: item.color }"></span><span v-if="item.direction" class="trend-triangle" :class="item.direction" :style="{ color: item.trendColor }"></span><span v-if="item.sign" class="sign">{{ item.sign + ' ' }}</span><span class="values">{{ item.value }}</span><span v-if="item.unit" class="unit"> {{ item.unit }}</span></div>
					</div>
				</div>
			</section>
		</div>
		<section class="measurement-period location-table-caption">{{ measurementPeriod }}</section>
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
	props: { device: Object, isInactive: Boolean },
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
			const daily = this.isInactive ? { dayValue: null, currentDryRun: null } : this.dailyNfkStats;
			const vol = this.isInactive ? { dayValue: null, dayTrend: null, weekChange: null } : this.dailyVolStats;
			const days = value => ({ value: value === null || value === 0 ? '–' : value, unit: value ? (value === 1 ? 'Tag' : 'Tage') : '' });
			return [
				{ title: 'Tageswerte', items: [
					{ class: 'nfk-tag', label: 'nFK', value: daily.dayValue === null ? '–' : Math.round(daily.dayValue), unit: daily.dayValue === null ? '' : '%', color: daily.dayValue === null ? null : dataModel.get_nfk_color(daily.dayValue) },
					{ class: 'vol-tag', label: 'Vol', value: vol.dayValue === null ? '–' : vol.dayValue.toLocaleString('de-DE', { maximumFractionDigits: 1 }), unit: vol.dayValue === null ? '' : '%' },
					{ class: 'vol-vortag', ...this.trendItem('Vol zu Vortag', vol.dayTrend, 'Änderung der Bodenfeuchte gegenüber dem Vortag in Vol.-Prozentpunkten', 1) },
				] },
				{ title: 'Gesamt', items: [
					{ class: 'nfk-gesamt', label: 'Ø nFK Gesamt', value: this.nfkColor ? this.nfkAverage : '–', unit: this.nfkColor ? '%' : '', color: this.nfkColor },
					{ class: 'vol-gesamt', label: 'Ø Vol Gesamt', value: this.volStats.average === null ? '–' : this.volStats.average.toLocaleString('de-DE', { maximumFractionDigits: 1 }), unit: this.volStats.average === null ? '' : '%' },
					{ class: 'vol-vorwoche', ...this.trendItem('Vol zu Vorwoche', vol.weekChange, 'Änderung der Bodenfeuchte gegenüber sieben Tagen zuvor in Vol.-Prozentpunkten', 1) },
				] },
				{ title: 'Trockenheit', items: [
					{ class: 'trockene-tage', label: 'Trockene Tage', value: stats.dryPercent === null ? '–' : Math.round(stats.dryPercent), unit: stats.dryPercent === null ? '' : '%', title: 'Anteil der gemessenen Tage unter 50 % nFK' },
					{ class: 'laengste-trockenphase', label: 'Längste Trockenphase', ...days(stats.longestDryRun) },
					{ class: 'aktuelle-trockenphase', label: 'Aktuelle Trockenphase', ...days(daily.currentDryRun) },
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
				value: value === null ? '–' : Math.abs(value).toLocaleString('de-DE', { maximumFractionDigits: decimals }),
				unit: value === null ? '' : '%',
				sign: value > 0 ? '+' : value < 0 ? '−' : '',
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
	color rgba(0,0,0,.8)
	opacity 1
	font-weight normal
	padding 4px 0 0

.standortanalyse
	cursor pointer
	width 100%

.soil-table
	display grid
	grid-template-rows repeat(3, minmax(0, 1fr))
	height var(--location-table-height)

section
	min-height 0
	padding-top 3px
	border-top var(--thinnerline)

.property-grid
	display grid
	grid-template-columns repeat(3, minmax(0, 1fr))
	gap 8px 12px

.label
	white-space nowrap

.value
	font-size 10pt
	font-weight bold

.sign
.unit
	font-weight normal
	opacity .78
	opacity .55
.unit
	font-size 8pt
	margin-left .2em


.laengste-trockenphase .unit
.aktuelle-trockenphase .unit
	margin-left .3em

.nfk-dot
.trend-triangle
	position relative
	top -2px

.nfk-dot
	display inline-block
	width 14px
	height 14px
	border-radius 50%
	margin-right 5px
	vertical-align middle

.trend-triangle
	display inline-block
	width 0
	height 0
	border-left 7px solid transparent
	border-right 7px solid transparent
	margin-right 5px
	vertical-align middle
	&.up
		border-bottom 12px solid currentColor
	&.down
		border-top 12px solid currentColor
.short-label
	display none

@media (max-width: 500px)
	.label
		overflow hidden
		text-overflow ellipsis
	.full-label
		display none
	.short-label
		display inline
</style>
