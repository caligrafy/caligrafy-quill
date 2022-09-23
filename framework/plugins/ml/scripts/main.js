/*
 * Instantiating the ml object with some settings
 * settings:
 * - media: provide the mediaId of the video element in the markup
 * - canvas: provide the canvasId of the canvas element in the markup
 * - filter: set to true if you want to extract the body image from the background
 * - hideVideo: hides the video
 * - brain: {...} defines the Neural Network settings
 */

var myMl = new MlCore();



 /*
 * ROUTINE 1
 *
 * Setup is the first routine to run when available
 * If there is a desire to do custom drawing on the detections, a handoff to the P5 library takes place here
 * 
 */
 function setup() {

	// Hand-off to P5 to do custom drawing on top
	myMl.toP5(myMl);

}



/*
 * ROUTINE 2
 *
 * Detect function allows detection of body poses
 * @callback: mlpose offers several callback methods
 * 		- drawFeature(MlPose, Array features to draw)
 *		- drawKeypoints(MlPose): draws the keypoints detected on the entire body
 *		- drawSkeleton(MlPose): draws the lines for arms, shoulders, legs etc...
 *		- any other custom method that takes MlBody object as a first argument
 * @args: several arguments can be appended to the callback if needed to be called directly from detect
 *		- The first argument is always the MlBody object
 */

myMl.detect((myMl) => {


	// Drawn Keypoints
	 myMl.drawKeypoints(myMl);

	// Draw Skeleton
	myMl.drawSkeleton(myMl);

	// Draw Particular Features
	// myMl.drawFeature(myMl, ['nose', 'rightEye'] );

	
});

/*
 * ROUTINE 3
 *
 * Draw function is called only if a hand-off to P5 has been initiative in the setup
 * ONLY EXECUTES IF P5 IS CALLED IN THE SETUP
 */
function draw() {
	if (myMl.poses && myMl.poses.length > 0) {
		let pose = myMl.poses[0].pose;
		let nose = pose['nose'];
		fill(255, 0,0);
		ellipse(nose.x, nose.y, 50);
	}
	
}
