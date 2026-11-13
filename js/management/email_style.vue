<template>
	<div ref="control" class="email-style" :class="{ disabled }" :tabindex="disabled ? -1 : 0" @click="toggle" @keydown.enter.space.prevent="toggle">
		<span>{{ selected.label }}</span>
		<span class="detail">{{ selected.detail }}</span>
		<span class="arrow">▾</span>
	</div>
	<Teleport to="body">
		<PopoverMenu ref="menu" class="email-style-menu" :items="items">
			<template #select="{ item }">
				<span>{{ item.label }}</span>
				<span class="detail">{{ item.detail }}</span>
			</template>
		</PopoverMenu>
	</Teleport>
</template>

<script>
import PopoverMenu from '@/ui/popovermenu.vue';

const styles = [
	{ value: 'compact', label: 'Kompakt', detail: 'nur Durchschnitt' },
	{ value: 'detailed', label: 'Ausführlich', detail: 'alle Messtiefen' },
];

export default {
	components: { PopoverMenu },
	props: {
		modelValue: { type: String, required: true },
		disabled: { type: Boolean, default: false },
	},
	emits: ['update:modelValue'],
	computed: {
		selected() {
			return styles.find(style => style.value === this.modelValue) || styles[1];
		},
		items() {
			return styles.map(style => ({
				...style, type: 'select', active: style.value === this.modelValue, closeOnSelect: true,
				onSelect: () => this.$emit('update:modelValue', style.value),
			}));
		},
	},
	methods: {
		toggle() {
			if (this.disabled) return;
			const rect = this.$refs.control.getBoundingClientRect();
			this.$refs.menu.open({ fixed: true, left: rect.left, bottom: window.innerHeight - rect.bottom, width: `${rect.width}px` });
		},
	},
	watch: {
		disabled(value) { if (value) this.$refs.menu?.close(); },
	},
};
</script>

<style lang="stylus" scoped>
.email-style
	position relative
	display grid
	grid-template-columns 1fr max-content
	align-items center
	gap 8px
	height calc(1.3em + 16px)
	padding 4px 22px 4px 7px
	border 1px solid rgba(0, 0, 0, .2)
	border-radius 4px
	box-sizing border-box
	background #fff
	color #000
	font-size 9pt
	line-height 16px
	white-space nowrap
	cursor pointer
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

.detail
	margin-left auto
	text-align right
	color #00000066

.email-style-menu
	box-sizing border-box
	.detail
		padding-left 8px
</style>
