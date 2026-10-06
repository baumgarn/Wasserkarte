<template>
	<Modal :title="item.type === 'video' ? 'Video löschen' : 'Bild löschen'" stacked :modal="false" @close="close">
		<div ref="panel" class="media-delete" :aria-busy="deleting || contextLoading">
			<div class="delete-preview">
				<img v-if="!previewFailed" :src="item.variants.thumbnail.url" alt="Vorschau des zu löschenden Bildes" @error="previewFailed = true">
				<p v-else>Bildvorschau nicht verfügbar.</p>
			</div>
			<div class="delete-copy">
				<p>{{ item.type === 'video' ? 'Dieses Video' : 'Dieses Bild' }} endgültig löschen?</p>
				<p v-if="contextLoading" class="delete-note" role="status"></p>
				<p v-else-if="retainedPostNote" class="delete-note">{{ retainedPostNote }}</p>
				<p v-if="contextError" class="error" role="alert">{{ contextError }} <button type="button" @click="loadContext">Erneut prüfen</button></p>
				<p v-if="error" class="error" role="alert">{{ error }}</p>
			</div>
			<div class="delete-actions">
				<button ref="cancelButton" type="button" :disabled="deleting" @click="close">Abbrechen</button>
				<button type="button" class="danger" :disabled="deleting || contextLoading || !context || !!contextError" @click="$emit('confirm', context.postRevision)">{{ deleting ? 'Wird gelöscht …' : 'Endgültig löschen' }}</button>
			</div>
		</div>
	</Modal>
</template>

<script>
import Modal from '@/views/modal.vue';
import { mediaApi } from '@/media/api.js';

export default {
	name: 'MediaDelete',
	components: { Modal },
	emits: ['close', 'confirm'],
	props: {
		item: { type: Object, required: true },
		deleting: { type: Boolean, default: false },
		error: { type: String, default: '' },
		contextVersion: { type: Number, default: 0 },
	},
	data: () => ({ previewFailed: false, context: null, contextLoading: true, contextError: '', disposed: false }),
	computed: {
		retainedPostNote() {
			if (!this.context?.attached) return '';
			const { remainingImages, hasText } = this.context;
			const contents = [];
			if (remainingImages > 0) contents.push(`${remainingImages} ${remainingImages === 1 ? 'weiteres Bild' : 'weitere Bilder'}`);
			if (hasText) contents.push('Text');
			return contents.length ? `Der Post enthält ${contents.join(' und ')} und wird nicht gelöscht.` : '';
		},
	},
	watch: { contextVersion() { this.loadContext(); } },
	mounted() {
		window.addEventListener('keydown', this.handleKeydown, true);
		this.$refs.cancelButton.focus();
		this.loadContext();
	},
	beforeUnmount() { this.disposed = true; window.removeEventListener('keydown', this.handleKeydown, true); },
	methods: {
		async loadContext() {
			this.contextLoading = true;
			this.contextError = '';
			try {
				const context = await mediaApi.deletionContext(this.item.id);
				if (!this.disposed) this.context = context;
			} catch (error) {
				if (!this.disposed) this.contextError = error.message || 'Post konnte nicht geprüft werden.';
			} finally { this.contextLoading = false; }
		},
		close() { if (!this.deleting) this.$emit('close'); },
		handleKeydown(event) {
			if (event.key === 'Escape') {
				event.preventDefault();
				event.stopImmediatePropagation();
				this.close();
				return;
			}
		},
	},
};
</script>

<style lang="stylus" scoped>
.media-delete
	display flex
	flex-direction column
	gap 14px
	width 400px
	max-width calc(100vw - 32px)
	max-height calc(100vh - 68px)
	padding 16px
	box-sizing border-box
.delete-preview
	display grid
	place-items center
	flex 0 1 260px
	min-height 0
	overflow hidden
.delete-preview img
	display block
	width 100%
	height 100%
	min-height 0
	object-fit contain
.delete-copy
	display flex
	flex-direction column
	gap 14px
	flex 1 0 auto
	font-size 9.5pt
	min-height 0
	overflow auto
.media-delete p
	margin 0
.delete-note
	font-size 9.5pt
	line-height 1.45
.delete-actions
	display flex
	justify-content space-between
	gap 8px
	flex 0 0 auto
.delete-actions button
	min-width 0
.danger, .error
	color #a82121
</style>
