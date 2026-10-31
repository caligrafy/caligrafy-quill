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
        $rules = array_filter($this->rules, function ($rule) { return is_string($rule) && trim($rule) !== ''; });
        $filters = array_filter(array_intersect_key($this->filters, $this->rules), function ($filter) { return is_string($filter) && trim($filter) !== ''; });
        $validator->validation_rules($rules);
        $validator->filter_rules($filters);
        $clean = $validator->run($data);
        if ($clean === false) {
            throw ServiceError::invalid($validator->get_errors_array());
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
