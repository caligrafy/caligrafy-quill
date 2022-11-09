/*
 * Instantiating the ml object with some settings
 * settings:
 * - brain: {...} defines the Neural Network settings
 * - type: either 'neuralnetwork' or 'featureextractor'. In this case we want to build a neural network
 * - options { inputs, outputs, debug, task}:  set debug to true to see the confidence visor. The task can either be classification or regression depending on whether the output is discreet or continuous
 */

var myMl = new MlCore({ brain: { type: 'neuralnetwork', options: { inputs: 3, outputs: 1, debug:true, task: 'regression'}}}); 

/* 
 * Neural Network Methods
 * - @constructor: define the brain by specifying number of inputs, outputs, debug mode and the type of task
 * - @addData: adds sample data for training the brain
 * - @addImage: adds sample image data for training the brain
 * - @train: trains the model and upon results automatically starts classifiying
 * - @classify: classifies the detections
 * - @normalizeData: normalizes the data (to be used when continuous data and not discrete)
 * - @save: saves the model
 * - @load: loads the saved model
 * 
 */



// Step 1: load data or create some data 
const data = [
  {r:255, g:0, b:0, color:'red-ish'},
  {r:254, g:0, b:0, color:'red-ish'},
  {r:253, g:0, b:0, color:'red-ish'},
  {r:180, g:0, b:20, color:'red-ish'},
  {r:180, g:10, b:20, color:'red-ish'},
  {r:50, g:30, b:70, color:'blue-ish'},
  {r:10, g:50, b:180, color:'blue-ish'},
  {r:0, g:0, b:255, color:'blue-ish'},
  {r:0, g:0, b:254, color:'blue-ish'},
  {r:0, g:0, b:253, color:'blue-ish'}
];


// Step 2: add data to the neural network
data.forEach(item => {
  const inputs = {
    r: item.r, 
    g: item.g, 
    b: item.b
  };
  const outputs = {
    color: item.color
  };

  myMl.brain.addData(inputs, outputs);
});

// Step 3: normalize your data;
myMl.brain.normalizeData();

// Step 4: train your neural network
const trainingOptions = {
  epochs: 20,
  batchSize: 2
}
  myMl.brain.train(trainingOptions, finishedTraining);

// Step 4 - Interim State to do something when training is finished. In this case, go directly to prediction/classification
function finishedTraining(){
  classify();
}

// Step 5: make a classification/prediction
function classify(){
  const input = {
    r: 10, 
    g: 10, 
    b: 1
  }
    myMl.brain.classify(input, handleResults);
}

// Step 5 - Interim State to do something upon prediction completion
function handleResults(error, results) {
    if(error){
      console.error(error);
      return;
    }
    console.log(results); // {label: 'red', confidence: 0.8};
}
