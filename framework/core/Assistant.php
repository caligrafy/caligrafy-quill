<?php

/**
 * Assistant.php is the model for an AI Assistant that uses OpenAI models
 * @copyright 2023
 * @author Dory A.Azar
 * @version 2.0
 */

/**
 * The Assistant class runs conversations with OpenAI models through the Responses and Conversations APIs.
 *
 * OpenAI shut down the Assistants API on August 26, 2026. Version 2.0 of this class keeps the same chainable
 * methods (start, createMessage, run, retrieveResponse, end) on top of the APIs that replace it:
 * - the assistant (model, instructions, tools) is configuration kept in this object and sent with every response
 * - a conversation (POST /v1/conversations) replaces the thread
 * - a response (POST /v1/responses) replaces the run
 * - files are searched through a vector store (file_search tool)
 * @author Dory A.Azar
 * @version 2.0
 */

namespace Caligrafy;
use \Exception as Exception;

define("ASSISTANT_GPT6_ASTRA", "gpt-6-astra");
define("ASSISTANT_GPT6_SOL", "gpt-6.1-sol");
define("ASSISTANT_GPT6_LUNA", "gpt-6-luna");
define("ASSISTANT_DEFAULT_MODEL", defined('OPEN_AI_MODEL') && OPEN_AI_MODEL !== ''? OPEN_AI_MODEL : ASSISTANT_GPT6_LUNA);

// Older model ids kept for backward compatibility (gpt-3.5-turbo, gpt-4, gpt-4-32k and gpt-4-vision-preview are shut down)
define("ASSISTANT_GPT3", "gpt-3.5-turbo");
define ("ASSISTANT_GPT4", "gpt-4");
define ("ASSISTANT_GPT4O", "gpt-4o");
define ("ASSISTANT_GPT4OMINI", "gpt-4o-mini");
define ("ASSISTANT_GPT4_32", "gpt-4-32k");
define ("ASSISTANT_GPT4_LATEST", ASSISTANT_GPT6_ASTRA);
define ("ASSISTANT_GPT4_LATEST_VISION", ASSISTANT_GPT6_ASTRA);


class Assistant extends \stdClass {

    /**
	* @var string the OpenAI API root
	*/
	private $_api_url = "https://api.openai.com/v1";

    /**
	* @var string the model used for the responses
	*/
	private $_model = ASSISTANT_DEFAULT_MODEL;

    /**
	* @var array input messages waiting for the next run()
	*/
	private $_pending = [];

	/**
	* @var string the name of the assistant (used for the vector store name and metadata)
	*/
	public $name;

	/**
	* @var string what the assistant does (local description)
	*/
	public $description;

	/**
	* @var string the instructions (system prompt) sent with every response
	*/
	public $instructions;

	/**
	* @var array the tools: ["type" => "file_search"] is completed with the vector store, ["type" => "code_interpreter"]
	*      gets an automatic container with the uploaded files
	*/
	public $tools = array(["type" => "file_search"]);

	/**
	* @var array ids of the files uploaded for the assistant
	*/
	public $file_ids = [];

	/**
	* @var array local paths of the files to upload
	*/
	public $file_paths = [];

	/**
	* @var array metadata sent with every response (up to 16 string pairs)
	*/
	public $metadata;

	/**
	* @var string the vector store that holds the files searched by file_search
	*/
	public $vector_store_id = '';

	/**
	* @var string kept for backward compatibility: the Assistants API no longer exists, nothing is created on OpenAI
	*/
	public $assistant_id = '';

	/**
	* @var string the active conversation id (conv_...). Kept under its old name for backward compatibility
	*/
	public $thread_id = '';

	/**
	* @var string the last response id (resp_...)
	*/
	public $run_id = '';

	/**
	* @var string the status of the last response: completed, failed, in_progress, queued, incomplete, cancelled
	*/
	public $status = 'queued';

	/**
	* @var array the messages of the conversation: the user messages added and the assistant messages received.
	*      Every message is ['id', 'role', 'content' => [['type' => 'input_text'|'output_text', 'text' => '...']]]
	*/
	public $messages = [];

	/**
	* @var string the text of the last assistant answer
	*/
	public $response_text = '';

	/**
	* @var array the last raw response returned by OpenAI (useful for errors and usage)
	*/
	public $last_response = [];



