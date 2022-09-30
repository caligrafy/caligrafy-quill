<!-- Component Template -->

<template>
	<div class='horizontal-center default-text'>
		<div class="semibold medium-text">
			{{ header }}
		</div>
		{{ message }}
	</div>
</template>

<script>
export default {
    name: 'show-message',
    props: ['message', 'header']

};
</script>