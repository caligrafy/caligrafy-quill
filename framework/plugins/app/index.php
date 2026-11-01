<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type", content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width initial-scale=1 maximum-scale=1">
        <meta name='apple-mobile-web-app-capable' content='yes'>
        <meta name='apple-mobile-web-app-status-bar-style' content='black'>
        <title>Index Page with Vue.js</title>

        <!-- Stylesheet and head scripts go here -->
        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <link rel="stylesheet" type="text/css" href="<?php echo scripts('css'); ?>" />
        <link rel="stylesheet" href="<?php echo scripts('bootstrap_css'); ?>" />
        <link rel="stylesheet" href="<?php echo session('public').'css/theme.css';?>">
        <link rel="stylesheet" href="<?php echo session('public').'css/tokens.css';?>">
        <?php if (design_stylesheet()): ?><link rel="stylesheet" href="<?php echo design_stylesheet(); ?>"><?php endif; ?>
        
    </head>
    
    <body>
        <!-- Beginning of the app: the page components of tokens.css, the Caligrafy header -->
        <div id="app" v-cloak>
            <header class="color0 caligrafy-header">
                <nav class="horizontal">
                    <h1 class="large-text horizontal-center"><span class="semibold">Cali</span>grafy</h1>
                </nav>
            </header>
            <main class="cq-page">
                <div class="cq-card">
                    <div class="cq-card-head">
                        <h2 class="cq-title">My Vue app</h2>
                        <p class="cq-note">The capabilities this visitor may call, through the generated JS client (public/js/capabilities.js).</p>
                    </div>
                    <p class="cq-error" role="alert" v-if="error">{{ error }}</p>
                    <ul class="cq-list">
                        <li class="cq-item" v-for="capability in capabilities" :key="capability.name">
                            <div class="cq-item-body">
                                <p class="cq-item-title">{{ capability.name }}</p>
                                <p class="cq-item-meta">{{ capability.description }}</p>
                            </div>
                        </li>
                    </ul>
                    <p class="cq-empty" v-if="!error && !capabilities.length">No capability yet: write one with php caligrafer.php service, then run php caligrafer.php client public/js/capabilities.js.</p>
                </div>
            </main>
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