<template>
	<main class="activation-page">
		<section class="activation-card">
			<h1>Wasserkarte-Account aktivieren</h1>
			<form v-if="!activated" @submit.prevent="activate">
				<label>
					<span>Passwort festlegen</span>
					<input v-model="password" type="password" autocomplete="new-password" required autofocus>
				</label>
				<label>
					<span>Passwort wiederholen</span>
					<input v-model="passwordConfirmation" type="password" autocomplete="new-password" required>
				</label>
				<p v-if="error" class="error">{{ error }}</p>
				<button type="submit" :disabled="submitting">{{ submitting ? 'Aktiviert …' : 'Account aktivieren' }}</button>
			</form>
			<div v-else>
				<p>Dein Account wurde aktiviert. Du kannst dich jetzt anmelden.</p>
			</div>
		</section>
	</main>
</template>

<script>
import { managementAuth } from '@/management/auth.js';

export default {
	name: 'ActivateAccount',
	data() {
		return {
			password: '',
			passwordConfirmation: '',
			submitting: false,
			error: '',
			activated: false,
		};
	},
	computed: {
		activateToken() {
			return typeof this.$route.query.activateToken === 'string' ? this.$route.query.activateToken : '';
		},
	},
	methods: {
		async activate() {
			this.error = '';
			if (this.activateToken === '') {
				this.error = 'Der Aktivierungslink ist ungültig.';
				return;
			}
			if (this.password !== this.passwordConfirmation) {
				this.error = 'Die Passwörter stimmen nicht überein.';
				return;
			}
			this.submitting = true;
			try {
				await managementAuth.activateAccount(this.activateToken, this.password);
				this.activated = true;
				this.password = '';
				this.passwordConfirmation = '';
			} catch (error) {
				this.error = error.message || 'Account konnte nicht aktiviert werden.';
			} finally {
				this.submitting = false;
			}
		},
	},
};
</script>

<style lang="stylus" scoped>
.activation-card
	padding 24px

h1
	margin 0 0 20px
	font-size 16pt

form
	display grid
	gap 12px

label
	display grid
	gap 4px
	font-size 9pt

input
	padding 8px
	border 1px solid #00000033
	border-radius 4px
	font inherit

.error
	margin 0
	color #b52323
</style>
