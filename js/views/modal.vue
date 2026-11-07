<template>
	<Teleport to="body">
		<div
			class="modal-backdrop"
			:class="{ 'close-click-outside': closeClickOutside, 'modal-stacked': stacked }"
			@click.self="closeOnOutsideClick">
			<section
				ref="window"
				class="modal-window"
				:style="{ background }"
				role="dialog"
				:aria-modal="modal ? 'true' : undefined"
				:aria-label="title || 'Dialog'">
				<header
					class="modal-titlebar"
					:class="{ dragging: drag }"
					@pointerdown="startDrag"
					@pointermove="moveDrag"
					@lostpointercapture="endDrag">
					<div class="modal-title">
						<Icon v-if="icon" :type="icon" :size="22" />
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
						@pointerdown.stop
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
import Icon from '@/ui/Icon.vue';

export default {
	name: 'Modal',
	components: { Icon },
	emits: ['close'],
	props: {
		background: { type: String, default: '#fff' },
		icon: { type: String, default: '' },
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
		positionKey: { type: String, default: '' },
	},
	data() {
		return { position: null, drag: null, resizeObserver: null };
	},
	computed: {
		storageKey() {
			return `modalPosition:${this.positionKey || this.title}`;
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
		placeWindow(x, y) {
			const dialog = this.$refs.window;
			const { width, height } = dialog.getBoundingClientRect();
			const bounds = dialog.parentElement.getBoundingClientRect();
			const spaceX = Math.max(0, bounds.width - width);
			const spaceY = Math.max(0, bounds.height - height);
			x = Math.min(spaceX, Math.max(0, x));
			y = Math.min(spaceY, Math.max(0, y));
			dialog.style.left = `${x - spaceX / 2}px`;
			dialog.style.top = `${y - spaceY / 2}px`;
			return { x, y };
		},
		fitPosition() {
			if (this.position) this.placeWindow(this.position.x, this.position.y);
		},
		startDrag(event) {
			if (event.button !== 0 || this.drag) return;
			const dialog = this.$refs.window;
			const rect = dialog.getBoundingClientRect();
			const bounds = dialog.parentElement.getBoundingClientRect();
			event.currentTarget.setPointerCapture(event.pointerId);
			this.drag = {
				id: event.pointerId,
				x: event.clientX - rect.left + bounds.left,
				y: event.clientY - rect.top + bounds.top,
				moved: false,
			};
		},
		moveDrag(event) {
			if (event.pointerId !== this.drag?.id) return;
			this.position = this.placeWindow(event.clientX - this.drag.x, event.clientY - this.drag.y);
			this.drag.moved = true;
		},
		endDrag(event) {
			if (event.pointerId !== this.drag?.id) return;
			if (this.drag.moved) {
				try {
					localStorage.setItem(this.storageKey, JSON.stringify(this.position));
				} catch {}
			}
			this.drag = null;
		},
	},
	mounted() {
		try {
			const position = JSON.parse(localStorage.getItem(this.storageKey));
			if (Number.isFinite(position?.x) && Number.isFinite(position?.y)) this.position = position;
		} catch {}
		this.fitPosition();
		this.resizeObserver = new ResizeObserver(this.fitPosition);
		this.resizeObserver.observe(this.$refs.window);
		this.resizeObserver.observe(this.$refs.window.parentElement);
		window.addEventListener('keydown', this.handleKeydown);
		this.$refs.closeButton.focus();
	},
	beforeUnmount() {
		this.resizeObserver.disconnect();
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
	position relative
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
	cursor grab
	touch-action none
	user-select none

.modal-titlebar.dragging
	cursor grabbing


.modal-title h2
	margin 0
	font-size 16px
	padding-right 8px
	line-height 36px

.modal-title .icon
	margin-left -.2em
	
.modal-title
	display flex
	align-items center
	gap 6px
	padding-left 16px
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
