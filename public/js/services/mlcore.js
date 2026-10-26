/**********************************************************************************
 * Machine Learning framework built on top of TensorFlow.js and ml5.js
 * @author: Dory A.Azar
 * @dependencies: ml5.js 1.4 (p5.js 2.x optional, for custom drawing)
 * @version: 2.0
 *
 * ml5 1.x replaced poseNet by bodyPose (MoveNet) and uNet by bodySegmentation. MlCore keeps its methods and
 * exposes every pose in both shapes:
 * - ml5 1.x: pose.keypoints[i] = {x, y, confidence, name}, pose.nose = {x, y, confidence}
 * - previous MlCore/poseNet shape: pose.pose.keypoints[i] = {part, position: {x, y}, score}, pose.pose.nose,
 *   pose.pose.rightEye (camelCase names) and pose.skeleton = [[keypointA, keypointB], ...]
 **********************************************************************************/

const MlCore = class MlCore {

	constructor(settings)
	{

		// load properties
		let defaultSettings = { media: 'video', canvas: 'canvas', brain: { type: 'neuralnetwork', options: { inputs: 34, outputs: 2, debug:true, task: 'classification'}}};
		this.settings = { ...defaultSettings, ...settings};
		this.poses = [];
		this.brain = null;

		// load DOM properties
		this.media = document.getElementById(this.settings.media) || null;
		this.canvas = document.getElementById(this.settings.canvas) || null;
		this.ctx = this.canvas? this.canvas.getContext('2d') : null;

		// logical drawing size: p5 scales its canvas for high density screens (canvas.width can be 2x)
		this.width = this.canvas? this.canvas.width : 0;
		this.height = this.canvas? this.canvas.height : 0;

		// Load filter properties
		this.segmentationImage = null;

		// ml5 1.x prefers WebGPU, which initializes asynchronously: the neural network needs a backend that is
		// ready right away (this is what the ml5 examples do)
		if (typeof ml5.setBackend === 'function') {
			ml5.setBackend(this.settings.backend || 'webgl');
		}

		const isFeatureExtractor = this.settings.brain.type == 'featureextractor';
		const needsStream = this.media && this.media.tagName.toLowerCase() == 'video' && !this.media.src;

		// the neural network is created synchronously; the feature extractor (MobileNet) loads asynchronously
		if (!isFeatureExtractor && !needsStream) {
			this.brain = ml5.neuralNetwork(this.settings.brain.options)?? null;
			console.log('Caligrafy MLCore Neural Networks launched');
		}

		// resolves when the brain and the media are ready
		this.ready = (async () => {
			if (needsStream) {
				await this.startStream();
			}
			if (isFeatureExtractor) {
				const { epochs, batchSize, numLabels, ...options } = this.settings.brain.options || {};
				this.brain = await ml5.featureExtractor('MobileNet', options);
			} else if (!this.brain) {
				this.brain = ml5.neuralNetwork(this.settings.brain.options)?? null;
			}
			return this;
		})();
	}

    /*
    * Method that starts streaming from computer cam
    */
    async startStream()
	{
        // Stream from media device returns a promise. Compatible on Safari, Chrome and Firefox
        const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        this.media.srcObject = stream;
        await this.media.play();
    }


	/*
    * Starts the body pose detection (ml5 bodyPose, MoveNet)
	* @callback: callback method called at every detection
	* @...arg: any additional arguments passed will be passed to the callback
    */
    async detect(callback, ...args)
	{
		await this.ready;
		this.bodyPose = await ml5.bodyPose('MoveNet');
		this.connections = this.bodyPose.getSkeleton();

		// background removal
		if (this.settings.filter) {
			this.segmenter = await ml5.bodySegmentation('SelfieSegmentation', { maskType: 'person' });
			this.segmenter.detectStart(this.media, (result) => this.filter(result));
		}

		this.modelReady();

		// fills "poses" with an array every time new poses are detected
		this.bodyPose.detectStart(this.media, (results) => {
			this.poses = results.map(pose => this.compatiblePose(this.scalePose(pose)));
			this.prepareCanva(this);
			callback(this, ...args);
		});
    }

	/*
	 * Stops the detections
	 */
	stopDetection()
	{
		if (this.bodyPose) this.bodyPose.detectStop();
		if (this.segmenter) this.segmenter.detectStop();
	}

	/*
	 * Converts the keypoints from the size the media is analyzed at to the canvas size
	 */
	scalePose(pose)
	{
		// TensorFlow reads <img>/<video> at their width/height attributes when set, else at their native size
		const nativeWidth = this.media.width || this.media.videoWidth || this.media.naturalWidth;
		const nativeHeight = this.media.height || this.media.videoHeight || this.media.naturalHeight;
		const sx = this.width && nativeWidth? this.width / nativeWidth : 1;
		const sy = this.height && nativeHeight? this.height / nativeHeight : 1;
		if (sx === 1 && sy === 1) return pose;
		pose.keypoints.forEach(k => {
			k.x *= sx;
			k.y *= sy;
			if (pose[k.name]) { pose[k.name].x = k.x; pose[k.name].y = k.y; }
		});
		if (pose.box) {
			pose.box.xMin *= sx; pose.box.xMax *= sx; pose.box.width *= sx;
			pose.box.yMin *= sy; pose.box.yMax *= sy; pose.box.height *= sy;
		}
		return pose;
	}

	/*
	 * Adds the previous poseNet shape (pose.pose, pose.skeleton) to an ml5 1.x pose
	 */
	compatiblePose(pose)
	{
		const camel = (name) => name.replace(/_([a-z])/g, (m, c) => c.toUpperCase());
		const legacy = { score: pose.confidence, keypoints: [] };
		pose.keypoints.forEach(k => {
			const point = { part: camel(k.name), position: { x: k.x, y: k.y }, score: k.confidence };
			legacy.keypoints.push(point);
			legacy[point.part] = { x: k.x, y: k.y, confidence: k.confidence };
		});
		pose.pose = legacy;
		pose.skeleton = (this.connections || [])
			.filter(([a, b]) => pose.keypoints[a] && pose.keypoints[b] && pose.keypoints[a].confidence > 0.2 && pose.keypoints[b].confidence > 0.2)
			.map(([a, b]) => [legacy.keypoints[a], legacy.keypoints[b]]);
		return pose;
	}

	/*
    * Hook when model is loaded
    */
	modelReady() {
		console.log('model loaded');
	}

	/*
    * function that draws the desired features
	* @mlpose: MlCore object is passed to the method
	* @posesToDraw: array of keypoint names (nose, leftEye or left_eye, rightWrist or right_wrist...)
    */
	drawFeature(mlpose = this, posesToDraw)
	{
		mlpose.ctx.fillStyle = 'rgb(255, 0, 0)';
		let valid = Array.isArray(posesToDraw) && posesToDraw.length > 0;

		for (let i = 0; valid && i < mlpose.poses.length; i++) {
			let pose = mlpose.poses[i];
			posesToDraw.forEach(poseToDraw => {
				let feature = pose[poseToDraw] || pose.pose[poseToDraw] || null;
				if (feature && (feature.confidence === undefined || feature.confidence > 0.2)) {
					mlpose.ctx.beginPath();
					mlpose.ctx.arc(feature.x, feature.y, 7, 0, 2 * Math.PI);
					mlpose.ctx.fill();
				}
			});
		}
	}


	/*
    * A function to draw circles over the detected keypoints
	* @mlpose: MlCore object is passed to the method
    */
	drawKeypoints(mlpose = this)
	{
	  mlpose.ctx.fillStyle = 'rgb(255, 0, 0)';
	  for (let i = 0; i < mlpose.poses.length; i++) {
		mlpose.poses[i].keypoints.forEach(keypoint => {
		  // Only draw a circle if the keypoint confidence is bigger than 0.2
		  if (keypoint.confidence > 0.2) {
			mlpose.ctx.beginPath();
			mlpose.ctx.arc(keypoint.x, keypoint.y, 7, 0, 2 * Math.PI);
			mlpose.ctx.fill();
		  }
		});
	  }
	}

	/*
    * A function to draw the skeletons
	* @mlpose: MlCore object is passed to the method
    */
	drawSkeleton(mlpose = this)
	{
	  mlpose.ctx.strokeStyle = 'rgb(255, 0, 0)';
	  mlpose.ctx.lineWidth = 2;
	  for (let i = 0; i < mlpose.poses.length; i++) {
		mlpose.poses[i].skeleton.forEach(([partA, partB]) => {
		  mlpose.ctx.beginPath();
		  mlpose.ctx.moveTo(partA.position.x, partA.position.y);
		  mlpose.ctx.lineTo(partB.position.x, partB.position.y);
		  mlpose.ctx.stroke();
		});
	  }
	}

	/*
    * Preparing the canva for use
	* @mlpose: MlCore object is passed to the method
    */
	prepareCanva(mlpose = this)
	{
		mlpose.ctx.clearRect(0, 0, mlpose.width, mlpose.height);
		let im = mlpose.media;

		// If filtering mode is on, draw the person only (mask of bodySegmentation)
		if (this.settings.filter && mlpose.segmentationImage && mlpose.segmentationImage.maskImageData) {
			im = mlpose.maskedMedia(mlpose.segmentationImage.maskImageData);
			mlpose.media.setAttribute('class', 'hide');
		} else if (this.settings.hideVideo) {
			im = mlpose.canvas;
			mlpose.media.setAttribute('class', 'hide');
		}

		mlpose.ctx.drawImage(im, 0, 0, mlpose.width, mlpose.height);
	}

	/*
    * Clearing the canva
	* @canvas: canvas object element
    */
	clearCanva(canvas)
	{
		canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
	}

	/*
    * Stores the latest segmentation result
	* @result: bodySegmentation result ({mask, maskImageData, imageData})
    */
	filter(result)
	{
		this.segmentationImage = result;
	}

	/*
	 * Returns a canvas with the media pixels kept only where the person mask is opaque
	 */
	maskedMedia(maskImageData)
	{
		const w = maskImageData.width, h = maskImageData.height;
		const canvas = document.createElement('canvas');
		canvas.width = w;
		canvas.height = h;
		const ctx = canvas.getContext('2d');
		ctx.drawImage(this.media, 0, 0, w, h);
		const frame = ctx.getImageData(0, 0, w, h);
		for (let i = 3; i < frame.data.length; i += 4) {
			frame.data[i] = maskImageData.data[i];
		}
		ctx.putImageData(frame, 0, 0);
		return canvas;
	}

	/*
    * Turn image data into a canvas
    */
	imageDataToCanvas(imageData, w, h)
	{
		const canvas = document.createElement('canvas');
		const ctx = canvas.getContext('2d');
		canvas.width = w;
		canvas.height = h;
		const imgData = ctx.createImageData(w, h);
		imgData.data.set(Array.from(imageData).slice(0, w * h * 4));
		ctx.putImageData(imgData, 0, 0);
		return canvas;
	};

	/*
    * Hands off to p5 (call it from p5's setup())
	* @mlpose: MlCore object is passed to the method
    */
	toP5(mlpose = this)
	{
		let myCanvas = createCanvas(mlpose.width, mlpose.height);
		const element = myCanvas.elt || myCanvas.canvas;
		element.id = mlpose.settings.canvas;
		element.classList.add('show');
		document.getElementById('detector').appendChild(element);
		mlpose.canvas.remove();

		mlpose.canvas = element;
		mlpose.ctx = element.getContext('2d');
		return mlpose.canvas;
	}


	/*
    * Trains the integrated model
	* @options: training options: epochs, batchSize...
	* @mlpose: MlCore object is passed to the method
    */
	train(mlpose = this, options = {})
	{
		if (mlpose.settings.brain.type == 'featureextractor'){
			const { epochs, batchSize } = mlpose.settings.brain.options || {};
			const trainingOptions = { ...(epochs? { epochs } : {}), ...(batchSize && batchSize >= 1? { batchSize } : {}), ...options };
			// ml5 only calls the last callback when the progress callback is a function
			mlpose.brain.train(trainingOptions, () => {}, () => mlpose.finishedTraining(mlpose));
		} else {
			mlpose.brain.normalizeData();
			mlpose.brain.train(options, () => mlpose.finishedTraining(mlpose));
		}
	}

	/*
	 * Method called when training is completed
	 */
	finishedTraining(mlpose = this) {
		mlpose.brain.results = 'Training Finishing...';
		mlpose.classify(mlpose);
	}

	/*
    * Classifies detections. Also called after training to start classification automatically upon training completion
    */
	classify(mlpose = this)
	{
		if (mlpose.settings.brain.type != 'featureextractor' && mlpose.poses.length > 0) {
			let inputs = mlpose.getInputs(mlpose);
			mlpose.brain.classify(inputs, (results, error) => mlpose.getResults(error, results, mlpose));
		} else if (mlpose.settings.brain.type == 'featureextractor') {
			mlpose.brain.classify(mlpose.media, (results, error) => mlpose.getResults(error, results, mlpose));
		}
	}

	/*
	 * Method triggered upon classification results
	 * @error: when the classification fails
	 * @results: the results, best label first
	 */
	getResults(error, results, mlpose = this)
	{
		if (error || !results || !results[0]) {
			if (error) console.error(error);
			return;
		}
		mlpose.brain.results = `${results[0].label} (${Math.floor(results[0].confidence * 100)})%`;
		mlpose.classify(mlpose);
	}

	/*
	 * Method that gets the inputs specifically for the body pose: x and y of the 17 keypoints (34 inputs)
	 */
	getInputs(mlpose = this)
	{
		let inputs = [];
		if (mlpose.hasBrain() && mlpose.poses.length > 0) {
			mlpose.poses[0].keypoints.forEach(keypoint => {
				inputs.push(keypoint.x);
				inputs.push(keypoint.y);
			});
		}
		return inputs;
	}

	/*
    * Add sample data to the model (body pose)
	* @labelInputs: label of the current pose
    */
	addData(mlpose = this, labelInputs)
	{
		if (mlpose.poses && mlpose.poses.length > 0) {
			let inputs = mlpose.getInputs(mlpose);
			mlpose.brain.addData(inputs, [labelInputs]);
		}
	}

	/*
    * Add the current media frame as a labeled image (feature extractor)
	* @labelInputs: label of the image
    */
	addImage(mlpose = this, labelInputs)
	{
		if (mlpose.hasBrain()) {
			mlpose.brain.addImage(mlpose.media, labelInputs);
		}
	}

	/*
    * Checks if the MlCore object has a brain already defined
    */
	hasBrain()
	{
		return this.brain != null;
	}

	/*
	 * Save the entire trained model
	 * @outputName: the name of the output. default is 'model'
	 * @callback: callback method to be called when download completed
	 */
	save(outputName, callback, mlpose = this)
	{
		mlpose.brain.save(outputName, callback);
	}

	/*
	 * Load a trained model
	 * @fileOrPath: the name of the file or path to JSON. if the name 'model' is given to the JSON, then 'model-meta.json', 'model-weights.bin' will be fetched as well
	 * @callback: callback method to be called when loading completed
	 */
	load(fileOrpath, callback, mlpose = this)
	{
		if (mlpose.hasBrain()) {
			mlpose.brain.load(fileOrpath, callback);
		}
	}

}
