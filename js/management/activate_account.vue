<template>
	<main class="activation-page">
		<section class="management-panel activation-card">
			<h1 class="management-section-title">Wasserkarte-Account aktivieren</h1>
			<form v-if="!activated" class="management-form" @submit.prevent="activate">
				<label class="management-field">
					<span>Passwort festlegen</span>
					<input v-model="password" type="password" autocomplete="new-password" required autofocus>
				</label>
				<label class="management-field">
					<span>Passwort wiederholen</span>
					<input v-model="passwordConfirmation" type="password" autocomplete="new-password" required>
				</label>
				<p v-if="error" class="management-note management-error">{{ error }}</p>
				<button type="submit" :disabled="submitting">{{ submitting ? 'Aktiviert …' : 'Account aktivieren' }}</button>
			</form>
			<div v-else>
				<p class="management-note management-success">Dein Account wurde aktiviert. Du kannst dich jetzt anmelden.</p>
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
	min-width 280px

.activation-card .management-section-title
	margin-bottom 8px
</style>
