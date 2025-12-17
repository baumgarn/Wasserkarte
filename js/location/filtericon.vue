<template>

	<div v-if="icon" class="filtericon" :style="{ backgroundImage: `url(${icon})`, width: size+'px', height: size+'px' }"></div>

</template>

<script>
import { IconFactory } from '@/location/iconfactory.js';
import { state } from '@/state.js';

export default {
	props: {
		obj: {
			type: Object,
			required: true
		},
		size: {
			type: Number,
			default: 30,
			required: false
		}
	},
	data() {
		return {
			icon: null,
			_loadToken: 0,
		};
	},
	async mounted() {
		await this.loadIcon();
	},
	watch: {
		obj: {
			handler: 'onObjChange',
			deep: false,
			immediate: false
		}
	},
	methods: {
		async onObjChange() {
			await this.loadIcon();
		},

		async loadIcon() {
			const token = ++this._loadToken;

			this.icon = null;

			if (!this.obj) return;

			if (this.obj.img) {
				if (token === this._loadToken) {
					this.icon = '/img/' + this.obj.img;
				}
				return;
			}

			try {
				if (this.obj.humusIcon != null) {
					const url = await IconFactory.getHumusIcon(this.obj, this.size);
					if (token === this._loadToken) this.icon = url;
					return;
				}

				if (this.obj.soilIcon) {
					const url = await IconFactory.getSoilIcon(this.obj, this.size);
					if (token === this._loadToken) this.icon = url;
					return;
				}

				if (token === this._loadToken) this.icon = null;

			} catch (err) {
				console.error('Icon load failed for', this.obj, err);
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
		},
	},
	computed: {
		borderColor() {
			return this.obj.borderColor;
		},
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
.filtericon
	display inline-flex
	background-size contain
	background-position center
	background-repeat no-repeat

</style>