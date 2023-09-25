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
        $type = $parameters['type']? constant(strtoupper($parameters['type'])) : TEXT;
        $input = $parameters['input']?? '';
        $outcome = $parameters['outcome']?? '';
        $command = isset($parameters['command'])? constant(strtoupper($parameters['command'])) : ANALYZE;
        $custom = $parameters['custom']?? array();
        $context = $parameters['context']?? array();
        $openai = new OpenAI($type);
        return view('AI/openai', array(
            'response' => $openai->delegate($input, $outcome, $command, $context, $custom),
            'authorized' => authorized(),
            'parameters' => $parameters
        ));

    }
}


