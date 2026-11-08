<?php
/**
 * Service.php
 * A unit of business logic, written once and used by every channel
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Service
 * Extend it in application/services/, declare the inputs and implement handle():
 *
 *   #[Capability(name: 'create_book', description: 'Adds a book', scope: 'books:write')]
 *   class CreateBook extends Service {
 *       public $rules = ['title' => 'required|max_len,255', 'author_id' => 'required|integer'];
 *       public $filters = ['title' => 'trim|sanitize_string'];
 *       public $descriptions = ['title' => 'The title of the book'];
 *       public function handle(array $input, Context $context) {
 *           return (new Book('books'))->create($input);
 *       }
 *   }
 *
 * - $rules: GUMP validation rules per input. Only the inputs listed here reach handle(); use '' for an input without
 *   a rule. They also give the JSON Schema that agents and clients see (Capabilities::schema()).
 *   A file input takes the rule 'file' (2.19.0): 'cover' => 'required|file|extension,png;jpg|max_size,2mb'. Web pages
 *   and REST save the uploaded file and give handle() its public URL; MCP and the command line send a URL or a path
 *   (Caligrafy\Uploads).
 * - $filters: GUMP filters applied before validation.
 * - handle() returns data (an array, a model, a list of models, a scalar) and throws ServiceError to refuse.
 *   It never renders a view, redirects, prints or reads the request or the session: the context says who calls.
 */
abstract class Service
{
    /** @var array input => GUMP rules */
    public $rules = array();

    /** @var array input => GUMP filters */
    public $filters = array();

    /** @var array input => description, for the schema */
    public $descriptions = array();

    /**
     * Does the work
     * @param array $input the validated and filtered inputs (only those of $rules)
     * @param Context $context who calls, through which channel
     * @return mixed the result
     */
    abstract public function handle(array $input, Context $context);

    /**
     * Keeps the declared inputs, filters them and validates them
     * @return array the clean inputs
     * @throws ServiceError invalid, with a message per field
     */
    public function validate(array $input)
    {
        $data = array_intersect_key($input, $this->rules);
        $validator = new Validator();
        // file inputs (2.19.0, Caligrafy\Uploads): GUMP never sees the file rules; the channel saved the file and sends
        // its URL, MCP and the command line a URL or a path, whose extension is checked here
        $files = Uploads::fields($this->rules);
        $fileErrors = array();
        foreach ($files as $field => $spec) {
            if (isset($data[$field]) && $data[$field] !== '' && ($problem = Uploads::checkString($data[$field], $spec)) !== null) {
                $fileErrors[$field] = $problem;
            } elseif ((!isset($data[$field]) || $data[$field] === '') && Uploads::required($this->rules[$field])) {
                // required_file (or required|file) with no file, URL or path (2.19.1: required_file was ignored)
                $fileErrors[$field] = 'The ' . ucwords(str_replace(array('_', '-'), ' ', (string) $field)) . ' field needs a file';
            }
        }
        $rules = array_filter($files ? Uploads::strip($this->rules) : $this->rules, function ($rule) { return is_string($rule) && trim($rule) !== ''; });
        $filters = array_filter(array_intersect_key($this->filters, $this->rules), function ($filter) { return is_string($filter) && trim($filter) !== ''; });
        $validator->validation_rules($rules);
        $validator->filter_rules($filters);
        $clean = $validator->run($data);
        if ($clean === false || $fileErrors) {
            throw ServiceError::invalid(array_merge($clean === false ? $validator->get_errors_array() : array(), $fileErrors));
        }
        return $clean;
    }

    /**
     * Runs the service directly, without a capability check or audit (tests, other services)
     */
    public function run(array $input, ?Context $context = null)
    {
        return $this->handle($this->validate($input), $context ?? Context::cli());
    }
}
