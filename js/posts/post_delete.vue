<template>
	<Modal title="Post löschen" @close="$emit('close')">
		<div class="management-panel management-window-size-mid post-delete">
			<p>Post von {{ post.authorName || 'Unbekannt' }} wirklich löschen?</p>
			<p v-if="error" class="management-note management-error">{{ error }}</p>
			<div class="management-actions">
				<button class="danger" type="button" :disabled="deleting" @click="deletePost">{{ deleting ? 'Löscht …' : 'Post löschen' }}</button>
			</div>
		</div>
	</Modal>
</template>

<script>
import Modal from '@/views/modal.vue';
import { postsApi } from '@/posts/api.js';

export default {
	name: 'PostDelete',
	components: { Modal },
	emits: ['close', 'deleted'],
	props: {
		post: {
			type: Object,
			required: true,
		},
	},
	data() {
		return { deleting: false, error: '' };
	},
	methods: {
		async deletePost() {
			this.error = '';
			this.deleting = true;
			try {
				await postsApi.delete(this.post.id);
				this.$emit('deleted', this.post.id);
				this.$emit('close');
			} catch (error) {
				this.error = error.message || 'Post konnte nicht gelöscht werden.';
			} finally {
				this.deleting = false;
			}
		},
	},
};
</script>

<style lang="stylus" scoped>
.post-delete
	display grid
	gap 12px

.post-delete p
	margin 0
</style>
