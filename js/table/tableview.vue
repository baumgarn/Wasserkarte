<template>
	<div class="tableview" :class="{ narrowview }" tabindex="0" @keydown="onKeydown">

		<div class="tableview-header">
			<StatusBar :intableview="true" :containerWidth="tableWidth"/>
		</div>

		<div class="table-content" :class="{ scrolltop: isScrollTop }" @scroll="onScroll" ref="content">
		
			<div class="table-col" :class="col.class" :style="col.width ? { width: col.width + 'px', flexShrink: 0 } : col.minWidth ? { minWidth: col.minWidth + 'px' } : {}" v-for="col in columns" :key="col.key">
				
				<template v-if="col.type == 'bookmark'">

					<div class="table-colheader bookmark" :class="{ active: state.tableview_bookmarksontop, bookmarks: state.tableview_bookmarksontop }" @click.stop="onHeaderClick(col)" 
						v-tooltip :tooltipcontent="state.tableview_bookmarksontop? 'Bookmarks zuoberst aufheben' : 'Bookmarks zuoberst'" tooltipside="top" tooltipoffset="-5">
					</div>

				</template>

				<template v-else-if="tableview_compact && (col.type == 'attribute')">
				
					<div class="table-colheader compact" :class="[col.key, { sortable: col.sortable, sortby: col.sortable && sortKey === col.key, asc: col.sortable && sortKey === col.key && sortAsc, desc: col.sortable && sortKey === col.key && !sortAsc }]" @click.stop="onHeaderClick(col)" v-tooltip :tooltipcontent="col.name" tooltipside="top" tooltipoffset="-5"></div>
					
				</template>

				<template v-else-if="col.type == 'timeline'">
					
					<div class="table-colheader timeline" ref="timelineref">

						<div class="dateaxiswrapper">

							<DateAxis
							:chartWidth="timelineWidth"
							:frameWidth="timelineWidth"
							:startTimestamp
							:numberOfDays
							:insideTimeline="true"
							:monthsOnly="true"
							></DateAxis>

						</div>

					</div>

				</template>
				
				<template v-else>
				
					<div class="table-colheader" :class="[col.key, { sortable: col.sortable, sortby: col.sortable && sortKey === col.key, asc: col.sortable && sortKey === col.key && sortAsc, desc: col.sortable && sortKey === col.key && !sortAsc }]" @click.stop="onHeaderClick(col)">

						{{ col.name }}

					</div>

				</template>

				<template v-for="(row, rowIndex) in filteredSortedTableData">

						<div
							class="table-data"
							:class="[col.type, col.key, { selected: isSelected(row.device), compact: (col.type == 'attribute' && tableview_compact), firstrow: rowIndex === 0, bookmarked: col.key === 'bookmark' && isBookmarked(row.device) }]"
							@click="onCellClick(col, row, $event)"
							v-tooltip
							:tooltipcontent="getDataHoverInfo(col, row)"
							tooltipside="right"
							tooltipoffset="10"
							tooltipfollowcursor="true"
							tooltiphandover="true"
							tooltipdelay="350"
							v-on="col.type === 'timeline' ? { mousemove: onTimelineHover, mouseleave: onTimelineHoverOut } : {}"
							>

						<template v-if="col.key === 'name'">
							
							<div class="label">{{ row.name }}</div>	

						</template>

						<template v-if="col.key === 'bookmark'">

								<div class="table-bookmark-icon"></div>

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
								:startTimestamp
								:endTimestamp="latestTimestamp"
								:selected="isSelected(row.device)"
								:timelineWidth
							></TableTimeline>

						</template>

					</div>

				</template>

				<div class="table-colfooter" @click="unselectDevice">
				</div>

				<div v-if="col.type === 'timeline' && timelineHoverLinePos > 0" class="timeline-col-hoverline" :style="{ left: timelineHoverLinePos + 'px', height: `calc(${filteredSortedTableData.length} * var(--rowheight))` }"></div>

			</div>

			
		</div>
		
		
		<div class="windowbuttons plain left row">
			<div class="iconbutton close" v-on:click="close()"></div>
		</div>
		
		<PopoverMenuMulti
		class="tablesettingspopup"
		ref="settingspopupref"
		:items="settingsMenuItems" />

		<PopoverMenuMulti
		class="tablefilterpopup"
		ref="filterpopupref"
		:items="filterMenuItems" />

		<div class="windowbuttons plain row tablesettings">
			<div class="iconbutton light filter" ref="filterbuttonref" :class="{ active: $refs.filterpopupref?.isOpen }" v-on:click="openFilterPopup()"
				v-tooltip tooltipcontent="Filter" tooltipside="bottom" :tooltipdisabled="$refs.filterpopupref?.isOpen || $refs.settingspopupref?.isOpen"></div>
			<div class="iconbutton light settings" :class="{ active: $refs.settingspopupref?.isOpen }" v-on:click="openSettingsPopup()" ref="settingsbuttonref"
				v-tooltip tooltipcontent="Einstellungen" tooltipside="bottom" tooltipalign="right" tooltipalignoffset="-2" :tooltipdisabled="$refs.filterpopupref?.isOpen || $refs.settingspopupref?.isOpen"></div>
		</div>

	</div>

