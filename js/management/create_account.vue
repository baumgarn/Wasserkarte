<template>
	<div class="create-account">
		<form v-if="!created" @submit.prevent="createAccount">
			<label>
				<span>Vorname</span>
				<input v-model.trim="firstName" autocomplete="given-name" maxlength="255">
			</label>
			<label>
				<span>Nachname</span>
				<input v-model.trim="lastName" autocomplete="family-name" maxlength="255">
			</label>
			<label>
				<span>E-Mail-Adresse</span>
				<input v-model.trim="email" type="email" autocomplete="email" required>
			</label>
			<p v-if="error" class="error">{{ error }}</p>
			<button type="submit" :disabled="saving">{{ saving ? 'Erstellt …' : 'Account erstellen' }}</button>
		</form>

		<div v-else class="activation-result">
			<p v-if="mailSent">Die Aktivierungs-E-Mail wurde an {{ email }} versendet.</p>
			<template v-else>
				<p>Der Account wurde angelegt. Der Mailversand ist derzeit deaktiviert.</p>
				<label>
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
.create-account
	padding 14px

form
.activation-result
	display grid
	gap 10px

label
	display grid
	gap 3px
	font-size 9pt

input
	padding 7px
	border 1px solid #00000033
	border-radius 4px
	font inherit

.activation-result input
	font-size 8pt

p
	margin 0

.error
	color #b52323
</style>
