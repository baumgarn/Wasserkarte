<template>
	<Modal title="Medien" :modal="false" @close="close">
		<template #titlebar>
			<span class="media-scope">
				<span v-if="allUsers" :class="{ 'is-active': scope === 'all' }" @click="scope = 'all'">Alle <span class="count" v-if="!loading">{{ items.length }}</span></span>&nbsp;
				<span :class="{ 'is-active': scope === 'own' || !allUsers }" @click="scope = 'own'">Deine <span class="count" v-if="!loading">{{ ownItems.length }}</span></span>
			</span>
		</template>
		<template #default>
			<div ref="panel" class="media-manager" tabindex="-1" :aria-busy="loading || !!deletingId">
				<p v-if="loading" role="status">Bilder werden geladen …</p>
				<p v-if="error" class="error" role="alert">{{ error }} <button v-if="!deletingId" type="button" @click="load">Erneut laden</button></p>
				<p v-if="!loading && !error && !visibleItems.length">Noch keine Bilder hochgeladen.</p>
				<p class="sr-only" role="status">{{ status }}</p>

				<div class="manager-grid">

					<div v-for="item in visibleItems" :key="item.id" class="media-item" :data-media-id="item.id">
						
						<div v-if="!failed[item.id]" class="preview">
							<img :src="item.variants.thumbnail.url" alt="">
						</div>

							<div class="media-info">
								
								<div class="split">

									<div v-if="allUsers" class="name">
										{{ item.authorName}}
									</div>
									<div>
										{{ date(item.uploadedAt) }}
									</div>
								</div>
								<!-- <div>
									{{ locationName(item) }}
								</div> -->
							</div>
							<div v-if="item.canDelete" type="div" class="media-more" aria-label="Optionen für dieses Bild" aria-haspopup="true" :aria-expanded="menuItem?.id === item.id && !!$refs.moreMenu?.isOpen" :disabled="!!deletingId" @click.stop="openMore(item, $event)">
								<Icon type="morev" :size="18" />
							</div>
							
							</div>

					</div>

				<PopoverMenu ref="moreMenu" :items="moreItems" />
			</div>
		</template>
	</Modal>
	<MediaDelete v-if="deleteItem" :key="deleteItem.id" :item="deleteItem" :deleting="!!deletingId" :error="deleteError" :context-version="deleteContextVersion" @close="cancel" @confirm="remove(deleteItem, $event)" />
</template>

<script>
import Modal from '@/views/modal.vue';
import MediaDelete from '@/media/media_delete.vue';
import PopoverMenu from '@/ui/popovermenu.vue';
import Icon from '@/ui/Icon.vue';
import { state } from '@/state.js';
import { mediaApi } from '@/media/api.js';
import { loadMedia } from '@/media/store.js';
import { loadPosts, upsertPost, removePost } from '@/posts/store.js';

