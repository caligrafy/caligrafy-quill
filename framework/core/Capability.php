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
     * @param string $scope the token scope it needs (books:write); '' = none for a read-only one, its own name for a write
     *                      one (2.26.0: a token needs the scope create_book, or *; TOKEN_DEFAULT_SCOPES=false, deprecated: none)
     * @param int $level the minimum value of the users permission column (0 = any signed-in user)
     * @param bool $readOnly true when it changes nothing (not audited when it succeeds; agents may call it freely)
     * @param bool $destructive true when it deletes or overwrites data (agents ask before calling it)
     * @param bool $public true when anyone may call it without signing in or a token
     * @param bool $mcp false keeps it out of the MCP tools (one only a browser can use, such as a push subscription)
     * @param bool $webOnly true (2.21.0): only Route::action() reaches it: it may read the session and sign people in
     * @param bool $async true (2.23.0): Snap cards run it in the background as a task; REST, MCP, pages and call run it directly
     * @param int $cache seconds (2.26.0): a GET of it over REST by a visitor with no session cookie and no token may be
     *                   kept by shared caches (the production image's own cache, a CDN) for that long; needs readOnly and
     *                   public, and an answer that is the same for every visitor
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public string $scope = '',
        public int $level = 0,
        public bool $readOnly = false,
        public bool $destructive = false,
        public bool $public = false,
        public bool $mcp = true,
        public bool $webOnly = false,
        public bool $async = false,
        public int $cache = 0
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name)) {
            throw new \InvalidArgumentException("Capability name '$name' must be snake_case: letters, digits and _, starting with a letter");
        }
        if ($cache < 0 || $cache > HttpCache::MAX) {
            throw new \InvalidArgumentException("Capability '$name': cache must be between 0 and " . HttpCache::MAX . " seconds");
        }
        if ($cache > 0 && (!$readOnly || !$public || $webOnly)) {
            throw new \InvalidArgumentException("Capability '$name': cache needs readOnly: true and public: true (one answer for every visitor), and no webOnly");
        }
    }
}
