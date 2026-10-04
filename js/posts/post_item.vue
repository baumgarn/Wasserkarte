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
			<div class="post-item-date">{{ formattedTimestamp }}</div>
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
import PopoverMenu from '@/ui/popovermenu.vue';
import Icon from '@/ui/Icon.vue';
import PostMedia from '@/media/post_media.vue';

export default {
	name: 'PostItem',
	emits: ['edit', 'delete', 'open-location'],
	components: { PopoverMenu, Icon, PostMedia },
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
				dateStyle: 'medium'
			}).format(this.date);
		},
	},
	methods: {
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
	flex 0 0 auto
	min-width 0
	margin-left auto
	text-align right

.post-item-header time
	opacity .6
	white-space nowrap
	flex 0 0 auto

.post-item-content
	margin 6px 0 0
	white-space pre-wrap
	overflow-wrap anywhere
	line-height 1.45
</style>
