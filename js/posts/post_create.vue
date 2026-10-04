<template>
	<Modal :title="isEditing ? 'Post bearbeiten' : 'Neuen Post erstellen'" @close="close">
		<form class="management-panel management-window-size-mid management-form post-form" @submit.prevent="savePost">
				
			<!-- <div class="author-name"><span>Autor:</span> {{ authorName }}</div> -->
			<div class="location-name"><span>Standort:</span> {{ locationName }}</div>
		
			<label class="management-field">
				<textarea v-model="content" rows="6" placeholder="Text zum Post (bei Bildern optional)" maxlength="5000" :disabled="saving"></textarea>
			</label>
			<section class="post-upload" aria-label="Bilder zum Post">
				<div class="upload-header">
					<button type="button" :disabled="saving || attachments.length >= 8" @click="$refs.fileInput.click()">Bilder hinzufügen</button>
					<span>{{ attachments.length }} / 8 Bilder</span>
				</div>
				<input ref="fileInput" class="upload-input" type="file" accept="image/jpeg,image/png,image/webp" multiple :disabled="saving" @change="selectFiles">
				<p class="upload-note">JPEG, PNG oder WebP · maximal 10 MB pro Bild</p>
				<div v-if="attachments.length" class="upload-grid">
					<div v-for="item in attachments" :key="item.key" class="upload-item">
						<div class="upload-preview">
							<img v-if="item.preview || media[item.id]" :src="item.preview || media[item.id].variants.thumbnail.url" :alt="item.name || 'Bild zum Post'">
							<span v-else>Vorschau nicht verfügbar</span>
							<button type="button" class="upload-remove" :disabled="saving" :aria-label="`${item.name || 'Bild'} entfernen`" @click="removeAttachment(item)">×</button>
						</div>
						<div class="upload-status" aria-live="polite">
							<template v-if="item.status === 'uploading'">
								<span>{{ item.progress < 100 ? `Lädt hoch … ${item.progress} %` : 'Verarbeitet Bild …' }}</span>
								<progress :value="item.progress" max="100" aria-label="Uploadfortschritt"></progress>
							</template>
							<span v-else-if="item.status === 'queued'">Wartet auf Upload …</span>
							<template v-else-if="item.status === 'error'">
								<span class="management-error">{{ item.error }}</span>
								<button type="button" @click="retry(item)">Erneut versuchen</button>
							</template>
							<span v-else>{{ item.existing ? 'Angehängt' : 'Bereit zum Veröffentlichen' }}</span>
						</div>
					</div>
				</div>
			</section>
			<div v-if="manualTimestamp || isEditing" class="post-date-fields">
				<label class="management-field">
					<span>Datum</span>
					<input v-model="date" type="date" required>
				</label>
				<label class="management-field">
					<span>Uhrzeit</span>
					<input v-model="time" type="time" required>
				</label>
			</div>
			<div class="split">

				<div v-if="!isEditing" class="settings-item">
					<input id="post-manual-time" v-model="manualTimestamp" type="checkbox" @change="setManualTimestamp">
					<label for="post-manual-time">Datum auswählen</label>
				</div>
				<div class="management-actions">
					<button type="submit" :disabled="saving || uploading || hasUploadErrors">{{ uploading ? 'Bilder werden hochgeladen …' : saving ? 'Speichert …' : isEditing ? 'Speichern' : 'Post erstellen' }}</button>
				</div>
			</div>
			<p v-if="error" class="management-note management-error">{{ error }}</p>
		</form>
	</Modal>
</template>

<script>
import { state } from '@/state.js';
import Modal from '@/views/modal.vue';
import { postsApi } from '@/posts/api.js';
import { markRaw } from 'vue';
import { mediaApi, draftId } from '@/media/api.js';
import { loadMedia, upsertMedia } from '@/media/store.js';

