<?php 

/**
 * defaultsettings.php is used for the default configurations needed for the framework
 * @author Dory A.Azar
 * @copyright 2019
 * @version 1.0
 *
*/


define('ROUTE_ARG_PATTERN', '#\{+(.*?)\}+#');
define('ROUTE_PARAM_PATTERN', '/([^?&=#]+)=([^&#]*)/');

// Shared front-end libraries (pinned versions). Bootstrap 5 does not need jQuery: 'jquery' is only for libraries
// that still require it (Summernote). 'bootstrap_jquery' is kept as an alias for older views.
session('scripts', array(  'favicon' => $_SESSION['imagesUrl'].getenv('FAVICON'),
                   'css' => $_SESSION['public'].'css/styles.css',
                   'animate' => $_SESSION['public'].'css/animate.css',
                   'script' => $_SESSION['public'].'js/script.js',
                   'bootstrap_css' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
                   'bootstrap_script' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js',
                   'jquery' => 'https://cdn.jsdelivr.net/npm/jquery@4.0.0/dist/jquery.min.js',
                   'bootstrap_jquery' => 'https://cdn.jsdelivr.net/npm/jquery@4.0.0/dist/jquery.min.js',
                   'vue' => 'https://cdn.jsdelivr.net/npm/vue@3.5.44/dist/vue.global.prod.js',
                   'axios' => 'https://cdn.jsdelivr.net/npm/axios@1.20.0/dist/axios.min.js',
                   'wysiwyg_css' => 'https://cdn.jsdelivr.net/npm/summernote@0.9.1/dist/summernote-lite.min.css',
                   'wysiwyg_js' => 'https://cdn.jsdelivr.net/npm/summernote@0.9.1/dist/summernote-lite.min.js',
                   'social_buttons_css' => $_SESSION['public'].'css/social_buttons.css'
                 ));


// Initialize metadata
session('metadata', ['site_name' => '', 'site_url' => '', 'creator' => '','title' => '', 'image' => '', 'description' => '', 'amount' => '', 'currency' => '', 'shipping_cost' => '', 'shipping_cost_currency' => '', 'google-site-verification' => '', 'availability' => ''] );

// Keywords
keywords("caligrafy, php, mvc, framework, mvc framework, php framework, model, view, controller, learn php, learn coding, laravel, symfony", 'keywords');

// Copyright notice
copyright(date("Y")." - Company Name");