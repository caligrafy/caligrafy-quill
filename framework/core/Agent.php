<?php

/**
 * Agent.php is the model for an AI Assistant that references different open source AI models such as llama
 * @copyright 2025
 * @author Dory A.Azar
 * @version 1.0
 */

/**
 * The Assistant class handles all exchanges with the groq 
 * @author Dory A.Azar
 * @version 1.0
 */

 namespace Caligrafy;
 use \Exception as Exception;
 use LucianoTonet\GroqPHP\Groq;
 use LucianoTonet\GroqPHP\GroqException;

 // Current Groq models (October 2026). Override them in .env with GROQ_MODEL and GROQ_VISION_MODEL
 define("GROQ_DEFAULT_MODEL", defined('GROQ_MODEL') && GROQ_MODEL !== ''? GROQ_MODEL : "openai/gpt-oss-120b");
 define("GROQ_SMALL_MODEL", "openai/gpt-oss-20b");
 define("GROQ_DEFAULT_VISION_MODEL", defined('GROQ_VISION_MODEL') && GROQ_VISION_MODEL !== ''? GROQ_VISION_MODEL : "qwen/qwen3.8-27b"); // preview model: Groq has no production vision model
 define("GROQ_TRANSCRIPTION_MODEL", "whisper-large-v3");


 class Agent extends \stdClass {

    /**
	* @var string the groq key
	* @property string the groq key
	*/
	private $_public_key;

    /**
	* @var object the groq php object
	* @property object  the groq php object
	*/
	private $_groq;


    public function __construct($apiKey = "")
    {
        $this->_public_key = $apiKey != "" ?  $apiKey : GROQ_API_KEY;
        $this->_groq = new Groq($this->_public_key);
        return $this;

    }

    /**
     * Set configuration of Agent
     * parameters include: temperature, max_tokens etc.
     */
    public function configure($parameters = array())
    {
        return $this->_groq->setConfig($parameters);

    }

    /**
     * Create a text prompt for agent
     * model: specify the model from groq
     * messages: array of role (system, user) and content
     * response_format: array of types  i.e ['type' => 'json_object']
     * stream: set to true if streaming response is expected. Check documentation for fetching results
     * tool_choice: set to "auto" when tools are used
     * tools: array of tool definition. consult groq php docs.
     */
    public function delegate($model = "", $messages  = array(), $response_format = array(), $stream = false, $tool_choice = "", $tools = array() ) 
    {
        $model = $model? $model : GROQ_DEFAULT_MODEL;
        // Prepare response
        $response = array(
            'api_success' => false,
            'response' => 'We could not resolve this ask'
        );
        
        // Prepare parameters
        $parameters = array(
            'model' => $model,
            'messages' => $messages,
            'stream' => $stream
        );

        // Appending additional parameters when available
        $parameters = $response_format? array_merge($parameters, ['response_format' => $response_format]) : $parameters;
        $parameters = $tool_choice? array_merge($parameters, ['tool_choice' => $tool_choice]) : $parameters;
        $parameters = $tools? array_merge($parameters, ['tools' => $tools]) : $parameters;


        try {
            $output = $this->_groq->chat()->completions()->create($parameters);

            $response = array(
                'api_success' => true,
                'response' => $output['choices'][0]['message']['content']?? ""
            );
        }
        catch (GroqException $err) {
            $response['response'] = $err->getMessage();
        }
        return $response;
    }


    /**
     * Vision
     * file: file path
     * prompt: what to do with image
     */
    public function visualize($file = "", $prompt = "Describe this image", $model = GROQ_DEFAULT_VISION_MODEL)
    {
       $options = array('model' => $model? $model : GROQ_DEFAULT_VISION_MODEL);
       try {
           $analysis = $this->_groq->vision()->analyze($file, $prompt, $options);
       } catch (GroqException $err) {
           return array('api_success' => false, 'response' => $err->getMessage());
       }

       // return output
       return array(
            'api_success' => true,
            'response' => $analysis['choices'][0]['message']['content']?? ""
        );

    }


    /**
     * Audio Transcribe
     * file: file path
     * model: specify model to use
     * response_format: string. 'json'
     * language: specify language of audio
     * prompt: string of an optional transcription prompt
     */
    public function transcribe($file = "", $prompt = "", $model = GROQ_TRANSCRIPTION_MODEL, $language = "en", $response_format = "json")
    {
        $transcription = $this->_groq->audio()->transcriptions()->create([
            'file' => $file,
            'prompt' => $prompt,
            'model' => $model,
            'response_format' => $response_format,
            'language' => $language,
        ]);
        
        return json_encode($transcription);
    }

    /**
     * Audio Translate
     * file: file path
     * model: specify model to use
     * response_format: string. 'json'
     * prompt: string of an optional transcription prompt
     */
    public function translate($file = "", $prompt = "", $model = GROQ_TRANSCRIPTION_MODEL, $response_format = "json")
    {
        $translation = $this->_groq->audio()->translations()->create([
            'file' => $file,
            'prompt' => $prompt,
            'model' => $model,
            'response_format' => $response_format,
        ]);
        
        return json_encode($translation);
    }



 }