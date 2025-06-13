<template>
	<div class="mobiletoggle" @click="openMobile()">
		
	</div>
	<div
		class="menubar"
		:class="{ anyactive: isAnyMenuItemActive, soloactive: isSoloActive, mobileopen: state.mobilemenuOpen }" >

		<template v-for="item in menuItems" :key="item.key">

		<div
			v-if="(item.key != 'info' || !state.isMobile) && (item.key != 'error' || showErrorMenu)"
			:key="item.key"
			class="menubaritem"
			:class="[item.key, { active: state.menuOpen[item.key] }, item.class]"
			@click="handleClick(item, $event )">

			<div class="tooltip">
				{{ item.tooltip }}
			</div>

			<div class="icon"></div>

			<div v-if="item.key =='error'" class="count">
				{{ state.faultyDevices.length }}
			</div>
			<!-- <div v-if="item.key =='orte'" class="count">
				{{ state.devices.length }}
			</div> -->
		</div>

		</template>

	</div>

</template>

<script>
import { state } from '@/state.js';
import { dataModel } from '@/datamodel.js';
import ColorScheme from '@/menu/colorscheme_item.vue';

export default {
	name: 'MenuBar',
	components: {
		ColorScheme
	},
	setup() {
		return {state};
	},
	data() {
		return {
		};
	},
	computed: {
		menuItems() {
			return [
				{ title: 'Orte', tooltip: 'Alle ' + this.state.devices.length + ' Standorte', key: 'orte', group: '1' },
				{ title: 'Fehlermeldungen', tooltip: 'Fehlermeldungen', key: 'error', group: '1' },
				{ title: 'Bodenfeuchte', tooltip: 'Bodenfeuchte Kartendarstellung', key: 'bodenfeuchte', group: '1' },
				{ title: 'Karten', tooltip: 'Karten der Landesvermessung\nund Geobasisinformation Brandenburg (LGB)', key: 'karten', group: '1' },
				{ title: 'Einstellungen', tooltip: 'Einstellungen', key: 'einstellungen', group: '1' },
				{ title: 'Info', tooltip: 'Über das Projekt', key: 'info', activate: this.activateInfo, solo: true, class: "solo", group: '1' },
			];
		},
		isAnyMenuItemActive() {
			return Object.values(state.menuOpen).some(isActive => isActive);
		},
		isSoloActive() {
			return this.menuItems.some(item =>
				item.solo && state.menuOpen[item.key]
			);
		},
		menuItemsWithHtmlTooltips() {
			return this.menuItems.map(item => {
				return {
					...item,
					tooltipHtml: item.tooltip.replace(/\n/g, '<br>')
				};
			})
		},
		selectedColorScheme() {
			return dataModel.color_schemes.nfk[state.colorScheme];
		}, 
		showErrorMenu() {
			return ( state.showErrors && state.faultyDevices.length > 0)
		}
	},
	methods: {
		handleClick(item, event) {
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

			// add class was-clicked to prevent tooltip to immediately appear when clicked
			const el = event.currentTarget;
			this.$nextTick(() => {
				el.classList.add('was-clicked');
			});
			setTimeout(() => {
				el.classList.remove('was-clicked');
			}, 2000);
			//
		},
		openMobile() {
			state.mobilemenuOpen = true;
		},
		deactivateSoil () {
			state.selectedSoil = "Alle";
		},
		activateInfo () {
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
	width 44px
	height 44px
	background-color var(--menuinactivebg)
	background-color #eaeaea
	border-bottom 1px solid #ccc
	position relative
	cursor pointer
	box-sizing border-box

.toggle
	width 22px
	border-bottom 1px solid var(--menubg)
	cursor pointer
	background url(/img/dreieck_links.png)  center / 65% no-repeat
	background-color var(--menuinactivebg)
	&:hover
		background-color #dbdbdb88

.menubar .menubaritem.active
	background #fff
	border-bottom 1px solid #fff
	z-index 10
	position relative

.menubar .menubaritem.solo.active
	border-bottom 1px solid #ccc

.count
	font-size 11px
	position absolute
	right 0
	top 0
	width 18px
	text-align center
	margin 1px 0
	color #c34949
	font-weight bold
	opacity .75

.icon
	position absolute
	left 0
	top 0
	width 100%
	height 100%
	background-size 90% 90%
	background-position center center
	background-repeat no-repeat

.icon.active:before
	opacity .9

.orte .icon
	background-image url('/img/sensor.png')
	background-size 77% 77%
	opacity .9

.bodenfeuchte .icon
	background-image url('/img/drop_dunkel.png')

.boeden .icon
	background-image url('/img/soil.png')
	background-size 80% 80%

.error .icon
	background-image url('/img/warningred.png')
	background-size 80% 80%
	opacity .7

.karten .icon
	background-image url('/img/karten.png')

.colorscheme .icon
	left 7px
	right 7px
	top 7px
	bottom 7px
	height auto
	width auto
.einstellungen .icon
	background-image url('/img/settings.png')
	background-size 85% 85%
	opacity .8

.info .icon
	background-image url('/img/info.png')
	opacity .8
	background-size 70% 70%

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
