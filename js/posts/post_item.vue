<template>
	<article class="post-item">
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
		<p class="post-item-content">{{ post.content }}</p>
	</article>
</template>

<script>
import { state } from '@/state.js';
import PopoverMenu from '@/ui/popovermenu.vue';
import Icon from '@/ui/Icon.vue';

export default {
	name: 'PostItem',
	emits: ['edit', 'delete'],
	components: { PopoverMenu, Icon },
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
			return this.isPostAuthor || this.isAdmin || state.account.user?.wasserkarteRole === 'super_wassermeister';
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
				dateStyle: 'medium',
				timeStyle: 'short',
			}).format(this.date);
		},
	},
	methods: {
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

.post-item-more
	flex 0 0 20px
	width 20px
	height 20px
	margin -3px -3px -3px 2px
	padding 0
	border 0
	border-radius 6px
	background transparent
	cursor pointer
	display flex
	align-items center
	justify-content center

.post-item-more:hover
	background-color #0000000d

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
