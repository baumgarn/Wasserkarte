<template>
	<div class="devices menuwindow" :class="{ open: isOpen, sideview }" tabindex="0" @keydown="onKeydown">
		<div class="menuwindow-header" @click="toggleOpen" v-if="!sideview">
			<h3>
				<span>Standorte</span>
				<span class="count" v-if="!sideview">{{ deviceItems.length }}</span>
			</h3>
			<!-- <div class="menuwindow-button-row" :class="{ 'has-visible-layer': hasVisibleLayer }">
				<div class="menuwindow-button icon pinbookmarks" @click="togglePinbookmarks" :class="{ active: state.wsmlegends }"></div>
			</div> -->

		</div>
			<div class="menuwindow-content" :class="{ scrolltop: isScrollTop }" @scroll="onContentScroll" ref="content">

			<div v-if="showBookmarks" class="bookmark-container" ref="bookmarkContainer">
				<div
					v-for="device in bookmarkedDeviceItems"
					:key="'bookmark-' + device.name"
					ref="items"
					:data-device-name="device.name"
					class="menuitem"
					:class="{ selected: isSelectedDevice(device), bookmarked: true }"
					@click="selectDevice(device)"
				>

					<ColorDot :device />

					<div class="title">
						{{ device.attributes?.Anzeigename || device.name }}
					</div>

					<div
						class="menuitem-bookmark-icon"
						@click.stop="toggleDeviceBookmark(device)"
					></div>

				</div>
			</div>

			<div
				v-for="device in regularDeviceItems"
				:key="device.name"
				ref="items"
				:data-device-name="device.name"
				class="menuitem"
				:class="{ selected: isSelectedDevice(device) }"
				@click="selectDevice(device)"
			>

				<ColorDot :device />

				<div class="title">
					{{ device.attributes?.Anzeigename || device.name }}
				</div>

				<div
					v-if="isBookmarked(device)"
					class="menuitem-bookmark-icon"
					@click.stop="toggleDeviceBookmark(device)"
				></div>

			</div>
		</div>
	</div>
</template>

<script>
import { state, isBookmarked, toggleBookmark } from '@/state.js';
import dataStore from '@/datastore.js';
import { dataModel } from '@/datamodel.js'
import ColorDot from '@/menu/colordot.vue';

