<template>
	<div class="tableview" tabindex="0" @keydown="onKeydown" @mousemove="onMouseMove" @mouseleave="hideTooltip">

		<div class="tableview-header">

		</div>

		<div class="table-content" :class="{ scrolltop: isScrollTop }" @scroll="onScroll" ref="content" @click="unselect">
		
			<div class="table-col" :class="col.class" :style="col.width ? { width: col.width + 'px', flexShrink: 0 } : {}" v-for="col in columns" :key="col.key">
				
				<template v-if="tableview_compact && (col.type == 'attribute')">
				
					<div class="table-colheader compact" :class="{ sortable: col.sortable, sortby: col.sortable && sortKey === col.key, asc: col.sortable && sortKey === col.key && sortAsc, desc: col.sortable && sortKey === col.key && !sortAsc }" @click.stop="col.sortable && sortBy(col.key)"></div>
					
				</template>

				<template v-else-if="col.type == 'timeline'">
					
					<div class="table-colheader timeline" ref="timelineref" @click.stop="">

						<div class="dateaxiswrapper">

							<DateAxis
							:chartWidth="timelineWidth"
							:frameWidth="timelineWidth"
							:startTimestamp="earliestTimestamp"
							:numberOfDays
							:insideTimeline="true"
							:monthsOnly="true"
							></DateAxis>

						</div>

					</div>

				</template>
				
				<template v-else>
				
					<div class="table-colheader" :class="{ sortable: col.sortable, sortby: col.sortable && sortKey === col.key, asc: col.sortable && sortKey === col.key && sortAsc, desc: col.sortable && sortKey === col.key && !sortAsc }" @click.stop="col.sortable && sortBy(col.key)">

						{{ col.name }}

					</div>

				</template>

				<template v-for="row in filteredSortedTableData">

					<div
						class="table-data"
						:class="[col.type, { selected: isSelected(row.device), compact: (col.type == 'attribute' && tableview_compact) }]"
						@click.stop="selectDevice(row.device)"
						:hoverinfo="getDataHoverInfo(col, row)"
						>

						<template v-if="col.key === 'name'">
							
							<div class="label">{{ row.name }}</div>	

						</template>

						<template v-if="col.type == 'attribute'">

							<template v-if="tableview_compact">

								<FilterItem v-if="row[col.key]" :obj="row[col.key]" type="tablecompact"/>
								
							</template>

							<template v-else>

								<FilterItem v-if="row[col.key]" :obj="row[col.key]" type="table"/>

							</template>

						</template>

						<template v-if="col.type== 'timeline'">

							<TableTimeline 
								:device="row.device"
								:startTimestamp="earliestTimestamp"
								:endTimestamp="latestTimestamp"
								:selected="isSelected(row.device)"
								:timelineWidth
							></TableTimeline>

						</template>

					</div>

				</template>

			</div>

			<div class="tableview-footer">

			</div>
			
		</div>

		<div class="mouse-tooltip" v-show="tooltip.visible" :class="{ ready: tooltip.ready }" :style="tooltipStyle">{{ tooltip.text }}</div>

		<div class="windowbuttons plain left row">
			<div class="iconbutton close" v-on:click="close()"></div>
		</div>
		
		<PopoverMenuMulti
		class="tablesettingspopup"
		ref="settingspopupref" 
		:items="settingsMenuItems" />
		
		<div class="windowbuttons plain row">
			<div class="iconbutton light filter" v-on:click="openFilterPopup()"></div>
			<div class="iconbutton light settings" :class="{ active: $refs.settingspopupref?.isOpen }" v-on:click="openSettingsPopup()" ref="settingsbuttonref"></div>
		</div>

	</div>

</template>

<script>
import { state } from '@/state.js';
import dataStore from '@/datastore.js';
import { dataModel } from '@/datamodel.js'
import ColorDot from '@/menu/colordot.vue';
import PopoverMenuMulti from '@/ui/popovermenu_multi.vue'
import FilterItem from '@/location/filteritem.vue'
import TableTimeline from '@/table/table_timeline.vue'
import DateAxis from '@/charts/dateaxis.vue'

