<template>
	<Teleport to="body">
		<div
			class="modal-backdrop"
			:class="{ 'close-click-outside': closeClickOutside, 'modal-stacked': stacked }"
			@click.self="closeOnOutsideClick">
			<section
				ref="window"
				class="modal-window"
				role="dialog"
				:aria-modal="modal ? 'true' : undefined"
				:aria-label="title || 'Dialog'">
				<header class="modal-titlebar">
					<div class="modal-title">
						<h2 v-if="title">{{ title }}</h2>
					</div>
					<div class="modal-titlebar-content">
						<slot name="titlebar"></slot>
					</div>
					<div
						ref="closeButton"
						class="iconbutton close"
						type="button"
						aria-label="Dialog schließen"
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
export default {
	name: 'Modal',
	emits: ['close'],
	props: {
		title: {
			type: String,
			default: '',
		},
		closeClickOutside: {
			type: Boolean,
			default: false,
		},
		modal: { type: Boolean, default: true },
		stacked: { type: Boolean, default: false },
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
	z-index 400
	pointer-events none

.modal-backdrop.close-click-outside
	pointer-events auto

.modal-backdrop.modal-stacked
	z-index 500

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
	min-width 400px
	max-width 100vw
	max-height 100vh

.modal-titlebar
	position relative
	display flex
	align-items center
	justify-content flex-start
	width 100%
	height 36px
	flex 0 0 36px
	box-sizing border-box
	border-bottom 1px solid #00000022

.modal-title h2
	margin 0
	font-size 16px
	padding-left 16px
	padding-right 8px
	line-height 36px

.modal-title
	min-width 0
	height 100%

.modal-titlebar-content
	min-width 0
	height 100%
	line-height 36px

.close
	position absolute
	right 4px
	top 4px


@media (max-width: 431px)
	.modal-window
		min-width calc(100vw - 32px)

.modal-content
	flex 1 1 auto
	overflow auto

</style>