    /**
     * Configure an assistant
     * parameters: name, description, instructions, model, tools, metadata, file_paths (uploaded to a new vector store),
     * file_ids (already uploaded files), vector_store_id (existing vector store)
     */
    public function __construct($parameters = array())
    {
        $this->_model = $parameters['model']?? $this->_model;
        $this->tools = $parameters['tools']?? $this->tools;
        $this->name = $parameters['name']?? $this->name;
        $this->description = $parameters['description']?? $this->description;
        $this->instructions = $parameters['instructions']?? $this->instructions;
        $this->metadata = $parameters['metadata']?? $this->metadata;
        $this->vector_store_id = $parameters['vector_store_id']?? $this->vector_store_id;
        $this->assistant_id = $parameters['assistant_id']?? $this->assistant_id;
        $this->file_ids = $parameters['file_ids']?? $this->file_ids;

        if (!empty($this->file_ids)) {
            $this->attachFiles($this->file_ids);
        }
        if (!empty($parameters['file_paths'])) {
            $this->uploadAssistantFiles($parameters['file_paths']);
        }
        return $this;
    }

    /**
     * Kept for backward compatibility: the assistant configuration lives in this object
     */
    public function retrieve()
    {
        return $this;
    }

    /**
     * Deletes the vector store created for the assistant (the uploaded files stay in the OpenAI file storage)
     */
    public function delete()
    {
        if ($this->vector_store_id) {
            $response = $this->request('DELETE', '/vector_stores/'.$this->vector_store_id);
            if (!empty($response['deleted'])) {
                $this->vector_store_id = '';
                return true;
            }
        }
        return $this;
    }

    /**
     * Start the contextual conversation (POST /v1/conversations)
     */
    public function start()
    {
        $body = !empty($this->metadata)? array('metadata' => $this->metadata) : new \stdClass(); // sent as {}
        $response = $this->request('POST', '/conversations', $body);
        $this->thread_id = $response['id']?? '';
        $this->messages = [];
        return $this;
    }

    /**
     * Delete the contextual conversation
     */
    public function end()
    {
        if ($this->thread_id) {
            $this->request('DELETE', '/conversations/'.$this->thread_id);
            $this->thread_id = '';
        }
        $this->_pending = [];
        return $this;
    }

    /**
     * Add a message to send with the next run()
     * $file_ids: files given to the code interpreter for this message
     */
    public function createMessage($content, $file_ids = [], $metadata = null, $role = "user")
    {
        $role = in_array($role, array('user', 'assistant', 'system', 'developer'), true)? $role : 'user';
        $this->_pending[] = array('role' => $role, 'content' => (string)$content);
        $this->messages[] = array('id' => '', 'role' => $role, 'content' => array(array('type' => 'input_text', 'text' => (string)$content)));
        if (!empty($file_ids)) {
            $this->file_ids = array_values(array_unique(array_merge($this->file_ids, $file_ids)));
        }
        return $this;
    }

    /**
     * Lists the items of the conversation (GET /v1/conversations/{id}/items), oldest first
     */
    public function listMessages()
    {
        return $this->thread_id? $this->request('GET', '/conversations/'.$this->thread_id.'/items?order=asc&limit=100') : '';
    }

    /**
     * Send the pending messages and get the answer (POST /v1/responses)
     * The overrides apply to this response only
     */
    public function run($additional_instructions = '', $instructions = null, $tools = [], $model = '', $metadata = null)
    {
        $instructions = !empty($instructions)? $instructions : $this->instructions;
        if (!empty($additional_instructions)) {
            $instructions = trim(($instructions?? '')."\n".$additional_instructions);
        }

        $body = array(
            'model' => !empty($model)? $model : $this->_model,
            'input' => $this->_pending
        );
        if (!empty($instructions)) { $body['instructions'] = $instructions; }
        if ($this->thread_id) { $body['conversation'] = $this->thread_id; }
        $toolList = $this->prepareTools(!empty($tools)? $tools : $this->tools);
        if (!empty($toolList)) { $body['tools'] = $toolList; }
        $meta = !empty($metadata)? $metadata : $this->metadata;
        if (!empty($meta)) { $body['metadata'] = $meta; }

        $response = $this->request('POST', '/responses', $body);
        $this->run_id = $response['id']?? '';
        $this->status = $response['status']?? 'failed';
        $this->_pending = [];
        $this->collect($response);
        return $this;
    }

    /**
     * Waits for the last response when it is still queued or in progress (background responses),
     * polling GET /v1/responses/{id} every $interval seconds for at most $timeout seconds
     */
    public function retrieveResponse($timeout = 120, $interval = 2)
    {
        $statuses = ["queued", "in_progress"];
        $start = time();
        while (in_array($this->status, $statuses) && $this->run_id && (time() - $start) < $timeout) {
            sleep($interval);
            $response = $this->request('GET', '/responses/'.$this->run_id);
            $this->status = $response['status']?? 'failed';
            $this->collect($response);
        }
        return $this;
    }