export default {
	name: 'TableView',
	setup() {
		return {state}
	},
	components: {ColorDot, PopoverMenuMulti, FilterItem, TableTimeline, DateAxis},
	props: {
		type: Boolean,
		sideview: {
			default: false
		},
	},
	data() {
		return {
			selectedIndex: -1,
			sortKey: 'name',
			sortAsc: true,
			isScrollTop: true,
			earliestTimestamp: 0,
			latestTimestamp: 0,
			tooltip: { visible: false, ready: false, text: '', x: 0, y: 0 },
			timelineWidth: 0,
		}
	},
	computed: {
		devices() {
			return state.devices;
		},
		columns() {
			return [
				{ key: 'name', name: 'Standort', sortable: true, width: 250 },
				{ key: 'nutzung', name: 'Nutzungsart', sortable: true, type: 'attribute' },
				{ key: 'wasser', name: 'Wasserhaushalt', sortable: true, type: 'attribute' },
				{ key: 'boden', name: 'Bodenart', sortable: true, type: 'attribute' },
				{ key: 'humus', name: 'Humusgehalt', sortable: true, type: 'attribute' },
				{ key: 'timeline', name: '', class: 'timeline', type:'timeline' },
				// { key: 'rightspacer', name: '', class: 'rightspacer' },
			]
		},
		tableData() {
			return this.devices.map(device => ({
				name: device.attributes && device.attributes.Anzeigename || device.name,
				boden: dataModel.get_soil_obj(device),
				humus: dataModel.get_humus_obj(device),
				nutzung: dataModel.get_usage_obj(device),
				wasser: dataModel.get_water_obj(device),
				device: device,
			}));
		},
		filteredSortedTableData() {
			if (state.includeFilter.length === 0 && state.excludeFilter.length === 0) {
				return this.sortedTableData;
			}
			return this.sortedTableData.filter(row => !this.isFiltered(row.device));
		},
		sortedTableData() {
			const key = this.sortKey;
			return [...this.tableData].sort((a, b) => {
				const av = a[key];
				const bv = b[key];
				const as = av && typeof av === 'object' ? (av.sort ?? av.name ?? '') : (av ?? '');
				const bs = bv && typeof bv === 'object' ? (bv.sort ?? bv.name ?? '') : (bv ?? '');
				const dir = this.sortAsc ? 1 : -1;
				const aEmpty = as === '';
				const bEmpty = bs === '';
				if (aEmpty !== bEmpty) return aEmpty ? dir : -dir;
				return dir * String(as).localeCompare(String(bs));
			});
		},
		settingsMenuItems() {
			let menu = [];
			menu.push(
				{type:'boolean', label:'Kompakte Darstellung', stateProp:'tableview_compact'},
				{type:'divider'},
				{type:'select', label:'Gesamte Zeit', value:'all', group:'timerange', stateProp:'tableview_timelinerange'},
				{type:'select', label:'Letzte 365 Tage', value:'365d', group:'timerange', stateProp:'tableview_timelinerange'},
				{type:'select', label:'Letzte 180 Tage', value:'180d', group:'timerange', stateProp:'tableview_timelinerange'},
			)
			return menu;
		},
		selectedDevice() {
			return state.selectedDevice;
		},
		filteredDevices() {
			return state.filteredDevices;
		},
		tableview_compact() {
			return state.tableview_compact
		},
		timelineRange() {
			if (this.numberOfDays <= 365) return 'all';
			return state.tableview_timelinerange;
		},
		telemetryLoaded() {
			return state.telemetryLoaded;
		},
		filteredDevices() {
			return state.filteredDevices;
		},
		numberOfDays() {
			return (this.latestTimestamp - this.earliestTimestamp) / (1000 * 60 * 60 * 24);
		},
		_365d() {
			const DAY = 24 * 60 * 60 * 1000;
			return this.latestTimestamp - (365 * DAY);
		},
		_180d() {
			const DAY = 24 * 60 * 60 * 1000;
			return this.latestTimestamp - (180 * DAY);
		},
		_90d() {
			const DAY = 24 * 60 * 60 * 1000;
			return this.latestTimestamp - (90 * DAY);
		},
		tooltipStyle() {
			return {
				left: (this.tooltip.x + 10) + 'px',
				top: (this.tooltip.y + 10) + 'px',
			};
		},
		minTimelineWidth() {
			return (numberOfDays * 1)
		},
	},
	methods: {
		selectDevice(device) {
			if (state.popupMenuOpen) return;
			if (state.selectedDevice == device?.name) {
				state.selectedDevice = null;
			} else {
				state.selectedDevice = device?.name || null;
				window.dispatchEvent(new CustomEvent('sidebar:open', { detail: device }));
				window.dispatchEvent(new CustomEvent('device-selected', { detail: device }));
				this.$nextTick(() => {
					const el = this.$refs.items?.[this.selectedIndex];
					el?.scrollIntoView({ block: 'nearest' });
				});
			}
		},
		unselect() {
			state.selectedDevice = null;
		},
		setSelectedDevice(device) {
			state.selectedDevice = device?.name || null;
		},
		isFiltered(device) {
			if (state.includeFilter.length == 0 && state.excludeFilter.length == 0) { 
				return false
			}
			var isfiltered = true;
			this.filteredDevices.forEach(d => {
				if (d.id == device.id) {
					isfiltered = false;
				}
			});
			return isfiltered;
		},
		nfk(device) {
			const nfk = dataModel.nfk(device);
			if (isNaN(nfk)) return '–'
			return parseFloat(nfk.toFixed(0));
		},
		nfk_color(device) {
			return dataModel.get_nfk_color(this.nfk(device));
		},
		onKeydown(e) {
			const data = this.filteredSortedTableData;
			if (!data.length) return;

			switch (e.key) {
				case 'ArrowDown':
					e.preventDefault();
					this.selectedIndex = (this.selectedIndex + 1) % data.length;
					this.selectDevice(data[this.selectedIndex].device);
					break;

				case 'ArrowUp':
					e.preventDefault();
					this.selectedIndex = (this.selectedIndex - 1 + data.length) % data.length;
					this.selectDevice(data[this.selectedIndex].device);
					break;

				case 'Enter':
					e.preventDefault();
					if (this.selectedIndex >= 0) {
						this.selectDevice(data[this.selectedIndex].device);
					}
					break;
			}
		},
		getCols() {
			return [this.$refs.col0, this.$refs.col1].filter(Boolean);
		},
		onScroll(e) {
			this.isScrollTop = e.target.scrollTop === 0;
			this._clearTooltipTimer();
			this.tooltip.visible = false;
			this.tooltip.ready = false;
			this._clearScrollEndTimer();
			this._scrollEndTimer = setTimeout(() => {
				this._scrollEndTimer = null;
				this._checkTooltipAtCursor();
			}, 150);
		},
		sortBy(key) {
			if (this.sortKey === key) {
				this.sortAsc = !this.sortAsc;
			} else {
				this.sortKey = key;
				this.sortAsc = true;
			}
		},
		isSelected(device) {
			return state.selectedDevice == device.name
		},
		close() {
			state.menuOpen.standorttabelle = false;
		},
		openFilterPopup() {
		},
		openSettingsPopup() {
			var position = {
				top: 32,
				right: 4,
			};
			this.$refs.settingspopupref.open(position);
		},
		getDataHoverInfo(col, row) {
			if (col.type !== 'attribute' || !this.tableview_compact) return null;
			const val = row[col.key];
			return val ? `${val.name}` : col.name;
		},
		_clearTooltipTimer() {
			if (this._tooltipTimer) {
				clearTimeout(this._tooltipTimer);
				this._tooltipTimer = null;
			}
		},
		_clearScrollEndTimer() {
			if (this._scrollEndTimer) {
				clearTimeout(this._scrollEndTimer);
				this._scrollEndTimer = null;
			}
		},
		_checkTooltipAtCursor() {
			const el = document.elementFromPoint(this.tooltip.x, this.tooltip.y);
			if (!el || !this.$el.contains(el)) return;
			let node = el;
			let text = null;
			while (node && node !== this.$el) {
				if (node.hasAttribute?.('hoverinfo')) {
					text = node.getAttribute('hoverinfo') || null;
					break;
				}
				node = node.parentElement;
			}
			if (text) {
				this.tooltip.text = text;
				this.tooltip.visible = true;
				if (!this._tooltipTimer) {
					this._tooltipTimer = setTimeout(() => {
						this._tooltipTimer = null;
						this.tooltip.ready = true;
					}, 600);
				}
			}
		},
		onMouseMove(e) {
			this._clearScrollEndTimer();
			this.tooltip.x = e.clientX;
			this.tooltip.y = e.clientY;

			let el = e.target;
			let text = null;
			while (el && el !== this.$el) {
				if (el.hasAttribute('hoverinfo')) {
					text = el.getAttribute('hoverinfo') || null;
					break;
				}
				el = el.parentElement;
			}

			if (text) {
				this.tooltip.text = text;
				if (this.tooltip.ready) {
					// already visible — switch content instantly, no delay
				} else {
					this.tooltip.visible = true;
					if (!this._tooltipTimer) {
						this._tooltipTimer = setTimeout(() => {
							this._tooltipTimer = null;
							this.tooltip.ready = true;
						}, 600);
					}
				}
			} else {
				this._clearTooltipTimer();
				this.tooltip.visible = false;
				this.tooltip.ready = false;
			}
		},
		hideTooltip() {
			this._clearTooltipTimer();
			this._clearScrollEndTimer();
			this.tooltip.visible = false;
			this.tooltip.ready = false;
		},
	},
	mounted() {
		this.$el.focus();
		this.earliestTimestamp = dataStore.earliestTimestamp;
		this.latestTimestamp = dataStore.latestTimestamp;
		this.$nextTick(() => {
			const timelineref = this.$refs.timelineref?.[0];
			if (timelineref) {
				this._resizeObserver = new ResizeObserver(entries => {
					this.timelineWidth = entries[0].contentRect.width;
				});
				this._resizeObserver.observe(timelineref);
				this.timelineWidth = timelineref.getBoundingClientRect().width;
			}
		});
	},
	beforeUnmount() {
		if (this._resizeObserver) this._resizeObserver.disconnect();
		this._clearTooltipTimer();
		this._clearScrollEndTimer();
	},
	watch: {
		selectedDevice: {
			immediate: true,
			handler(name) {
				this.selectedIndex = this.filteredSortedTableData.findIndex(
					d => d.device.name === name
				);
			}
		},
		filteredSortedTableData() {
			this.selectedIndex = this.filteredSortedTableData.findIndex(
				d => d.device.name === state.selectedDevice
			);
			this.$nextTick(() => this.$el.focus());
		},
		telemetryLoaded: {
			handler(val) {
				this.earliestTimestamp = dataStore.earliestTimestamp;
				this.latestTimestamp = dataStore.latestTimestamp;
			}
		},
	},
}
</script>

