<?php

use Caligrafy\Controller;
use Caligrafy\OpenAI;

class AIController extends Controller {

    public function index()
    {
        return view('AI/openai');
    }

    public function query()
    {
        $parameters = $this->request->parameters;

        // type and command come from the form: only the documented names are accepted (never an arbitrary constant)
        $type = OpenAI::type($parameters['type']?? 'TEXT');
        $command = OpenAI::command($parameters['command']?? 'ANALYZE');
        $errors = array();
        if (!$type) { $errors['type'] = 'Invalid type. Use TEXT or IMAGE'; }
        if (!$command) { $errors['command'] = 'Invalid command. Use one of: ' . implode(', ', OpenAI::COMMANDS); }
        if (!empty($errors)) {
            return view('AI/openai', array(
                'error' => true, 'status' => 'danger', 'message_header' => 'Whoops, something is not right',
                'message' => implode('. ', $errors), 'errors' => $errors,
                'authorized' => authorized(), 'parameters' => $parameters
            ));
        }

        $input = $parameters['input']?? '';
        $outcome = $parameters['outcome']?? '';

        // optional conversation memory: a list of ['role' => 'user'|'assistant', 'content' => '...']
        $context = array();
        foreach ((array)($parameters['context']?? array()) as $message) {
            if (is_array($message) && in_array($message['role']?? '', array('user', 'assistant'), true) && is_string($message['content']?? null)) {
                $context[] = array('role' => $message['role'], 'content' => $message['content']);
            }
        }

        $openai = new OpenAI($type);
        return view('AI/openai', array(
            'response' => $openai->delegate($input, $outcome, $command, $context),
            'authorized' => authorized(),
            'parameters' => $parameters
        ));

    }
}

