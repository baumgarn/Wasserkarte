<template>
	<div class="management-panel create-account">
		<form v-if="!created" class="management-form" @submit.prevent="createAccount">
			<label class="management-field">
				<span>Vorname</span>
				<input v-model.trim="firstName" autocomplete="given-name" maxlength="255">
			</label>
			<label class="management-field">
				<span>Nachname</span>
				<input v-model.trim="lastName" autocomplete="family-name" maxlength="255">
			</label>
			<label class="management-field">
				<span>E-Mail-Adresse</span>
				<input v-model.trim="email" type="email" autocomplete="email" required>
			</label>
			<p v-if="error" class="management-note management-error">{{ error }}</p>
			<button type="submit" :disabled="saving">{{ saving ? 'Erstellt …' : 'Account erstellen' }}</button>
		</form>

		<div v-else class="activation-result">
			<p v-if="mailSent" class="management-note management-success">Die Aktivierungs-E-Mail wurde an {{ email }} versendet.</p>
			<template v-else>
				<p class="management-note management-notice">Der Account wurde angelegt. Der Mailversand ist derzeit deaktiviert.</p>
				<label class="management-field">
					<span>Aktivierungslink</span>
					<input ref="activationLink" :value="activationLink" readonly>
				</label>
				<button type="button" @click="copyActivationLink">{{ copied ? 'Kopiert' : 'Link kopieren' }}</button>
			</template>
		</div>
	</div>
</template>

<script>
import { managementAuth } from '@/management/auth.js';

export default {
	name: 'CreateAccount',
	data() {
		return {
			firstName: '',
			lastName: '',
			email: '',
			saving: false,
			error: '',
			created: false,
			mailSent: false,
			activationLink: '',
			copied: false,
		};
	},
	methods: {
		async createAccount() {
			this.error = '';
			this.saving = true;
			try {
				const response = await managementAuth.createUser(this.firstName, this.lastName, this.email);
				this.created = true;
				this.mailSent = response.mailSent === true;
				this.activationLink = response.activationLink || '';
			} catch (error) {
				this.error = error.message || 'Account konnte nicht angelegt werden.';
			} finally {
				this.saving = false;
			}
		},
		async copyActivationLink() {
			try {
				await navigator.clipboard.writeText(this.activationLink);
				this.copied = true;
			} catch (error) {
				this.$refs.activationLink.focus();
				this.$refs.activationLink.select();
			}
		},
	},
};
</script>

<style lang="stylus" scoped>
.activation-result
	display grid
	gap 10px

.activation-result input
	font-size 8pt

</style>
