<template>
	<Modal :title="isEditing ? 'Post bearbeiten' : 'Neuen Post erstellen'" @close="close">
		<form class="management-panel management-window-size-mid management-form post-form" @submit.prevent="savePost">
				
			<!-- <div class="author-name"><span>Autor:</span> {{ authorName }}</div> -->
			<div class="location-name"><span>Standort:</span> {{ locationName }}</div>
		
			<label class="management-field">
				<textarea v-model="content" rows="6" placeholder="Text" maxlength="5000" :disabled="saving"></textarea>
			</label>
			<section class="post-upload">
				<div class="upload-strip" :class="{ 'has-images': attachments.length, 'is-dragging-files': dragActive }" @click="openFilePicker" @dragenter.prevent="startFileDrag" @dragover.prevent="startFileDrag" @dragleave="endFileDrag" @drop.prevent="dropFiles">
					<div v-for="(item, index) in attachments" :key="item.key" class="upload-item" :class="{ 'upload-item-pending': !item.existing && item.status !== 'ready', 'is-reordering': draggedAttachmentKey === item.key, 'has-insertion-before': dropInsertionIndex === index, 'has-insertion-after': index === attachments.length - 1 && dropInsertionIndex === attachments.length }" :draggable="!saving" @dragstart="startReordering(item, $event)" @dragend="endReordering" @dragover.prevent="dragOverItem(item, $event)" @drop.stop.prevent="dropOnItem(item, $event)">
						<div class="upload-preview">
							<img v-if="item.preview || media[item.id]" :src="item.preview || media[item.id].variants.thumbnail.url" draggable="false">
							<span v-else>Vorschau nicht verfügbar</span>
							<span v-if="(item.type || media[item.id]?.type) === 'video'" class="video-badge">▶ {{ videoDuration(item.duration ?? media[item.id]?.duration) }}</span>
							<div class="upload-remove" @click.stop="removeAttachment(item)"><Icon type="close" :size="20" /></div>
						</div>
						<div v-if="!item.removing && (item.status === 'uploading' || item.status === 'error')" class="upload-status">
							<progress v-if="item.status === 'uploading'" :value="item.progress" max="100"></progress>
							<template v-else>
								<span class="management-error">{{ item.error }}</span>
								<div @click.stop="retry(item)">Erneut versuchen</div>
							</template>
						</div>
					</div>
					<span v-if="!attachments.length" class="upload-hint">{{ canUploadVideo ? 'Fotos oder ein Video hierher ziehen oder auswählen' : 'Fotos hierher ziehen oder auswählen' }}</span>
				</div>
				<input ref="fileInput" class="upload-input" type="file" :accept="canUploadVideo && !attachments.length ? 'image/jpeg,image/png,image/webp,video/mp4' : 'image/jpeg,image/png,image/webp'" :multiple="!hasVideo" :disabled="!canAddAttachments" @change="selectFiles">
				<p v-if="uploadError" class="upload-error management-error">{{ uploadError }}</p>
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
			<p v-if="error" class="management-note management-error">{{ error }}</p>
			<div class="split post-actions">

				<div v-if="!isEditing" class="settings-item">
					<input id="post-manual-time" v-model="manualTimestamp" type="checkbox" @change="setManualTimestamp">
					<label for="post-manual-time">Datum auswählen</label>
				</div>
				<div class="management-actions">
					<button type="submit" :disabled="saving || discarding || uploading || hasUploadErrors">{{ isEditing ? 'Speichern' : 'Post erstellen' }}</button>
				</div>
			</div>
		</form>
	</Modal>
</template>

<script>
import { state } from '@/state.js';
import Modal from '@/views/modal.vue';
import Icon from '@/ui/Icon.vue';
import { postsApi } from '@/posts/api.js';
import { markRaw } from 'vue';
import { videoPoster, videoDuration } from '@/media/video.js';
import { mediaApi, draftId } from '@/media/api.js';
import { loadMedia, removeMedia, upsertMedia } from '@/media/store.js';

