<template>
	<Modal title="E-Mail Benachrichtigungen" icon="email" @close="$emit('close')">
	<form class="management-panel management-form email-settings" @submit.prevent="save" @input="saved = false" @change="saved = false">
		<label class="management-field">
			<span>E-Mail-Adresse</span>
			<input :value="state.account.user?.email" type="email" disabled>
		</label>
		<fieldset :disabled="loading || loadFailed" :inert="saving" class="email-fields">
			<LocationSelect
				:model-value="locations"
				:save-selection="saveLocations"
				:disabled="loading || loadFailed"
				@update:model-value="setLocations" />
			<div class="email-global-threshold">
				<span>Benachrichtigen bei</span>
				<EmailThreshold v-model="threshold" :disabled="loading || loadFailed" @update:model-value="saved = false" />
				<span>Darstellung</span>
				<EmailStyle v-model="emailStyle" :disabled="loading || loadFailed" @update:model-value="saved = false" />
			</div>
			<p class="email-hint">Der Versand erfolgt täglich kurz nach Mitternacht,<br>nachdem die Tagesmittelwerte berechnet wurden.<br>Angaben und Verfügbarkeit ohne Gewähr.</p>
			<button type="button" @click="previewEmail"><Icon type="email" :size="20" />E-Mail Vorschau</button>
			<button type="button" :disabled="sendingTest || !locations.length" @click="sendTestEmail"><Icon type="send" :size="20" />{{ sendingTest ? 'Sendet …' : 'Test-E-Mail senden' }}</button>
			<p v-if="testSent" class="management-note management-success">Test-E-Mail an den Mailserver übergeben.</p>
			<div class="email-actions">
				<button class="save-button" type="submit" :disabled="!hasChanges || saving"><Icon :type="saved && !hasChanges ? 'check' : 'save'" :size="20" />{{ saved && !hasChanges ? 'Gespeichert' : 'Speichern' }}</button>
				<label class="email-toggle">
					Benachrichtigungen aktivieren
					<input v-model="enabled" type="checkbox">
				</label>
			</div>
		</fieldset>
		<p v-if="error" class="management-note management-error">{{ error }}</p>
		<button v-if="loadFailed" type="button" @click="load">Erneut versuchen</button>
	</form>
	<Modal v-if="previewHtml" title="E-Mail Vorschau" icon="email" stacked close-click-outside @close="previewHtml = ''">
		<div class="email-preview-style">
			<EmailThreshold :model-value="threshold" :disabled="previewLoading" @update:model-value="changePreviewThreshold" />
			<EmailStyle :model-value="emailStyle" :disabled="previewLoading" @update:model-value="changePreviewStyle" />
		</div>
		<div class="email-preview-container">
			<iframe class="email-preview" :srcdoc="previewHtml" sandbox></iframe>
			<div v-if="state.popupMenuOpen" class="email-preview-dismiss" @pointerdown.prevent="closePreviewDropdowns"></div>
		</div>
	</Modal>
	</Modal>
</template>

<script>
import { state } from '@/state.js';
import EmailStyle from '@/management/email_style.vue';
import EmailThreshold from '@/management/email_threshold.vue';
import Icon from '@/ui/Icon.vue';
import LocationSelect from '@/management/location_select.vue';
import { managementAuth } from '@/management/auth.js';
import Modal from '@/views/modal.vue';

