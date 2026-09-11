
<template>

	<div class="wrapper" :class="{ showtooltips: state.tooltips, sidebaropen: state.sidebarOpen }" >

		<div class="mapareawrapper">

			<Map />
			
			<div class="ui">
				
				<div class="topbar" :style="state.sidebarOpen ? { right: '600px' } : {}">

					<MenuBar />
					
					<div v-if="state.isMobile && !state.menuOpen.info" class="infobutton" @click="state.menuOpen.info = true"></div>

					<StatusBar />
					
				</div>

				<div class="leftui">

					<div class="menuwindows">	

						<AccountMenu v-if="state.menuOpen.account"/>
						<GeraeteMenu v-if="state.menuOpen.orte"/>
						<ErrorMenu v-if="state.menuOpen.error"/>
						<MarkerMenu v-if="state.menuOpen.bodenfeuchte"/>
						<FilterMenu v-if="state.menuOpen.filter"/>
						<ColorschemeMenu v-if="state.menuOpen.colorscheme"/>
						<!-- <BodenkundeMenu v-if="!state.isMobile && state.menuOpen.bodenkunde"/> -->
						<SettingsMenu v-if="state.menuOpen.einstellungen"/>
						<KartenMenu v-if="state.menuOpen.karten"/>
					</div>
										
				</div>
				
				<a v-if="!state.isMobile" href="http://badbelzig-klimadaten.de" class="klimadaten desktop"><img src="/img/klimadaten.png" ></a>

				<a v-else-if="state.isMobile" href="http://badbelzig-klimadaten.de" class="klimadaten mobile"><img src="/img/klimadaten.png" ></a>
				
			</div>

		</div>

		<!-- <BodenkundeMenu v-if="state.menuOpen.bodenkunde"/> -->
		
		<TimelineWrapper />
		
		<TableView v-if="state.menuOpen.standorttabelle"/>

		<BodenkundeMenu v-if="state.menuOpen.bodenkunde"/>
		<!-- <BodenkundeMenu v-if="state.isMobile && state.menuOpen.bodenkunde"/> -->

	<Modal
		v-if="state.accountDetailsOpen"
		title="Konto"
		:min-width="320"
		:max-width="560"
		:min-height="180"
		:max-height="600"
		@close="state.accountDetailsOpen = false">
		<AccountSettings />
	</Modal>

	<Modal
		v-if="state.accountsOpen"
		title="Accounts"
		:min-width="560"
		:max-width="820"
		:min-height="180"
		:max-height="600"
		@close="state.accountsOpen = false">
		<Accounts ref="accounts" @create="state.createAccountOpen = true" @permissions="openAccountPermissions" />
	</Modal>

	<Modal
		v-if="state.createAccountOpen"
		title="Neuer Account"
		:min-width="360"
		:max-width="640"
		:min-height="180"
		:max-height="600"
		@close="state.createAccountOpen = false">
		<CreateAccount />
	</Modal>

	<Modal
		v-if="state.accountPermissionsOpen && state.accountPermissionsUser"
		title="Berechtigungen"
		:min-width="320"
		:max-width="480"
		:min-height="180"
		:max-height="480"
		@close="closeAccountPermissions">
		<AccountPermissions :key="state.accountPermissionsUser.id" :user="state.accountPermissionsUser" @delete-account="openDeleteAccount" @saved="closeAccountPermissions" />
	</Modal>

	<Modal
		v-if="state.deleteAccountOpen && state.accountPermissionsUser"
		title="Account löschen"
		@close="closeDeleteAccount">
		<DeleteAccount :user="state.accountPermissionsUser" @close="closeDeleteAccount" @deleted="accountDeleted" />
	</Modal>

	<Modal
		v-if="activationRequested"
		title="Wasserkarte-Account aktivieren"
		:min-width="320"
		:max-width="480"
		:min-height="180"
		:max-height="520"
		@close="closeActivation">
		<ActivateAccount @close="closeActivation" />
	</Modal>

	<PostCreate
		v-if="state.postCreateOpen && state.postCreateDevice"
		:key="state.postCreateDevice.id"
		:device="state.postCreateDevice"
		@close="closePostCreate" />

	</div>

	<div class="rightui">

		<Info v-if="state.menuOpen.info"/>
		

		<Sidebar @create-post="openPostCreate" />
		
		<LayerLegends />

	</div>

	<!-- <div class="loadingoverlay" v-if="!loaded">

	</div> -->

</template>

