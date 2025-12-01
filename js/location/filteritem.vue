<template>
	<div class="filteritem" :class="{ hasicon: icon, active: isActive, hover: isHover }"
	     @mouseleave="mouseLeave" @mouseenter="mouseEnter" @click="click">
		
		<div v-if="icon" class="icon" :style="{ backgroundImage: `url(${icon})` }"></div>

		<div class="name">
			{{ obj.name }}
		</div>
	</div>
</template>

<script>
import { IconFactory } from '@/location/iconfactory.js';
import { state } from '@/state.js';

export default {
	props: {
		obj: {
			type: Object,
			required: true
		}
	},
	data() {
		return {
			icon: null,
			_loadToken: 0,   // used to avoid race conditions
		};
	},
	async mounted() {
		// load initial icon
		await this.loadIcon();
	},
	watch: {
		// when obj changes, reload icon
		obj: {
			handler: 'onObjChange',
			deep: false,
			immediate: false
		}
	},
	methods: {
		async onObjChange() {
			// If the component is reused for a different obj, reload icon.
			await this.loadIcon();
		},

		async loadIcon() {
			// increment token to cancel previous pending load results
			const token = ++this._loadToken;

			// clear current icon so UI won't show stale image while new one loads
			this.icon = null;

			if (!this.obj) return;

			// If there is a simple image path, set it synchronously and return early
			if (this.obj.img) {
				// Ensure the token didn't change synchronously (unlikely but safe)
				if (token === this._loadToken) {
					this.icon = '/img/' + this.obj.img;
				}
				return;
			}

			try {
				// If humusIcon / soilIcon are numbers/arrays, they may require async generation
				if (this.obj.humusIcon != null) {
					// getHumusIcon is async and returns a data URL
					const url = await IconFactory.getHumusIcon(this.obj.humusIcon);
					if (token === this._loadToken) this.icon = url;
					return;
				}

				if (this.obj.soilIcon) {
					// getSoilIcon is async and returns a data URL
					const url = await IconFactory.getSoilIcon(this.obj.soilIcon);
					if (token === this._loadToken) this.icon = url;
					return;
				}

				// fallback: no icon
				if (token === this._loadToken) this.icon = null;

			} catch (err) {
				// loading or drawing failed; keep icon null and optionally log
				/* eslint-disable no-console */
				console.error('Icon load failed for', this.obj, err);
				/* eslint-enable no-console */
				if (token === this._loadToken) this.icon = null;
			}
		},

		mouseEnter() {
			state.hoverFilter = this.obj;
		},
		mouseLeave() {
			state.hoverFilter = null;
		},
		click() {
			state.activeFilter = state.activeFilter?.name !== this.obj.name ? this.obj : null;
		} 
	},
	computed: {
		isActive() {
			return (state.activeFilter?.name == this.obj.name);
		},
		isHover() {
			return (state.hoverFilter?.name == this.obj.name);
		}
	}
};
</script>

<style lang="stylus" scoped>
.filteritem
	display inline-flex
	align-items center
	border 1px solid #ddd
	border-radius 20px
	height 40px
	padding 0 8px
	background #fff
	box-shadow 0 2px 1px rgba(0,0,0,.05)
	cursor pointer
	&.hasicon
		padding-left 3px
		padding-right 8px
	&.hover
		background var(--activecolorbrightest)
		background var(--activecolorgreybrighter)
	&.active
	&.active.hover
		background var(--activecolorbrighter)
		background var(--activecolorgrey)
		border 1px solid #ccc
.icon
	width 32px
	height 32px
	background-size contain
	background-position center
	background-repeat no-repeat
	margin-right 3px

.name
	white-space nowrap
</style>