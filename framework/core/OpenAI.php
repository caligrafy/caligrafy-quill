<?php

/**
 * OpenAI.php is the file that controls all the interactions with the OpenAI models
 * @copyright 2023
 * @author Dory A.Azar
 * @version 1.0
 */

/**
 * The Watson class handles all exchanges with the OpenAI models
 * @author Dory A.Azar
 * @version 1.0
 */

namespace Caligrafy;
use \Exception as Exception;

define("DAVINCI", "text-davinci-003");

define ("CONVERSE", "");
define ("SUMMARIZE", "Summarize the following:\n");
define ("ANALYZE", "Analyze the following:\n");
define ("CORRECT", "Fix the following:\n");
define ("CLASSIFY", "Classify the following:\n");
define ("COMPLETE", "Complete the following:\n");
define ("PREDICT", "Predict based on the following:\n");

define("DEFAULT_TEXT_PARAMETERS", array(    
    "model" => DAVINCI,   
    "temperature" => 0,
    "max_tokens" => 1000,
    "top_p" => 1.0,
    "frequency_penalty" => 0.0,
    "presence_penalty"=> 0.0
));

define("DEFAULT_IMAGE_PARAMETERS", array(
    "n" => 1,
    "size" => "1024x1024"
));


define("TEXT", array(
    "type" => "text",
    "endpoint"=> "https://api.openai.com/v1/completions",
));

define("IMAGE", array(
    "type" => "url",
    "endpoint" => "https://api.openai.com/v1/images/generations"
));


class OpenAI {

    /**
	* @var string the OpenAI API Endpoint
	* @property OpenAI API URI
	*/
	private $_openai_url;


    /**
	* @var string the OpenAI model type: text or image
	* @property OpenAI model type: text or image
	*/
	public $type;


    /**
	* @var string the OpenAI  model parameters 
	* @property OpenAI model parameters
	*/
	public $parameters;

    /**
	* @var string the OpenAI file attachments necessary for fine-tuning models
	* @property OpenAI file attachments
	*/
	public $attachments;


    public function __construct($mode = TEXT)
    {
        $this->type = $mode['type'];
        $this->parameters = $mode['type'] == 'text'? DEFAULT_TEXT_PARAMETERS : DEFAULT_IMAGE_PARAMETERS;
        $this->_openai_url = $mode['endpoint'];
        return $this;

    }


    /**
     * General method to command the AI to converse in text
     * parameters:
     ** For type text: array of model, prompt/input, instructions, temperature, tokens, max_tokens, top_p, frequency_penalty
     ** For type image: array of prompt, n, size
     *
     * attachments:
     ** For type image: array("image" => uri to image)
     *
     * method: POST, GET, PUT, DELETE. default is POST
     * additionalHeaders: array of request headers to complement default
     */
    public function converse($parameters = array(), $attachments = array(), $method = 'POST', $additionalHeaders = array())
    {
        $this->parameters = array_merge($this->parameters, $parameters, $attachments);
        return $this->prepareRequest($method, $additionalHeaders);  
    }

    public function delegate($input = '', $outcome = '', $command = ANALYZE, $parameters = array())
    {
        // initialize variables
        $inputConstraints = '';
        $suffix = '';

        if ($this->type == 'text') {
           
            // If there is insertion needed then divide input into input and suffix
            $inputArray = $input? explode("[insert]", $input) : array();
            if (!empty($inputArray) && $command == COMPLETE) {
                $suffix = implode("", array_slice($inputArray, 1));
                $input = $inputArray[0];
            } 
            
            // If an outcome is given, concatenate to input and remove them from parameters
            $outcome = is_array($outcome) && !empty($outcome)? "\n| ".implode(" | ", $outcome)." | " : "\n".$outcome;

            return $this->converse(array_merge($parameters, ["prompt" => $command.$input.$outcome, "suffix" => $suffix]));
        }
        // if image
        elseif ($this->type == 'url') {
            return $this->converse(array_merge($parameters, ["prompt" => $input]));
        }
            return "Delegation does not work for this model";

    }


    /**
     * Preparing the request to interact with the OpenAI api
     * parameters:
     ** method: POST, GET, PUT, DELETE. Default is POST
     ** additionalHeaders: complementing/overriding headers if needed
     */
    private function prepareRequest($method, $additionalHeaders)
    {
        // Initialize the output
        $output = array(
            'api_success' => false,
            'response' => 'We could not resolve this ask'
        );

        // Identifying the request method
        $method = in_array(strtoupper($method), array('POST', 'GET', 'PUT', 'DELETE'))? $method : 'POST';

        // Preparing the headers
        $headers = array_merge(array(
            "Accept: application/json",
            "Content-Type: application/json",
            "Authorization: Bearer ".OPEN_AI_KEY
        ), $additionalHeaders);


        if ($this->_openai_url && $method && $this->parameters && $headers )
        {
            // interact with openai API 
            $response = httpRequest($this->_openai_url, $method, $this->parameters, $headers);

            // handle request
            if (!empty($response)) {
                $output['api_success'] = true;
                $output['response'] = !empty($response['choices']) ? $response['choices'] : (!empty($response['data']) ? $response['data'] : $response);
                $output['type'] = $this->type;
            }
        } 

        return $output;
    }




}