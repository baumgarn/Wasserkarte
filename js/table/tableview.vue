<template>
	<div class="tableview" :class="{ narrowview }" tabindex="0" @keydown="onKeydown">

		<div class="tableview-header">
			<StatusBar :intableview="true" :containerWidth="tableWidth"/>
		</div>

		<div class="table-content" :class="{ scrolltop: isScrollTop }" @scroll="onScroll" @mousemove="onContentMouseMove" @mouseleave="onTimelineHoverOut" @wheel.passive="onWheel" @click.self="unselectDevice" ref="content">

			<div class="sticky-stack-shadow" :style="{ top: stickyShadowTop + 'px' }"></div>

			<div class="table-grid" :style="rowGridStyle" ref="tableGridRef">

				<div class="table-row table-row-header" :class="{ laststicky: pinnedRowsCount === 0, haspinned: pinnedRowsCount > 0 }">
					<template v-for="col in columns" :key="'header-' + col.key">

						<template v-if="col.type == 'bookmark'">
							<div
								class="table-colheader bookmark"
								:class="{ active: state.tableview_bookmarksontop, bookmarks: state.tableview_bookmarksontop }"
								@click.stop="onHeaderClick(col)"
								v-tooltip
								:tooltipcontent="state.tableview_bookmarksontop? 'Bookmarks fixieren aufheben' : 'Bookmarks fixieren'"
								tooltipside="top"
								tooltipoffset="-5"></div>
						</template>

						<template v-else-if="tableview_compact && (col.type == 'attribute')">
							<div
								class="table-colheader compact"
								:class="[col.key, { sortable: col.sortable, sortby: col.sortable && sortKey === col.key, asc: col.sortable && sortKey === col.key && sortAsc, desc: col.sortable && sortKey === col.key && !sortAsc }]"
								@click.stop="onHeaderClick(col)"
								v-tooltip
								:tooltipcontent="col.tooltip || col.name"
								tooltipside="top"
								tooltipoffset="-5"></div>
						</template>

						<template v-else-if="col.type == 'timeline'">
							<div class="table-colheader timeline" ref="timelineHeaderRef">
								<div class="dateaxiswrapper">
									<DateAxis
										:chartWidth="timelineWidth"
										:frameWidth="timelineWidth"
										:startTimestamp
										:numberOfDays
										:insideTimeline="true"
										:monthsOnly="true"></DateAxis>
								</div>
							</div>
						</template>

						<template v-else>
							<div
								class="table-colheader"
								:class="[col.key, { sortable: col.sortable, sortby: col.sortable && sortKey === col.key, asc: col.sortable && sortKey === col.key && sortAsc, desc: col.sortable && sortKey === col.key && !sortAsc }]"
								@click.stop="onHeaderClick(col)"
								v-tooltip
								:tooltipcontent="col.tooltip"
								:tooltipdisabled="!col.tooltip"
								tooltipside="top"
								tooltipoffset="-5">
								{{ col.name }}
							</div>
						</template>

					</template>
				</div>

				<div
					v-for="(item, pinnedIndex) in pinnedVisibleRows"
					:key="'pinned-' + item.key"
					class="table-row table-body-row"
					:class="{ selectedrow: isSelected(item.row.device), pinned: true, laststicky: pinnedIndex === (pinnedRowsCount - 1) }"
					:style="getPinnedRowStyle(pinnedIndex)">

					<template v-for="col in columns" :key="'pinned-' + item.key + '-' + col.key">

							<div
								class="table-data"
								:class="[col.type, col.key, { selected: isSelected(item.row.device), compact: (col.type == 'attribute' && tableview_compact), bookmarked: col.key === 'bookmark' && isBookmarked(item.row.device) }]"
							@click="onCellClick(col, item.row, $event)"
							v-tooltip
							:tooltipcontent="getDataHoverInfo(col, item.row)"
							:tooltipdisabled="isCompactAttributeTooltipDisabled(col)"
							tooltipside="right"
							tooltipoffset="10"
							tooltipfollowcursor="true"
							:tooltiphandover="col.type == 'attribute' && tableview_compact"
							tooltipdelay="350">

							<template v-if="col.key === 'name'">
								<div class="label">{{ item.row.name }}</div>
							</template>

							<template v-if="col.key === 'bookmark'">
								<div class="table-bookmark-icon"></div>
							</template>

							<template v-if="col.key === 'nfk_avg_all'">
								<div v-if="getNfkDotStyle(item.row.nfk_avg_all)" class="nfk-dot" :style="getNfkDotStyle(item.row.nfk_avg_all)"></div>
								<div class="label">{{ formatNfkAverage(item.row.nfk_avg_all) }}</div>
							</template>

							<template v-if="col.key === 'first_measurement_ts'">
								<div class="label">{{ formatShortDate(item.row.first_measurement_ts) }}</div>
							</template>

							<template v-if="col.key === 'last_measurement_ts'">
								<div class="label">{{ formatShortDate(item.row.last_measurement_ts) }}</div>
							</template>

							<template v-if="col.type == 'attribute'">
								<template v-if="tableview_compact">
									<FilterItem v-if="item.row[col.key]" :obj="item.row[col.key]" type="tablecompact"/>
								</template>
								<template v-else>
									<FilterItem v-if="item.row[col.key]" :obj="item.row[col.key]" type="table"/>
								</template>
							</template>

							<template v-if="col.type== 'timeline'">
								<div class="timeline-inner">
									<TableTimeline
										:device="item.row.device"
										:startTimestamp
										:endTimestamp="latestTimestamp"
										:selected="isSelected(item.row.device)"
										:timelineWidth></TableTimeline>
								</div>
							</template>

						</div>

					</template>
					</div>

				<div
					v-if="regularRowsTopSpacerHeight > 0"
					class="table-row-spacer"
					:style="{ height: regularRowsTopSpacerHeight + 'px' }"></div>

				<div
					v-for="(item, regularIndex) in virtualRegularRows"
					:key="'regular-' + item.key"
					class="table-row table-body-row"
					:class="{ selectedrow: isSelected(item.row.device) }">

					<template v-for="col in columns" :key="'regular-' + item.key + '-' + col.key">

								<div
								class="table-data"
								:class="[col.type, col.key, { selected: isSelected(item.row.device), compact: (col.type == 'attribute' && tableview_compact), firstrow: (virtualRegularStartIndex + regularIndex) === 0 && pinnedRowsCount === 0, bookmarked: col.key === 'bookmark' && isBookmarked(item.row.device) }]"
							@click="onCellClick(col, item.row, $event)"
							v-tooltip
							:tooltipcontent="getDataHoverInfo(col, item.row)"
							:tooltipdisabled="isCompactAttributeTooltipDisabled(col)"
							tooltipside="right"
							tooltipoffset="10"
							tooltipfollowcursor="true"
							:tooltiphandover="col.type == 'attribute' && tableview_compact"
							tooltipdelay="350">

							<template v-if="col.key === 'name'">
								<div class="label">{{ item.row.name }}</div>
							</template>

							<template v-if="col.key === 'bookmark'">
								<div class="table-bookmark-icon"></div>
							</template>

							<template v-if="col.key === 'nfk_avg_all'">
								<div v-if="getNfkDotStyle(item.row.nfk_avg_all)" class="nfk-dot" :style="getNfkDotStyle(item.row.nfk_avg_all)"></div>
								<div class="label">{{ formatNfkAverage(item.row.nfk_avg_all) }}</div>
							</template>

							<template v-if="col.key === 'first_measurement_ts'">
								<div class="label">{{ formatShortDate(item.row.first_measurement_ts) }}</div>
							</template>

							<template v-if="col.key === 'last_measurement_ts'">
								<div class="label">{{ formatShortDate(item.row.last_measurement_ts) }}</div>
							</template>

							<template v-if="col.type == 'attribute'">
								<template v-if="tableview_compact">
									<FilterItem v-if="item.row[col.key]" :obj="item.row[col.key]" type="tablecompact"/>
								</template>
								<template v-else>
									<FilterItem v-if="item.row[col.key]" :obj="item.row[col.key]" type="table"/>
								</template>
							</template>

							<template v-if="col.type== 'timeline'">
								<div class="timeline-inner">
									<TableTimeline
										:device="item.row.device"
										:startTimestamp
										:endTimestamp="latestTimestamp"
										:selected="isSelected(item.row.device)"
										:timelineWidth></TableTimeline>
								</div>
							</template>

						</div>

					</template>
				</div>

				<div
					v-if="regularRowsBottomSpacerHeight > 0"
					class="table-row-spacer"
					:style="{ height: regularRowsBottomSpacerHeight + 'px' }"></div>

			</div>

			<div
				v-if="timelineHoverLinePos > 0"
				class="timeline-col-hoverline"
				:style="{ left: timelineHoverLinePos + 'px', height: `calc(${filteredSortedTableData.length} * var(--rowheight))` }"></div>

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
import { state, toggleBookmark } from '@/state.js';
import dataStore from '@/datastore.js';
import { dataModel } from '@/datamodel.js'
import { displayutil } from '@/displayutil.js';
import PopoverMenuMulti from '@/ui/popovermenu.vue'
import FilterItem from '@/location/filteritem.vue'
import TableTimeline from '@/table/table_timeline.vue'
import DateAxis from '@/charts/dateaxis.vue'
import StatusBar from '@/map/statusbar.vue';
import { getActiveTooltipSource, hideTooltip } from '@/tooltip.js';

