<template>
	<div class="management-location-select">
		<button class="location-select-toggle" type="button" @click="openSelection">
			{{ locationSelectLabel }}
		</button>

		<Modal v-if="open" title="Standorte auswählen" close-click-outside @close="closeSelection">
			<div class="management-panel management-window-size-mid location-select-modal">
			<input
				ref="search"
				v-model.trim="query"
				class="location-select-search"
				type="search"
				placeholder="Standorte suchen"
				aria-label="Standorte suchen">
				<div class="location-select-options">
				<button
					v-for="location in filteredLocations"
					:key="location.id"
					class="location-select-option"
					:class="{ selected: isSelected(location.id) }"
					type="button"
					@click="toggleLocation(location.id)">
					<span class="location-select-label"><ColorDot :device="location" />{{ locationLabel(location) }}</span>
					<span aria-hidden="true">{{ isSelected(location.id) ? '✓' : '' }}</span>
				</button>
					<p v-if="filteredLocations.length === 0" class="management-note management-notice">Keine Standorte gefunden.</p>
				</div>
				</div>
		</Modal>

		<div v-if="!open" class="location-select-selected">
			<div v-for="location in selectedLocations" :key="location.id" class="location-select-selected-item">
				<span class="location-select-label"><ColorDot :device="location" />{{ locationLabel(location) }}</span>
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
	},
	data() {
		return {
			open: false,
			query: '',
		};
	},
	computed: {
		allLocations() {
			return this.locations || state.devices;
		},
		selectedIds() {
			return this.modelValue.filter(id => typeof id === 'string');
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
			return this.selectedIds.includes(locationId);
		},
		toggleLocation(locationId) {
			const selected = this.isSelected(locationId)
				? this.selectedIds.filter(id => id !== locationId)
				: [...this.selectedIds, locationId];
			this.$emit('update:modelValue', selected);
		},
		async openSelection() {
			this.open = true;
			this.query = '';
			await nextTick();
			this.$refs.search?.focus();
		},
		closeSelection() {
			this.open = false;
		},
	},
};
</script>
