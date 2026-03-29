<template>

	<div v-if="shouldRender"
		 class="icon filtericon"
		 :class="[filterNameClass, typeClass, { excluded: exclude, shadow: shadow }]"
		 :style="iconStyle"></div>

</template>

<script>
import { IconFactory } from '@/ui/iconfactory.js';
import { state } from '@/state.js';

function slugify(value) {
	if (!value) return null;

	return String(value)
		.toLowerCase()
		.replace(/ä/g, 'ae')
		.replace(/ö/g, 'oe')
		.replace(/ü/g, 'ue')
		.replace(/ß/g, 'ss')
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');
}

function toFilterNameClass(name) {
	const slug = slugify(name);
	return slug ? 'filter-' + slug : null;
}



export default {
	name: 'Icon',
	props: {
		obj: {
			type: Object,
			required: false,
			default: null
		},
		type: {
			type: String,
			default: null
		},
		size: {
			type: Number,
			default: 24,
			required: false
		},
		exclude: {
			type: Boolean,
			default: false
		},
		shadow: {
			type: Boolean,
			default: false
		}
	},
	data() {
		return {
			inlineIcon: null,
			_loadToken: 0,
		};
	},
	watch: {
		obj: {
			handler: 'loadIcon',
			deep: false,
			immediate: true
		},
		type: 'loadIcon',
		size: 'loadIcon',
	},
	methods: {
		normalizedType() {
			const slug = slugify(this.type);
			if (!slug) return null;

			return slug;
		},

		async loadIcon() {
			const token = ++this._loadToken;

			this.inlineIcon = null;

			if (this.normalizedType()) return;

			if (!this.obj) return;

			try {
				if (this.obj.img) {
					if (token === this._loadToken) {
						this.inlineIcon = '/img/' + this.obj.img;
					}
					return;
				}

				if (this.obj.short != null) {
					const url = await IconFactory.getShortcodeIcon(this.obj, this.size);
					if (token === this._loadToken) this.inlineIcon = url;
					return;
				}

				if (token === this._loadToken) this.inlineIcon = null;

			} catch (err) {
				console.error('Icon load failed for', this.obj, err);
				if (token === this._loadToken) this.inlineIcon = null;
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
		typeClass() {
			const normalizedType = this.normalizedType();
			return normalizedType ? 'type-' + normalizedType : null;
		},
		shouldRender() {
			return Boolean(this.inlineIcon || this.typeClass);
		},
		iconStyle() {
			const style = {
				width: this.size + 'px',
				height: this.size + 'px',
				'flex-basis': this.size + 'px',
			};

			if (this.inlineIcon) {
				style.backgroundImage = `url(${this.inlineIcon})`;
			}

			return style;
		},
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
	flex-grow 0
	flex-shrink 0
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
	&.shadow
		filter: drop-shadow(0 1px 1px rgba(0,0,0,.22));

.filtericon.filter-bookmarks
	opacity .65
	background-size 85%

.filtericon.type-pflanze
	background-image url(/img/plant.svg)
	opacity .8

.filtericon.type-soil
	background-image url(/img/soil.png)
	background-size 112%
	opacity .7
.filtericon.type-soil
	background-image url(/img/soil.png)
	background-size 112%
	opacity .7

.filtericon.type-soilblack
	background-image url(/img/soilblack.png)
	background-size 120%
	opacity 1

.filtericon.type-tropfen-flat
	background-image url(/img/tropfen_flat.png)

// .filtericon.type-drop-amount
	// background-image url(/img/tropfen_flat.png)
	// background-size 60% 90%

.filtericon.type-bewaessert
	background-image url(/img/Bewaessert.svg)

.filtericon.type-grundwasser
	background-image url(/img/Grundwasser.svg)
	
.filtericon.type-cursor
	background-image url(/img/cursor.png)

.filtericon.type-regenabhaengig
	background-image url(/img/regenabhaengig.svg)

.filtericon.type-bookmarks
	background-image url(/img/bookmarkfill.svg)

.filtericon.type-totwasser
	background-image url(/img/totwasser2.png)
	background-size auto
	background-repeat repeat
	background-position -3px 0

.filtericon.type-gesamt
	background-image url(/img/barrellflat.png)
</style>