export default {
	name: 'TableView',
	setup() {
		return { state }
	},
	components: { PopoverMenuMulti, FilterItem, TableTimeline, DateAxis, StatusBar },
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
			tableWidth: 0,
			timelineHoverX: -1,
			timelineWidthPx: 0,
			timelineColumnOffsetPx: 0,
			rowHeight: 28,
			scrollTopPx: 0,
			viewportHeight: 0,
			virtualOverscan: 8,
			timelineMetricsRaf: null,
			timelineMetricsSettleTimeout: null,
			timelineLayoutSettling: false,
			timelineHoverRaf: null,
			pendingTimelineHoverClientX: null,
			pendingTimelineHoverEvent: null,
			suppressCompactAttributeTooltip: false,
			compactAttributeTooltipCooldownMs: 1000,
			compactAttributeTooltipCooldownTimeout: null,
			lastPointerClientX: null,
			lastPointerClientY: null,
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

			if (this.tableview_col_bookmarks) {
				cols.push({ key: 'bookmark', name: '', type: 'bookmark' })
			}

			cols.push({ key: 'name', name: 'Standort', sortable: true, width: 250 })
			
			if (this.tableview_col_attributes) {
			cols.push(
				{ key: 'nutzung', name: 'Nutzungsart', sortable: true, type: 'attribute' },
				{ key: 'wasser', name: 'Wasserhaushalt', sortable: true, type: 'attribute' },
				{ key: 'boden', name: 'Bodenart', sortable: true, type: 'attribute' },
				{ key: 'humus', name: 'Humusgehalt', sortable: true, type: 'attribute' });
			}
			if (this.tableview_col_nfkavg) {
				cols.push({ key: 'nfk_avg_all', name: 'Ø nFK', sortable: true, width: 65, tooltip: 'Ø nutzbare Feldkapazität alle Tage' })
			}
			// cols.push({ key: 'last_measurement_ts', name: 'bis', sortable: true, width: 74 })
			if (this.tableview_col_von) {
				cols.push({ key: 'first_measurement_ts', name: 'Start', sortable: true, width: 'max-content' })
			}
			cols.push({ key: 'timeline', name: '', type: 'timeline' })
			return cols;
		},
		timelineWidth() {
			return Math.max(this.minTimelineWidth, this.timelineWidthPx || 0);
		},
		timelineColumnOffset() {
			return this.timelineColumnOffsetPx || 0;
		},
		rowGridStyle() {
			const tracks = this.columns.map(col => this.getColumnTrack(col));
			return {
				gridTemplateColumns: tracks.join(' ')
			};
		},
		tableData() {
			return this.devices.map(device => {
				const measurementRange = dataStore.getDeviceMeasurementRange(device);
				return {
					name: device.attributes && device.attributes.Anzeigename || device.name,
					nfk_avg_all: dataStore.getDeviceNfkAverage(device),
					first_measurement_ts: measurementRange.first,
					last_measurement_ts: measurementRange.last,
					boden: dataModel.get_soil_obj(device),
					humus: dataModel.get_humus_obj(device),
					nutzung: dataModel.get_usage_obj(device),
					wasser: dataModel.get_water_obj(device),
					device: device,
				};
			});
		},
		bookmarkedDeviceIds() {
			return new Set(state.bookmarks);
		},
		filteredDeviceIds() {
			return new Set(this.filteredDevices.map(device => device.id));
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
			const bookmarkedDeviceIds = this.bookmarkedDeviceIds;
			return [...this.tableData].sort((a, b) => {
				if (this.pinBookmarksActive) {
					const aBookmarked = bookmarkedDeviceIds.has(a.device && a.device.id) ? 1 : 0;
					const bBookmarked = bookmarkedDeviceIds.has(b.device && b.device.id) ? 1 : 0;
					if (aBookmarked !== bBookmarked) {
						return bBookmarked - aBookmarked;
					}
				}

				const av = a[key];
				const bv = b[key];
				const as = this.getComparableValue(av);
				const bs = this.getComparableValue(bv);
				const aEmpty = as === '';
				const bEmpty = bs === '';
				if (aEmpty !== bEmpty) return aEmpty ? dir : -dir;
				if (typeof as === 'number' && typeof bs === 'number') {
					return dir * (as - bs);
				}
				return dir * String(as).localeCompare(String(bs), undefined, { numeric: true, sensitivity: 'base' });
			});
		},
		pinBookmarksActive() {
			return this.tableview_col_bookmarks && state.tableview_bookmarksontop;
		},
		visibleRows() {
			return this.filteredSortedTableData.map((row, rowIndex) => {
				const rowId = row.device && (row.device.id || row.device.name) || row.name;
				return {
					key: String(rowId) + '-' + rowIndex,
					row,
					rowIndex,
					bookmarked: this.bookmarkedDeviceIds.has(row.device && row.device.id),
				};
			});
		},
		pinnedVisibleRows() {
			if (!this.pinBookmarksActive) return [];
			return this.visibleRows.filter(item => item.bookmarked);
		},
		regularVisibleRows() {
			if (!this.pinBookmarksActive) return this.visibleRows;
			return this.visibleRows.filter(item => !item.bookmarked);
		},
		regularRowsOffset() {
			return this.rowHeight + (this.pinnedRowsCount * this.rowHeight);
		},
		virtualRegularStartIndex() {
			if (!this.regularVisibleRows.length) return 0;
			const visibleTop = Math.max(0, this.scrollTopPx - this.regularRowsOffset);
			return Math.max(0, Math.floor(visibleTop / this.rowHeight) - this.virtualOverscan);
		},
		virtualRegularEndIndex() {
			const total = this.regularVisibleRows.length;
			if (!total) return 0;
			const effectiveViewportHeight = Math.max(this.viewportHeight, this.rowHeight * 12);
			const visibleBottom = Math.max(0, (this.scrollTopPx + effectiveViewportHeight) - this.regularRowsOffset);
			return Math.min(total, Math.ceil(visibleBottom / this.rowHeight) + this.virtualOverscan);
		},
		virtualRegularRows() {
			return this.regularVisibleRows.slice(this.virtualRegularStartIndex, this.virtualRegularEndIndex);
		},
		regularRowsTopSpacerHeight() {
			return this.virtualRegularStartIndex * this.rowHeight;
		},
		regularRowsBottomSpacerHeight() {
			return Math.max(0, (this.regularVisibleRows.length - this.virtualRegularEndIndex) * this.rowHeight);
		},
		pinnedRowsCount() {
			return this.pinnedVisibleRows.length;
		},
		stickyShadowTop() {
			return this.rowHeight + (this.pinnedRowsCount * this.rowHeight);
		},
		filterMenuItems() {
			let items = [];
			if (state.bookmarks.length > 0 ) {
				items.push({ type: 'filteritem', obj: dataModel.bookmarkfilter_obj });
				items.push({ type: 'divider' });
			}  
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
			items.push({ type: 'thinline' });
			items.push({ type: 'note', label: 'SHIFT + Klick - Mehrfachauswahl' });
			items.push({ type: 'note', label: 'ALT + Klick - Ausschließen' });
			return items;
		},
		settingsMenuItems() {
			let menu = [];
			menu.push(
				{ type: 'header', label: 'Tabelle' },
				{ type: 'boolean', label: 'Bookmarks', stateProp: 'tableview_col_bookmarks' },
				{ type: 'boolean', label: 'Standort Eigenschaften', stateProp: 'tableview_col_attributes' },
				{ type: 'boolean', label: 'Ø nFK alle Tage', stateProp: 'tableview_col_nfkavg' },
				{ type: 'boolean', label: 'Startdatum', stateProp: 'tableview_col_von' },
				{ type: 'boolean', label: 'Kompakte Darstellung', stateProp: 'tableview_compact' },
				{ type: 'divider' },
				{ type: 'header', label: 'Zeitachse' },
				{ type: 'select', label: 'Gesamte Zeit', value: 'all', group: 'timelinerange', stateProp: 'tableview_timelinerange' },
				{ type: 'select', label: 'Letzte 365 Tage', value: '365d', group: 'timelinerange', stateProp: 'tableview_timelinerange' },
				{ type: 'select', label: 'Letzte 180 Tage', value: '180d', group: 'timelinerange', stateProp: 'tableview_timelinerange' },
				{ type: 'thinline' },
				{ type: 'boolean', label: 'Schichten anzeigen', stateProp: 'tableview_showdepths', tooltip: 'Zeigt Farbbalken für jede gemessene Schichttiefe anstelle eines Durchschnittswertes' },
				{ type: 'thinline' },
				{ type: 'boolean', label: 'Datenlücken anzeigen', stateProp: 'showDataGaps' },
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
		tableview_col_bookmarks() {
			return state.tableview_col_bookmarks
		},
		tableview_col_nfkavg() {
			return state.tableview_col_nfkavg
		},
		tableview_col_von() {
			return state.tableview_col_von
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
		numberOfDays() {
			return (this.latestTimestamp - this.startTimestamp) / (1000 * 60 * 60 * 24)
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
		minTimelineWidth() {
			return 356;
		},
		timelineSpan() {
			return Math.max(1, this.latestTimestamp - this.startTimestamp);
		},
		timelineHoverLinePos() {
			let pos = -1;
			if (this.timelineHoverX > -1) {
				pos = this.timelineHoverX;
				if (this.earliestTimestamp && this.latestTimestamp) {
					const msPerDay = 24 * 60 * 60 * 1000;
					const earliestX = ((this.earliestTimestamp - this.startTimestamp) / this.timelineSpan) * this.timelineWidth;
					const latestX = ((this.latestTimestamp + msPerDay - this.startTimestamp) / this.timelineSpan) * this.timelineWidth;
					if (pos < earliestX) pos = earliestX;
					if (pos > latestX) pos = latestX;
				}
			} else if (state.timelineDate && state.timelineDate > this.startTimestamp && state.timelineDate < this.latestTimestamp) {
				pos = ((state.timelineDate - this.startTimestamp) / this.timelineSpan) * this.timelineWidth;
			}
			if (pos < 0) return -1;
			return this.timelineColumnOffset + pos;
		},
	},
	methods: {
		queueTimelineMetricsUpdate() {
			if (this.timelineMetricsRaf != null) {
				cancelAnimationFrame(this.timelineMetricsRaf);
			}
			this.timelineMetricsRaf = requestAnimationFrame(() => {
				this.timelineMetricsRaf = null;
				this.updateTimelineMetrics();
				this.observeTimelineLayout();
			});
		},
		queueSettledTimelineMetricsUpdate(delay = 80) {
			this.timelineLayoutSettling = true;
			if (this.timelineMetricsSettleTimeout != null) {
				clearTimeout(this.timelineMetricsSettleTimeout);
			}
			this.timelineMetricsSettleTimeout = setTimeout(() => {
				this.timelineMetricsSettleTimeout = null;
				this.timelineLayoutSettling = false;
				this.queueTimelineMetricsUpdate();
			}, delay);
		},
		observeTimelineLayout() {
			const observer = this._timelineLayoutResizeObserver;
			if (!observer) return;

			if (this._observedTimelineHeaderEl) observer.unobserve(this._observedTimelineHeaderEl);
			if (this._observedTableGridEl) observer.unobserve(this._observedTableGridEl);

			let timelineHeader = this.$refs.timelineHeaderRef;
			if (Array.isArray(timelineHeader)) timelineHeader = timelineHeader[0];
			const tableGrid = this.$refs.tableGridRef;

			if (timelineHeader) {
				observer.observe(timelineHeader);
				this._observedTimelineHeaderEl = timelineHeader;
			} else {
				this._observedTimelineHeaderEl = null;
			}

			if (tableGrid) {
				observer.observe(tableGrid);
				this._observedTableGridEl = tableGrid;
			} else {
				this._observedTableGridEl = null;
			}
		},
		getColumnTrack(col) {
			if (col.type === 'bookmark') return '28px';
			if (col.type === 'timeline') return `minmax(${this.minTimelineWidth}px, 1fr)`;
			if (col.type === 'attribute') {
				if (this.tableview_compact) return `${this.rowHeight}px`;
				return 'max-content';
			}
			if (col.width) return typeof col.width === 'number' ? `${col.width}px` : col.width;
			return '120px';
		},
		getComparableValue(value) {
			if (value && typeof value === 'object') {
				return value.sort ?? value.name ?? '';
			}
			return value ?? '';
		},
		updateViewportMetrics(target = null) {
			const content = target || this.$refs.content;
			if (!content) return;

			this.scrollTopPx = content.scrollTop || 0;
			this.viewportHeight = content.clientHeight || content.getBoundingClientRect().height || 0;
		},
		updateTimelineMetrics() {
			const content = this.$refs.content;
			let timelineHeader = this.$refs.timelineHeaderRef;
			if (Array.isArray(timelineHeader)) timelineHeader = timelineHeader[0];
			if (!content || !timelineHeader) return;

			const contentRect = content.getBoundingClientRect();
			const timelineRect = timelineHeader.getBoundingClientRect();
			const nextOffset = Math.max(0, (timelineRect.left - contentRect.left) + content.scrollLeft);
			const nextWidth = Math.max(this.minTimelineWidth, timelineRect.width);

			if (Math.abs(nextOffset - this.timelineColumnOffsetPx) > 0.5) {
				this.timelineColumnOffsetPx = nextOffset;
			}
			if (Math.abs(nextWidth - this.timelineWidthPx) > 0.5) {
				this.timelineWidthPx = nextWidth;
			}
		},
		queueTimelineHoverUpdate(clientX, event) {
			this.pendingTimelineHoverClientX = clientX;
			this.pendingTimelineHoverEvent = event || null;
			if (this.timelineHoverRaf != null) return;
			this.timelineHoverRaf = requestAnimationFrame(() => {
				this.timelineHoverRaf = null;
				const nextClientX = this.pendingTimelineHoverClientX;
				const nextEvent = this.pendingTimelineHoverEvent;
				this.pendingTimelineHoverClientX = null;
				this.pendingTimelineHoverEvent = null;
				if (!this.setTimelineHoverFromClientX(nextClientX)) {
					this.clearTimelineHover(nextEvent);
				}
			});
		},
		setTimelineHoverFromClientX(clientX) {
			let timelineHeader = this.$refs.timelineHeaderRef;
			if (Array.isArray(timelineHeader)) timelineHeader = timelineHeader[0];
			if (!timelineHeader) return false;

			const rect = timelineHeader.getBoundingClientRect();
			if (clientX < rect.left || clientX > rect.right) return false;

			const nextHoverX = clientX - rect.left;
			if (Math.abs(nextHoverX - this.timelineHoverX) > 0.25) {
				this.timelineHoverX = nextHoverX;
			}
			const fraction = nextHoverX / Math.max(1, this.timelineWidth - 1);
			let ts = this.startTimestamp + fraction * this.timelineSpan;
			ts = dataStore.floorToMidnight(ts);
			if (ts < this.earliestTimestamp) ts = this.earliestTimestamp;
			if (ts > this.latestTimestamp) ts = this.latestTimestamp;
			if (state.timelineDate !== ts) {
				state.timelineDate = ts;
			}
			return true;
		},
		clearTimelineHover(event) {
			if (this.timelineHoverRaf != null) {
				cancelAnimationFrame(this.timelineHoverRaf);
				this.timelineHoverRaf = null;
			}
			this.pendingTimelineHoverClientX = null;
			this.pendingTimelineHoverEvent = null;
			if (this.timelineHoverX === -1 && state.timelineDate == null) {
				hideTooltip(event && event.currentTarget);
				return;
			}
			this.timelineHoverX = -1;
			if (state.timelineDate != null) {
				state.timelineDate = null;
			}
			hideTooltip(event && event.currentTarget);
		},
		getPinnedRowStyle(index) {
			return {
				position: 'sticky',
				top: `${this.rowHeight + (this.rowHeight * index)}px`,
				zIndex: 14,
			};
		},
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
				event && event.stopPropagation();
				toggleBookmark(row.device);
				return;
			}
			if (col.type === 'attribute' && this.tableview_compact) {
				this.lastPointerClientX = event?.clientX ?? this.lastPointerClientX;
				this.lastPointerClientY = event?.clientY ?? this.lastPointerClientY;
				hideTooltip();
				this.suppressCompactAttributeTooltip = true;
				if (this.compactAttributeTooltipCooldownTimeout != null) {
					clearTimeout(this.compactAttributeTooltipCooldownTimeout);
				}
				this.compactAttributeTooltipCooldownTimeout = setTimeout(() => {
					this.compactAttributeTooltipCooldownTimeout = null;
					this.suppressCompactAttributeTooltip = false;
					this.$nextTick(() => this.showCompactAttributeTooltipUnderPointer());
				}, this.compactAttributeTooltipCooldownMs);
			}
			this.selectDevice(row.device);
		},
		selectDevice(device) {
			if (state.popupMenuOpen) return;
			if (state.selectedDevice == (device && device.name)) {
				state.selectedDevice = null;
			} else {
				state.selectedDevice = device && device.name || null;
				window.dispatchEvent(new CustomEvent('sidebar:open', { detail: device }));
				window.dispatchEvent(new CustomEvent('device-selected', { detail: device }));
			}
		},
		unselectDevice() {
			state.selectedDevice = null;
		},
		isFiltered(device) {
			if (state.includeFilter.length == 0 && state.excludeFilter.length == 0) {
				return false
			}
			return !this.filteredDeviceIds.has(device.id);
		},
		onKeydown(e) {
			const data = this.filteredSortedTableData;

			switch (e.key) {
				case 'ArrowDown':
					if (!data.length) return;
					e.preventDefault();
					this.selectedIndex = (this.selectedIndex + 1) % data.length;
					this.selectDevice(data[this.selectedIndex].device);
					break;

				case 'ArrowUp':
					if (!data.length) return;
					e.preventDefault();
					this.selectedIndex = (this.selectedIndex - 1 + data.length) % data.length;
					this.selectDevice(data[this.selectedIndex].device);
					break;

				case 'Enter':
					if (!data.length) return;
					e.preventDefault();
					if (this.selectedIndex >= 0) {
						this.selectDevice(data[this.selectedIndex].device);
					}
					break;
			}
		},
		onScroll(e) {
			this.updateViewportMetrics(e.target);
			this.isScrollTop = this.scrollTopPx === 0;
			this.updateTimelineMetrics();
			hideTooltip();
		},
		onWheel(event) {
			this.queueTimelineHoverUpdate(event.clientX, event);
		},
		onContentMouseMove(event) {
			this.lastPointerClientX = event.clientX;
			this.lastPointerClientY = event.clientY;
			const target = event.target instanceof Element ? event.target : null;
			const activeTooltipSource = getActiveTooltipSource();
			if (activeTooltipSource?.classList?.contains('table-data')) {
				const currentTableData = target?.closest('.table-data');
				if (!currentTableData) {
					hideTooltip(activeTooltipSource);
				}
			}
			const timelineZone = target?.closest('.table-data.timeline, .table-colheader.timeline, .timeline-inner, .dateaxiswrapper');
			if (!timelineZone) {
				this.clearTimelineHover(event);
				return;
			}
			this.queueTimelineHoverUpdate(event.clientX, event);
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
		isCompactAttributeTooltipDisabled(col) {
			return this.suppressCompactAttributeTooltip && col.type === 'attribute' && this.tableview_compact;
		},
		showCompactAttributeTooltipUnderPointer() {
			if (this.suppressCompactAttributeTooltip) return;
			if (!Number.isFinite(this.lastPointerClientX) || !Number.isFinite(this.lastPointerClientY)) return;
			const target = document.elementFromPoint(this.lastPointerClientX, this.lastPointerClientY);
			const cell = target?.closest?.('.table-data.attribute.compact');
			if (!cell || !this.$el.contains(cell)) return;
			cell.__appTooltip?.showNow?.(false);
		},
		getDataHoverInfo(col, row) {
			if (col.type !== 'attribute' || !this.tableview_compact) return null;
			const val = row[col.key];
			return val ? `${val.name}` : col.name;
		},
		formatNfkAverage(value) {
			if (typeof value !== 'number' || Number.isNaN(value)) return '-';
			return `${value.toFixed(0)} %`;
		},
		getNfkDotStyle(value) {
			if (typeof value !== 'number' || Number.isNaN(value)) return null;
			return {
				backgroundColor: dataModel.get_nfk_color(value),
			};
		},
		formatShortDate(timestamp) {
			if (!Number.isFinite(timestamp)) return '-';
			const date = new Date(timestamp);
			const day = String(date.getDate()).padStart(2, '0');
			const month = String(date.getMonth() + 1).padStart(2, '0');
			const year = String(date.getFullYear()).slice(-2);
			return `${day}.${month}.${year}`;
		},
		isBookmarked(device) {
			return this.bookmarkedDeviceIds.has(device && device.id);
		},
		onTimelineHoverOut(event) {
			this.clearTimelineHover(event);
		},
	},
	mounted() {
		this.$el.focus();
		this.earliestTimestamp = dataStore.earliestTimestamp;
		this.latestTimestamp = dataStore.latestTimestamp;
		const measureTarget = this.$refs.content || this.$el;
		this._tableResizeObserver = new ResizeObserver(() => {
			this.tableWidth = measureTarget.clientWidth || measureTarget.getBoundingClientRect().width;
			this.updateViewportMetrics(measureTarget);
			this.$nextTick(() => {
				if (this.timelineLayoutSettling) return;
				this.queueTimelineMetricsUpdate();
			});
		});
		this._timelineLayoutResizeObserver = new ResizeObserver(() => {
			if (this.timelineLayoutSettling) return;
			this.queueTimelineMetricsUpdate();
		});
		this._tableResizeObserver.observe(measureTarget);
		this.observeTimelineLayout();
		this.tableWidth = measureTarget.clientWidth || measureTarget.getBoundingClientRect().width;
		this.updateViewportMetrics(measureTarget);
		this.$nextTick(() => this.queueTimelineMetricsUpdate());
	},
	beforeUnmount() {
		if (this._tableResizeObserver) this._tableResizeObserver.disconnect();
		if (this._timelineLayoutResizeObserver) this._timelineLayoutResizeObserver.disconnect();
		if (this.timelineMetricsRaf != null) cancelAnimationFrame(this.timelineMetricsRaf);
		if (this.timelineMetricsSettleTimeout != null) clearTimeout(this.timelineMetricsSettleTimeout);
		if (this.timelineHoverRaf != null) cancelAnimationFrame(this.timelineHoverRaf);
		if (this.compactAttributeTooltipCooldownTimeout != null) clearTimeout(this.compactAttributeTooltipCooldownTimeout);
		hideTooltip();
	},
	watch: {
		tableview_compact() {
			this.$nextTick(() => this.queueSettledTimelineMetricsUpdate());
		},
		tableview_col_attributes() {
			this.$nextTick(() => this.queueSettledTimelineMetricsUpdate());
		},
		tableview_col_bookmarks() {
			this.$nextTick(() => this.queueSettledTimelineMetricsUpdate());
		},
		tableview_col_nfkavg() {
			this.$nextTick(() => this.queueSettledTimelineMetricsUpdate());
		},
		tableview_col_von() {
			this.$nextTick(() => this.queueSettledTimelineMetricsUpdate());
		},
		timelineRange() {
			this.$nextTick(() => this.queueTimelineMetricsUpdate());
		},
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
			this.$nextTick(() => {
				this.$el.focus();
				this.updateViewportMetrics();
				this.queueTimelineMetricsUpdate();
			});
		},
		telemetryLoaded: {
			handler() {
				this.earliestTimestamp = dataStore.earliestTimestamp;
				this.latestTimestamp = dataStore.latestTimestamp;
				this.$nextTick(() => this.updateViewportMetrics());
			}
		},
	},
}
</script>

