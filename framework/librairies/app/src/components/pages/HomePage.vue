<!-- Page Template Example
@component: show-welcome
@template
@script
-->

<template>
    <div id='vue-home'>
		<div class="spacer-4"></div>
		<show-message header="Welcome to Caligrafy" message="Start from the ground up with this light MVC framework"></show-message>
		<div class="spacer-4"></div>
		<template v-for="homecard in homecards" :key="homecard.id">
			<div class="col-sm-6 col-md-4">
				<show-card :homecard="homecard" ></show-card>
			</div>
		</template>
		<div class='spacer-2'></div>
    </div>
</template>

<script>
import ShowMessage from '../global/ShowMessage.vue';
import ShowGeneralCard from '../global/ShowGeneralCard.vue';

export default {
    name: 'vue-home',
    components: {
		'show-message': ShowMessage,
		'show-card': ShowGeneralCard
    },
	data() {
		return {
			homecards:  [
					{
						"id": 1,
						"link":"https://github.com/caligrafy/caligrafy-quill/wiki/9.8.2-Routing-for-Single-Page-Applications#mainjs",
						"title": "router/index.js",
						"description": "It all starts in this file where you define the routes, styles, keys and other settings"

					},
					{
						"id": 2,
						"link":"https://github.com/caligrafy/caligrafy-quill/wiki" ,
						"title": "Docs and Cookbook",
						"description": "Learn the ins and outs of the Caligrafy Quill framework"

					},
					{
						"id": 3,
						"link": "https://youtube.com/playlist?list=PLsJG81fLk3_VWmXl9DnEldYY9ei4jTCjA",
						"title": "Caligrafy Channel",
						"description": "Watch our video tutorials to learn how to build powerful and sophisticated features"
					},
					{
						"id": 4,
						"link": "https://www.udemy.com/courses/search/?src=ukw&q=caligrafy" ,
						"title": "Caligrafy Academy",
						"description": "We offer detailed hands-on online courses that will make you an expert in no time"
					}
				]
		}
	},
	methods: {
	},
	computed: {
		// computed variables
	},
	
	mounted() {
	},
	watch: {
		// watched properties
	}
};
</script>