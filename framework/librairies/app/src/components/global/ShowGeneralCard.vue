<template>
	<a class='cq-tile' :href='homecard.link'>
		<h3>{{ homecard.title }}</h3>
		<p>{{ homecard.description }}</p>
		<span class='cq-tile-link'>Open ›</span>
	</a>
</template>

<script>
export default {
    name: 'show-card',
    props: ['homecard']

};
</script>