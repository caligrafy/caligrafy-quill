<!-- The app: the top bar, then the page of the route (the structure of public/css/layout.css) -->
<template>
	<show-navigation></show-navigation>
	<router-view></router-view>
</template>

<script>
	import ShowNavigation from './components/global/ShowNavigation.vue';
	import { onSessionChange } from './mobile/session.js';
	import { onNotificationOpened } from './mobile/push.js';

	export default {
		name: 'App',
		components: { 'show-navigation': ShowNavigation },
		mounted() {
			// signed out on the site (the token revoked or expired): back to the sign-in page
			this.stopSession = onSessionChange(user => {
				if (!user && this.$route.meta.signedIn) this.$router.replace({ name: 'sign-in' });
			});
			// a tapped notification: its url is a page of the site; map it to a page of the app here
			this.stopNotifications = onNotificationOpened(() => this.$router.push({ name: 'home' }));
		},
		unmounted() {
			this.stopSession();
			this.stopNotifications();
		}
	};
</script>