    /**
     * Returns the text of the last assistant answer
     */
    public function text()
    {
        return $this->response_text;
    }

    /**
     * Uploads files (POST /v1/files) and adds them to the assistant's vector store for file_search
     * @return array the ids of the uploaded files
     */
    public function uploadAssistantFiles($specified_paths = [])
    {
        $paths = !empty($specified_paths)? $specified_paths : $this->file_paths;
        $uploaded = [];
        foreach ((array)$paths as $file) {
            // no Content-Type header: cURL sets multipart/form-data with its boundary
            $response = httpFile($this->_api_url.'/files', $file, array("Authorization: Bearer ".OPEN_AI_KEY, "Accept: application/json"));
            if (isset($response['id'])) {
                $uploaded[] = $response['id'];
                $this->file_paths[] = $file;
            }
        }
        if (!empty($uploaded)) {
            $this->file_ids = array_values(array_unique(array_merge($this->file_ids, $uploaded)));
            $this->attachFiles($uploaded);
        }
        return $uploaded;
    }

    /**
     * Removes a file from the vector store and deletes it from the OpenAI file storage
     */
    public function deleteAssistantFile($file_id)
    {
        if ($this->vector_store_id) {
            $this->request('DELETE', '/vector_stores/'.$this->vector_store_id.'/files/'.$file_id);
        }
        $response = $this->request('DELETE', '/files/'.$file_id);
        $this->file_ids = array_values(array_diff($this->file_ids, array($file_id)));
        return $response['deleted']?? false;
    }

    /**
     * Adds files to the vector store, creating it when needed
     */
    private function attachFiles($fileIds)
    {
        if (!$this->vector_store_id) {
            $store = $this->request('POST', '/vector_stores', array('name' => $this->name? $this->name : 'caligrafy-assistant', 'file_ids' => array_values($fileIds)));
            $this->vector_store_id = $store['id']?? '';
            return;
        }
        foreach ($fileIds as $fileId) {
            $this->request('POST', '/vector_stores/'.$this->vector_store_id.'/files', array('file_id' => $fileId));
        }
    }

    /**
     * Completes the tools with the vector store (file_search) and the files (code_interpreter)
     */
    private function prepareTools($tools)
    {
        $prepared = [];
        foreach ((array)$tools as $tool) {
            $type = $tool['type']?? '';
            if ($type === 'file_search') {
                if (!isset($tool['vector_store_ids'])) {
                    if (!$this->vector_store_id) continue; // nothing to search
                    $tool['vector_store_ids'] = array($this->vector_store_id);
                }
            } elseif ($type === 'code_interpreter' && !isset($tool['container'])) {
                $tool['container'] = array('type' => 'auto', 'file_ids' => array_values($this->file_ids));
            } elseif ($type === 'retrieval') {
                // the old Assistants v1 name of file_search
                if (!$this->vector_store_id) continue;
                $tool = array('type' => 'file_search', 'vector_store_ids' => array($this->vector_store_id));
            }
            $prepared[] = $tool;
        }
        return $prepared;
    }

    /**
     * Reads the assistant messages and text out of a response
     */
    private function collect($response)
    {
        $this->last_response = is_array($response)? $response : array();
        if (($response['status']?? '') !== 'completed') {
            return;
        }
        $texts = [];
        foreach (($response['output']?? array()) as $item) {
            if (($item['type']?? '') !== 'message') continue;
            $this->messages[] = array('id' => $item['id']?? '', 'role' => $item['role']?? 'assistant', 'content' => $item['content']?? array());
            foreach (($item['content']?? array()) as $content) {
                if (($content['type']?? '') === 'output_text') {
                    $texts[] = $content['text']?? '';
                }
            }
        }
        $this->response_text = implode("\n", $texts);
    }

    /**
     * Sends a JSON request to the OpenAI API
     */
    private function request($method, $path, $body = array())
    {
        $headers = array("Authorization: Bearer ".OPEN_AI_KEY, "Content-Type: application/json", "Accept: application/json");
        if (strpos($path, '/vector_stores') === 0) {
            $headers[] = "OpenAI-Beta: assistants=v2"; // still shown in the vector store reference, harmless otherwise
        }
        $response = httpRequest($this->_api_url.$path, $method, $body, $headers);
        return is_array($response)? $response : array();
    }

}