export default {
	name: 'PostCreate',
	components: { Modal },
	emits: ['close', 'create', 'saved'],
	props: {
		device: {
			type: Object,
			default: null,
		},
		post: {
			type: Object,
			default: null,
		},
	},
	data() {
		const initialDate = this.post ? new Date(Number(this.post.timestamp)) : null;
		return {
			manualTimestamp: this.post !== null,
			date: initialDate && !Number.isNaN(initialDate.getTime()) ? [initialDate.getFullYear(), String(initialDate.getMonth() + 1).padStart(2, '0'), String(initialDate.getDate()).padStart(2, '0')].join('-') : '',
			time: initialDate && !Number.isNaN(initialDate.getTime()) ? [String(initialDate.getHours()).padStart(2, '0'), String(initialDate.getMinutes()).padStart(2, '0')].join(':') : '',
			content: this.post?.content || '',
			postId: this.post?.id || draftId(),
			attachments: (this.post?.mediaIds || []).map((id) => ({ key: id, id, existing: true, status: 'ready' })),
			processingQueue: false,
			closed: false,
			saveTimestamp: null,
			saving: false,
			error: '',
		};
	},
	computed: {
		media() { return state.media; },
		deviceId() { return this.post?.deviceId || this.device?.id; },
		uploading() { return this.attachments.some((item) => ['queued', 'uploading'].includes(item.status)); },
		hasUploadErrors() { return this.attachments.some((item) => item.status === 'error'); },
		isEditing() {
			return this.post !== null;
		},
		authorName() {
			const user = state.account.user;
			return [user?.firstName, user?.lastName].filter(Boolean).join(' ') || user?.email || '';
		},
		locationName() {
			const device = this.device || state.devices.find((item) => item?.id === this.post?.deviceId);
			return device?.attributes?.Anzeigename || device?.name || 'Unbekannter Standort';
		},
	},
	mounted() {
		loadMedia().catch(() => {});
		window.addEventListener('keydown', this.guardSaving, true);
	},
	beforeUnmount() {
		window.removeEventListener('keydown', this.guardSaving, true);
		this.closed = true;
		for (const item of this.attachments) {
			item.controller?.abort();
			if (item.preview) URL.revokeObjectURL(item.preview);
		}
	},
	methods: {
		guardSaving(event) {
			if (this.saving && event.key === 'Escape') {
				event.preventDefault();
				event.stopImmediatePropagation();
			}
		},
		close() { if (!this.saving) this.$emit('close'); },
		selectFiles(event) {
			this.error = '';
			const files = [...event.target.files];
			event.target.value = '';
			for (const file of files) {
				if (this.attachments.length >= 8) { this.error = 'Es können höchstens 8 Bilder angehängt werden.'; break; }
				if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024) {
					this.error = `${file.name}: Bitte JPEG, PNG oder WebP mit höchstens 10 MB auswählen.`;
					continue;
				}
				this.attachments.push({ key: draftId(), file: markRaw(file), name: file.name, preview: URL.createObjectURL(file), status: 'queued', progress: 0, error: '', id: null });
			}
			this.processQueue();
		},
		async processQueue() {
			if (this.processingQueue || this.closed) return;
			this.processingQueue = true;
			try {
				let item;
				while (!this.closed && (item = this.attachments.find((entry) => entry.status === 'queued'))) {
					item.status = 'uploading';
					item.controller = markRaw(new AbortController());
					try {
						const uploaded = await mediaApi.upload(item.file, this.deviceId, this.postId, (progress) => { item.progress = progress; }, item.controller.signal);
						if (this.closed || !this.attachments.includes(item)) continue;
						item.id = uploaded.id;
						item.status = 'ready';
						upsertMedia(uploaded);
					} catch (error) {
						if (this.closed || !this.attachments.includes(item)) continue;
						item.status = 'error';
						item.error = error.message;
					}
				}
			} finally { this.processingQueue = false; }
		},
		retry(item) {
			item.error = '';
			item.progress = 0;
			item.status = 'queued';
			this.processQueue();
		},
		removeAttachment(item) {
			item.controller?.abort();
			if (item.preview) URL.revokeObjectURL(item.preview);
			this.attachments = this.attachments.filter((entry) => entry.key !== item.key);
		},
		async savePost() {
			if (this.saving || this.uploading || this.hasUploadErrors) return;
			this.error = '';
			const mediaIds = this.attachments.map((item) => item.id);
			if (!this.content.trim() && mediaIds.length === 0) {
				this.error = 'Bitte Text eingeben oder ein Bild hinzufügen.';
				return;
			}
			const timestamp = this.manualTimestamp
				? new Date(`${this.date}T${this.time}`).getTime()
				: (this.saveTimestamp || Date.now());
			if (!Number.isFinite(timestamp)) {
				this.error = 'Bitte einen gültigen Zeitpunkt auswählen.';
				return;
			}

			this.saving = true;
			this.saveTimestamp = timestamp;
			try {
				const response = this.isEditing
					? await postsApi.update(this.post.id, this.content, timestamp, mediaIds)
					: await postsApi.create(this.deviceId, this.content, timestamp, mediaIds, this.postId);
				this.$emit(this.isEditing ? 'saved' : 'create', response.post);
				loadMedia(true).catch(() => {});
				this.$emit('close');
			} catch (error) {
				this.error = error.message || 'Eintrag konnte nicht gespeichert werden.';
			} finally {
				this.saving = false;
			}
		},
		setManualTimestamp() {
			if (!this.manualTimestamp) return;
			const now = new Date();
			this.date = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
			this.time = [String(now.getHours()).padStart(2, '0'), String(now.getMinutes()).padStart(2, '0')].join(':');
		},
	},
};
</script>

<style lang="stylus" scoped>
.post-date-fields
	display grid
	grid-template-columns 1fr 1fr
	gap 8px
// .location-name
	// font-weight bold
	// span
		// font-weight normal
	// font-size 10pt

.post-form textarea
	resize vertical
.post-form .split
	flex-wrap wrap
	gap 12px
.post-form .management-actions
	margin-left auto
</style>

<style scoped>
.post-upload { margin: 8px 0 16px; }
.upload-header { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.upload-header span, .upload-note { font-size: 9pt; color: #666; }
.upload-note { margin: 7px 0 12px; }
.upload-input { display: none; }
.upload-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.upload-item { min-width: 0; }
.upload-preview { position: relative; aspect-ratio: 4 / 3; background: #eee; border-radius: 4px; overflow: hidden; display: grid; place-items: center; }
.upload-preview img { width: 100%; height: 100%; object-fit: cover; }
.upload-preview span { font-size: 9pt; padding: 8px; color: #666; }
.upload-remove { position: absolute; top: 4px; right: 4px; padding: 0; min-width: 0; width: 28px; height: 28px; border: 0; border-radius: 50%; background: #000a; color: #fff; font-size: 22px; line-height: 1; cursor: pointer; }
.upload-status { display: grid; gap: 4px; margin-top: 6px; font-size: 8pt; color: #666; overflow-wrap: anywhere; }
.upload-status progress { width: 100%; height: 5px; }
.upload-status button { justify-self: start; font-size: 8pt; }
@media (max-width: 480px) { .upload-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
