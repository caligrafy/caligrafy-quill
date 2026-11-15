<?php
/**
 * models.php: the AI model and prompt constants of Agent (Groq), Assistant and OpenAI (2.26.0).
 * They were defined at the top of those class files, which made the class map load the three files (and their
 * classes) on every request. Defined here, loaded with the core settings, the classes load only when used.
 * Values of .env (GROQ_MODEL, GROQ_VISION_MODEL, OPEN_AI_MODEL, OPEN_AI_IMAGE_MODEL) are constants by then.
 */

// Agent.php: Groq
// Current Groq models (October 2026). Override them in .env with GROQ_MODEL and GROQ_VISION_MODEL (or AGENT_MODEL and AGENT_VISION_MODEL)
define("GROQ_DEFAULT_MODEL", defined('GROQ_MODEL') && GROQ_MODEL !== ''? GROQ_MODEL : "openai/gpt-oss-120b");
define("GROQ_SMALL_MODEL", "openai/gpt-oss-20b");
define("GROQ_DEFAULT_VISION_MODEL", defined('GROQ_VISION_MODEL') && GROQ_VISION_MODEL !== ''? GROQ_VISION_MODEL : "qwen/qwen3.8-27b"); // preview model: Groq has no production vision model
define("GROQ_TRANSCRIPTION_MODEL", "whisper-large-v3");

// Assistant.php
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

// OpenAI.php
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