<style scoped lang="stylus">
	.tableview
		--headerheight 36px
		--rowheight 28px
		--cellpad 0 8px
		--line 1px solid #00000018
		// --line 1px solid transparent
		--tablefontsize 12px
		--hovercolor #e9edf3
		*
			box-sizing border-box
		position absolute
		left 0
		top 0
		bottom 0
		right 0
		z-index 90
		background #fff
		display flex
		flex-direction column
		.tableview-header
			flex-basis var(--headerheight)
			flex-shrink 0
		.table-content
			flex-grow 1
			overflow-x auto
			overflow-y auto
			display flex
			flex-direction row
			align-items flex-start
			position relative
			padding-bottom var(--rowheight)
			&.scrolltop .table-colheader:before
				opacity 0
			.table-colheader:before
				opacity 1
	.table-col.rightspacer
		flex-basis 12px
	.table-col.timeline
	.table-col.spacer
		flex-grow 1
		.table-colheader
		.table-data
			padding 0
			// border-right none
	// .table-col
	// 	margin-bottom var(--rowheight)
	.table-colheader
		white-space nowrap
		padding var(--cellpad)
		position sticky
		top 0
		user-select none
		border-top var(--line)
		border-bottom var(--line)
		border-right var(--line)
		height var(--rowheight)
		display flex
		align-items center
		background #fff
		font-weight bold
		font-size var(--tablefontsize)
		z-index 10
		&:before
			content ''
			position absolute
			left 0
			top calc( 100% + 1px )
			right 0
			height 8px
			transition opacity linear .1s
			background linear-gradient(to bottom, rgba(0, 0, 0, 0.07), transparent)
		&.sortable
			cursor pointer
			padding-right 20px
			transition background linear .1s
			&:hover
				background var(--hovercolor)
		&.sortby::after
			content ''
			position absolute
			right 6px
			opacity .6
			margin-left 5px 
			margin-top 2px 
			display block
			border-left 5px solid transparent
			border-right 5px solid transparent
		&.desc::after
			border-bottom 8px solid currentColor
		&.asc::after
			border-top 8px solid currentColor
		&.timeline
			border-right none
			// overflow hidden
		.dateaxiswrapper
			position absolute
			inset 0
	.table-colheader.compact
		&:after
			right 50%
			margin-right -5px
	.table-data
		white-space nowrap
		border-right var(--line)
		height var(--rowheight)
		padding var(--cellpad)
		display flex
		align-items center
		border-bottom var(--line)
		font-size var(--tablefontsize)
		cursor default
		position relative
		&.selected
			background var(--activecolorgrey)
			border-bottom 1px solid transparent
			// background var(--activecolorgreybrighter)
			// border-right 1px solid transparent
			// &:after
			// 	content ''
			// 	z-index 2
			// 	display block
			// 	position absolute
			// 	top calc( 100% + 1px)
			// 	left 0
			// 	right -1px
			// 	height 8px
			// 	background linear-gradient(to bottom, rgba(0, 0, 0, 0.12), transparent)
			// &:before
			// 	content ''
			// 	z-index 2
			// 	display block
			// 	position absolute
			// 	bottom calc( 100%)
			// 	left 0
			// 	right -1px
			// 	height 8px
			// 	background linear-gradient(to top, rgba(0, 0, 0, 0.1), transparent)
				// inset -1px -1px -1px
				// border-top 1px solid #00000033
				// border-bottom 1px solid #00000033
	.table-data.attribute
		font-size var(--tablefontsize)
	.table-data.timeline
		border-right none
	.table-data.attribute.compact .filteritem
		filter brightness(1)
		transition filter .1s linear
	.table-data.attribute.compact:hover
		.filteritem
			filter brightness(.9)
	.label
		text-overflow ellipsis
		overflow: hidden;
		white-space: nowrap;
	.red
		color: red
	.menu-item
		display: flex
	.menu-title
		flex-grow 1
	.count
		font-weight normal
		display inline-block
		margin-left .5em
		opacity	.45
		font-size 11pt
	.menu-item
		display flex
		padding-right 2px
	.title
		text-overflow ellipsis
		overflow hidden
		white-space nowrap
		flex-grow 1
		font-weight: normal

.mouse-tooltip
	position fixed
	background var(--infobg)
	color #fff
	font-size 9pt
	padding: 4px 6px 4px
	border-radius: 3px;
	font-weight normal
	pointer-events none
	z-index 9999
	white-space nowrap
	box-shadow 0 2px 8px rgba(0,0,0,.2)
	opacity 0
	transition opacity 0.15s linear
	// border-top-left-radius 0
	&.ready
		opacity 1
	// &:after
	// 	content ''
	// 	position absolute
	// 	left 0
	// 	bottom 100%
	// 	border-bottom 6px solid var(--infobg)
	// 	border-right 6px solid transparent

</style> 
