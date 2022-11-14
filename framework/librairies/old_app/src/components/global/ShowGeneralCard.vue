<template>
    <div>
        <a :href="homecard.link">
            <div class="card animated zoomIn color0 equally-spaced-1 dropshadow">
                <h2 class="medium-text semibold textcolor5 reset-margin">{{ homecard.title }}</h2>
                <div class="spacer-1"></div>
                <div class="wrapper reset-margin">
                    <p class="textcolor5 default-text block-with-text">{{ homecard.description }}</p>
                </div>
                <div class="spacer-4"></div>
            </div>
        </a>
    </div>	
</template>

<script>
export default {
    name: 'show-card',
    props: ['homecard']

};
</script>