<template>
	<Modal :title="isEditing ? 'Post bearbeiten' : 'Neuer Post'" @close="close">
		<template #titlebar><span class="post-title-location">{{ locationName }}</span></template>
		<form class="management-panel management-window-size-mid management-form post-form" @submit.prevent="savePost">
			<div class="post-category-picker">
				<button ref="categoryTrigger" class="post-category-trigger" type="button" :disabled="saving" @click="toggleCategory">
					<PostCategoryIcon :category="category" inline />
					<span>{{ category || 'Unkategorisiert' }}</span>
					<span class="arrow">▾</span>
				</button>
				<Teleport to="body">
					<PopoverMenu ref="categoryMenu" class="post-category-menu" :items="categoryItems">
						<template #select="{ item }">
							<span v-if="item.value" class="post-category-menu-icon"><PostCategoryIcon :category="item.value" inline /></span>
							<span>{{ item.label }}</span>
						</template>
					</PopoverMenu>
				</Teleport>
			</div>
		
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
					<span v-if="!attachments.length" class="upload-hint">{{ canUploadVideo ? 'Bis zu 10 Fotos oder ein Video hierherziehen' : 'Bis zu 10 Fotos hierherziehen' }}<br>Klick zum Auswählen</span>
				</div>
				<input ref="fileInput" class="upload-input" type="file" :accept="canUploadVideo && !attachments.length ? 'image/jpeg,image/png,image/webp,video/mp4' : 'image/jpeg,image/png,image/webp'" :multiple="!hasVideo" :disabled="!canAddAttachments" @change="selectFiles">
				<p v-if="uploadError" class="upload-error management-error">{{ uploadError }}</p>
			</section>
			<p v-if="error" class="management-note management-error">{{ error }}</p>
			<div class="post-actions">
				<label class="management-field post-date-field">
					<input v-model="date" type="date" required>
				</label>
				<div class="management-actions">
					<button :class="{ 'save-button': isEditing }" type="submit" :disabled="saving || discarding || uploading || hasUploadErrors || (isEditing && !hasChanges)"><Icon v-if="!isEditing" type="send" :size="20" />{{ isEditing ? 'Speichern' : 'Abschicken' }}</button>
				</div>
			</div>
		</form>
	</Modal>
</template>

<script>
import { state } from '@/state.js';
import Modal from '@/views/modal.vue';
import Icon from '@/ui/Icon.vue';
import PopoverMenu from '@/ui/popovermenu.vue';
import { postsApi } from '@/posts/api.js';
import PostCategoryIcon, { postCategories } from '@/posts/post_category_icon.vue';
import { markRaw } from 'vue';
import { videoPoster, videoDuration } from '@/media/video.js';
import { mediaApi, draftId } from '@/media/api.js';
import { loadMedia, removeMedia, upsertMedia } from '@/media/store.js';

