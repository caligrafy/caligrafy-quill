<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type", content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name='apple-mobile-web-app-capable' content='yes'>
        <meta name='apple-mobile-web-app-status-bar-style' content='black'>
        <title>My Vue app</title>

        <!-- Stylesheet and head scripts go here -->
        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <link rel="stylesheet" href="<?php echo scripts('bootstrap_css'); ?>" />
        <link rel="stylesheet" href="<?php echo session('public').'css/theme.css';?>">
        <link rel="stylesheet" href="<?php echo session('public').'css/tokens.css';?>">
        <!-- the structure of Caligrafy's own pages: top bar, title band, rows (public/css/layout.css) -->
        <link rel="stylesheet" href="<?php echo session('public').'css/layout.css';?>">
        <link rel="stylesheet" href="<?php echo session('public').'css/look.css';?>">
        <?php if (design_stylesheet()): ?><link rel="stylesheet" href="<?php echo design_stylesheet(); ?>"><?php endif; ?>
        
    </head>
    
    <body class="cq-site">
        <!-- Beginning of the app: the structure of public/css/layout.css, restyled by the design style if one is selected -->
        <div id="app" v-cloak>
            <nav class="cq-nav" aria-label="Main">
                <a class="cq-nav-brand" :href="env.home"><img class="cq-nav-mark" :src="env.home + 'public/images/resources/caligrafy-mark.svg'" alt="" width="28" height="28"><span class="semibold">Cali</span>grafy</a>
            </nav>
            <section class="cq-hero cq-hero--page">
                <h1 class="cq-display">My Vue app</h1>
                <p class="cq-lead">The capabilities this visitor may call, through the generated JS client (public/js/capabilities.js).</p>
            </section>
            <section class="cq-band cq-band--tight">
                <div class="cq-inner cq-inner--narrow">
                    <p class="cq-alert cq-alert--error" role="alert" v-if="error">{{ error }}</p>
                    <ul class="cq-rows" v-if="capabilities.length">
                        <li class="cq-row" v-for="capability in capabilities" :key="capability.name">
                            <div class="cq-row-main">
                                <p class="cq-row-title">{{ capability.name }}</p>
                                <p class="cq-row-meta">{{ capability.description }}</p>
                            </div>
                        </li>
                    </ul>
                    <p class="cq-empty-state" v-if="!error && !capabilities.length">No capability yet: write one with php caligrafer.php service, then run php caligrafer.php client public/js/capabilities.js.</p>
                </div>
            </section>
        </div>

        <!-- TEMPLATES FOR VUE COMPONENTS -->

        <!--
        <script type='text/x-template' id='template'>
            <div>
                <!-- code here
            </div>
        </script>
        -->

        <!-- Initialization scripts -->
        <script src="<?php echo scripts('vue'); ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'app.js'; ?>"></script>
        <script>loadEnvironment(`<?php echo $env; ?>`);</script>
        <script> 
            /* Loading the app client framework
             * Any environment variables to be passed on from the server can take place in this here
             */
            loadVue({
                scripts: ['main']
             });
        </script>
        
        <!-- Additional scripts go here -->
        <script src="<?php echo scripts('bootstrap_script'); ?>"></script>
        <script src="<?php echo scripts('script'); ?>"></script>
        
    </body>
</html>