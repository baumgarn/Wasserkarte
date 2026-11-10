<template>
	<Icon v-if="icon"
		:type="icon.type"
		:size="inline ? '1lh' : icon.size"
		:tag="inline ? 'span' : 'div'"
		:bg-color="inline ? color : undefined"
		:class="{ 'post-category-inline': inline }" />
	<slot v-else />
</template>

<script>
import { dataModel } from '@/datamodel.js';
import Icon from '@/ui/Icon.vue';

const categoryIcons = new Map([
	['Niederschlag', { type: 'post-niederschlag', size: 16, nfk: 120 }],
	['Bewässerung', { type: 'post-bewaessert', size: 18, nfk: 120 }],
	['Fehler', { type: 'post-fehler', size: 18, nfk: 0 }],
]);

export const postCategories = [...categoryIcons.keys()];

export default {
	components: { Icon },
	props: {
		category: String,
		inline: Boolean,
	},
	computed: {
		icon() {
			return categoryIcons.get(this.category);
		},
		color() {
			return dataModel.get_nfk_color(this.icon.nfk);
		},
	},
};
</script>

<style lang="stylus" scoped>
.icon.post-category-inline
	display inline-block
	left -1px
	vertical-align top
	margin-right .25em
	&.type-post-bewaessert
		mask-size 115%
</style>
