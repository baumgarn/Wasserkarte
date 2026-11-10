<template>
	<article
		class="post-item"
		:class="{ 'post-item-open-location': canOpenLocation, hovered: highlighted }"
		:role="canOpenLocation ? 'button' : undefined"
		:tabindex="canOpenLocation ? 0 : undefined"
		:aria-label="canOpenLocation ? `Standort ${locationName} öffnen` : undefined"
		@pointerenter="$event.pointerType === 'mouse' && (state.hoveredPostId = post.id)"
		@pointerleave="$event.pointerType === 'mouse' && (state.hoveredPostId = null)"
		@click="openLocation"
		@keydown.enter.self="openLocation"
		@keydown.space.self.prevent="openLocation">
		<header class="post-item-header">

			<div class="post-item-date">{{ formattedTimestamp }}</div>
			<template v-if="context === 'allposts'">
				<span class="post-item-separator"></span>
				<div class="post-item-location">{{ locationName }}</div>
			</template>
			<span class="post-item-separator"></span>
			<div class="post-item-author">{{ post.authorName || 'Unbekannt' }}</div>

			<div
				v-if="moistureLayers.length"
				ref="moistureIcon"
				class="post-item-schichten"
				:class="{ active: moistureOpen }"
				:style="{ background: moistureBackground }"
				@click.stop="toggleMoisture">
			</div>

			<div
				v-if="canManagePost"
				ref="moreButton"
				class="post-item-more"
				aria-label="Optionen für diesen Post"
				@click.stop="openMore">
				<Icon type="morev" :size="18" />
			</div>

			<PopoverMenu v-if="canManagePost" ref="moreMenu" :items="moreItems" />

			<Teleport to="body">
				<div v-if="moistureOpen" ref="moisturePopup" class="post-moisture-popup" :style="moisturePosition" @click.stop>
					<table><tbody>
						<tr v-for="layer in moistureLayers" :key="layer.depth" :style="{ backgroundColor: layer.color }">
							<td>{{ layer.depth }} cm</td>
							<td class="vol">{{ layer.value.toLocaleString('de-DE', { maximumFractionDigits: 1 }) }} Vol %</td>
							<td class="nfk">{{ formatMoisture(layer.nfk) }} nFK %</td>
							<td class="level">{{ moistureLabel(layer.nfk) }}</td>
						</tr>
					</tbody></table>
				</div>
			</Teleport>

		</header>
		<p v-if="post.content" class="post-item-content"><PostCategoryIcon :category="post.category" inline />{{ post.content }}</p>
		<PostMedia :inert="context === 'allposts'" :ids="post.mediaIds || []" :style="{ '--post-image-height': context === 'allposts' ? '200px' : '200px' }" />
	</article>
</template>

<script>
import { formatPostDate } from '@/posts/format.js';
import { state } from '@/state.js';
import { postMoistureLayers, moistureBackground } from '@/posts/moisture.js';
import dataStore from '@/datastore.js';
import { dataModel } from '@/datamodel.js';
import PopoverMenu from '@/ui/popovermenu.vue';
import Icon from '@/ui/Icon.vue';
import PostCategoryIcon from '@/posts/post_category_icon.vue';
import PostMedia from '@/media/post_media.vue';