export default {
	name: 'PostCreate',
	components: { Modal, Icon },
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
			uploadStarted: false,
			queueRequest: null,
			closed: false,
			saved: false,
			discardRequest: null,
			discarding: false,
			saveTimestamp: null,
			saving: false,
			error: '',
			uploadError: '',
			dragActive: false,
			draggedAttachmentKey: null,
			dropInsertionIndex: null,
		};
	},
	computed: {
		media() { return state.media; },
		deviceId() { return this.post?.deviceId || this.device?.id; },
		canUploadVideo() { return ['TENANT_ADMIN', 'SYS_ADMIN'].includes(state.account.user?.thingsboardAuthority) || state.account.user?.wasserkarteRole === 'super_wassermeister'; },
		hasVideo() { return this.attachments.some(item => (item.type || this.media[item.id]?.type) === 'video'); },
		canAddAttachments() { return !this.saving && !this.discarding && !this.hasVideo && this.attachments.length < 10 && this.attachments.every(item => !item.existing || this.media[item.id]); },
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
		if (!this.saved) {
			this.discardUploads().catch(error => { state.mediaManagerError = error.message; });
		}
		this.closed = true;
		for (const item of this.attachments) {
			if (!this.isEditing || this.saved) item.controller?.abort();
			if (item.preview) URL.revokeObjectURL(item.preview);
		}
	},
	methods: {
		videoDuration,
		guardSaving(event) {
			if (this.saving && event.key === 'Escape') {
				event.preventDefault();
				event.stopImmediatePropagation();
			}
		},
		async close() {
			if (this.saving || this.discarding) return;
			this.discarding = true;
			this.error = '';
			try {
				await this.discardUploads();
				this.closed = true;
				for (const item of this.attachments) item.controller?.abort();
				this.$emit('close');
			} catch (error) {
				this.error = error.message || 'Bilder konnten nicht gelöscht werden.';
				this.discarding = false;
			}
		},
		discardUploads() {
			if (this.discardRequest) return this.discardRequest;
			this.discarding = true;
			this.discardRequest = (async () => {
				if (this.isEditing) {
					await this.queueRequest;
					for (const item of [...this.attachments]) {
						if (!item.existing && item.id) await this.deleteUploadedAttachment(item);
					}
				} else if (this.uploadStarted || this.attachments.length || this.processingQueue) {
					const result = await mediaApi.discardDraft(this.postId, this.deviceId);
					const ids = new Set([
						...(result.ids || []),
						...this.attachments.map(item => item.id).filter(Boolean),
						...state.mediaManagerItems.filter(item => item.postId === this.postId).map(item => item.id),
					]);
					for (const id of ids) removeMedia(id);
				}
			})();
			return this.discardRequest.catch(error => {
				this.discardRequest = null;
				throw error;
			});
		},
		openFilePicker() {
			if (this.canAddAttachments) this.$refs.fileInput?.click();
		},
		selectFiles(event) {
			this.addFiles([...event.target.files]);
			event.target.value = '';
		},
		dropFiles(event) {
			this.dragActive = false;
			this.dropInsertionIndex = null;
			if (this.draggedAttachmentKey) return;
			this.addFiles([...event.dataTransfer.files]);
		},
		startFileDrag(event) {
			if (!this.draggedAttachmentKey && this.canAddAttachments && Array.from(event.dataTransfer?.types || []).includes('Files')) this.dragActive = true;
		},
		endFileDrag(event) {
			if (event.currentTarget === event.target) this.dragActive = false;
		},
		startReordering(item, event) {
			if (this.saving) {
				event.preventDefault();
				return;
			}
			this.draggedAttachmentKey = item.key;
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData('text/plain', item.key);
			const rect = event.currentTarget.getBoundingClientRect();
			const image = event.currentTarget.querySelector('.upload-preview img');
			const preview = document.createElement('div');
			const canvas = document.createElement('canvas');
			canvas.width = rect.width;
			canvas.height = rect.height;
			const context = canvas.getContext('2d');
			context.roundRect(0, 0, rect.width, rect.height, 4);
			context.fillStyle = '#eee';
			context.fill();
			context.clip();
			if (image?.naturalWidth) {
				const scale = Math.min(rect.width / image.naturalWidth, rect.height / image.naturalHeight);
				const width = image.naturalWidth * scale, height = image.naturalHeight * scale;
				context.drawImage(image, (rect.width - width) / 2, (rect.height - height) / 2, width, height);
			}
			Object.assign(preview.style, { position: 'fixed', left: '-9999px', top: '0', width: `${rect.width}px`, height: `${rect.height}px` });
			preview.append(canvas);
			document.body.append(preview);
			event.dataTransfer.setDragImage(preview, event.clientX - rect.left, event.clientY - rect.top);
			requestAnimationFrame(() => preview.remove());
		},
		endReordering() {
			this.draggedAttachmentKey = null;
			this.dropInsertionIndex = null;
		},
		dragOverItem(item, event) {
			if (!this.draggedAttachmentKey) return;
			if (this.draggedAttachmentKey === item.key) {
				this.dropInsertionIndex = null;
				return;
			}
			event.dataTransfer.dropEffect = 'move';
			const targetIndex = this.attachments.findIndex((attachment) => attachment.key === item.key);
			const rect = event.currentTarget.getBoundingClientRect();
			this.dropInsertionIndex = targetIndex + (event.clientX < rect.left + rect.width / 2 ? 0 : 1);
		},
		dropOnItem(item, event) {
			if (!this.draggedAttachmentKey) {
				this.dropFiles(event);
				return;
			}
			const from = this.attachments.findIndex((attachment) => attachment.key === this.draggedAttachmentKey);
			const targetIndex = this.attachments.findIndex((attachment) => attachment.key === item.key);
			const rect = event.currentTarget.getBoundingClientRect();
			let insertAt = targetIndex + (event.clientX < rect.left + rect.width / 2 ? 0 : 1);
			if (from !== -1 && targetIndex !== -1) {
				const [attachment] = this.attachments.splice(from, 1);
				if (from < insertAt) insertAt--;
				this.attachments.splice(insertAt, 0, attachment);
			}
			this.endReordering();
		},
		addFiles(files) {
			if (this.saving || this.discarding) return;
			this.uploadError = '';
			files = Array.from(files);
			const videos = files.filter(file => file.type.startsWith('video/') || /\.(mp4|mov|webm)$/i.test(file.name));
			if (this.hasVideo || (videos.length && (videos.length !== 1 || files.length !== 1 || this.attachments.length))) {
				this.uploadError = 'Bitte entweder Fotos oder ein einzelnes Video auswählen.';
				return;
			}
			if (videos.length && !this.canUploadVideo) {
				this.uploadError = 'Video-Uploads sind Super-Wassermeister*innen und Admins vorbehalten.';
				return;
			}
			if (!this.canAddAttachments) return;
			for (const file of files) {
				if (this.attachments.length >= 10) { this.uploadError = 'Es können höchstens 10 Bilder angehängt werden.'; break; }
				const isVideo = file.type === 'video/mp4';
				if (!(isVideo || ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) || file.size > (isVideo ? 100 : 10) * 1024 * 1024) {
					this.uploadError = `${file.name}: Bitte Fotos (JPEG, PNG, WebP) bis 10 MB oder MP4-Videos bis 100 MB auswählen.`;
					continue;
				}
				this.attachments.push({ key: draftId(), file: markRaw(file), type: isVideo ? 'video' : 'image', name: file.name, preview: isVideo ? null : URL.createObjectURL(file), status: 'queued', progress: 0, error: '', id: null });
			}
			this.processQueue();
		},
		async processQueue() {
			if (this.processingQueue || this.closed || this.discarding) return this.queueRequest;
			this.processingQueue = true;
			this.queueRequest = (async () => {
			try {
				let item;
				while (!this.closed && !this.discarding && (item = this.attachments.find((entry) => entry.status === 'queued'))) {
					this.uploadStarted = true;
					item.status = 'uploading';
					item.controller = markRaw(new AbortController());
					try {
						if (item.type === 'video' && !item.poster) {
							const { poster, duration } = await videoPoster(item.file, item.controller.signal);
							item.poster = markRaw(poster);
							item.duration = duration;
							if (!this.attachments.includes(item) || this.closed) continue;
							item.preview = URL.createObjectURL(item.poster);
						}
						const uploaded = await mediaApi.upload(item.file, this.deviceId, this.postId, (progress) => { item.progress = progress; }, item.controller.signal, item.poster);
						if (!this.attachments.includes(item) || (this.closed && !this.discarding)) continue;
						item.id = uploaded.id;
						if (item.removeAfterUpload || this.discarding) {
							await this.deleteUploadedAttachment(item);
							continue;
						}
						item.status = 'ready';
						upsertMedia(uploaded);
					} catch (error) {
						if (this.closed || !this.attachments.includes(item)) continue;
						if (item.removeAfterUpload || this.discarding) {
							if (!item.id) this.detachAttachment(item);
							else {
								item.removing = false;
								item.status = 'ready';
								this.uploadError = error.message;
							}
							continue;
						}
						item.status = 'error';
						item.error = error.message;
					}
				}
			} finally {
				this.processingQueue = false;
				this.queueRequest = null;
			}
			})();
			return this.queueRequest;
		},
		retry(item) {
			item.error = '';
			item.progress = 0;
			item.status = 'queued';
			this.processQueue();
		},
		detachAttachment(item) {
			if (item.preview) URL.revokeObjectURL(item.preview);
			this.attachments = this.attachments.filter((entry) => entry.key !== item.key);
		},
		async deleteUploadedAttachment(item) {
			if (!item.id) return;
			item.removing = true;
			const result = await mediaApi.delete(item.id);
			removeMedia(item.id);
			this.detachAttachment(item);
			if (item.existing && result.removedPostIds?.includes(this.post?.id)) {
				this.$emit('deleted', this.post.id);
				this.closed = true;
				this.$emit('close');
			} else if (item.existing && result.posts?.[0]) {
				this.$emit('saved', result.posts[0]);
			}
		},
		async removeAttachment(item) {
			if (this.saving || this.discarding || item.removing) return;
			if (item.status === 'uploading') {
				item.removeAfterUpload = true;
				item.removing = true;
				return;
			}
			if (!item.id) {
				item.controller?.abort();
				this.detachAttachment(item);
				return;
			}
			try {
				await this.deleteUploadedAttachment(item);
			} catch (error) {
				item.removing = false;
				this.uploadError = error.message || 'Bild konnte nicht gelöscht werden.';
			}
		},
		async savePost() {
			if (this.saving || this.discarding || this.uploading || this.hasUploadErrors) return;
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
				this.saved = true;
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

.post-form
	min-width 0
	grid-template-columns minmax(0, 1fr)
	textarea
		resize vertical
	.split
		flex-wrap wrap
		gap 12px
	.management-actions
		margin-left auto
	.post-actions
		position sticky
		bottom 0
		z-index 1
		padding 12px 0
		background #fff

.video-badge
	position absolute
	top 50%
	left 50%
	transform translate(-50%, -50%)
	white-space nowrap
	padding 3px 6px
	background #0009
	color white
	border-radius 4px
	font-size 9pt

.post-upload
	min-width 0
	margin 8px 0 16px

.upload-input
	display none

.upload-error
	margin 7px 0 0
	font-size 9pt

.upload-strip
	display flex
	gap 10px
	box-sizing border-box
	width 100%
	max-width 100%
	min-width 0
	min-height 118px
	overflow-x auto
	overflow-anchor none
	padding 2px
	border 1px dashed #aaa
	border-radius 4px
	cursor pointer
	&.has-images
		border 1px solid #ddd
	&.is-dragging-files
		background #e7f3f6
		border-color #186d84

.upload-item
	flex 0 0 112px
	min-width 112px
	height 112px
	position relative
	cursor grab
	&.is-reordering
		opacity .35
	&-pending
		opacity .6
	&.has-insertion-before::before, &.has-insertion-after::after
		content ''
		position absolute
		top 0
		z-index 2
		width 3px
		height 112px
		border-radius 2px
		background #186d84
		pointer-events none
	&.has-insertion-before::before
		left -6px
	&.has-insertion-after::after
		right -6px

.upload-preview
	position relative
	width 100%
	height 100%
	background #eee
	border-radius 4px
	overflow hidden
	display grid
	place-items center
	img
		width 100%
		height 100%
		min-width 0
		min-height 0
		object-fit contain
	span:not(.video-badge)
		font-size 9pt
		padding 8px
		color #666

.upload-hint
	flex 1
	display grid
	place-items center
	padding 8px
	color #666
	font-size 9pt
	text-align center

.upload-remove
	position absolute
	top 2px
	right 2px
	display flex
	align-items center
	justify-content center
	padding 0
	min-width 0
	width 20px
	height 20px
	border 0
	border-radius 50%
	background #0008
	cursor pointer
	.icon
		filter invert(1)

.upload-status
	position absolute
	right 4px
	bottom 4px
	left 4px
	z-index 3
	display grid
	gap 4px
	padding 3px 4px
	background #fffd
	font-size 8pt
	color #666
	overflow-wrap anywhere
	progress
		width 100%
		height 5px
	button
		justify-self start
		min-width 0
		max-width 100%
		font-size 8pt
</style>
