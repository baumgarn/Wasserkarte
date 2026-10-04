<template>
	<div class="management-location-select">
		<button class="location-select-toggle" type="button" :disabled="disabled || saving" @click="openSelection">
			{{ locationSelectLabel }}
		</button>

		<Modal v-if="open" title="Standorte auswählen" close-click-outside @close="closeSelection">
			<div class="management-panel management-window-size-mid location-select-modal">
				<input
					ref="search"
					v-model.trim="query"
					class="location-select-search"
					type="search"
					:disabled="saving"
					placeholder="Standorte suchen"
					aria-label="Standorte suchen">
				<div class="location-select-options">
					<div
						v-for="location in filteredLocations"
						:key="location.id"
						class="location-item"
						:class="{ selected: isSelected(location.id) }"
						role="checkbox"
						:tabindex="disabled || saving ? -1 : 0"
						:aria-disabled="disabled || saving"
						:aria-checked="isSelected(location.id)"
						@click="toggleLocation(location.id)"
						@keydown.enter.prevent="toggleLocation(location.id)"
						@keydown.space.prevent="toggleLocation(location.id)">
						<span class="location-select-label"><ColorDot :device="location" />{{ locationLabel(location) }}</span>
						<span aria-hidden="true">{{ isSelected(location.id) ? '✓' : '' }}</span>
					</div>
					<p v-if="filteredLocations.length === 0" class="management-note management-notice">Keine Standorte gefunden.</p>
				</div>
				<footer class="location-select-footer">
					<p v-if="error" class="management-note management-error" role="alert">{{ error }}</p>
					<div class="management-actions location-select-actions">
						<button type="button" :disabled="disabled || saving || !hasChanges" @click="save">{{ saving ? 'Speichert …' : 'Speichern' }}</button>
					</div>
				</footer>
			</div>
		</Modal>

		<div v-if="!open" class="location-select-selected">
			<div v-for="location in selectedLocations" :key="location.id" class="location-item">
				<span class="location-select-label"><ColorDot :device="location" />{{ locationLabel(location) }}</span>
				<div class="location-select-remove" role="button" :tabindex="disabled || saving ? -1 : 0" :aria-label="`${locationLabel(location)} entfernen`" :aria-disabled="disabled || saving" @click="removeLocation(location.id)" @keydown.enter.prevent="removeLocation(location.id)" @keydown.space.prevent="removeLocation(location.id)">
					<div class="iconbutton close" aria-hidden="true"></div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { nextTick } from 'vue';
import { state } from '@/state.js';
import Modal from '@/views/modal.vue';
import ColorDot from '@/menu/colordot.vue';

