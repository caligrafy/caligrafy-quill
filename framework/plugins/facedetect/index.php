<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type", content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name='apple-mobile-web-app-capable' content='yes'>
        <meta name='apple-mobile-web-app-status-bar-style' content='black'>
        <title>Face Detect</title>

        <!-- Stylesheet and head scripts go here -->
        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <!-- full-screen camera (public/css/camera.css), then this app's styles, then the design style if one is selected -->
        <link rel="stylesheet" href="<?php echo session('public').'css/camera.css';?>">
        <link rel="stylesheet" href="<?php echo session('public').'css/look.css';?>">
        <link rel="stylesheet" href="<?php echo session('public').end(request()->uriComponents).'/css/styles.css';?>">
        <?php if (design_stylesheet()): ?><link rel="stylesheet" href="<?php echo design_stylesheet(); ?>"><?php endif; ?>

    </head>


    <body class="cq-camera-body">
        <!-- Beginning of the app: the camera fills the screen (public/css/camera.css) -->
        <div id="app" class="cq-camera">
            <section id="detector" class="cq-camera-media">

                <!-- media can be an image -->
                <!--<img id="detection" class="show" src="">-->

                <!-- media can be a video -->
                <!--<video id="detection"  src="<path to local video>" type="video/mp4" width="640" height="480" class="show" autoplay="autoplay" muted playsinline controls></video>-->

                <!-- media can be a webcam: 640 x 480, the canvas of the detections gets the same size and both cover the screen -->
                <video id="detection" width="640" height="480" class="show" autoplay="autoplay" muted playsinline></video>

            </section>
            <header class="cq-camera-top">
                <h1 class="cq-camera-title">Face Detect</h1>
                <section id="infobar" class="cq-camera-status"></section>
            </header>
            <section class="controls cq-camera-bottom">
                <div id="apps" class="cq-camera-actions"></div>
            </section>
        </div>

        <!-- Initialization scripts -->
        <script src="<?php echo scripts('vue'); ?>"></script>
        <script src="<?php echo scripts('axios'); ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'app.js'; ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'face-api.min.js'; ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'detect.js'; ?>"></script>
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
        
    </body>

</html>