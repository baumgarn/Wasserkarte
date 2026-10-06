<template>
	<Modal title="Medien" :modal="false" @close="close">
		<template #titlebar>
			<span v-if="loaded && allUsers" class="media-scope">
				<span :class="{ 'is-active': scope === 'all' }" @click="scope = 'all'">Alle <span class="count" v-if="!loading">{{ items.length }}</span></span>&nbsp;
				<span :class="{ 'is-active': scope === 'own' }" @click="scope = 'own'">Deine <span class="count" v-if="!loading">{{ ownItems.length }}</span></span>
			</span>
		</template>
		<template #default>
			<div ref="panel" class="media-manager" tabindex="-1" :aria-busy="loading || !!deletingId">
				<p v-if="loading && !items.length" role="status"></p>
				<div class="nomedia" v-if="loaded && !visibleItems.length">Noch keine Medien hochgeladen.</div>
				<p class="sr-only" role="status">{{ status }}</p>

				<div class="manager-grid">

					<div v-for="item in visibleItems" :key="item.id" class="media-item" :class="{ 'media-item-open-location': canOpenLocation(item) }" :data-media-id="item.id" :role="canOpenLocation(item) ? 'button' : null" :tabindex="canOpenLocation(item) ? 0 : null" :aria-label="canOpenLocation(item) ? `${locationName(item)} öffnen` : null" @click="openLocation(item)" @keydown.enter.self="openLocation(item)" @keydown.space.self.prevent="openLocation(item)">
						
						<div v-if="!failed[item.id]" class="preview">
							<img :src="item.variants.thumbnail.url" alt="">
							<span v-if="item.type === 'video'" class="manager-video">▶ {{ videoDuration(item.duration) }}</span>
						</div>

							<div class="media-info">
								
								<div class="split">

									<div class="name">
										{{ allUsers && scope === 'all' ? item.authorName : locationName(item) }}
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
import { videoDuration } from '@/media/video.js';
import Modal from '@/views/modal.vue';
import MediaDelete from '@/media/media_delete.vue';
import PopoverMenu from '@/ui/popovermenu.vue';
import Icon from '@/ui/Icon.vue';
import { state } from '@/state.js';
import { mediaApi } from '@/media/api.js';
import { loadManagedMedia, loadMedia, removeManagedMedia } from '@/media/store.js';
import { loadPosts, upsertPost, removePost } from '@/posts/store.js';

export default {
	name: 'MediaManager',
	components: { Modal, MediaDelete, PopoverMenu, Icon },
	emits: ['close'],
	data: () => ({ scope: 'all', deleteItem: null, deleteError: '', deleteContextVersion: 0, deletingId: null, menuItem: null, failed: {}, status: '', previousFocus: null }),
	computed: {
		items() { return state.mediaManagerItems; },
		loading() { return state.mediaManagerLoading; },
		error() { return state.mediaManagerError; },
		allUsers() { return state.mediaManagerAllUsers && ['TENANT_ADMIN', 'SYS_ADMIN'].includes(state.account.user?.thingsboardAuthority); },
		loaded() {
			return !this.loading && !this.error;
		},
		ownItems() {
			return this.items.filter(item => item.authorUserId === state.account.user?.id);
		},
		visibleItems() {
			return this.allUsers && this.scope === 'all' ? this.items : this.ownItems;
		},
		moreItems() {
			return this.menuItem?.canDelete ? [{ type: 'action', label: this.menuItem.type === 'video' ? 'Video löschen' : 'Bild löschen', action: () => this.confirm(this.menuItem) }] : [];
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
		window.removeEventListener('keydown', this.handleKeydown, true);
		window.removeEventListener('scroll', this.handleScroll, true);
		window.removeEventListener('resize', this.closeMore);
		this.previousFocus?.focus();
	},
	methods: {
		videoDuration,
		locationDevice(item) {
			return state.devices.find(device => device?.id === item?.deviceId) || null;
		},
		canOpenLocation(item) {
			return this.locationDevice(item) !== null;
		},
		openLocation(item) {
			const device = this.locationDevice(item);
			if (!device || this.deletingId) return;
			this.closeMore();
			state.selectedDevice = device.name;
			window.dispatchEvent(new CustomEvent('sidebar:open', { detail: device }));
			window.dispatchEvent(new CustomEvent('device-selected', { detail: device }));
		},
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
		async load(force = false) {
			try {
				await loadManagedMedia(force);
				if (!this.allUsers) this.scope = 'own';
				this.failed = {};
			} catch { /* Die Fehlermeldung wird im gemeinsamen Store gehalten. */ }
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
				removeManagedMedia(item.id);
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
.manager-video
	position absolute
	top 50%
	left 50%
	transform translate(-50%, -50%)
	white-space nowrap
	padding 3px 6px
	border-radius 4px
	background #0009
	color white
	font-size 9pt
.media-manager { 
	width: 900px; 
	min-height 600px
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

.nomedia 
	width 100%
	text-align center
	font-size 9pt
	opacity .4

.media-item { 
	min-width: 0; 
	border: 1px solid #ddd; 
	border-radius: 4px; 
	aspect-ratio 1 / 1
	overflow: hidden; 
	position relative
}
.media-item-open-location
	cursor pointer
.media-item-open-location:focus-visible
	outline 2px solid #186d84
	outline-offset 2px
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
