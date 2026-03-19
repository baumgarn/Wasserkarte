<template>

	<div v-if="icon"
		 class="filtericon"
		 :class="[filterNameClass, { excluded: exclude }]"
		 :style="{ backgroundImage: `url(${icon})`, width: size+'px', height: size+'px' }"></div>

</template>

<script>
import { IconFactory } from '@/location/iconfactory.js';
import { state } from '@/state.js';

function toFilterNameClass(name) {
	if (!name) return null;

	return 'filter-' + String(name)
		.toLowerCase()
		.replace(/ä/g, 'ae')
		.replace(/ö/g, 'oe')
		.replace(/ü/g, 'ue')
		.replace(/ß/g, 'ss')
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');
}

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
		},
		exclude: {
			type: Boolean,
			default: false
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

			try {
				if (this.obj.img) {
					if (token === this._loadToken) {
						this.icon = '/img/' + this.obj.img;
					}
					return;
				}

				if (this.obj.short != null) {
					const url = await IconFactory.getShortcodeIcon(this.obj, this.size);
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
		filterName() {
			return this.obj && this.obj.name ? this.obj.name : null;
		},
		filterNameClass() {
			return toFilterNameClass(this.filterName);
		},
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
	position relative
	background-size contain
	background-position center
	background-repeat no-repeat
	&.excluded::after
		content ''
		position absolute
		left 0
		top 50%
		width 100%
		height 11%
		background var(--warningred)
		opacity .8
		transform translateY(-50%) rotate(-45deg)
		transform-origin center
		pointer-events none

.filtericon.filter-bookmarks
	opacity .65
	background-size 85%
</style>
