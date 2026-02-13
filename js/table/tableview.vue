<template>
	<div class="tableview" tabindex="0" @keydown="onKeydown">

		<div class="tableview-header">

		</div>

		<!-- <div class="table-content-wrapper"> -->

			<div class="table-content" :class="{ scrolltop: isScrollTop }" @scroll="onScroll" ref="content">
			
				<div class="table-col" :class="col.class" v-for="col in columns" :key="col.key">
					
					<div class="table-colheader" :class="{ sortable: col.sortable, sortby: col.sortable && sortKey === col.key, asc: col.sortable && sortKey === col.key && sortAsc, desc: col.sortable && sortKey === col.key && !sortAsc }" @click="col.sortable && sortBy(col.key)">{{ col.header }}</div>

					<div
						v-for="row in sortedTableData"
						class="table-data"
						:class="[col.type, { selected: isSelected(row.device) }]"
						@click="selectDevice(row.device)"
						>

						<template v-if="col.key === 'name'">
							
							<div class="label">{{ row.name }}</div>	

						</template>

						<template v-if="col.key === 'wasser' || col.key === 'boden' || col.key === 'humus' || col.key === 'nutzung'">

							<div class="label">{{ row[col.key]?.name }}</div>	

						</template>
				
						

					</div>

				</div>

			<!-- </div> -->

		</div>

		<div class="windowbuttons plain left row">
			<div class="iconbutton close" v-on:click="close()"></div>
		</div>
		
		<PopoverMenuMulti
		class="tablesettingspopup"
		ref="settingspopupred" 
		:items="settingsMenuItems" />
		
		<div class="windowbuttons plain row">
			<!-- <div class="iconbutton light filter" v-on:click="openFilterPopup()"></div> -->
			<div class="iconbutton light settings" v-on:click="openSettingsPopup()" ref="settingsbuttonref"></div>
		</div>
		

	</div>
</template>

<script>
import { state } from '@/state.js';
import dataStore from '@/datastore.js';
import { dataModel } from '@/datamodel.js'
import ColorDot from '@/menu/colordot.vue';
import PopoverMenuMulti from '@/ui/popovermenu_multi.vue'

export default {
	name: 'TableView',
	setup() {
		return {state}
	},
	components: {ColorDot, PopoverMenuMulti},
	props: {
		type: Boolean,
		sideview: {
			default: false
		},
	},
	data() {
		return {
			isOpen: false,
			selectedIndex: -1,
			sortKey: 'name',
			sortAsc: true,
			isScrollTop: true,
		}
	},
	computed: {
		devices() {
			return state.devices;
		},
		columns() {
			return [
				{ key: 'name', header: 'Standort', sortable: true },
				{ key: 'wasser', header: 'Wasserhaushalt', sortable: true, type: 'attribut' },
				{ key: 'boden', header: 'Bodenart', sortable: true, type: 'attribut' },
				{ key: 'humus', header: 'Humusgehalt', sortable: true, type: 'attribut' },
				{ key: 'nutzung', header: 'Nutzungsart', sortable: true, type: 'attribut' },
				{ key: 'spacer', header: '', class: 'spacer' },
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
		selectedDevice() {
			return state.selectedDevice;
		},
		settingsMenuItems() {
			let menu = [];
			menu.push(
				{type:'boolean', label:'Kompakte Darstellung', stateProp:'tableview_compact'},
			)
			return menu;
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
		setSelectedDevice(device) {
			state.selectedDevice = device?.name || null;
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
			if (!this.sortedTableData.length) return;

			switch (e.key) {
				case 'ArrowDown':
					e.preventDefault();
					this.selectedIndex =
						(this.selectedIndex + 1) % this.sortedTableData.length;
					this.selectDevice(this.sortedTableData[this.selectedIndex].device);
					break;

				case 'ArrowUp':
					e.preventDefault();
					this.selectedIndex =
						(this.selectedIndex - 1 + this.sortedTableData.length) %
						this.sortedTableData.length;
					this.selectDevice(this.sortedTableData[this.selectedIndex].device);
					break;

				case 'Enter':
					e.preventDefault();
					if (this.selectedIndex >= 0) {
						this.selectDevice(this.sortedTableData[this.selectedIndex].device);
					}
					break;
			}
		},
		getCols() {
			return [this.$refs.col0, this.$refs.col1].filter(Boolean);
		},
		onScroll(e) {
			this.isScrollTop = e.target.scrollTop === 0;
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
				top: 4,
				right: 4,
			};
			this.$refs.settingspopupred.open(position);
		},
	},
	mounted() {
		this.$el.focus();
	},
	beforeUnmount() {
		if (this._resizeObserver) this._resizeObserver.disconnect();
	},
	watch: {
		selectedDevice: {
			immediate: true,
			handler(name) {
				this.selectedIndex = this.sortedTableData.findIndex(
					d => d.device.name === name
				);
			}
		}
	},
}
</script>

<style scoped lang="stylus">
	.tableview
		--headerheight 36px
		--rowheight 28px
		--cellpad 0 8px
		--line 1px solid #00000020
		--tablefontsize 12px
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
			&.scrolltop .table-colheader:before
				opacity 0
			.table-colheader:before
				opacity 1
	.table-col.spacer
		flex-grow 1
		.table-colheader
		.table-data
			padding 0
			border-right none
	.table-col
		padding-bottom var(--rowheight)
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
				background #e9edf3
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
		&.selected
			background var(--activecolorgrey)
	.table-data.attribut
		font-size var(--tablefontsize)
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

</style> 
