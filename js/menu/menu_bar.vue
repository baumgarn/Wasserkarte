<template>
	<div class="mobiletoggle" @click="openMobile()">
		
	</div>
	<div
		class="menubar"
		:class="{ groupactive: isGroupActive, soloactive: isSoloActive, mobileopen: state.mobilemenuOpen }" >

		<template v-for="item in menuItems" :key="item.key">

		<div
			v-if="(item.key != 'info' || !state.isMobile) && (item.key != 'error' || showErrorMenu)"
			:key="item.key"
			class="menubaritem"
			:class="[item.key, { active: state.menuOpen[item.key] }, item.class]"
			v-tooltip
			:tooltipcontent="item.tooltip"
			:tooltipdisabled="isGroupActive"
			tooltipalignoffset="5"
			tooltipside="bottom"
			tooltipalign="left"
			@click="handleClick(item)">

			<Icon
				class="menubaricon"
				:type="item.key"
				fill
				:style="item.iconStyle || {}" />

			<div v-if="item.key =='error'" class="count">
				{{ state.faultyDevices.length }}
			</div>
		</div>

		</template>

	</div>

</template>

<script>
import { state } from '@/state.js';
import Icon from '@/ui/Icon.vue';

export default {
	name: 'MenuBar',
	components: {
		Icon
	},
	setup() {
		return {state};
	},
	computed: {
		menuItems() {
			return [
				{ title: 'Standorttabelle', tooltip: 'Tabellarische Zeitachse', activate: this.activateTableView, key: 'standorttabelle', group: '1' },
				{ title: 'Standorte', tooltip: 'Standort Liste', key: 'orte', group: '1' },
				{ title: 'Fehlermeldungen', tooltip: 'Fehlermeldungen', key: 'error', group: '1' },
				{ title: 'Darstellung', tooltip: 'Standort Marker Darstellung', key: 'bodenfeuchte', group: '1' },
				{ title: 'Filter', tooltip: 'Filter nach Standorteigenschaften', key: 'filter', group: '1' },
				{ title: 'Karten', tooltip: 'Karten der Landesvermessung\nund Geobasisinformation Brandenburg (LGB)', key: 'karten', group: '1' },
				// { title: 'Bodenarten', tooltip: 'Bodenarten', key: 'bodenarten', group: '1' },
				{ title: 'Einstellungen', tooltip: 'Einstellungen', key: 'einstellungen', group: '1' },
				{ title: 'Bodenkunde', tooltip: 'Hintergrundwissen Bodenkunde', key: 'bodenkunde', group: '1' },
				{ title: 'Info', tooltip: 'Über das Projekt', key: 'info', activate: this.activateInfo, solo: true, class: "solo", group: '2' },
			];
		},
		isGroupActive() {
			return this.menuItems.some(item =>
				item.group && !item.solo && state.menuOpen[item.key]
			);
			// return Object.values(state.menuOpen).some(isActive => isActive);
		},
		isSoloActive() {
			return this.menuItems.some(item =>
				item.solo && state.menuOpen[item.key]
			);
		},
		showErrorMenu() {
			return ( this.telemetryLoaded && state.showErrors && state.faultyDevices.length > 0)
		},
		telemetryLoaded() {
			return state.telemetryLoaded;
		}
	},
	methods: {
		handleClick(item) {
			const wasActive = state.menuOpen[item.key];


			if (item.toggleable) {
				state.menuOpen[item.key] = !wasActive;
			} else if (item.group) {
				if (wasActive) {
					state.menuOpen[item.key] = false;

					if (item.deactivate) {
						item.deactivate();
					}
				} else {
					this.menuItems
						.filter(i => i.group === item.group)
						.forEach(i => {
							if (state.menuOpen[i.key]) {
								state.menuOpen[i.key] = false;
								if (i.deactivate) i.deactivate();
							}
						});

					state.menuOpen[item.key] = true;
					if (item.activate) item.activate();
				}
			}

			},
		openMobile() {
			state.mobilemenuOpen = true;
		},
		deactivateSoil () {
			state.selectedSoil = "Alle";
		},
		activateInfo () {
			state.selectedDevice = null;
		},
		activateTableView () {
			state.menuOpen.info = false;
			state.selectedDevice = null;
		}
	}
};
</script>

<style scoped lang="stylus">


.menubar
	display flex
	flex-direction row
	flex-grow 0
	gap 1px
	background-color #bbb
	align-self flex-start
	padding 1px 1px 0 0
	pointer-events all
	box-shadow 2px 1px 4px #00000022

.menubaritem
	width var(--menubariconsize)
	height var(--menubariconsize)
	background-color var(--menuinactivebg)
	background-color #eaeaea
	// background-color #fff
	border-bottom 1px solid #ccc
	position relative
	cursor pointer
	box-sizing border-box

.toggle
	width 22px
	border-bottom 1px solid #aaa
	cursor pointer
	background url(/img/dreieck_links.png)  center / 65% no-repeat
	background-color var(--menuinactivebg)
	&:hover
		background-color #dbdbdb88

// .menubar .menubaritem:active .icon
	// opacity .5
	// background #d8d8d8
	// background #f0f0f0
// .menubar .menubaritem.active
.menubar .menubaritem.active
	background #fff
	border-bottom 1px solid #fff
	z-index 10
	position relative

.menubar .menubaritem.solo.active
	border-bottom 1px solid #ccc

.count
	font-size 10px
	position absolute
	right 0
	top 0
	width 18px
	text-align center
	margin 1px 0
	color #c34949
	font-weight bold
	opacity .75

.menubaricon
	width 100%
	height 100%

// mobile

.mobiletoggle
	display none

.is-mobile
	.menubar
		display none
	.mobiletoggle
		cursor pointer
		display block
		width 36px
		height 36px
		flex-shrink 0
		border-radius 50%
		background white
		margin 6px
		filter drop-shadow(2px 3px 2px #00000022)
		background white url(/img/moreh.png) no-repeat center / 80%
	.menubar.mobileopen
		position fixed
		display flex
		left 0
		top 0
		z-index 10

</style>