<style scoped lang="stylus">
	.tableview
		--headerheight 42px
		--rowheight 28px
		// --rowheight 36px
		--cellpad 0 8px
		--line 1px solid #00000018
		--tablefontsize 12px
		--hovercolor #e9edf3
		--darklinecolor #777
		*
			box-sizing border-box
		position absolute
		inset 0
		z-index 90
		background #f4f4f4
		display flex
		flex-direction column
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
			overflow auto
			position relative
			&.scrolltop .sticky-stack-shadow
				opacity 0
	.sticky-stack-shadow
		position sticky
		height 0
		transition opacity linear .1s
		pointer-events none
		z-index 12
		&:after
			content ''
			display block
			height 8px
			background linear-gradient(to bottom, rgba(0, 0, 0, 0.075), transparent)
	.table-grid
		display grid
		min-width 100%
		width max-content
		grid-auto-flow row
	.table-row-spacer
		grid-column 1 / -1
		pointer-events none
	.table-row
		display grid
		grid-column 1 / -1
		grid-template-columns subgrid
		background #fff
		position relative
	.table-row-header.haspinned::after
	.table-body-row.pinned.laststicky::after
		content ''
		position absolute
		left 0
		right 0
		bottom 0
		height 1px
		background var(--darklinecolor)
		pointer-events none
		z-index 4
	// .table-body-row.pinned
		// background #f8f8f8
	.table-body-row.pinned .table-data.selected
		background var(--activecolorgreybrighter)
	.table-row-header
		position sticky
		top 0
		z-index 19
	.table-colheader
		white-space nowrap
		padding var(--cellpad)
		height var(--rowheight)
		user-select none
		border-top var(--line)
		border-bottom var(--line)
		border-right var(--line)
		display flex
		align-items center
		background #fff
		font-weight bold
		font-size var(--tablefontsize)
		position relative
		&:after
			pointer-events none
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
			padding 0
			border-right none
		.dateaxiswrapper
			position absolute
			inset 0
	.table-colheader.compact
		border-right var(--line)
		box-shadow inset -1px 0 0 rgba(0,0,0,0.12)
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
	.table-data
		white-space nowrap
		height var(--rowheight)
		padding var(--cellpad)
		display flex
		align-items center
		font-size var(--tablefontsize)
		cursor default
		position relative
		// background #fff
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
			z-index 2
			&:after
				content ''
				z-index 2
				display block
				position absolute
				inset 0
				top -1px
				pointer-events none
				border-top 1px solid var(--darklinecolor)
				border-bottom 1px solid var(--darklinecolor)
	.table-data.firstrow.selected:after
		top 0
	.table-data.attribute
		padding 0
		font-size var(--tablefontsize)
	.table-data.attribute .filteritem
		margin-left -0.5px
		margin-top -1px
	.table-data.nfk_avg_all
	.table-data.first_measurement_ts
	.table-data.last_measurement_ts
		justify-content flex-end
		font-variant-numeric tabular-nums
	.table-data.nfk_avg_all
		padding-left 22px
	.nfk-dot
		position absolute
		left 7px
		top 50%
		width 12px
		height 12px
		border-radius 50%
		transform translateY(-50%)
		z-index 2
		pointer-events none
	.table-data.timeline
		padding 0
		border-right none
		overflow visible
	.timeline-inner
		width 100%
		height 100%
		overflow hidden
	.table-data.bookmark
		padding 0
		// width var(--rowheight)
		justify-content center
		cursor pointer
		.table-bookmark-icon
			width 16px
			height 16px
			background-image url('/img/bookmarkfill.svg')
			background-repeat no-repeat
			background-size 100% 100%
			background-position center
			opacity 0
			transition opacity linear .1s
			position relative
			z-index 2
		&:hover .table-bookmark-icon
			opacity .3
		&.bookmarked .table-bookmark-icon
			opacity .7
		&.bookmarked:hover .table-bookmark-icon
			opacity .5
	.timeline-col-hoverline
		position absolute
		top var(--rowheight)
		border-left 1px dotted #000
		pointer-events none
		opacity .6
		z-index 18
	.label
		text-overflow ellipsis
		overflow hidden
		white-space nowrap
</style>
