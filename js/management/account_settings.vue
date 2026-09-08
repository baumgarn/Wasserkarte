<template>
	<div class="account-settings">

		<form @submit.prevent="saveProfile">
			<label>
				<span>E-Mail-Adresse</span>
				<input :value="user.email" type="email" disabled>
			</label>
			<label>
				<span>Vorname</span>
				<input v-model.trim="firstName" autocomplete="given-name" maxlength="255">
			</label>
			<label>
				<span>Nachname</span>
				<input v-model.trim="lastName" autocomplete="family-name" maxlength="255">
			</label>
			<p v-if="profileMessage" :class="profileError ? 'error' : 'success'">{{ profileMessage }}</p>
			<button type="submit" :disabled="savingProfile">{{ savingProfile ? 'Speichert …' : 'Speichern' }}</button>
		</form>

		<form @submit.prevent="changePassword">
			<h3>Passwort ändern</h3>
			<label>
				<span>Aktuelles Passwort</span>
				<input v-model="currentPassword" type="password" autocomplete="current-password" required>
			</label>
			<label>
				<span>Neues Passwort</span>
				<input v-model="newPassword" type="password" autocomplete="new-password" required>
			</label>
			<label>
				<span>Neues Passwort wiederholen</span>
				<input v-model="newPasswordConfirmation" type="password" autocomplete="new-password" required>
			</label>
			<p v-if="passwordMessage" :class="passwordError ? 'error' : 'success'">{{ passwordMessage }}</p>
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
	padding 14px
	display grid
	gap 24px

form
	display grid
	gap 10px

h3
	margin 0
	font-size 11pt

label
	display grid
	gap 3px
	font-size 9pt

input
	padding 7px
	border 1px solid #00000033
	border-radius 4px
	font inherit

input:disabled
	background #f1f1f1
	color #555

// button
	// justify-self start

p
	margin 0
	font-size 9pt

.error
	color #b52323

.success
	color #177143
</style>
