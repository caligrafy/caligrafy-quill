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

// Current OpenAI models (October 2026)
define ("GPT6_ASTRA", "gpt-6-astra");    // flagship
define ("GPT6_SOL", "gpt-6.1-sol");      // close to the flagship, cheaper
define ("GPT6_LUNA", "gpt-6-luna");      // fastest and cheapest
define ("GPT_IMAGE", "gpt-image-2");     // image generation (base64 output)
define ("GPT_IMAGE_BEST", "gpt-image-2.5-sunburst");
define ("GPT_IMAGE_FAST", "gpt-image-2.5-flare");

// Default models: set OPEN_AI_MODEL / OPEN_AI_IMAGE_MODEL in .env to change them without touching the code
define ("OPENAI_DEFAULT_MODEL", defined('OPEN_AI_MODEL') && OPEN_AI_MODEL !== ''? OPEN_AI_MODEL : GPT6_LUNA);
define ("OPENAI_DEFAULT_IMAGE_MODEL", defined('OPEN_AI_IMAGE_MODEL') && OPEN_AI_IMAGE_MODEL !== ''? OPEN_AI_IMAGE_MODEL : GPT_IMAGE);

// Older model ids kept for backward compatibility. OpenAI shut down gpt-3.5-turbo, gpt-4-turbo, gpt-4-32k and
// gpt-4-vision-preview, and retires the gpt-4o family on January 14, 2027: use the constants above
define("GPT3", "gpt-3.5-turbo");
define ("GPT4", "gpt-4-turbo");
define ("GPT4O", "gpt-4o");
define ("GPT4OMINI", "gpt-4o-mini");
define ("GPT4-32", "gpt-4-32k"); // only reachable with constant("GPT4-32")
define ("GPT4_32", "gpt-4-32k");
define ("GPT4_LATEST", GPT6_ASTRA);
define ("GPT4_LATEST_VISION", GPT6_ASTRA);

define ("CONVERSE", "You are a helpful assistant:\n");
define ("SUMMARIZE", "Summarize the following:\n");
define ("ANALYZE", "Analyze the following:\n");
define ("CORRECT", "Fix the following:\n");
define ("CLASSIFY", "Classify the following:\n");
define ("COMPLETE", "Complete the following:\n");
define ("PREDICT", "Predict based on the following:\n");
define ("RECOMMEND", "Recommend about the following:\n");
define ("CUSTOM",  "");

// Only the model is sent by default: current reasoning models reject some sampling parameters
// (temperature, top_p, max_tokens...). Pass them in $parameters when the chosen model supports them,
// for example array("max_completion_tokens" => 1000)
define("DEFAULT_TEXT_PARAMETERS", array(
    "model" => OPENAI_DEFAULT_MODEL
));

define("DEFAULT_IMAGE_PARAMETERS", array(
    "model" => OPENAI_DEFAULT_IMAGE_MODEL,
    "n" => 1,
    "size" => "1024x1024",
    "quality" => "medium",
    "output_format" => "png"
));


define("TEXT", array(
    "type" => "text",
    "endpoint"=> "https://api.openai.com/v1/chat/completions",
));

// GPT image models return base64 images (data[].b64_json), never URLs
define("IMAGE", array(
    "type" => "image",
    "endpoint" => "https://api.openai.com/v1/images/generations"
));


class OpenAI extends \stdClass {

    /**
     * Commands that delegate() accepts, by name (case insensitive)
     */
    const COMMANDS = array('CONVERSE', 'SUMMARIZE', 'ANALYZE', 'CORRECT', 'CLASSIFY', 'COMPLETE', 'PREDICT', 'RECOMMEND', 'CUSTOM');

    /**
     * Model types that the constructor accepts, by name (case insensitive)
     */
    const TYPES = array('TEXT', 'IMAGE');

    /**
     * Resolves a command name (ex: 'summarize' from a form) to its prompt. Returns null for anything else,
     * so that user input can never select another constant (such as APP_KEY or DB_PASSWORD)
     */
    public static function command($name)
    {
        $name = strtoupper(trim((string)$name));
        return in_array($name, self::COMMANDS, true)? constant($name) : null;
    }

    /**
     * Resolves a model type name ('text' or 'image') to its configuration. Returns null for anything else
     */
    public static function type($name)
    {
        $name = strtoupper(trim((string)$name));
        return in_array($name, self::TYPES, true)? constant($name) : null;
    }

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
	* @var array conversation context
	* @property OpenAI model context
	*/
	public $context;

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

    public function delegate($input = '', $outcome = '', $command = ANALYZE,  $context  = array(), $parameters = array())
    {
        if ($this->type == 'text') {
           
            // COMPLETE with an [insert] marker: ask for the text that goes at the marker, keeping the text after it
            if ($command == COMPLETE && strpos((string)$input, "[insert]") !== false) {
                $command = COMPLETE . "Write only the text that replaces [insert] so that it fits between the text before and after it.\n";
            } 
            
            // If an outcome is given, concatenate to input and remove them from parameters
            $outcome = is_array($outcome) && !empty($outcome)? "\n| ".implode(" | ", $outcome)." | " : "\n".$outcome;

            return $this->converse(array_merge($parameters, ["messages" => array_merge($context, 
                                                                                        array(["role" =>  "system", "content" => $command], ["role" => "user", "content" => $input."\n".$outcome."\n"])
                                                                                        )]));
        }
        // if image
        elseif ($this->type == 'image' || $this->type == 'url') {
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