export default {
	name: 'LocationSelect',
	emits: ['update:modelValue'],
	components: { Modal, ColorDot },
	props: {
		modelValue: {
			type: Array,
			default: () => [],
		},
		locations: {
			type: Array,
			default: null,
		},
		saveSelection: { type: Function, required: true },
		disabled: { type: Boolean, default: false },
	},
	data() {
		return {
			open: false,
			query: '',
			draftIds: [],
			saving: false,
			error: '',
		};
	},
	computed: {
		allLocations() {
			return this.locations || state.devices;
		},
		selectedIds() {
			return this.modelValue.filter(id => typeof id === 'string');
		},
		hasChanges() {
			const saved = new Set(this.selectedIds);
			return saved.size !== this.draftIds.length || this.draftIds.some(id => !saved.has(id));
		},
		locationSelectLabel() {
			return 'Standorte auswählen';
		},
		filteredLocations() {
			const query = this.query.toLocaleLowerCase('de-DE');
			return [...this.allLocations]
				.filter(location => location?.id)
				.filter(location => !query || this.locationLabel(location).toLocaleLowerCase('de-DE').includes(query))
				.sort((a, b) => this.locationLabel(a).localeCompare(this.locationLabel(b), 'de'));
		},
		selectedLocations() {
			const byId = new Map(this.allLocations.map(location => [location.id, location]));
			return this.selectedIds
				.map(id => byId.get(id))
				.filter(Boolean)
				.sort((a, b) => this.locationLabel(a).localeCompare(this.locationLabel(b), 'de'));
		},
	},
	methods: {
		locationLabel(location) {
			return location.attributes?.Anzeigename || location.name || location.id;
		},
		isSelected(locationId) {
			return this.draftIds.includes(locationId);
		},
		toggleLocation(locationId) {
			if (this.saving || this.disabled) return;
			this.draftIds = this.isSelected(locationId)
				? this.draftIds.filter(id => id !== locationId)
				: [...this.draftIds, locationId];
			this.error = '';
		},
		removeLocation(locationId) {
			if (this.disabled || this.saving || !this.selectedIds.includes(locationId)) return;
			this.$emit('update:modelValue', this.selectedIds.filter(id => id !== locationId));
		},
		async openSelection() {
			if (this.disabled || this.saving) return;
			this.draftIds = [...new Set(this.selectedIds)];
			this.open = true;
			this.query = '';
			this.error = '';
			await nextTick();
			this.$refs.search?.focus();
		},
		closeSelection() {
			if (this.saving) return;
			this.open = false;
		},
		async save() {
			if (this.disabled || this.saving || !this.hasChanges) return;
			this.saving = true;
			this.error = '';
			const selected = [...this.draftIds];
			try {
				await this.saveSelection(selected);
				this.$emit('update:modelValue', selected);
				this.open = false;
			} catch (error) {
				this.error = error.message || 'Standorte konnten nicht gespeichert werden.';
			} finally {
				this.saving = false;
			}
		},
		handleKeydown(event) {
			if (!this.open || event.key !== 'Escape') return;
			event.preventDefault();
			event.stopImmediatePropagation();
			this.closeSelection();
		},
	},
	mounted() {
		window.addEventListener('keydown', this.handleKeydown, true);
	},
	beforeUnmount() {
		window.removeEventListener('keydown', this.handleKeydown, true);
	},
};
</script>

<style lang="stylus" scoped>
.management-location-select
	display grid
	gap 8px

.location-select-modal
	display grid
	gap 10px
	height calc(70vh - 36px)
	max-height calc(70vh - 36px)
	grid-template-rows auto minmax(0, 1fr) auto

.location-select-options
	min-height 0
	max-height none
	overflow-y auto

.location-select-footer
	display grid
	gap 8px

.location-select-actions
	justify-content flex-start

.location-select-search
	box-sizing border-box
	width 100%
	padding 7px
	border 0
	border-bottom 1px solid #00000022
	font inherit

.location-item
	display flex
	align-items center
	position relative
	width 100%
	min-width 0
	justify-content space-between
	gap 8px
	padding 5px
	// border-radius 3px
	font-size 9pt
	text-align left
	&:hover
		background var(--activecolorgreybrightest)


.location-item[role="checkbox"]
	cursor pointer

.location-item:focus-visible
	outline 2px solid #186d84
	outline-offset -2px

.location-item[aria-disabled="true"]
.location-select-remove[aria-disabled="true"]
	cursor default
	pointer-events none

.location-item.selected
	background var(--activecolorgreymid)

.location-select-label
	display flex
	align-items center
	min-width 0

.location-select-options
.location-select-selected
	display flex
	flex-direction column
	gap 0

.location-item .location-select-label
	flex 1
	overflow-wrap anywhere

.location-select-selected .location-select-label
	padding-right 35px

.location-select-remove
	position absolute
	right 0
	top 0
	bottom 0
	width 24px
	height 100%
	display flex
	align-items center
	justify-content center
	padding 0
	background transparent
	align-self center
	cursor pointer
	opacity .4
	.close
		height 16px
		width 16px
		opacity 0
		pointer-events none
	&:hover
		opacity 1

.location-select-remove:focus-visible
	outline 2px solid #186d84
	outline-offset 2px

.location-item:hover .location-select-remove .iconbutton
.location-select-remove:focus-visible .iconbutton
	opacity .8

@media (hover: none)
	.location-select-remove .iconbutton
		opacity .8
</style>