</template>

<script>
import { state, isBookmarked, toggleBookmark } from '@/state.js';
import dataStore from '@/datastore.js';
import { dataModel } from '@/datamodel.js'
import ColorDot from '@/menu/colordot.vue';
import PopoverMenuMulti from '@/ui/popovermenu.vue'
import FilterItem from '@/location/filteritem.vue'
import TableTimeline from '@/table/table_timeline.vue'
import DateAxis from '@/charts/dateaxis.vue'
import StatusBar from '@/map/statusbar.vue';
import { hideTooltip } from '@/tooltip.js';

export default {
	name: 'TableView',
	setup() {
		return {state}
	},
	components: {ColorDot, PopoverMenuMulti, FilterItem, TableTimeline, DateAxis, StatusBar},
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
			popoverMenuPosition: { top: 32, right: 8 },
			timelineWidth: 0,
			tableWidth: 0,
			timelineHoverX: -1,
}
	},
	computed: {
		devices() {
			return state.devices;
		},
		narrowview() {
			return (this.tableWidth < 700)
		},
		columns() {
			let cols = [];

			cols.push({ key: 'bookmark', name: '', type: 'bookmark' })
			cols.push({ key: 'name', name: 'Standort', sortable: true, width: 250 })
				
			if (this.tableview_col_attributes) {
				cols.push(
					{ key: 'nutzung', name: 'Nutzungsart', sortable: true, type: 'attribute' },
					{ key: 'wasser', name: 'Wasserhaushalt', sortable: true, type: 'attribute' },
					{ key: 'boden', name: 'Bodenart', sortable: true, type: 'attribute' },
					{ key: 'humus', name: 'Humusgehalt', sortable: true, type: 'attribute' });
			}

			cols.push({ key: 'timeline', name: '', class: 'timeline', type:'timeline', minWidth: this.minTimelineWidth })

			return cols;
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
			const dir = this.sortAsc ? 1 : -1;
			return [...this.tableData].sort((a, b) => {
				if (state.tableview_bookmarksontop) {
					const aBookmarked = state.bookmarks.includes(a.device?.id) ? 1 : 0;
					const bBookmarked = state.bookmarks.includes(b.device?.id) ? 1 : 0;
					if (aBookmarked !== bBookmarked) {
						return bBookmarked - aBookmarked;
					}
				}

				const av = a[key];
				const bv = b[key];
				const as = av && typeof av === 'object' ? (av.sort ?? av.name ?? '') : (av ?? '');
				const bs = bv && typeof bv === 'object' ? (bv.sort ?? bv.name ?? '') : (bv ?? '');
				const aEmpty = as === '';
				const bEmpty = bs === '';
				if (aEmpty !== bEmpty) return aEmpty ? dir : -dir;
				return dir * String(as).localeCompare(String(bs));
			});
		},
		filterMenuItems() {
			let items = [];
			items.push({ type: 'header', label: 'Wasserhaushalt' });
			items.push({ type: 'filteritem', obj: dataModel.regenabhängig_obj });
			items.push({ type: 'filteritem', obj: dataModel.bewaessert_obj });
			items.push({ type: 'filteritem', obj: dataModel.grundwasser_obj });
			items.push({ type: 'divider' });
			items.push({ type: 'header', label: 'Bodenart' });
			Object.values(dataModel.soil_table).forEach(item => items.push({ type: 'filteritem', obj: item }));
			items.push({ type: 'divider' });
			items.push({ type: 'header', label: 'Humusgehalt' });
			Object.values(dataModel.humus_table).forEach(item => items.push({ type: 'filteritem', obj: item }));
			items.push({ type: 'divider' });
			items.push({ type: 'header', label: 'Nutzungsart' });
			Object.values(dataModel.usage_table).forEach(item => items.push({ type: 'filteritem', obj: item }));
			return items;
		},
		settingsMenuItems() {
			let menu = [];
			menu.push(
				{type:'header', label:'Tabelle'},
				{type:'boolean', label:'Bookmarks', stateProp:'tableview_col_bookmarks'},
				{type:'boolean', label:'Standort Eigenschaften', stateProp:'tableview_col_attributes'},
				{type:'thinline'},
				{type:'boolean', label:'Kompakte Darstellung', stateProp:'tableview_compact'},
				{type:'divider'},
				{type:'header', label:'Zeitachse'},
				{type:'select', label:'Gesamte Zeit', value:'all', group:'timelinerange', stateProp:'tableview_timelinerange'},
				{type:'select', label:'Letzte 365 Tage', value:'365d', group:'timelinerange', stateProp:'tableview_timelinerange'},
				{type:'select', label:'Letzte 180 Tage', value:'180d', group:'timelinerange', stateProp:'tableview_timelinerange'},
				{type:'thinline'},
				// {type:'select', label:'Durchschnitt nFK', value:'nfk_avg', group:'timelinestyle', stateProp:'tableview_timelinestyle'},
				// {type:'select', label:'Schichten nFK', value:'nfk_schichten', group:'timelinestyle', stateProp:'tableview_timelinestyle'},
				{type:'boolean', label:'Schichten anzeigen', stateProp:'tableview_showdepths', tooltip:'Zeigt Farbbalken für jede gemessene Schichttiefe anstelle eines Durchschnittswertes'},
				{type:'thinline'},
				{type:'boolean', label:'Datenlücken anzeigen', stateProp:'showDataGaps'},
			)
			return menu;
		},
		selectedDevice() {
			return state.selectedDevice;
		},
		filteredDevices() {
			return state.filteredDevices;
		},
		tableview_col_attributes() {
			return state.tableview_col_attributes
		},
		tableview_compact() {
			return state.tableview_compact
		},
		timelineRange() {
			const totalDays = (this.latestTimestamp - this.earliestTimestamp) / (1000 * 60 * 60 * 24);
			if (totalDays <= 365) return 'all';
			return state.tableview_timelinerange;
		},
		telemetryLoaded() {
			return state.telemetryLoaded;
		},
		filteredDevices() {
			return state.filteredDevices;
		},
		numberOfDays() {
			return  (this.latestTimestamp - this.startTimestamp) / (1000 * 60 * 60 * 24)
		},
		startTimestamp() {
			if (this.timelineRange == 'all') {
				return this.earliestTimestamp;
			} else if (this.timelineRange == '365d') {
				return this._365d;
			} else if (this.timelineRange == '180d') {
				return this._180d;
			}
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
		minTimelineWidth() {
			return 356;
			// return (this.numberOfDays * 2)
		},
		timelineSpan() {
			return Math.max(1, this.latestTimestamp - this.startTimestamp);
		},
		timelineHoverLinePos() {
			if (this.timelineHoverX > -1) {
				let pos = this.timelineHoverX;
				if (this.earliestTimestamp && this.latestTimestamp) {
					const msPerDay = 24 * 60 * 60 * 1000;
					const earliestX = ((this.earliestTimestamp - this.startTimestamp) / this.timelineSpan) * this.timelineWidth;
					const latestX = ((this.latestTimestamp + msPerDay - this.startTimestamp) / this.timelineSpan) * this.timelineWidth;
					if (pos < earliestX) pos = earliestX;
					if (pos > latestX) pos = latestX;
				}
				return pos;
			} else if (state.timelineDate && state.timelineDate > this.startTimestamp && state.timelineDate < this.latestTimestamp) {
				return ((state.timelineDate - this.startTimestamp) / this.timelineSpan) * this.timelineWidth;
			}
			return -1;
		},
	},
	methods: {
		onHeaderClick(col) {
			if (col.key === 'bookmark') {
				state.tableview_bookmarksontop = !state.tableview_bookmarksontop;
				return;
			}
			if (!col.sortable) return;
			this.sortBy(col.key);
		},
		onCellClick(col, row, event) {
			if (col.key === 'bookmark') {
				event?.stopPropagation();
				toggleBookmark(row.device);
				return;
			}
			this.selectDevice(row.device, event);
		},
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
		unselectDevice() {
			console.log('ddsd')
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
			hideTooltip();
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
			this.$refs.filterpopupref.open(this.popoverMenuPosition);
		},
		openSettingsPopup() {
			this.$refs.settingspopupref.open(this.popoverMenuPosition);
		},
		getDataHoverInfo(col, row) {
			if (col.type !== 'attribute' || !this.tableview_compact) return null;
			const val = row[col.key];
			return val ? `${val.name}` : col.name;
		},
		isBookmarked(device) {
			return isBookmarked(device);
		},
		onTimelineHover(event) {
			const rect = event.currentTarget.getBoundingClientRect();
			this.timelineHoverX = event.clientX - rect.left;
			const fraction = this.timelineHoverX / (this.timelineWidth - 1);
			let ts = this.startTimestamp + fraction * this.timelineSpan;
			ts = dataStore.floorToMidnight(ts);
			if (ts < this.earliestTimestamp) ts = this.earliestTimestamp;
			if (ts > this.latestTimestamp) ts = this.latestTimestamp;
			state.timelineDate = ts;
		},
		onTimelineHoverOut(event) {
			this.timelineHoverX = -1;
			state.timelineDate = null;
			hideTooltip(event?.currentTarget);
		},
		getTimelineRef() {
			const ref = this.$refs.timelineref;
			return Array.isArray(ref) ? ref[0] : ref;
		},
		calculateTimelineWidth() {
			const timelineref = this.getTimelineRef();
			if (timelineref) {
				const w = timelineref.getBoundingClientRect().width;
				this.timelineWidth = Math.max(w, this.minTimelineWidth);
			}
		},
	},
	mounted() {
		this.$el.focus();
		this.earliestTimestamp = dataStore.earliestTimestamp;
		this.latestTimestamp = dataStore.latestTimestamp;
		this._tableResizeObserver = new ResizeObserver(() => {
			this.tableWidth = this.$el.getBoundingClientRect().width;
		});
		this._tableResizeObserver.observe(this.$el);
		this.tableWidth = this.$el.getBoundingClientRect().width;
		this.$nextTick(() => {
			const timelineref = this.getTimelineRef();
			if (timelineref) {
				this._resizeObserver = new ResizeObserver(() => {
					this.calculateTimelineWidth();
				});
				this._resizeObserver.observe(timelineref);
				this.calculateTimelineWidth();
			}
		});
	},
	beforeUnmount() {
		if (this._tableResizeObserver) this._tableResizeObserver.disconnect();
		if (this._resizeObserver) this._resizeObserver.disconnect();
		hideTooltip();
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
				this.$nextTick(() => this.calculateTimelineWidth());
			}
		},
	},
}
</script>

