<!-- Home page of the app, for the signed-in person: replace it with the app's own pages. Data comes from the
capabilities, as that person: await call('list_books', { page: 1 }) (src/mobile/session.js) -->

<template>
	<main id='vue-home'>
		<show-message :header="'Hello ' + (user ? user.username : '')" message="This app calls the capabilities of your Caligrafy application, as you."></show-message>
		<section class='cq-band cq-band--tight'>
			<div class='cq-inner cq-inner--narrow'>
				<p class='cq-alert' role='status' v-if='status'>{{ status }}</p>
				<p class='cq-alert cq-alert--error' role='alert' v-if='error'>{{ error }}</p>
				<div class='cq-app-actions'>
					<button v-if='canPush' type='button' class='btn btn-secondary cq-btn-block' :disabled='busy' @click='turnOnNotifications'>Turn on notifications</button>
					<button v-if='canPush' type='button' class='btn btn-secondary cq-btn-block' :disabled='busy' @click='test'>Send me a test notification</button>
				</div>
			</div>
		</section>
	</main>
</template>

<script>
import ShowMessage from '../global/ShowMessage.vue';
import { call, currentUser } from '../../mobile/session.js';
import { enablePush, pushAvailable } from '../../mobile/push.js';

export default {
	name: 'vue-home',
	components: { 'show-message': ShowMessage },
	data() {
		return { user: currentUser(), canPush: pushAvailable(), busy: false, status: '', error: '' };
	},
	methods: {
		async run(work, done) {
			this.busy = true;
			this.status = this.error = '';
			try {
				await work();
				this.status = done;
			} catch (error) {
				this.error = error.message;
			} finally {
				this.busy = false;
			}
		},
		turnOnNotifications() {
			return this.run(() => enablePush(), 'Notifications are on for this phone.');
		},
		test() {
			return this.run(() => call('notify_test'), 'Sent: it arrives in a few seconds.');
		}
	}
};
</script>