export default {
	name: 'PostItem',
	emits: ['edit', 'delete', 'open-location'],
	components: { PopoverMenu, Icon, PostCategoryIcon, PostMedia },
	setup() { return { state }; },
	data() {
		return { moistureOpen: false, moisturePosition: {} };
	},
	beforeUnmount() {
		this.closeMoisture();
		if (state.hoveredPostId === this.post.id) state.hoveredPostId = null;
	},
	props: {
		post: {
			type: Object,
			required: true,
		},
		context: {
			type: String,
			default: 'location',
			validator: (value) => ['allposts', 'location'].includes(value),
		},
	},
	computed: {
		highlighted() {
			if (state.hoveredPostId !== null) return state.hoveredPostId === this.post.id;
			return state.timelineDate !== null && formatPostDate(state.timelineDate) === this.formattedTimestamp;
		},
		moistureLayers() {
			return postMoistureLayers(this.post);
		},
		moistureBackground() {
			return moistureBackground(this.moistureLayers);
		},
		isAdmin() {
			const authority = state.account.user?.thingsboardAuthority;
			return authority === 'TENANT_ADMIN' || authority === 'SYS_ADMIN';
		},
		isPostAuthor() {
			return state.account.authenticated && state.account.user?.id === this.post.authorUserId;
		},
		canManagePost() {
			if (state.postsLocalMode && this.post.environment !== 'local') return false;
			return this.isPostAuthor || this.isAdmin || state.account.user?.wasserkarteRole === 'super_wassermeister';
		},
		canOpenLocation() {
			return this.context === 'allposts' && state.devices.some((device) => device?.id === this.post.deviceId);
		},
		moreItems() {
			return [
				{ type: 'action', label: 'Post bearbeiten', action: () => this.$emit('edit', this.post) },
				{ type: 'action', label: 'Post löschen', action: () => this.$emit('delete', this.post) },
			];
		},
		locationName() {
			const device = state.devices.find((item) => item?.id === this.post.deviceId);
			return device?.attributes?.Anzeigename || device?.name || 'Unbekannter Standort';
		},
		date() {
			return new Date(Number(this.post.timestamp));
		},
		isoTimestamp() {
			return Number.isNaN(this.date.getTime()) ? '' : this.date.toISOString();
		},
		formattedTimestamp() {
			return formatPostDate(this.post.timestamp);
		},
	},
	methods: {
		async toggleMoisture() {
			if (this.moistureOpen) return this.closeMoisture();
			this.moistureOpen = true;
			await this.$nextTick();
			if (!this.$refs.moisturePopup) return;
			const icon = this.$refs.moistureIcon.getBoundingClientRect();
			const popup = this.$refs.moisturePopup.getBoundingClientRect();
			const postRight = this.$el.getBoundingClientRect().right;
			this.moisturePosition = {
				left: `${Math.max(4, Math.min(postRight - 4, window.innerWidth - 4) - popup.width)}px`,
				top: `${Math.max(4, icon.top - popup.height - 7) + 3}px`,
			};
			document.addEventListener('pointerdown', this.closeMoistureOutside);
			window.addEventListener('resize', this.closeMoisture);
			window.addEventListener('scroll', this.closeMoisture, true);
		},
		closeMoistureOutside(event) {
			if (!this.$refs.moisturePopup?.contains(event.target) && !this.$refs.moistureIcon?.contains(event.target)) this.closeMoisture();
		},
		closeMoisture() {
			this.moistureOpen = false;
			document.removeEventListener('pointerdown', this.closeMoistureOutside);
			window.removeEventListener('resize', this.closeMoisture);
			window.removeEventListener('scroll', this.closeMoisture, true);
		},
		formatMoisture(value) {
			return Number.isFinite(value) ? value.toLocaleString('de-DE', { maximumFractionDigits: 0 }) : '–';
		},
		moistureLabel(value) {
			return Number.isFinite(value) ? dataModel.get_nfk_label(value) : '–';
		},
		openLocation() {
			if (this.canOpenLocation) this.$emit('open-location', this.post);
		},
		openMore() {
			const button = this.$refs.moreButton;
			if (!button) return;
			const rect = button.getBoundingClientRect();
			this.$refs.moreMenu?.open({
				top: rect.top,
				right: window.innerWidth - rect.right,
				fixed: true,
			});
		},
	},
};
</script>

<style lang="stylus" scoped>
.post-item-open-location
	touch-action manipulation

.post-item-content :deep(.icon.post-category-inline)
	font-size .85em
	top 3px
	// Align the visible shapes by offsetting the transparent space below each mask.
	&.type-post-niederschlag
		vertical-align -4%
	&.type-post-bewaessert
		vertical-align -15%
		top 2px
	&.type-post-fehler
		vertical-align -17%
		top 1px
</style>
