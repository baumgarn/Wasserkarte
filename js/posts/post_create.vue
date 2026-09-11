<template>
	<Modal title="Neuen Post erstellen" @close="$emit('close')">
		<form class="management-panel management-window-size-mid management-form post-form" @submit.prevent="$emit('create')">
				
			<!-- <div class="author-name management-readonly-value"><span>Autor:</span> {{ authorName }}</div> -->
			<div class="location-name"><span>Standort:</span> {{ locationName }}</div>
		
			<label class="management-field">
				<textarea v-model="content" rows="6" placeholder="Inhalt"></textarea>
			</label>
			<div v-if="manualTimestamp" class="post-date-fields">
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

				<div class="settings-item">
					<input id="post-manual-time" v-model="manualTimestamp" type="checkbox" @change="setManualTimestamp">
					<label for="post-manual-time">Datum auswählen</label>
				</div>
				<div class="management-actions">
					<button type="submit">Eintrag erstellen</button>
				</div>
			</div>
		</form>
	</Modal>
</template>

<script>
import { state } from '@/state.js';
import Modal from '@/views/modal.vue';

export default {
	name: 'PostCreate',
	components: { Modal },
	emits: ['close', 'create'],
	props: {
		device: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			manualTimestamp: false,
			date: '',
			time: '',
			content: '',
		};
	},
	computed: {
		authorName() {
			const user = state.account.user;
			return [user?.firstName, user?.lastName].filter(Boolean).join(' ') || user?.email || '';
		},
		locationName() {
			return this.device.attributes?.Anzeigename || this.device.name;
		},
	},
	methods: {
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