<style scoped lang="stylus">
	.tableview
		--headerheight 42px
		--rowheight 28px
		--cellpad 0 8px
		--line 1px solid #00000018
		--tablefontsize 12px
		--hovercolor #e9edf3
		*
			box-sizing border-box
		position absolute
		inset 0
		z-index 90
		background #fff
		display flex
		flex-direction column
		background #f4f4f4
		.tableview-header
			flex-basis var(--headerheight)
			flex-shrink 0
			display flex
			justify-content center
			align-items center
			padding 5px 0 4px
		.windowbuttons.tablesettings
			margin-right 4px
		.table-content
			flex-grow 1
			overflow-x auto
			overflow-y auto
			display flex
			flex-direction row
			align-items flex-start
			position relative
			&.scrolltop .table-colheader:before
				opacity 0
			.table-colheader:before
				opacity 1
	.table-col.rightspacer
		flex-basis 12px
	.table-col.timeline
		position relative
	.table-col.timeline
	.table-col.spacer
		flex-grow 1
		.table-colheader
		.table-data
			padding 0
			// border-right none
	.table-col
		min-height 100%
		display flex
		flex-direction column
	// 	margin-bottom var(--rowheight)
	.table-colheader
		white-space nowrap
		padding var(--cellpad)
		position sticky
		flex-basis var(--rowheight)
		flex-shrink 0
		flex-grow 0
		top 0
		user-select none
		border-top var(--line)
		border-bottom var(--line)
		border-right var(--line)
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
		&.sortable:hover:not(.sortby)::after
			content ''
			position absolute
			right 5px
			opacity .28
			margin-left 5px 
			margin-top 2px 
			display block
			border-left 5px solid transparent
			border-right 5px solid transparent
			border-top 8px solid currentColor
		&.sortby::after
		&.bookmarks::after
			content ''
			position absolute
			right 5px
			opacity .7
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
			// min-width 400px
			border-right none
			// overflow hidden
		.dateaxiswrapper
			position absolute
			inset 0
	.table-colheader.compact
		&:after
			right 50%
			margin-right -5px
		&.sortable:hover:not(.sortby)::after
			right 50%
			margin-right -5px
	.table-colheader.bookmark
		padding 0
		justify-content center
		cursor pointer
		background-color #fff
		&:hover
			background var(--hovercolor)
		&:hover:not(.bookmarks)::after
			content ''
			width 16px
			height 16px
			position absolute
			right 50%
			top 54%
			margin 0
			margin-right -8px
			transform translateY(-50%)
			opacity .35
			background-image url('/img/pinned.svg')
			background-repeat no-repeat
			background-size 100% 100%
			background-position center
		&.bookmarks::after
			width 16px
			height 16px
			right 50%
			top 54%
			margin 0
			margin-right -8px
			transform translateY(-50%)
			border none
			opacity .7
			background-image url('/img/pinned.svg')
			background-repeat no-repeat
			background-size 100% 100%
			background-position center
	.table-colfooter
		flex-basis var(--rowheight)
		flex-grow 1
		width 100%
	.table-data
		white-space nowrap
		flex-basis var(--rowheight)
		flex-shrink 0
		flex-grow 0
		height var(--rowheight)
		padding var(--cellpad)
		display flex
		align-items center
		font-size var(--tablefontsize)
		cursor default
		position relative
		background #fff
		&:before
			content ''
			inset 0
			display block
			position absolute
			border-bottom var(--line)
			border-right var(--line)
			z-index 1
			pointer-events none
		&.selected.name
			font-weight bold
			padding-right 0
			.label
				text-overflow unset 
		&.selected
			background var(--activecolorgreybrighter)
			&:after
				content ''
				z-index 2
				display block
				position absolute
				inset 0
				top -1px
				pointer-events none
				border-top 1px solid #00000066
				border-bottom 1px solid #00000066
	.table-data.firstrow.selected:after
		top 0
	.table-data.attribute
		font-size var(--tablefontsize)
	.table-data.timeline
		border-right none
	.table-data.bookmark
		padding 0
		width var(--rowheight)
		justify-content center
		cursor pointer
		&:hover .table-bookmark-icon
			opacity .3
		&.bookmarked .table-bookmark-icon
			opacity .7
		&.bookmarked:hover .table-bookmark-icon
			opacity .7
	.table-bookmark-icon
		width 17px
		height 17px
		background-image url('/img/bookmarkfill.svg')
		background-repeat no-repeat
		background-size 100% 100%
		background-position center
		opacity 0
		transition opacity linear .1s
		position relative
		z-index 2
	.timeline-col-hoverline
		position absolute
		top var(--rowheight)
		border-left 1px dotted #000
		pointer-events none
		opacity .6
		z-index 5
	.table-data.attribute
		padding 0
	.label
		text-overflow ellipsis
		overflow: hidden;
		white-space: nowrap;
	.red
		color: red
	.menuitem
		display: flex
	.menu-title
		flex-grow 1
	.count
		font-weight normal
		display inline-block
		margin-left .5em
		opacity	.45
		font-size 11pt
	.menuitem
		display flex
		padding-right 2px
	.title
		text-overflow ellipsis
		overflow hidden
		white-space nowrap
		flex-grow 1
		font-weight: normal

</style> 