export default {
	name: 'EmailSettings',
	components: { Icon, LocationSelect, EmailThreshold, EmailStyle, Modal },
	emits: ['close'],
	data() {
		const cached = state.account.user?.emailSettings;
		return { state, emailStyle: cached?.style || 'detailed', enabled: cached?.enabled ?? false, locations: [...(cached?.locations || [])], threshold: cached?.threshold ?? 50, loading: !cached, saving: false, loadFailed: false, saved: false, savedSettings: null, error: '', sendingTest: false, testSent: false, previewLoading: false, previewHtml: '' };
	},
	computed: {
		settingsSnapshot() {
			return JSON.stringify({ enabled: this.enabled, style: this.emailStyle, threshold: this.threshold, locations: [...this.locations].sort() });
		},
		hasChanges() {
			return this.savedSettings !== null && this.settingsSnapshot !== this.savedSettings;
		},
	},
	methods: {
		closePreviewDropdowns() {
			window.dispatchEvent(new Event('app:close-popovers'));
		},
		changePreviewThreshold(value) {
			this.threshold = value;
			this.saved = false;
			this.previewEmail();
		},
		changePreviewStyle(value) {
			this.emailStyle = value;
			this.saved = false;
			this.previewEmail();
		},
		async previewEmail() {
			if (this.previewLoading) return;
			this.previewLoading = true;
			this.error = '';
			try {
				const { html } = await managementAuth.previewEmail({ enabled: this.enabled, style: this.emailStyle, threshold: this.threshold, locations: this.locations });
				const preview = new DOMParser().parseFromString(html, 'text/html');
				preview.querySelectorAll('a[href]').forEach(link => {
					link.style.textDecoration ||= 'underline';
					link.removeAttribute('href');
				});
				this.previewHtml = '<!doctype html>' + preview.documentElement.outerHTML;
			} catch (error) {
				this.error = error.message || 'E-Mail Vorschau konnte nicht geladen werden.';
			} finally {
				this.previewLoading = false;
			}
		},
		async sendTestEmail() {
			if (this.sendingTest) return;
			this.sendingTest = true;
			this.testSent = false;
			this.error = '';
			try {
				await managementAuth.sendTestEmail({ enabled: this.enabled, style: this.emailStyle, threshold: this.threshold, locations: this.locations });
				this.testSent = true;
			} catch (error) {
				this.error = error.message || 'Test-E-Mail konnte nicht versendet werden.';
			} finally {
				this.sendingTest = false;
			}
		},
		async saveLocations(ids) {
			await this.persist(ids);
		},
		setLocations(ids) {
			this.locations = ids;
			this.saved = false;
		},
		async persist(locations) {
			if (this.loading || this.saving || this.loadFailed) throw new Error('Einstellungen sind noch nicht verfügbar.');
			this.error = '';
			this.saved = false;
			this.saving = true;
			try {
				const { settings } = await managementAuth.updateEmailSettings({ enabled: this.enabled, style: this.emailStyle, threshold: this.threshold, locations });
				this.locations = settings.locations;
				this.threshold = settings.threshold;
				this.emailStyle = settings.style || 'detailed';
				this.enabled = settings.enabled;
				this.savedSettings = this.settingsSnapshot;
				this.saved = true;
			} finally {
				this.saving = false;
			}
		},
		async save() {
			if (!this.hasChanges || this.saving) return;
			try {
				await this.persist(this.locations);
			} catch (error) {
				this.error = error.message || 'E-Mail-Einstellungen konnten nicht gespeichert werden.';
			}
		},
		async load() {
			this.loading = true;
			this.loadFailed = false;
			this.error = '';
			try {
				const { settings } = await managementAuth.getEmailSettings();
				this.enabled = settings.enabled;
				this.locations = settings.locations;
				this.threshold = settings.threshold;
				this.emailStyle = settings.style || 'detailed';
				this.savedSettings = this.settingsSnapshot;
			} catch (error) {
				this.loadFailed = true;
				this.error = error.message || 'E-Mail-Einstellungen konnten nicht geladen werden.';
			} finally {
				this.loading = false;
			}
		},
	},
	mounted() {
		if (this.loading) this.load();
		else this.savedSettings = this.settingsSnapshot;
	},
};
</script>

<style lang="stylus" scoped>
.email-preview-container
	position relative

.email-preview-dismiss
	position absolute
	inset 0

.email-preview
	display block
	width 680px
	max-width calc(100vw - 32px)
	height 720px
	max-height calc(100vh - 192px)
	border 0

.email-settings
	box-sizing border-box
	width 400px
	max-width 100vw
	button
		border-radius 14px

.email-fields
	display grid
	gap 12px
	border 0
	padding 0
	margin 0
	min-width 0

.email-toggle
	display flex
	align-items center
	gap 6px
	cursor pointer
	input
		transform translateY(-2px)

.email-global-threshold
	display grid
	grid-template-columns auto 240px
	justify-content end
	align-items center
	gap 12px
	> span
		text-align right
		white-space nowrap
	:deep(.email-threshold)
		width 100%
		grid-template-columns 1fr max-content
		margin 0

.email-preview-style
	display grid
	grid-template-columns 240px
	justify-content center
	gap 12px
	padding 12px
	:deep(.email-threshold)
		width 100%
		grid-template-columns 1fr max-content
		margin 0


.email-hint
	margin 6px 0
	text-align right
	line-height 1.4
	color #888

.email-actions
	display flex
	align-items center
	justify-content space-between
	gap 12px
	.email-toggle
		margin-left auto

@media (max-width: 431px)
	.email-settings
		width calc(100vw - 32px)
</style>