export default {
	name: 'MediaManager',
	components: { Modal, MediaDelete, PopoverMenu, Icon },
	emits: ['close'],
	data: () => ({ items: [], loading: true, error: '', allUsers: false, scope: 'all', localMode: false, deleteItem: null, deleteError: '', deleteContextVersion: 0, deletingId: null, menuItem: null, failed: {}, status: '', disposed: false, previousFocus: null }),
	computed: {
		ownItems() {
			return this.items.filter(item => item.authorUserId === state.account.user?.id);
		},
		visibleItems() {
			return this.allUsers && this.scope === 'all' ? this.items : this.ownItems;
		},
		moreItems() {
			return this.menuItem?.canDelete ? [{ type: 'action', label: 'Bild löschen', action: () => this.confirm(this.menuItem) }] : [];
		},
	},
	beforeMount() { this.previousFocus = document.activeElement; },
	mounted() {
		window.addEventListener('keydown', this.handleKeydown, true);
		window.addEventListener('scroll', this.handleScroll, true);
		window.addEventListener('resize', this.closeMore);
		this.load();
	},
	beforeUnmount() {
		this.disposed = true;
		window.removeEventListener('keydown', this.handleKeydown, true);
		window.removeEventListener('scroll', this.handleScroll, true);
		window.removeEventListener('resize', this.closeMore);
		this.previousFocus?.focus();
	},
	methods: {
		async openMore(item, event) {
			if (this.deletingId || !item.canDelete) return;
			const menu = this.$refs.moreMenu;
			const sameItem = this.menuItem?.id === item.id;
			if (!sameItem) menu?.close();
			this.menuItem = item;
			const rect = event.currentTarget.getBoundingClientRect();
			menu?.open({ top: rect.top, right: window.innerWidth - rect.right, fixed: true });
			await this.$nextTick();
			if (menu?.isOpen) menu.$el.querySelector('[role="button"]')?.focus();
		},
		closeMore() { if (this.$refs.moreMenu?.isOpen) this.$refs.moreMenu.close(); },
		handleScroll(event) {
			if (!this.$refs.moreMenu?.$el?.contains?.(event.target)) this.closeMore();
		},
		confirm(item) {
			if (this.deletingId || !item?.canDelete) return;
			this.closeMore();
			this.deleteError = '';
			this.deleteContextVersion = 0;
			this.deleteItem = item;
		},
		async cancel() {
			if (this.deletingId) return;
			const item = this.deleteItem;
			this.deleteItem = null;
			this.deleteError = '';
			await this.$nextTick();
			if (item) this.$refs.panel?.querySelector(`[data-media-id="${item.id}"] .media-more`)?.focus();
		},
		async load() {
			this.loading = true;
			this.error = '';
			try {
				const response = await mediaApi.manageList();
				if (this.disposed) return;
				this.items = response.media || [];
				this.allUsers = response.allUsers === true;
				if (!this.allUsers) this.scope = 'own';
				this.localMode = response.localMode === true;
				this.failed = {};
			} catch (error) {
				if (!this.disposed) this.error = error.message || 'Bilder konnten nicht geladen werden.';
			} finally { this.loading = false; }
		},
		async remove(item, postRevision) {
			if (this.deletingId || !item.canDelete) return;
			this.deletingId = item.id;
			this.deleteError = '';
			try {
				const response = await mediaApi.delete(item.id, postRevision);
				for (const post of response.posts || []) upsertPost(post);
				for (const id of response.removedPostIds || []) removePost(id);
				// Nach verlorener Löschantwort kennt eine Wiederholung den entfernten
				// Post eventuell nicht mehr. Dann die Übersicht frisch abgleichen.
				if (!response.posts?.length && !response.removedPostIds?.length) await loadPosts(true).catch(() => {});
				// Auch bei einer wiederholten Löschung ohne Post-Antwort sofort ausblenden.
				for (const post of state.posts) post.mediaIds = (post.mediaIds || []).filter(id => id !== item.id);
				delete state.media[item.id];
				this.items = this.items.filter(entry => entry.id !== item.id);
				this.status = response.removedPostIds?.includes(item.postId) ? 'Bild und leerer Post gelöscht.' : 'Bild gelöscht.';
				await loadMedia(true).catch(() => {});
				delete state.media[item.id];
				this.deleteItem = null;
				this.deletingId = null;
				await this.$nextTick();
				const panel = this.$refs.panel;
				(panel?.querySelector('button:not(:disabled), a[href]') || panel)?.focus();
			} catch (error) {
				this.deleteError = error.message || 'Bild konnte nicht gelöscht werden.';
				if (error.status === 409) this.deleteContextVersion++;
			} finally { this.deletingId = null; }
		},
		close() { if (!this.deletingId) this.$emit('close'); },
		locationName(item) {
			const device = state.devices.find(device => device.id === item.deviceId);
			return device?.attributes?.Anzeigename || device?.name || 'Unbekannter Standort';
		},
		date(timestamp) {
			const date = new Date(Number(timestamp));
			return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' }).format(date);
		},
		handleKeydown(event) {
			if (event.key === 'Escape' && this.$refs.moreMenu?.isOpen) {
				this.closeMore();
				this.$refs.panel?.querySelector(`[data-media-id="${this.menuItem.id}"] .media-more`)?.focus();
				event.preventDefault();
				event.stopImmediatePropagation();
				return;
			}
			if (this.deleteItem) return;
			if (event.key === 'Escape' && this.deletingId) {
				event.preventDefault();
				event.stopImmediatePropagation();
			}
			if (event.key !== 'Tab') return;
			const dialog = this.$refs.panel?.closest('.modal-window');
			const controls = [...(dialog?.querySelectorAll('button:not(:disabled), a[href], [role="button"][tabindex="0"]') || [])];
			const first = controls[0], last = controls[controls.length - 1];
			if (!controls.includes(document.activeElement) || (event.shiftKey && document.activeElement === first) || (!event.shiftKey && document.activeElement === last)) {
				event.preventDefault();
				(event.shiftKey ? last : first)?.focus();
			}
		},
	},
};
</script>

<style lang="stylus" scoped>
	.media-manager { 
		width: 900px; 
		max-width: calc(100vw - 32px); 
		padding: 16px; 
		box-sizing: border-box; 
	}
	.media-scope { 
		color: #666; 
		font-size: 10pt; 
	}
	.media-scope > span { 
		cursor: pointer; 
		margin-left: 4px;
		display inline-block
		line-height 1
		font-size 9pt
		padding 3px 4px
	}
	.media-scope .count
		opacity .5
	.media-scope > .is-active { 
		background #e8e8e8
		border-radius 4px
		// text-decoration: underline; 
	}
	.manager-grid { 
		display: grid; 
		grid-template-columns: repeat(4, minmax(0, 1fr)); 
		gap: 6px; 
	}
	.media-item { 
		min-width: 0; 
		border: 1px solid #ddd; 
		border-radius: 4px; 
		aspect-ratio 1 / 1
		overflow: hidden; 
		position relative
	}
	.preview {
		position absolute
		width 100%
		height 100%
		background #eee
	}
	.preview img { 
		display: block; 
		width: 100%; 
		height: 100%; 
		object-fit: contain; 
		object-position: center center;
	}
	.media-more
		position absolute
		right 3px
		top 5px
		transition opacity linear .2s

	.media-info
		left 0
		bottom 0
		left 0
		width 100%
		position absolute
		font-size 8pt
		padding 2px 6px
		color #000000cc
		background #eeeeeecc
		transition opacity linear .2s
		> *
			white-space nowrap
			text-overflow ellipsis
			overflow hidden


	// .media-info
	// .media-more
	// 	opacity 0
	// .media-item:hover 
	// 	.media-info
	// 	.media-more
	// 		opacity 1

	.error { 
		color: #a82121; 
	}
	.sr-only { 
		position: absolute; 
		width: 1px; 
		height: 1px; 
		overflow: hidden; 
		clip-path: inset(50%); 
	}
	@media (max-width: 650px) { 
		.manager-grid { 
			grid-template-columns: repeat(2, minmax(0, 1fr)); 
	} }
	@media (max-width: 430px) { 
		.manager-grid { 
			grid-template-columns: minmax(0, 1fr); 
	} }
</style>
