<template>
	<Teleport to="body">
		<div
			class="modal-backdrop"
			:class="{ 'close-click-outside': closeClickOutside }"
			@click.self="closeOnOutsideClick">
			<section
				ref="window"
				class="modal-window"
				:style="dimensions"
				role="dialog"
				aria-modal="true"
				:aria-label="title || 'Dialog'">
				<header class="modal-titlebar">
					<h2 v-if="title">{{ title }}</h2>
					<div
						ref="closeButton"
						class="iconbutton close"
						@click="close">
				</div>
				</header>

				<div class="modal-content">
					<slot></slot>
				</div>
			</section>
		</div>
	</Teleport>
</template>

<script>

function toCssLength(value) {
	return typeof value === 'number' ? `${value}px` : value;
}

export default {
	name: 'Modal',
	emits: ['close'],
	props: {
		title: {
			type: String,
			default: '',
		},
		minWidth: {
			type: [Number, String],
			default: 400,
		},
		maxWidth: {
			type: [Number, String],
			default: 640,
		},
		minHeight: {
			type: [Number, String],
			default: 400,
		},
		maxHeight: {
			type: [Number, String],
			default: 640,
		},
		closeClickOutside: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		dimensions() {
			return {
				minWidth: toCssLength(this.minWidth),
				maxWidth: toCssLength(this.maxWidth),
				minHeight: toCssLength(this.minHeight),
				maxHeight: toCssLength(this.maxHeight),
			};
		},
	},
	methods: {
		close() {
			this.$emit('close');
		},
		closeOnOutsideClick() {
			if (this.closeClickOutside) this.close();
		},
		handleKeydown(event) {
			if (event.key === 'Escape') this.close();
		},
	},
	mounted() {
		window.addEventListener('keydown', this.handleKeydown);
		this.$refs.closeButton.focus();
	},
	beforeUnmount() {
		window.removeEventListener('keydown', this.handleKeydown);
	},
};
</script>

<style lang="stylus" scoped>

.modal-backdrop
	position fixed
	inset 0
	display flex
	align-items center
	justify-content center
	padding 0
	box-sizing border-box
	z-index 80
	pointer-events none

.modal-backdrop.close-click-outside
	pointer-events auto

.modal-window
	display flex
	flex-direction column
	box-sizing border-box
	width fit-content
	height fit-content
	overflow hidden
	pointer-events auto
	background #fff
	box-shadow 0 4px 18px #00000044

.modal-titlebar
	height 36px
	flex 0 0 36px
	display flex
	align-items center
	justify-content space-between
	box-sizing border-box
	border-bottom 1px solid #00000022

.modal-titlebar h2
	margin 0
	padding 0 13px
	font-size 12pt
	font-weight bold
	line-height 24px
	color #000000bb
	
.close
	margin-right 4px

.modal-content
	min-width 0
	min-height 0
	flex 1 1 auto
	overflow auto

</style>