<script>
import { nextTick } from 'vue';
import TableView from '@/table/tableview.vue';
import Sidebar from '@/views/sidebar.vue';
import Info from '@/views/info.vue';
import MenuBar from '@/menu/menu_bar.vue';
import Legend from '@/map/legend.vue';
import MarkerMenu from '@/menu/menu_marker.vue';
import FilterMenu from '@/menu/menu_filter.vue';
import BodenkundeMenu from '@/menu/menu_bodenkunde.vue';
import KartenMenu from '@/menu/menu_karten.vue';
import ErrorMenu from '@/menu/menu_error.vue';
import LayerLegends from '@/menu/layer_legends.vue';
import SettingsMenu from '@/menu/menu_settings.vue';
import ColorschemeMenu from '@/menu/menu_colorscheme.vue';
import ColorschemeGradient from '@/menu/colorscheme_gradient.vue';
import SoilMenu from '@/menu/menu_soil.vue';
import GeraeteMenu from '@/menu/menu_devices.vue';
import AccountMenu from '@/menu/menu_account.vue';
import Modal from '@/views/modal.vue';
import AccountSettings from '@/management/account_settings.vue';
import Accounts from '@/management/accounts.vue';
import CreateAccount from '@/management/create_account.vue';
import AccountPermissions from '@/management/account_permissions.vue';
import DeleteAccount from '@/management/delete_account.vue';
import ActivateAccount from '@/management/activate_account.vue';
import PostCreate from '@/posts/post_create.vue';
import StatusBar from '@/map/statusbar.vue';
import { accountSettingsKeys, getAccountSettings, state } from '@/state.js';
import { managementAuth } from '@/management/auth.js';
import { dataModel } from '@/datamodel.js';
import { config } from '@/config.js';
import Map from '@/map/map.vue';
import TimelineWrapper from '@/map/timeline_wrapper.vue';

export default {
	name: 'Home',
	setup() {
		return {state, dataModel}
	},
	data(){
		return {
			settingsSaveTimer: null,
		}
	}, 
	components: {
		Sidebar,
		TableView,
		KartenMenu,
		MenuBar,
		LayerLegends,
		Legend,
		MarkerMenu,
		FilterMenu,
		BodenkundeMenu,
		ErrorMenu,
		SettingsMenu,
		GeraeteMenu,
		ColorschemeMenu,
		ColorschemeGradient,
		AccountMenu,
		Modal,
		AccountSettings,
		Accounts,
		CreateAccount,
		AccountPermissions,
		DeleteAccount,
		ActivateAccount,
		PostCreate,
		SoilMenu,
		TimelineWrapper,
		StatusBar,
		Map,
		Info
	},
	computed: {
		activationRequested() {
			return typeof this.$route.query.activateToken === 'string' && this.$route.query.activateToken !== '';
		},
		telemetryLoaded() {
			return state.telemetryLoaded;
		},
		accountSettings() {
			return accountSettingsKeys.map(key => state[key]);
		},
	},
	methods: {
		openAccountPermissions(user) {
			state.accountPermissionsUser = user;
			state.accountPermissionsOpen = true;
		},
		closeAccountPermissions() {
			state.accountPermissionsOpen = false;
			state.accountPermissionsUser = null;
		},
		openDeleteAccount() {
			state.deleteAccountOpen = true;
		},
		closeDeleteAccount() {
			state.deleteAccountOpen = false;
		},
		accountDeleted() {
			state.deleteAccountOpen = false;
			this.closeAccountPermissions();
			this.$refs.accounts?.loadUsers(true);
		},
		closeActivation() {
			const query = { ...this.$route.query };
			delete query.activateToken;
			this.$router.replace({ name: 'home', query });
		},
		openPostCreate(device) {
			state.postCreateDevice = device;
			state.postCreateOpen = true;
		},
		closePostCreate() {
			state.postCreateOpen = false;
			state.postCreateDevice = null;
		},
		scheduleAccountSettingsSave() {
			if (!state.account.authenticated) return;
			window.clearTimeout(this.settingsSaveTimer);
			this.settingsSaveTimer = window.setTimeout(async () => {
				try {
					await managementAuth.updateSettings(getAccountSettings());
				} catch (error) {
					console.error('Einstellungen konnten nicht gespeichert werden.', error);
				}
			}, 300);
		},
	},
	watch: {
		accountSettings: {
			deep: true,
			handler() {
				this.scheduleAccountSettingsSave();
			},
		},
		'state.menuOpen.standorttabelle'(isOpen, wasOpen) {
			if (isOpen && !wasOpen && this.activationRequested) this.closeActivation();
		},
	},
	beforeUnmount() {
		window.clearTimeout(this.settingsSaveTimer);
	},
};
</script>

<style lang="stylus" scoped>

	.klimadaten
		user-select none
		background white
		display flex
		padding 0 12px
		align-items center
		justify-content center
		box-shadow 0 0 4px #00000044
		img
			height 80%

	// .klimadaten.mobile
		// height 30px
		// align-self center
		// position relative
		
	.klimadaten.mobile
	.klimadaten.desktop
		position absolute
		left 0
		bottom 0
		height 32px
		margin 16px

	.klimadaten.mobile
		height 32px
		margin 0 8px 48px

	.infobutton
		margin 6px
		font-size 10px
		display flex
		align-items center
		justify-content center
		flex-shrink 0
		background white
		height 36px
		width 36px
		padding 3px
		border-radius 50%
		filter drop-shadow(2px 3px 2px #00000022)
		cursor pointer
		background #fff url(/img/info.png) center / 80% no-repeat

</style>
