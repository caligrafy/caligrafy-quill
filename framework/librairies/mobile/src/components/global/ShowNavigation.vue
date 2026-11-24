<!-- Show Navigation Component: the top bar of the app, with Sign out while someone is signed in -->

<template>
	<nav class='cq-nav' aria-label='Main'>
		<router-link class='cq-nav-brand' :to='{ name: "home" }'>
			<img class='cq-nav-mark' :src='mark' alt='' width='28' height='28'>
			<span class='semibold'>{{ name }}</span>
		</router-link>
		<button v-if='user' type='button' class='cq-nav-link cq-app-signout' @click='signOut'>Sign out</button>
	</nav>
</template>

<script>
import mark from '../../assets/caligrafy-mark.svg';
import capacitor from '../../../capacitor.config.json';
import { currentUser, onSessionChange, signOut } from '../../mobile/session.js';

export default {
	name: 'show-navigation',
	data() {
		return { mark, name: capacitor.appName, user: currentUser() };
	},
	mounted() {
		this.stop = onSessionChange(user => { this.user = user; });
	},
	unmounted() {
		this.stop();
	},
	methods: {
		async signOut() {
			await signOut();
			this.$router.replace({ name: 'sign-in' });
		}
	}
};
</script>
