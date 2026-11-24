<!-- Component Template -->

<template>
	<section class='cq-hero cq-hero--page'>
		<h1 class='cq-display'>{{ header }}</h1>
		<p class='cq-lead'>{{ message }}</p>
	</section>
</template>

<script>
export default {
    name: 'show-message',
    props: ['message', 'header']

};
</script>