/********************************************************************************** 
 * Face detection framework built on top of the face-api framework
 * @author: Dory A.Azar
 * @dependencies: @vladmandic/face-api 1.7.15 (public/js/services/face-api.min.js)
 **********************************************************************************/

 class FaceDetector {
    
    /*
     * @media: id of the <video>, <img> or <canvas> element to analyze
     * @options: Optional.
     *      - autoStart: Boolean (default true). When true and the media is a <video> without a source, the camera starts
     *        once the models are loaded. Set it to false to start the camera yourself with startStream() and handle its errors
     */
    constructor(media, options = {}) {
        
        // neural network models url
        const MODEL_URL = env.app_resources + 'models';
        this.options = Object.assign({ autoStart: true }, options);
        this.streamError = null;
        this.mediaId = media;
        this.media = document.querySelector(`#${media}`) || null;

        // load the faceapi models (this.ready resolves once they are all loaded)
        this.ready = Promise.all([
            // face detection algorithms
            faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL), // SsdMobilenetv1Options

            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL), // TinyFaceDetectorOptions

            // Models for landmarks, age/gender, recognition and expressions
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.ageGenderNet.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
            faceapi.nets.faceExpressionNet.loadFromUri(MODEL_URL)
        ])
        .then(() => { 
                // when loaded start the stream. The media is looked up again: Vue replaces the page elements when it
                // mounts (after the detector is created in the scaffolds), which detaches the element found earlier
                this.resolveMedia(); 
                if (this.options.autoStart && this.media && this.media.tagName.toLowerCase() == 'video' && !this.media.src && !this.media.srcObject) {
                    // errors are kept in this.streamError
                    this.startStream().catch(error => console.log(error));
                }
        })
        .catch((error) => { console.log(error); }); 
    }
    
    /* 
    * Method that starts streaming from computer cam into the media <video>
    * Returns a promise: resolves with the stream once the video plays, rejects with the camera error
    * (NotAllowedError: access blocked, NotFoundError: no camera...), also kept in this.streamError
    * @constraints: Optional. getUserMedia constraints, ex: { video: { facingMode: 'user' }, audio: false }
    */
    async startStream(constraints = { video: true, audio: false }) {

        this.streamError = null;
        this.resolveMedia();
        try {
            if (!this.media) {
                throw new Error('No media element to stream the camera into');
            }
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('This browser cannot use a camera (the page needs https or localhost)');
            }
            // Stream from media device returns a promise. Compatible on Safari, Chrome and Firefox
            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            this.media.srcObject = stream;
            await this.media.play();
            return stream;
        } catch (error) {
            this.streamError = error;
            throw error;
        }
    };

    /*
    * Returns the media element that is on the page now (the element found earlier may have been replaced by Vue)
    */
    resolveMedia() {
        if (!this.media || !this.media.isConnected) {
            this.media = document.querySelector(`#${this.mediaId}`) || this.media || null;
        }
        return this.media;
    };

    /*
    * Method that stops the camera stream
    */
    stopStream() {
        if (this.media && this.media.srcObject) {
            this.media.srcObject.getTracks().forEach(track => track.stop());
            this.media.srcObject = null;
        }
    };

    /*
    * Promise that returns the face print of the only face in the media: { descriptor, detection }
    * The descriptor is an array of 128 numbers: 2 descriptors of the same person are usually closer than 0.5
    * (faceapi.euclideanDistance(a, b)), different people are usually further than 0.6
    * Rejects when there is no face or more than one face
    * @media: Optional. <video>, <img> or <canvas> element (default: the detector media)
    * @minConfidence: Optional. minimum detection score (default 0.6)
    */
    async faceprint(media = null, minConfidence = 0.6) {
        await this.ready;
        const detections = await faceapi.detectAllFaces(media || this.resolveMedia(), new faceapi.SsdMobilenetv1Options({ minConfidence: minConfidence }))
            .withFaceLandmarks()
            .withFaceDescriptors();
        if (detections.length === 0) {
            throw new Error('No face found');
        }
        if (detections.length > 1) {
            throw new Error('More than one face found');
        }
        return { descriptor: Array.from(detections[0].descriptor), detection: detections[0].detection };
    };

    /*
    * Promise that runs a liveness check on the live camera: the person performs the challenges in the given order
    * (the server chooses them and their order, see FaceLogin::challenge()). Face prints are captured while they move.
    * Resolves (never rejects on a failed check) with:
    *   { passed, reason, challenges: [{ name, passed, at }], prints: [{ descriptor, challenge, at }], frames, duration }
    * Rejects only when the models or the media cannot be used.
    * Liveness in the browser raises the bar against photos and replayed prints, it is not proof against a determined
    * attacker (a deepfake video or a modified page can pass): keep a passkey for sensitive accounts.
    * @options: Optional.
    *   - challenges: Array of 'turn-left', 'turn-right', 'smile' (default ['turn-left', 'smile']).
    *     Left and right are the person's own left and right
    *   - timeout: Integer. ms for the whole check (default 20000)
    *   - prints: Integer. face prints to capture (default 4, at least one per challenge when possible). The check
    *     passes only once min(prints, 3) are captured: FaceLogin::verify() wants 3 to 12 prints
    *   - printInterval: Integer. minimum ms between 2 prints (default 350)
    *   - onProgress: Function({ challenge, index, total, passed }) called when a challenge starts or passes
    *   - thresholds: Object. overrides of FaceDetector.LIVENESS_THRESHOLDS
    */
    async liveness(options = {}) {
        await this.ready;
        const media = this.resolveMedia();
        if (!media) {
            throw new Error('No media element to check');
        }
        const settings = Object.assign({ challenges: ['turn-left', 'smile'], timeout: 20000, prints: 4, printInterval: 350, onProgress: null }, options);
        const tracker = new LivenessTracker(settings.challenges, settings.thresholds);
        const prints = [];
        const start = Date.now();
        let frames = 0, lastPrint = 0, reason = 'timeout', announced = -1;
        const progress = (passed) => {
            if (typeof settings.onProgress === 'function') {
                settings.onProgress({ challenge: tracker.current(), index: tracker.index, total: tracker.challenges.length, passed: passed });
            }
        };

        while (Date.now() - start < settings.timeout) {
            if (announced !== tracker.index) { announced = tracker.index; progress(false); }
            const now = Date.now() - start;
            // a print every printInterval ms, spread over the challenges: SSD + descriptor is slower than tiny + landmarks
            const wantPrint = prints.length < settings.prints && now - lastPrint >= settings.printInterval;
            let task = faceapi.detectAllFaces(media, new faceapi.TinyFaceDetectorOptions({ inputSize: 320 })).withFaceLandmarks().withFaceExpressions();
            if (wantPrint) {
                task = task.withFaceDescriptors();
            }
            const detections = await task;
            frames++;
            if (detections.length > 1) {
                reason = 'More than one face found';
                break;
            }
            if (detections.length === 1) {
                const d = detections[0];
                const before = tracker.index;
                tracker.add(FaceDetector.frameSummary(d.landmarks.positions, d.expressions, now));
                if (wantPrint && d.descriptor) {
                    prints.push({ descriptor: Array.from(d.descriptor), challenge: tracker.challenges[Math.min(before, tracker.challenges.length - 1)] || null, at: now });
                    lastPrint = now;
                }
                if (tracker.index > before) { progress(true); }
            }
            if (tracker.done() && prints.length >= Math.min(settings.prints, 3)) {
                reason = null;
                break;
            }
            await new Promise(r => setTimeout(r, 30));
        }

        const passed = tracker.done() && reason === null;
        return {
            passed: passed,
            reason: passed ? null : (reason === 'timeout' && tracker.done() ? 'Not enough face prints' : reason === 'timeout' ? 'Challenge not completed: ' + tracker.current() : reason),
            challenges: tracker.results(),
            prints: prints,
            frames: frames,
            duration: Date.now() - start
        };
    };

    /*
    * Pure helpers of the liveness check (also used by the QA tests in Node)
    */

    // head yaw from 68-point landmarks: 0 facing the camera, > 0 turned to the person's own left, < 0 to their right
    // (in the camera image the person's left is on the right: the nose moves towards jaw point 16)
    static headYaw(points) {
        const left = points[0], right = points[16], nose = points[30];
        const width = right.x - left.x;
        return width > 0 ? ((nose.x - left.x) / width - 0.5) * 2 : 0;
    };

    // summary of one detection used by LivenessTracker: { yaw, happy, at }
    static frameSummary(points, expressions, at) {
        return { yaw: FaceDetector.headYaw(points), happy: expressions && typeof expressions.happy === 'number' ? expressions.happy : 0, at: at };
    };


    
    /* 
     * Promise that loads the app settings
     * @settings: Optional. Every app is controlled by a set of configuration that degines its behavior
     * - name: String. defines the name of the app that will appear in the UI
     * - custom: Boolean. When true, the application bypasses detection or recognition by calling a callback method
     * - method: 2 framework methods can be called. "draw" is for drawing on top of the medium. "recognize" is for calling the facial recognition. Any other callback method can be used
     * - algorithm: the faceapi algorithm used. The default algorithm is SsdMobilenetv1Options
     * - options: 
     *      - welcome: String. welcome message
     *      - detection: Boolean. when true it draws a box around detected faces
     *      - age, gender, expression: Boolean. when true, they are evaluated
     *      - landmarks: Boolean. when true, the line drawing is drawn on top of the face
     *      - recognition: Boolean. when true the recognition engine is called
     * - models
     *      - labels: Array. Collection of the names of "faces" to be recognized. A folder of the same name needs to be created in the "recognition" folder
     *      - images: Array. Collection of the pictures associated with a label. Used only to create on the fly training of one face
     *      - sampleSize: Integer. size of the picture of sample
     */
    async loadApp(settings = null) {

        const app = settings? settings : {
                name: 'Detect',
                method: this.draw, // this.recognize
                //algorithm: faceapi.SsdMobilenetv1Options,
                options: {
                    //welcome: "Show yourselves and we will detect your faces",
                    detection: true,
                    //age: true,
                    //gender: true,
                    //expression: true,
                    ////puppeteer: true,
                    //landmarks: true,
                    //recognition: true
                }
        };
        
        // the recognition models need the neural networks: wait until they are loaded
        await this.ready;

        // load recognition models for use in the "Where is" app
        try {
           app.options.recognitionModel = settings && settings.models && app.options.recognition? await this.loadRecognition(settings.models) : null; 
        } catch (error) {
            console.log(error);
        }
        

        let button = document.createElement('button');
        button.innerHTML = app.name;
        button.id = 'app' + app.id;
        button.addEventListener('click', !app.custom ? this.detectFaces(app, this) : this.custom(app, this));
        document.getElementById('apps').appendChild(button);

    };
    
    /* 
     * Method that detects faces throughout the life cycle of the video stream
     * @app: object definition of the app and its options
     * @facedetector: FaceDetector object
     *
     */
     detectFaces(app, facedetector) {
        return function() {
            // Apply algorithm from app settings
            const algorithm = app.algorithm || faceapi.TinyFaceDetectorOptions;
            

            // Get the canvas ready with the app options
            const canvas = facedetector.prepareCanva(app.options);


            // Match the canva size with the video
            const displaySize = {
                width: facedetector.media.width,
                height: facedetector.media.height
            };
            faceapi.matchDimensions(canvas, displaySize);

            // Run the face detection algorithm every 100ms 
            facedetector.runningEvent = setInterval(async () => {
                
                

                // Detect all the faces present in the stream with Landmarks, Age and Gender, and Facial Expressions
                const detections = await faceapi.detectAllFaces(facedetector.media, new algorithm()).withFaceLandmarks().withFaceDescriptors().withAgeAndGender().withFaceExpressions();

                // resize the detections to fit in the canva
                const resizedDetections = faceapi.resizeResults(detections, displaySize);
                
                // expose the detections to the app
                facedetector.app = app;
                facedetector.app.detections = resizedDetections;
                facedetector.app.canvas = canvas;
                
                
                // call the defined app method
                app.method(facedetector);
                
            }, 100); // end interval
        } // end callback 
    };
    
    
    /* 
     * Method that creates and prepares the canva for the application selected
     * @options: the application selected options are provided
     */
     prepareCanva(options) {

        // clear any running event
        if (this.runningEvent) {
            clearInterval(this.runningEvent);
        }
         
        // Display welcome message if defined
        this.clearDisplay();
        if(options && options.welcome) {
            this.display(options.welcome, 'message');
        }

        // set whether or not the video is to be visible
        if (options && options.puppeteer) {
            this.media.classList.replace('show', 'hide');
        } else {
            this.media.classList.replace('hide', 'show');
        }

        // create a canva from the video media and match its size to the video size
        // If the canva exists then remove it first
        const existingCanva = document.getElementById('detectfaces') || null;
        if (existingCanva) {
            existingCanva.parentNode.removeChild(existingCanva);
        }

        // Create the canva on top of the video and add it to the document
        const canvas = faceapi.createCanvasFromMedia(this.media);
        canvas.id = "detectfaces";
        document.getElementById('detector').append(canvas);

        return canvas;

    };
    
    /* 
     * Method that draws different renderings of the detections on the canvas
     * 
     * @facedetector: FaceDetector object
     * - detections: face objects detected from the stream
     * - options: Optional. Object defining the different options applied to the rendering
     * - canvas: Optional. The canvas on which the drawings will be rendered
     *
     */
     draw(facedetector = this) {
         
         const detections = facedetector.app.detections || null;
         const options = facedetector.app.options || null;
         const canvas = facedetector.app.canvas || null;

        // Clear the canva so that it doesn't accumulate drawing at every detection
        canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);

        // Draw the Detections for face detect
        if (options && options.detection) {
            faceapi.draw.drawDetections(canvas, detections);
        }


        // Draw face in line of different colors for even and odd indices
        if (options && (options.landmarks || options.puppeteer)) {

            // for each detection get the landmarks from the api and draw them as a full line
            detections.forEach((result, i) => {
                const landmark = result.landmarks;
                const drawPuppeteer = new faceapi.draw.DrawFaceLandmarks(landmark, {
                    drawLines: true,
                    drawPoints: false,
                    lineWidth: 2,
                    lineColor: i % 2 == 1 ? 'rgba(0,0,0,1)' : 'rgba(255,0,0, 1)',
                    pointSize: 2,
                    pointColor: 'rgba(0,0,0,1)'
                });
                drawPuppeteer.draw(canvas);
            });
        }


        // Custom Draw Gender, Age and Expressions
        if (options && (options.age || options.gender || options.expression)) {
            detections.forEach((result, i) => {

                // Get the most probable expression
                let sorted = result.expressions ? result.expressions.asSortedArray() : null;
                let expression = Array.isArray(sorted) ? sorted.filter(function(expr) {
                    return expr.probability > 0.9;
                }) : null;
                let expressionToDisplay = Array.isArray(expression) ? expression[0] ? expression[0].expression : 'neutral' : 'neutral';

                // Compose the array of results to display on the canva
                let resultToDisplay = [];
                if (options && options.expression) {
                    resultToDisplay.push("Expression: " + expressionToDisplay);
                }
                if (options && options.gender) {
                    resultToDisplay.push("Gender: " + result.gender);
                }
                if (options && options.age) {
                    resultToDisplay.push("Age: " + Math.round(result.age));
                }

                // Draw the values at the bottom of the canva
                const box = result.detection.box;
                const anchor = box.bottomLeft;
                const drawTextField = new faceapi.draw.DrawTextField(resultToDisplay, anchor);
                drawTextField.draw(canvas);

            });
        }
    }
    
    /* 
     * Utility method that resets the message display
     */
     clearDisplay() {

        // clear the ordered list
        let messageElement = document.getElementById('message') || null;
        if (messageElement) {
            messageElement.innerHTML = '';      
        }

    }
    
    
    /* 
     * Method that displays messages to the status area in the UI
     *
     * @message: the message to be displayed
     * @output: the HTML id where it should be displayed
     */
     display(message, output) {
         
        let infobarElement = document.getElementById('infobar') || null;
        if (!document.getElementById('infobar')) {
            infobarElement = document.createElement('SECTION');
            infobarElement.id = 'infobar';
            
            // If the ids have been respected and the detector id is identified
            let detectorElement = document.getElementById('detector') || null;
            if (detectorElement) {
                // place it before the detector section
                detectorElement.parentNode.insertBefore(infobarElement, document.getElementById('detector'));
            } else {
                // otherwise place it in the beginning of the body
                document.body.prepend(infobarElement);
            }
        }

        // If the output area where message needs to be placed does not exist then create it
        if (!document.getElementById(output)) {

            // The message bar is an unordered list and list items can be accumulated in it
            let messageElement = document.getElementById('message') || null;
            if (!messageElement) {
                messageElement = document.createElement('UL');
                messageElement.id = 'message';
                infobarElement.appendChild(messageElement);
            } else {
                let separator = document.createElement('LI');
                separator.innerHTML = "|";
                messageElement.appendChild(separator);            
            }
            let newStatus = document.createElement('LI');
            newStatus.id = output;
            messageElement.appendChild(newStatus);
        }

        // add message to the area
        let outputElement = document.getElementById(output) || null;
        outputElement.innerHTML = message;

    }
    
    /* 
     * Method that finds the best match for a face based on a model defined
     * 
     * @facedetector: FaceDetector object
     * - detections: face objects detected from the stream
     * - options: Optional. Object defining the different options applied to the rendering
     * - canvas: Optional. The canvas on which the drawings will be rendered
     *
     */
     recognize(facedetector = this) {
         
         const detections = facedetector.app.detections || null;
         const options = facedetector.app.options || null;
         const canvas = facedetector.app.canvas || null;

        // Clear the canva so that it doesn't accumulate drawing at every detection
        canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
         
        // initialize the face matcher based on the recognition model loaded
        const labeledFaceDescriptors = options.recognitionModel;
        const faceMatcher = new faceapi.FaceMatcher(labeledFaceDescriptors, 0.6);

        const results = detections.map(d => faceMatcher.findBestMatch(d.descriptor));
        
        // expose the recognitions
        facedetector.app.recognitions = results;

        // For each detection, draw a box. Draw the match in a different color
        results.forEach((result, i) => {
            const box = detections[i].detection.box;

            // Let the box highlight matches in a different box colors
            const drawBox = new faceapi.draw.DrawBox(box, {
                label: result.toString(),
                boxColor: !result.toString().includes("unknown") ? 'rgba(255,0,0, 1)' : 'rgb(192,192,192,1)'
            });
            drawBox.draw(canvas);
        })

    }
    
    /* 
     * Method that loads models for recognition
     *
     * @models: Object. Default null. When specified the recognitions are loaded from the images provided as opposed to the ones in storage
     * @output: return promise with face descriptors of the models to be recognized
     */
     loadRecognition(models = null) {
         let labels = models.labels || [];
         let images = models.images || [];
         let sampleSize = models.sampleSize || 0;

        // return a promise that loads all the images and fetches their descriptors
        return Promise.all(

            // for each label get the descriptors based on the model image
            labels.map(async (label, key) => {

                const descriptors = [];
                let url;

                // iterate through all the model images in the folder (there are 6 right now and this number should be changed if more pics need to be added)
                for (let i = 1; i <= sampleSize; i++) {

                    if(images[0] && images[0].constructor === Array) {
                        url =  images[key].length > 0 ? images[key][i-1] : env.home + 'public/' + env.appName + `/recognition/${label}/${i}.png`;
                    } else {
                        url =  images.length > 0 ? images[i-1] : env.home + 'public/' + env.appName + `/recognition/${label}/${i}.png`;
                    }

                    //url =  images.length > 0 ? images[i-1] : env.home + 'public/' + env.appName + `/recognition/${label}/${i}.png`;

                    // fetch the images from the model
                    const img = await faceapi.fetchImage(url);


                    // detect the single face from the image
                    const detections = await faceapi.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();

                    // push the descriptiors from each image detection onto the decriptor collections that will be needed
                    if (detections && detections.descriptor) {
                        descriptors.push(detections.descriptor);
                    };
                }

                return new faceapi.LabeledFaceDescriptors(label, descriptors);
            })
        );

    }
    
    
    /* 
     * Utility method that reads the detections to perform something on them
     * @callback: takes a callback method that is defined at method calling
     * @recognize: set to true to get the recognitions rather than the detections if the recognition engine is running
     * @fetchRate: define in ms the fetching rate of the detections. Default is 100ms
     *
     */
    detect(callback, recognize = false, fetchRate = 100) {
        setInterval(() => {
            if (recognize && this.app && this.app.recognitions) {
                    callback(this.app.recognitions);
                } else if(this.app && this.app.detections) {
                    callback(this.app.detections);
                }
        }, fetchRate)
    }
    
    
    /* 
     * Utility method that captures an image from the video and draws to a canva
     * @canvas: the canvas where the image will be drawn
     * @media: video or img
     * @output: the data url of the blob image created
     *
     */
     fetchImage(canvas, media) {
        let context = canvas.getContext('2d');
        context.drawImage(media, 0, 0, media.offsetWidth, media.offsetHeight)
        return canvas.toDataURL();
    }
    
    
    
    
    /* 
     * Method for custom app that doesn't use Face Detections
     * @app: object definition of the app and its options
     * @facdetector: takes this object as a parameter as well
     *
     */
     custom(app, facedetector) {

        return function() {

            // Get the canvas ready with the app options
            const canvas = facedetector.prepareCanva(app.options);


            // Match the canva size with the video
            const displaySize = {
                width: facedetector.media.width,
                height: facedetector.media.height
            };
            faceapi.matchDimensions(canvas, displaySize);
            
            
            // initialize the app in the object
            facedetector.app = app;
            facedetector.app.canvas = canvas;

            
            // call the app features from here
            app.method(facedetector);

        }

    }

    /* 
     * Method for clearing the canva
     * @canvas: the canvas where the image will be drawn
     *
     */
        clearCanva(canvas) {
            if (canvas) {
                // Clear the canva so that it doesn't accumulate drawing at every detection
                canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
            }
    
        }
    
    
}
/*
 * Default thresholds of the liveness check (FaceDetector.liveness({ thresholds }) overrides them)
 * - frontalYaw / turnYaw: a turn starts facing the camera (|yaw| <= frontalYaw) and reaches |yaw| >= turnYaw
 * - neutral / smile: a smile starts with happy <= neutral and reaches happy >= smile (face-api expression scores)
 */
