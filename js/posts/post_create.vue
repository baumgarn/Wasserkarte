<template>
	<Modal :title="isEditing ? 'Post bearbeiten' : 'Neuen Post erstellen'" @close="$emit('close')">
		<form class="management-panel management-window-size-mid management-form post-form" @submit.prevent="savePost">
				
			<!-- <div class="author-name"><span>Autor:</span> {{ authorName }}</div> -->
			<div class="location-name"><span>Standort:</span> {{ locationName }}</div>
		
			<label class="management-field">
				<textarea v-model="content" rows="6" placeholder="Inhalt" maxlength="5000" required></textarea>
			</label>
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
					<button type="submit" :disabled="saving">{{ saving ? 'Speichert …' : isEditing ? 'Speichern' : 'Post erstellen' }}</button>
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
			saving: false,
			error: '',
		};
	},
	computed: {
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
	methods: {
		async savePost() {
			this.error = '';
			const timestamp = this.manualTimestamp
				? new Date(`${this.date}T${this.time}`).getTime()
				: Date.now();
			if (!Number.isFinite(timestamp)) {
				this.error = 'Bitte einen gültigen Zeitpunkt auswählen.';
				return;
			}

			this.saving = true;
			try {
				const response = this.isEditing
					? await postsApi.update(this.post.id, this.content, timestamp)
					: await postsApi.create(this.device.id, this.content, timestamp);
				this.$emit(this.isEditing ? 'saved' : 'create', response.post);
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
</style>
