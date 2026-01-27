<template>
	<div class="scrollview chart-time" :class="{ insideGraph, insideTimeline, firstItemPadding }">
		<div class="scrollframe" :style="{ width: frameWidth + 'px'}">
			<div class="scrollinner" :style="{ width: chartWidth + 'px', marginLeft: -scrollLeft + 'px' }">
				<template v-if="dayWidth > 80">
					<div v-for="day in days" class="day" :key="day.timestamp" :style="{ left: day.position + '%' }">
						{{ day.label }}
					</div>
				</template>
				<template v-else-if="dayWidth > 30">
					<div v-for="day in everyotherday" class="day" :key="day.timestamp" :style="{ left: day.position + '%' }">
						{{ day.label }}
					</div>
				</template>
				<template v-else-if="dayWidth > 10">
					<div v-for="day in everyfourthday" class="day" :key="day.timestamp" :style="{ left: day.position + '%' }">
						{{ day.label }}
					</div>
				</template>
				<template v-else-if="dayWidth > 5">
					<div v-for="day in months" class="day" :key="day.timestamp" :style="{ left: day.position + '%' }">
						<!-- {{ day.monthlong }} -->
						{{ day.month }}
					</div>
				</template>
				<template v-else-if="dayWidth > 1">
					<div v-for="day in months" class="day" :key="day.timestamp" :style="{ left: day.position + '%' }">
						{{ day.month }}
					</div>
				</template>
			</div>
			<div 
				class="hoverline" 
				v-show="hoverPosition >= 0" 
				:style="{ left: (hoverPosition ) + 'px' }"
			></div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'ChartTime',
	props: {
		chartWidth: {
			type: Number,
			required: true
		},
		frameWidth: {
			type: Number,
			required: true
		},
		scrollLeft: {
			type: Number,
			required: true
		},
		hoverPosition: {
			type: Number,
			required: false
		},
		numberOfDays: {
			type: Number,
			required: true
		},
		startTimestamp: {
			type: Number,
			required: true
		},
		insideGraph: {
			type: Boolean,
			default: false
		},
		insideTimeline: {
			type: Boolean,
			default: false
		},
		firstItemPadding: {
			type: Boolean,
			default: false
		},
	},
	computed: {
		days() {
			const daysArray = [];
			const oneDay = 24 * 60 * 60 * 1000; // milliseconds in a day
			const startDate = new Date(this.startTimestamp);
			const startOffset = startDate.getHours() * 60 * 60 * 1000 + startDate.getMinutes() * 60 * 1000 + startDate.getSeconds() * 1000 + startDate.getMilliseconds();

			for (let i = 0; i < this.numberOfDays + 1; i++) {
				const currentDate = new Date(this.startTimestamp + i * oneDay);
				const dayOfMonth = currentDate.getDate();
				const weekDay = currentDate.toLocaleString('default', { weekday: 'short' });
				const month = currentDate.toLocaleString('default', { month: 'short' });
				const monthlong = currentDate.toLocaleString('default', { month: 'long' });
				const label = dayOfMonth + '. ' + month;
				const position = ((i * oneDay - startOffset) / (this.numberOfDays * oneDay)) * 100;

				daysArray.push({
					timestamp: currentDate.getTime(),
					label: label,
					position: position,
					dayOfMonth: dayOfMonth,
					month: month,
					monthlong: monthlong,
					weekDay: weekDay
				});
				
			}
			return daysArray;
		},
		everyotherday() {
			return this.days.filter((_, index) => index % 2 === 0);
		},
		everyfourthday() {
			return this.days.filter((_, index) => index % 4 === 0);
		},
		mondays() {
			return this.days.filter(day => day.weekDay === 'Mo');
		},
		months() {
			return this.days.filter(day => day.dayOfMonth === 1);
		},
		dayWidth() {
			return this.chartWidth / this.numberOfDays;
		},
		visibleDays() {
			const scrollFrameWidth = this.frameWidth;
			const scrollLeft = this.scrollLeft;

			return this.days.filter(day => {
				const dayLeft = (day.position / 100) * this.chartWidth - scrollLeft;
				const dayRight = dayLeft + this.dayWidth;

				// Check if the day is fully visible within the scrollframe
				return dayLeft >= 0 && dayRight <= scrollFrameWidth;
			});
		},
	}
}
</script>

<style lang="stylus" scoped>



.chart-time.insideTimeline
.chart-time.insideGraph
	position absolute
	top 0
	left 0
	margin 0
	filter none
	opacity .9
	.scrollframe
		background transparent
		border-radius 0
		border none
	.hoverline
		display none


.scrollview {	
	margin .9em 0 .6em
	filter var(--dropshadowfilter)
}
.scrollframe {	
	position: relative;
	overflow: hidden;
	height: 24px
	background transparent
	border-radius calc(var(--barheight) / 2)
	border var(--borderstyle)
	user-select none
}

.scrollinner {
	height: 100%;
	position: relative;
}

.hoverline
	display none

.day
	position: absolute;
	left: 0;
	top 13px;
	top 4px;
	width 0;
	display: flex;
	align-items: center;
	justify-content: left;
	white-space: nowrap;
	overflow: visible;
	font-size 9px
	color #000000cc

.firstItemPadding .day:first-of-type
	padding-left 0.5vw

	
.chart-time.insideTimeline .day
	top 10px


</style>