export default {
	name: 'GeraeteMenu',
	setup() {
		return {state}
	},
	components: {ColorDot},
	props: {
		sideview: {
			type: Boolean,
			default: false
		},
		nowarning: {
			type: Boolean,
			default: false
		},
		stateProperty: {
			type: String,
			required: false,
			default: "selectedDevice"
		}
	},
	data() {
		return {
			isOpen: false,
			selectedIndex: -1,
			showbookmarksontop: true,
			isScrollTop: true
		}
	},
	computed: {
		devices() {
			return state.devices;
		},
		bookmarks() {
			return state.bookmarks;
		},
		selectedDevice() {
			return state[this.stateProperty];
		},
		deviceItems() {
			return [...this.deviceTitles].sort((a, b) => {
				let a_name = a.attributes?.Anzeigename || a.name;
				let b_name = b.attributes?.Anzeigename || b.name;
				return a_name.localeCompare(b_name);
			});
		},
		bookmarkedDeviceItems() {
			if (!this.showbookmarksontop) return [];
			return this.deviceItems.filter(device => this.isBookmarked(device));
		},
		regularDeviceItems() {
			if (!this.showbookmarksontop) return this.deviceItems;
			return this.deviceItems.filter(device => !this.isBookmarked(device));
		},
		displayedDeviceItems() {
			if (!this.showbookmarksontop) return this.deviceItems;
			return [...this.bookmarkedDeviceItems, ...this.regularDeviceItems];
		},
		showBookmarks() {
			return this.showbookmarksontop && this.bookmarkedDeviceItems.length > 0;
		},
		deviceTitles() {
			return this.devices;
		}
	},
	methods: {
		syncSelectedIndex() {
			this.selectedIndex = this.displayedDeviceItems.findIndex(
				d => d.name === this.selectedDevice
			);
		},
		findItemElement(deviceName) {
			const items = Array.isArray(this.$refs.items) ? this.$refs.items : [];
			return items.find(el => el && el.dataset && el.dataset.deviceName === deviceName);
		},
		scrollItemIntoView(deviceName) {
			const content = this.$refs.content;
			const item = this.findItemElement(deviceName);
			if (!content || !item) return;

			const contentRect = content.getBoundingClientRect();
			const itemRect = item.getBoundingClientRect();
			const bookmarkContainer = this.$refs.bookmarkContainer;
			const stickyHeight = bookmarkContainer ? bookmarkContainer.getBoundingClientRect().height : 0;
			const topBoundary = contentRect.top + stickyHeight + 6;
			const bottomBoundary = contentRect.bottom;

			if (itemRect.top < topBoundary) {
				content.scrollTop -= (topBoundary - itemRect.top);
			} else if (itemRect.bottom > bottomBoundary) {
				content.scrollTop += (itemRect.bottom - bottomBoundary);
			}
		},
		selectDevice(device) {
			state[this.stateProperty] = device?.name || null;
			window.dispatchEvent(new CustomEvent('sidebar:open', { detail: device }));
			window.dispatchEvent(new CustomEvent('device-selected', { detail: device }));
			this.$nextTick(() => {
				this.syncSelectedIndex();
				this.scrollItemIntoView(device && device.name);
			});
		},
		toggleDeviceBookmark(device) {
			toggleBookmark(device);
		},
		setSelectedDevice(device) {
			state[this.stateProperty] = device?.name || null;
		},
		isBookmarked(device) {
			return isBookmarked(device);
		},
		isSelectedDevice(device) {
			return this.selectedDevice === (device && device.name);
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
			if (!this.displayedDeviceItems.length) return;

			switch (e.key) {
				case 'ArrowDown':
					e.preventDefault();
					this.selectedIndex =
						(this.selectedIndex + 1) % this.displayedDeviceItems.length;
					this.selectDevice(this.displayedDeviceItems[this.selectedIndex]);
					break;

				case 'ArrowUp':
					e.preventDefault();
					this.selectedIndex =
						(this.selectedIndex - 1 + this.displayedDeviceItems.length) %
						this.displayedDeviceItems.length;
					this.selectDevice(this.displayedDeviceItems[this.selectedIndex]);
					break;

				case 'Enter':
					e.preventDefault();
					if (this.selectedIndex >= 0) {
						this.selectDevice(this.displayedDeviceItems[this.selectedIndex]);
					}
					break;
			}
		},
		onContentScroll(event) {
			this.isScrollTop = event.target.scrollTop === 0;
		},
	},
	mounted() {
		this.$el.focus();
	},
	beforeUnmount() {
	},
	watch: {
		bookmarks: {
			deep: true,
			handler() {
				this.$nextTick(() => {
					this.syncSelectedIndex();
					this.$el.focus();
				});
			}
		},
		displayedDeviceItems() {
			this.$nextTick(() => {
				this.syncSelectedIndex();
				this.$el.focus();
			});
		},
		selectedDevice: {
			immediate: true,
			handler() {
				this.syncSelectedIndex();
			}
		}
	},
}
</script>

<style scoped lang="stylus">
		.menuwindow.devices:focus
			outline none
		.menuwindow.devices
			background #fff
			width 275px
			.menuwindow-content
				overflow-y auto
				overflow-x hidden
				padding 6px
				min-height 0
				position relative
		.bookmark-container
			position sticky
			z-index 5
			top -6px
			background #fff
			border-bottom var(--thinline)
			margin-bottom 6px
			margin-top -6px
			padding 6px 0
	.sideview
		flex-grow 0
		flex-shrink 0
		position relative
		padding-right 6px
		margin-right 32px
		margin-right 0
		flex-basis 300px !important
		background var(--windowbg)
		border-radius 0
	.sideview:after
		content ''
		position absolute
		right -7px
		top 6px
		bottom 6px
	.sideview .menuwindow-header
		margin-bottom 3px
	.sideview .menuwindow-header h3:before
		content ''
		height 28px
		width 28px
		margin-bottom -7px
		margin-right 5px 
		margin-left -10px 
		display inline-block
		opacity .85
		background url('/img/sensor.png') no-repeat center center / 100%
	.red
		color: red
	.menuitem
		display flex
		align-items center
		padding-right 2px
	.menu-title
		flex-grow 1
	.count
		font-weight normal
		display inline-block
		margin-left .5em
		opacity	.45
		font-size 11pt
	.title
		min-width 0
		text-overflow ellipsis
		overflow hidden
		white-space nowrap
		flex-grow 1
		flex-shrink 1
		font-weight: normal
	.menuitem-bookmark-icon
		flex-grow 0
		flex-shrink 0
		flex-basis 16px
		width 16px
		height 16px
		margin-left 4px
		margin-right 4px
		opacity .6
		background-image url('/img/bookmarkfill.svg')
		background-repeat no-repeat
		background-size 100% 100%
		background-position center
		cursor pointer
		transition opacity linear .05s
		&:hover
			opacity .35

</style> 
