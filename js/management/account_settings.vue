<template>
	<div class="management-panel account-settings">

		<form class="management-form" @submit.prevent="saveProfile">
			<label class="management-field">
				<span>E-Mail-Adresse</span>
				<input :value="user.email" type="email" disabled>
			</label>
			<label class="management-field">
				<span>Vorname</span>
				<input v-model.trim="firstName" autocomplete="given-name" maxlength="255">
			</label>
			<label class="management-field">
				<span>Nachname</span>
				<input v-model.trim="lastName" autocomplete="family-name" maxlength="255">
			</label>
			<p v-if="profileMessage" :class="['management-note', profileError ? 'management-error' : 'management-success']">{{ profileMessage }}</p>
			<button type="submit" :disabled="savingProfile">{{ savingProfile ? 'Speichert …' : 'Speichern' }}</button>
		</form>

		<form class="management-form" @submit.prevent="changePassword">
			<h3 class="management-section-title">Passwort ändern</h3>
			<label class="management-field">
				<span>Aktuelles Passwort</span>
				<input v-model="currentPassword" type="password" autocomplete="current-password" required>
			</label>
			<label class="management-field">
				<span>Neues Passwort</span>
				<input v-model="newPassword" type="password" autocomplete="new-password" required>
			</label>
			<label class="management-field">
				<span>Neues Passwort wiederholen</span>
				<input v-model="newPasswordConfirmation" type="password" autocomplete="new-password" required>
			</label>
			<p v-if="passwordMessage" :class="['management-note', passwordError ? 'management-error' : 'management-success']">{{ passwordMessage }}</p>
			<button type="submit" :disabled="savingPassword">{{ savingPassword ? 'Ändert …' : 'Passwort ändern' }}</button>
		</form>

	</div>
</template>

<script>
import { state } from '@/state.js';
import { managementAuth } from '@/management/auth.js';

export default {
	name: 'AccountSettings',
	data() {
		return {
			firstName: state.account.user?.firstName || '',
			lastName: state.account.user?.lastName || '',
			currentPassword: '',
			newPassword: '',
			newPasswordConfirmation: '',
			savingProfile: false,
			savingPassword: false,
			profileMessage: '',
			profileError: false,
			passwordMessage: '',
			passwordError: false,
		};
	},
	computed: {
		user() {
			return state.account.user || {};
		},
	},
	methods: {
		async saveProfile() {
			this.profileMessage = '';
			this.savingProfile = true;
			try {
				await managementAuth.updateProfile(this.firstName, this.lastName);
				this.profileMessage = 'Profil gespeichert.';
				this.profileError = false;
			} catch (error) {
				this.profileMessage = error.message || 'Profil konnte nicht gespeichert werden.';
				this.profileError = true;
			} finally {
				this.savingProfile = false;
			}
		},
		async changePassword() {
			this.passwordMessage = '';
			if (this.newPassword !== this.newPasswordConfirmation) {
				this.passwordMessage = 'Die neuen Passwörter stimmen nicht überein.';
				this.passwordError = true;
				return;
			}
			this.savingPassword = true;
			try {
				await managementAuth.changePassword(this.currentPassword, this.newPassword);
				this.currentPassword = '';
				this.newPassword = '';
				this.newPasswordConfirmation = '';
				this.passwordMessage = 'Passwort geändert.';
				this.passwordError = false;
			} catch (error) {
				this.passwordMessage = error.message || 'Passwort konnte nicht geändert werden.';
				this.passwordError = true;
			} finally {
				this.savingPassword = false;
			}
		},
	},
};
</script>

<style lang="stylus" scoped>
.account-settings
	display grid
	gap 24px
</style>
