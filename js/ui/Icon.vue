<template>

	<div v-if="shouldRender"
		 class="icon icon"
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

function normalizeCssSize(value) {
	if (value === null || value === undefined || value === '') return null;
	if (typeof value === 'number') return value + 'px';

	const normalized = String(value).trim();
	return /^-?\d+(\.\d+)?$/.test(normalized) ? normalized + 'px' : normalized;
}

function normalizePixelSize(value) {
	if (typeof value === 'number') return value;
	if (typeof value === 'string' && /^-?\d+(\.\d+)?$/.test(value.trim())) {
		return Number(value);
	}

	return value;
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
			type: [Number, String],
			default: 24,
			required: false
		},
		fill: {
			type: Boolean,
			default: false
		},
		opacity: {
			type: [Number, String],
			default: null
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
					const url = await IconFactory.getShortcodeIcon(this.obj, normalizePixelSize(this.size));
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
			const style = {};

			if (!this.fill && this.size !== null && this.size !== '') {
				const sizeValue = normalizeCssSize(this.size);
				style.width = sizeValue;
				style.height = sizeValue;
				style['flex-basis'] = sizeValue;
			}

			if (this.inlineIcon) {
				style.backgroundImage = `url(${this.inlineIcon})`;
			}

			if (this.opacity !== null && this.opacity !== '') {
				style.opacity = this.opacity;
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
.icon
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

.icon.filter-bookmarks
	opacity .65
	background-size 85%

.icon.type-pflanze
	background-image url(/img/plant.svg)
	opacity .8

.icon.type-soil
	background-image url(/img/soil.png)
	background-size 112%
	opacity .7
.icon.type-soil
	background-image url(/img/soil.png)
	background-size 112%
	opacity .7

.icon.type-soilblack
	background-image url(/img/soilblack.png)
	background-size 120%
	opacity 1

.icon.type-tropfen-flat
	background-image url(/img/tropfen_flat.png)

// .icon.type-drop-amount
	// background-image url(/img/tropfen_flat.png)
	// background-size 60% 90%

.icon.type-bewaessert
	background-image url(/img/Bewaessert.svg)

.icon.type-grundwasser
	background-image url(/img/Grundwasser.svg)
	
.icon.type-cursor
	background-image url(/img/cursor.png)

.icon.type-mouseclick
	background-image url(/img/mouseclick.png)

.icon.type-mouseclicklight
	background-image url(/img/mouseclicklight.png)

.icon.type-mouseclickblau
	background-image url(/img/mouseclickblau.png)

.icon.type-regenabhaengig
	background-image url(/img/regenabhaengig.svg)

.icon.type-bookmarks
	background-image url(/img/bookmarkfill.svg)

.icon.type-totwasser
	background-image url(/img/totwasser2.png)
	background-size auto
	background-repeat repeat
	background-position -3px 0

.icon.type-gesamt
	background-image url(/img/barrellflat.png)

.icon.type-standorttabelle
	background-image url(/img/table.png)
	background-size 66% 66%
	background-position 58% center
	opacity .6

.icon.type-orte
	background-image url(/img/sensor.png)
	background-size 77% 77%
	opacity .7

.icon.type-bodenarten
	background-image url(/img/soil.png)
	background-size 85% 85%
	opacity .9

.icon.type-bodenfeuchte
	background-image url(/img/tropfen.png)
	background-size 45%

.icon.type-filter
	background-image url(/img/filter.png)
	background-size 95%
	opacity .5

.icon.type-boeden
	background-image url(/img/soil.png)
	background-size 80% 80%

.icon.type-error
	background-image url(/img/warningred.png)
	background-size 80% 80%
	opacity .65

.icon.type-karten
	background-image url(/img/karten.png)
	background-size 90%
	opacity .9

.icon.type-bodenkunde
	background-image url(/img/buch2.png)
	background-size 90%

.icon.type-einstellungen
	background-image url(/img/settings.png)
	background-size 85% 85%
	opacity .8

.icon.type-info
	background-image url(/img/info.png)
	opacity .8
	background-size 70% 70%

.icon.type-moreinfo
	background-image url(/img/morev.png)
	background-size 70% 70%
	background-color #eeeeeeee
	border-radius 50%
</style>
