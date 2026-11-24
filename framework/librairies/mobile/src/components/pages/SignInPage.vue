<!-- Sign-in page: the site's own sign-in page opens in the system browser (src/mobile/session.js); the app never sees a
password -->

<template>
	<main class='cq-auth'>
		<h1>{{ name }}</h1>
		<p class='cq-lead'>Sign in with your {{ name }} account to start.</p>
		<p class='cq-alert cq-alert--error' role='alert' v-if='error'>{{ error }}</p>
		<div class='cq-app-actions'>
			<button type='button' class='btn btn-primary cq-btn-block' :disabled='busy' @click='signIn'>{{ busy ? 'Waiting for the browser…' : 'Sign in' }}</button>
		</div>
		<p class='cq-app-note'>Your browser opens {{ site }}: sign in there (or create an account), then allow this device.</p>
	</main>
</template>

<script>
import capacitor from '../../../capacitor.config.json';
import { apiRoute, signIn } from '../../mobile/session.js';

export default {
	name: 'sign-in-page',
	data() {
		return { name: capacitor.appName, site: apiRoute.replace(/^https?:\/\//, ''), busy: false, error: '' };
	},
	methods: {
		async signIn() {
			this.busy = true;
			this.error = '';
			try {
				await signIn();
				this.$router.replace(this.$route.query.back || { name: 'home' });
			} catch (error) {
				this.error = error.message === 'Sign-in cancelled' ? '' : error.message;
			} finally {
				this.busy = false;
			}
		}
	}
};
</script>