FaceDetector.LIVENESS_CHALLENGES = ['turn-left', 'turn-right', 'smile'];
FaceDetector.LIVENESS_THRESHOLDS = { frontalYaw: 0.15, turnYaw: 0.3, neutral: 0.3, smile: 0.8 };

/*
 * State machine of the liveness check: feed it frame summaries ({ yaw, happy, at }) with add(), it moves to the
 * next challenge when the current one is seen. Pure (no camera, no face-api): FaceDetector.liveness() feeds it
 */
class LivenessTracker {

    constructor(challenges, thresholds = {}) {
        if (!Array.isArray(challenges) || challenges.length === 0) {
            throw new Error('Liveness needs at least one challenge');
        }
        challenges.forEach(c => {
            if (!FaceDetector.LIVENESS_CHALLENGES.includes(c)) { throw new Error('Unknown liveness challenge: ' + c); }
        });
        this.challenges = challenges.slice();
        this.t = Object.assign({}, FaceDetector.LIVENESS_THRESHOLDS, thresholds || {});
        this.index = 0;
        this.passedAt = [];
        this.reset();
    }

    // state of the current challenge
    reset() {
        this.state = { phase: 0 };
    }

    current() {
        return this.challenges[this.index] || null;
    }

    done() {
        return this.index >= this.challenges.length;
    }

    results() {
        return this.challenges.map((name, i) => ({ name: name, passed: i < this.passedAt.length, at: i < this.passedAt.length ? this.passedAt[i] : null }));
    }

    // returns true when this frame completed the current challenge
    add(frame) {
        if (this.done() || !frame) {
            return false;
        }
        const t = this.t, s = this.state;
        let passed = false;
        switch (this.current()) {
            case 'turn-left':
            case 'turn-right': {
                const sign = this.current() === 'turn-left' ? 1 : -1;
                if (s.phase === 0 && Math.abs(frame.yaw) <= t.frontalYaw) { s.phase = 1; }
                else if (s.phase === 1 && frame.yaw * sign >= t.turnYaw) { passed = true; }
                break;
            }
            case 'smile':
                if (s.phase === 0 && frame.happy <= t.neutral) { s.phase = 1; }
                else if (s.phase === 1 && frame.happy >= t.smile) { passed = true; }
                break;
        }
        if (passed) {
            this.passedAt.push(frame.at);
            this.index++;
            this.reset();
        }
        return passed;
    }
}

// Node (QA tests): the pure helpers can be required without a browser
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { FaceDetector, LivenessTracker };
}
