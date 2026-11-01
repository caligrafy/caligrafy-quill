<!-- Show Navigation Component
Global component responsible for rendering the global navigation of the application
-->

<template>
	<nav class='cq-nav' aria-label='Main'>
		<router-link class='cq-nav-brand' :to='{ name: "home" }'>
			<img class='cq-nav-mark' :src='mark' alt='' width='28' height='28'>
			<span class='semibold'>Cali</span><span>grafy</span>
		</router-link>
	</nav>
</template>

<script>
import mark from '../../assets/caligrafy-mark.svg';

export default {
    name: 'show-navigation',
    data() {
        return { mark };
    }
};
</script>