export default {
	name: 'PostCreate',
	components: { Modal, Icon, PostCategoryIcon, PopoverMenu },
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
		const initialDate = this.post ? new Date(Number(this.post.timestamp)) : new Date();
		return {
			date: initialDate && !Number.isNaN(initialDate.getTime()) ? [initialDate.getFullYear(), String(initialDate.getMonth() + 1).padStart(2, '0'), String(initialDate.getDate()).padStart(2, '0')].join('-') : '',
			time: initialDate && !Number.isNaN(initialDate.getTime()) ? [String(initialDate.getHours()).padStart(2, '0'), String(initialDate.getMinutes()).padStart(2, '0')].join(':') : '',
			content: this.post?.content || '',
			category: this.post?.category || '',
			postId: this.post?.id || draftId(),
			attachments: (this.post?.mediaIds || []).map((id) => ({ key: id, id, existing: true, status: 'ready' })),
			processingQueue: false,
			uploadStarted: false,
			queueRequest: null,
			closed: false,
			saved: false,
			initialSnapshot: null,
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
		categoryItems() {
			return ['', ...postCategories].map(value => ({
				type: 'select', value, label: value || 'Unkategorisiert',
				active: value === this.category, closeOnSelect: true,
				onSelect: () => {
					this.category = value;
					this.$refs.categoryTrigger.focus();
				},
			}));
		},
		postSnapshot() {
			return JSON.stringify({ content: this.content, category: this.category, date: this.date, time: this.time, mediaIds: this.attachments.map(item => item.id || item.key) });
		},
		hasChanges() {
			return this.initialSnapshot !== null && this.postSnapshot !== this.initialSnapshot;
		},
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
		this.initialSnapshot = this.postSnapshot;
		loadMedia().catch(() => {});
		window.addEventListener('keydown', this.guardEscape, true);
	},
	beforeUnmount() {
		window.removeEventListener('keydown', this.guardEscape, true);
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
		toggleCategory() {
			if (this.saving) return;
			const rect = this.$refs.categoryTrigger.getBoundingClientRect();
			this.$refs.categoryMenu.open({ fixed: true, left: rect.left, bottom: window.innerHeight - rect.bottom });
		},
		guardEscape(event) {
			if (event.key !== 'Escape' || (!this.saving && !this.$refs.categoryMenu?.isOpen)) return;
			event.preventDefault();
			event.stopImmediatePropagation();
			if (this.$refs.categoryMenu?.isOpen) {
				this.$refs.categoryMenu.close();
				this.$refs.categoryTrigger.focus();
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
			if (this.saving || this.discarding || this.uploading || this.hasUploadErrors || (this.isEditing && !this.hasChanges)) return;
			this.error = '';
			const mediaIds = this.attachments.map((item) => item.id);
			if (!this.content.trim() && mediaIds.length === 0) {
				this.error = 'Bitte Text eingeben oder ein Bild hinzufügen.';
				return;
			}
			const timestamp = new Date(`${this.date}T${this.time}`).getTime();
			if (!Number.isFinite(timestamp)) {
				this.error = 'Bitte einen gültigen Zeitpunkt auswählen.';
				return;
			}

			this.saving = true;
			this.$refs.categoryMenu.close();
			this.saveTimestamp = timestamp;
			try {
				const response = this.isEditing
					? await postsApi.update(this.post.id, this.content, timestamp, mediaIds, this.category)
					: await postsApi.create(this.deviceId, this.content, timestamp, mediaIds, this.postId, this.category);
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
	},
};
</script>

<style lang="stylus" scoped>
.post-title-location
	font-size 12px
	padding-right 36px


.post-form
	padding-top 12px
	gap 14px
	min-width 0
	grid-template-columns minmax(0, 1fr)
	.management-field
		input, textarea
			background #fff
	textarea
		resize vertical
	.post-actions
		display grid
		grid-template-columns repeat(3, minmax(0, 1fr))
		align-items center
		.management-field input
			width 100%
		.management-actions
			grid-column 3
			min-width 0
			button
				width 100%
				min-width 0
				padding-left 4px
				padding-right 4px
				box-sizing border-box
				height calc(1.3em + 16px)
				border 1px solid #00000033
				border-radius 4px
				background #fff
		position sticky
		bottom 0
		z-index 1
		background transparent

.post-category-picker
	position relative
	width calc(100% / 3)
	button
		display flex
		align-items center
		gap 4px
		min-width 0
		box-sizing border-box
		height calc(1.3em + 16px)
		padding 4px 22px 4px 7px
		border-radius 4px
		background #fff
		font inherit
		line-height 16px
		color inherit
		:deep(.post-category-inline)
			margin-right 0
			line-height 18px
	.post-category-trigger
		position relative
		width 100%
		border 1px solid #00000033
		.arrow
			position absolute
			right 6px
			width 8px
			text-align center
			line-height 16px
			color #666

.post-category-menu
	box-sizing border-box
	:deep(.popovericon)
		flex 0 0 16px
		margin-right 0
	:deep(.item)
		gap 2px
		box-sizing border-box
		min-height 28px
		padding-top 5px
		padding-bottom 5px
		text-align left

.post-category-menu-icon
	display flex
	align-items center
	justify-content center
	flex 0 0 18px
	margin-right 2px
	height 18px
	line-height 18px
	:deep(.post-category-inline)
		left 0
		margin-right 0

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
	align-self center
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
