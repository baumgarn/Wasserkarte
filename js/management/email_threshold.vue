<template>
	<div ref="control" class="email-threshold" :style="{ backgroundColor: color(selected.value) }" :class="{ disabled }" :tabindex="disabled ? -1 : 0" @click="toggle" @keydown.enter.space.prevent="toggle">
		<span class="level-label control-value">
			<span v-for="level in levels" :key="level.value" :class="{ concealed: level.value !== selected.value }">{{ level.name }}</span>
		</span>
		<span class="level-limit control-value">
			<span v-for="level in levels" :key="level.value" :class="{ concealed: level.value !== selected.value }">{{ level.value === 'always' ? '' : `< ${level.value} nFK %` }}</span>
		</span>
		<span class="arrow">▾</span>
	</div>
	<Teleport to="body">
		<PopoverMenu ref="menu" class="email-threshold-menu" :items="items">
			<template #select="{ item }">
				<span class="level-label">{{ item.label }}</span>
				<span class="level-limit">{{ item.value === 'always' ? '' : `< ${item.value} nFK %` }}</span>
			</template>
		</PopoverMenu>
	</Teleport>
</template>

<script>
import { dataModel } from '@/datamodel.js';
import PopoverMenu from '@/ui/popovermenu.vue';

export default {
	components: { PopoverMenu },
	props: {
		modelValue: { type: [Number, String], required: true },
		disabled: { type: Boolean, default: false },
	},
	emits: ['update:modelValue'],
	computed: {
		items() {
			return this.levels.map(level => ({
				type: 'select', label: level.name, value: level.value,
				backgroundColor: this.color(level.value),
				active: level.value === this.modelValue, closeOnSelect: true,
				onSelect: () => this.$emit('update:modelValue', level.value),
			}));
		},
		levels() {
			return [{ value: 'always', name: 'Immer' }, ...dataModel.nfk_labels.filter(level => level.value <= 50).slice().reverse()];
		},
		selected() {
			return this.levels.find(level => level.value === this.modelValue) || this.levels[0];
		},
	},
	methods: {
		color(value) {
			if (value === 'always') return '#fff';
			const index = dataModel.nfk_labels.findIndex(level => level.value === value);
			return dataModel.get_nfk_color(((dataModel.nfk_labels[index - 1]?.value ?? value) + value) / 2);
		},
		toggle() {
			if (this.disabled) return;
			const rect = this.$refs.control.getBoundingClientRect();
			this.$refs.menu.open({
				fixed: true,
				left: Math.max(8, Math.min(rect.left, window.innerWidth - rect.width - 8)),
				bottom: window.innerHeight - rect.bottom,
				width: `${rect.width}px`,
			});
		},
	},
	watch: {
		disabled(value) { if (value) this.$refs.menu?.close(); },
	},
};
</script>

<style lang="stylus" scoped>
.email-threshold
	position relative
	display grid
	grid-template-columns max-content max-content
	align-items center
	justify-content flex-start
	height calc(1.3em + 16px)
	font-size 9pt
	width max-content
	flex 0 0 auto
	padding 4px 22px 4px 7px
	border 1px solid rgba(0, 0, 0, .2)
	border-radius 4px
	box-sizing border-box
	cursor pointer
	gap 8px
	&.disabled
		pointer-events none
		opacity .5
	.arrow
		position absolute
		right 6px
		width 8px
		text-align center
		line-height 16px
		color #666
		font-size 9pt

.control-value
	display grid
	> span
		grid-area 1 / 1
	.concealed
		visibility hidden

.level-label
	line-height 16px
	color #000
	font-weight normal
	font-size 9pt
	text-transform none
	white-space nowrap

.email-threshold-menu
	box-sizing border-box
	.level-limit
		margin-left auto
		padding-left 8px

.level-limit
	font-size 9pt
	font-weight normal
	white-space nowrap
	font-variant-numeric tabular-nums
	color #00000066
</style>
