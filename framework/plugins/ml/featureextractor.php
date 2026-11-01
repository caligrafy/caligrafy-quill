<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type", content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name='apple-mobile-web-app-capable' content='yes'>
        <meta name='apple-mobile-web-app-status-bar-style' content='black'>
        <title>Machine Learning</title>
		
		<!-- Stylesheet and head scripts go here -->
        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <!-- full-screen camera (public/css/camera.css), then this app's styles, then the design style if one is selected -->
        <link rel="stylesheet" href="<?php echo session('public').'css/camera.css';?>">
        <link rel="stylesheet" href="<?php echo session('public').'css/look.css';?>">
        <link rel="stylesheet" href="<?php echo session('public').end(request()->uriComponents).'/css/styles.css';?>">
        <?php if (design_stylesheet()): ?><link rel="stylesheet" href="<?php echo design_stylesheet(); ?>"><?php endif; ?>
		
		<!-- Initialization scripts -->
		
		<!-- p5.js -->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/p5.js/2.3.4/p5.min.js"></script>
		
		<!-- ml5 -->
		<script src="https://unpkg.com/ml5@1.4.0/dist/ml5.min.js" type="text/javascript"></script>
        
    </head>
    
    <body class="cq-camera-body">
        <!-- Beginning of the app: the camera fills the screen (public/css/camera.css) -->
        <div id="app" class="cq-camera">
            <section id="detector" class="cq-camera-media">
				<!-- the drawing canvas and the camera share one size (640 x 480, a webcam's 4:3) and both cover the screen -->
				<canvas id='canvas' width="640" height="480" class="show"></canvas>

				<!-- media can be a video -->
                <!--<video id="video"  src="path/to/video" type="video/mp4" width="640" height="480" class="show" autoplay="autoplay" muted playsinline controls></video>-->

                <!-- media can be a webcam -->
                <video id='video' width="640" height="480" class="show" autoplay="autoplay" muted playsinline></video>

                <img src="" style="display:none" width="640" height="480" alt="segmentation image" id="segmentationImage" />
            </section>
            <header class="cq-camera-top">
                <h1 class="cq-camera-title">Feature Extractor</h1>
                <section id="infobar" class="cq-camera-status"></section>
            </header>
            <section class="controls cq-camera-bottom">
                <div id="apps" class="cq-camera-actions"></div>
            </section>
        </div>

		<!-- Scripts -->
		<script src="<?php echo APP_SERVICE_ROOT.'app.js'; ?>"></script>
		<script>loadEnvironment(`<?php echo $env; ?>`);</script>
		<script src="<?php echo APP_SERVICE_ROOT.'mlcore.js'; ?>"></script>
		<script> 
			/* Loading the app client framework
			 * Any environment variables to be passed on from the server can take place in this here
			 */
			loadVue({
				scripts: [
					'featureextractor'
				]
			 });
		</script>

		<!-- Additional scripts go here -->
		<script src="<?php echo scripts('bootstrap_script'); ?>"></script>
		<script src="<?php echo scripts('script'); ?>"></script>

    </body>
</html>