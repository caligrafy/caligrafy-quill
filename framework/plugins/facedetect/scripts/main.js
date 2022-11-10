var detector = new FaceDetector('detection');

const app = Vue.createApp({
  el: '#app',
  data () {
    return {
        detector: detector,
        env: env

    }
  },
  /* Method Definition  */
  methods: {
      
      // Load App method
      loadApp: function(app) {
        this.detector.loadApp(app);
      },
      
      continuousMethod: function() {
          console.log(this.detector.app.detections);
      },
      
      callbackMethod: function() {
         
        //<---- do whatever you want here
          console.log('Example of what can be done');
      }
      
      
      /* FaceDetect methods and properties
        * this.detector.app.detections: returns a stream of detections or recognitions. You can iterate through them to act on them
        * this.detector.app.canvas: returns the canva that overlays the video
        * this.detector.app.options: exposes all the options of the app
        * 
        * this.detector.media: exposes the media used (video, picture, cam stream)
        *             .media.width: gets the width
        *             .media.height: gets the height
        * 
        * this.detector.loadApp(app): load another app
        * (this.detector.detectFaces(app, facedetector))(): self invoking function to start face detection
        * this.detector.detect(callback, recognize = false, fetchRate = 100): starts a parallel stream that captures any detections or recognitions when available
        * this.detector.prepareCanva(options = null): returns a new canvas on top of the media source
        * this.detector.draw(facedetector): draws the detections on the canvas
        * this.detector.loadRecognition({ labels: [], images: [], sampleSize: 100}): load models to recognize by the recognition engine
        * this.detector.recognize(facedetector): runs the recognition engine and draws on canvas. Must make sure that detections is started before
        * this.detector.fetchImage(canvas, media): takes a canvas capture of the media and returns a blob data image (data url)
        * this.detector.display(message, output): displays a message in the infobar and gives it an ID as specified by the 'output' input
        * this.detector.clearDisplay(): clears the infobar display
        */

      /* FACEAPI methods and properties
       *
       * Properties of every detection: detection.box, age, expression, gender, landmarks, descriptors
       *
       * faceapi.draw.DrawFaceLandmarks(landmark, {settings})).draw(canva): function that draws the face landmark. Landmarks are attributes of the detections like age, gender etc.
                * draw landmark settings : drawLines (boolean), drawPoints (boolean), lineWidth (number), lineColor (rgba(x, x, x, x)), pointSize(number), pointColor(rgba(x, x, x, x))
       * 
       * faceapi.draw.DrawDetections(canva, detections): Draws the detections on the canva
       * 
       * faceapi.draw.DrawTextField(Array of strings to display, anchor).draw(canva): Draws text on the canva around the detections
       *        * anchor: where the text should be placed at every detection. For example it could be: detection.detection.box.bottomLeft
       * 
       * faceapi.draw.DrawBox(box, {settings}).draw(canva): draws a box around the face 
       * 
       * faceapi.matchDimensions(canva, {width: media width, height: media height}): changes the dimensions of the canva to match the media
       * faceapi.resizeResults(detections, {width: media width, height: media height}): resizes the detections to fit within the video
       * faceapi.detectAllFaces(media, algorithm) could be appended with:
       *                                         .withFaceLandmarks()
       *                                         .withFaceDescriptors()
       *                                         .withAgeAndGender()
       *                                         .withFaceExpressions()
       * 
       * faceapi.detectSingleFace(image model, algorithm): detects a single face
       *                                        .withFaceLandmarks()
       *                                        .withFaceDescriptor()
       *                                        .withAgeAndGender()
       *                                        .withFaceExpressions()
       * 
       * faceapi.LabeledFaceDescriptors(label, descriptors): returns the face descriptors from a an image and associates them with a label
       * 
       * faceapi.FaceMatcher(recognition model, threshold for matching): Compares the detections to the model and identifies if there is a match
       *         * Recognition Model 
       * 
       * faceapi.createCanvasFromMedia(media): creates a canva on top of the video or picture to manipulate it
       * faceapi.fetchImage(url): creates a model from the image to be use by the recognition engine
       * 
       * 
      */
     
      
      
  },
  /* upon object load, the following will be executed */
  mounted () {
      
      // Load general detection
      this.loadApp();
      
      // Load full detection
      this.loadApp({
          name: 'Full Detection',
          method: this.detector.draw,
          options: {
               welcome: "Detect faces, genders, ages and expressions",
               detection: true,
               landmarks: true,
               gender: true,
               expression: true,
               age: true
          }
      });
      
      // Load Model Recognition 
      this.loadApp({
              name: 'Recognize',
              method: this.detector.recognize,
              models: {
                  labels: ['Flash'],
                  //   images: [],
                  sampleSize: 3
              },
              options: {
                welcome: "Flash will be recognized if he is present",
                recognition: true
             },
             algorithm: faceapi.SsdMobilenetv1Options        
      });
      
      // Load puppeteer mode
      this.loadApp({
          name: "Puppeteer",
          method: this.detector.draw,
          options: {
                welcome: "Line Drawing",
                detection: false,
                puppeteer: true 
          }

      });
      
      this.loadApp({
          name: "Custom continuous",
          method: this.continuousMethod,
          custom: false, // set to false if you want the method to be applied continuously at every interval of detection
          options: {
              welcome: "Open the console to see how it is continuously being called at every detection",
              detection: true
          }
          
      });
      
      
    this.loadApp({
          name: "Custom callback",
          method: this.callbackMethod,
          custom: true, // set to true if you want the method to do something else before calling in FaceDetect features
          options: {
              welcome: "Open the console to see how it is executing its content and waiting for more to be done",
              detection: true
          }
          
      });
      
  }

});

// mount the app
app.mount('#app');