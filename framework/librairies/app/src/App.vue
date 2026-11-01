<!-- Main App Template
Ensures Single Page Application through the use of vue routers: the top bar, the page of the route, the footer
(the structure of public/css/layout.css)
-->
<template>
	<div id='app' v-cloak>
		<show-navigation></show-navigation>
		<router-view></router-view>
		<footer class='cq-footer'>
			<p>Copyright &copy; {{ new Date().getFullYear() }}</p>
		</footer>
	</div>
</template>
  
<script>
	import ShowNavigation from './components/global/ShowNavigation.vue';
	export default {
        name: 'App',
        components: {
            'show-navigation': ShowNavigation
        }
    };
</script>