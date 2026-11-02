<template>
	<article
		class="post-item"
		:class="{ 'post-item-open-location': canOpenLocation }"
		:role="canOpenLocation ? 'button' : undefined"
		:tabindex="canOpenLocation ? 0 : undefined"
		:aria-label="canOpenLocation ? `Standort ${locationName} öffnen` : undefined"
		@click="openLocation"
		@keydown.enter.self="openLocation"
		@keydown.space.self.prevent="openLocation">
		<header class="post-item-header">
			<div v-if="context === 'allposts'" class="post-item-location">{{ locationName }}</div>
			<div class="post-item-author">{{ post.authorName || 'Unbekannt' }}</div>
			<div class="post-item-date">
				{{ formattedTimestamp }}
				<span
					v-if="moistureLayers.length"
					ref="moistureIcon"
					class="post-item-moisture"
					:class="{ active: moistureOpen }"
					:style="{ background: moistureBackground }"
					@click.stop="toggleMoisture"></span>
			</div>
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
			<div
				v-if="canManagePost"
				ref="moreButton"
				class="post-item-more"
				aria-label="Optionen für diesen Post"
				@click.stop="openMore">
				<Icon type="morev" :size="18" />
			</div>
			<PopoverMenu v-if="canManagePost" ref="moreMenu" :items="moreItems" />
		</header>
		<p v-if="post.content" class="post-item-content">{{ post.content }}</p>
		<PostMedia :ids="post.mediaIds || []" />
	</article>
</template>

<script>
import { state } from '@/state.js';
import { postMoistureLayers, moistureBackground } from '@/posts/moisture.js';
import dataStore from '@/datastore.js';
import { dataModel } from '@/datamodel.js';
import PopoverMenu from '@/ui/popovermenu.vue';
import Icon from '@/ui/Icon.vue';
import PostMedia from '@/media/post_media.vue';

export default {
	name: 'PostItem',
	emits: ['edit', 'delete', 'open-location'],
	components: { PopoverMenu, Icon, PostMedia },
	data() {
		return { moistureOpen: false, moisturePosition: {} };
	},
	beforeUnmount() {
		this.closeMoisture();
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
			if (Number.isNaN(this.date.getTime())) return '';
			return new Intl.DateTimeFormat('de-DE', {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric',
			}).format(this.date);
		},
	},
	methods: {
		async toggleMoisture() {
			if (this.moistureOpen) return this.closeMoisture();
			this.moistureOpen = true;
			await this.$nextTick();
			if (!this.$refs.moisturePopup) return;
			const icon = this.$refs.moistureIcon.getBoundingClientRect();
			const post = this.$el.getBoundingClientRect();
			const popup = this.$refs.moisturePopup.getBoundingClientRect();
			this.moisturePosition = {
				left: `${Math.max(4, Math.min(post.right - popup.width, window.innerWidth - popup.width - 4))}px`,
				top: `${Math.max(4, icon.top - popup.height - 6) + 2}px`,
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
.post-item
	padding 8px 0
	width 100%
	min-width 0
	max-width 100%
.post-item + .post-item
	border-top var(--thinline)

.post-item-open-location
	cursor pointer

// .post-item-open-location:hover
// 	background-color #00000008

.post-item-open-location:focus-visible
	outline 2px solid var(--menusectionheadercolor)
	outline-offset 2px


.post-item-header
	display flex
	align-items center
	font-size 8pt
	width 100%
	min-width 0
	color #777
	
.post-item-location
.post-item-author
	overflow hidden
	text-overflow ellipsis
	white-space nowrap
	min-width 0

.post-item-location
.post-item-author
	flex-shrink 1
.post-item-location + .post-item-author:before
	content '-'
	display inline-block
	margin 0 .25em
	
.post-item-date
	display flex
	align-items center
	flex 0 0 auto
	min-width 0
	margin-left auto
	text-align right

.post-item-moisture
	flex 0 0 14px
	width 14px
	height 14px
	margin-left 5px
	border-radius 50%
	cursor pointer
	&:hover:not(.active)
		outline 2px solid #ddd
		outline-offset 0

.post-item-header time
	opacity .6
	white-space nowrap
	flex 0 0 auto

.post-item-content
	margin 6px 0 0
	white-space pre-wrap
	overflow-wrap anywhere
	line-height 1.45

.post-moisture-popup
	position fixed
	z-index 10000
	padding 0px
	background #fff
	color #111
	border none
	outline none
	border-radius 6px
	box-shadow 0 2px 8px #0002
	font-size 7.5pt
	max-width calc(100vw - 8px)
	overflow-x auto
	table
		border-collapse collapse
		white-space nowrap
	td
		padding 3px 4px
	td:first-child
		padding-left 6px
	td:last-child
		padding-right 6px
	.vol
	.nfk
		text-align right
		font-variant-numeric tabular-nums
	.level
		color #000
		font-weight normal
</style>
