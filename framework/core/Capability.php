<?php
/**
 * Capability.php
 * Declares a service as a capability that every channel can call
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Attribute Capability
 * #[Capability(name: 'create_book', description: 'Adds a book to the catalog', scope: 'books:write')]
 * on a class that extends Caligrafy\Service. Its name is how REST, MCP, the JS client and the CLI call it.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Capability
{
    /**
     * @param string $name snake_case, unique in the application (create_book)
     * @param string $description one or two sentences: what it does and returns (shown to agents as the tool description)
     * @param string $scope the token scope it needs (books:write); '' = any token
     * @param int $level the minimum value of the users permission column (0 = any signed-in user)
     * @param bool $readOnly true when it changes nothing (not audited when it succeeds; agents may call it freely)
     * @param bool $destructive true when it deletes or overwrites data (agents ask before calling it)
     * @param bool $public true when anyone may call it without signing in or a token
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public string $scope = '',
        public int $level = 0,
        public bool $readOnly = false,
        public bool $destructive = false,
        public bool $public = false
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name)) {
            throw new \InvalidArgumentException("Capability name '$name' must be snake_case: letters, digits and _, starting with a letter");
        }
    }
}
