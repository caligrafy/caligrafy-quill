<?php
/**
 * Context.php
 * Who is calling a capability, through which channel, with which rights
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Context
 * Built by the channel adapters (web pages, REST, MCP, CLI) and read by the services: a service never looks at the
 * request, the session or the headers itself.
 *
 * The actor is one of:
 * - user: a signed-in user (session), or the owner of a token
 * - token: a personal access token (Tokens), with its scopes
 * - key: the legacy API key (every generated key is the same key: full access, as before 2.1)
 * - cli: a script or the command line (full access)
 * - guest: nobody: only public capabilities
 */
class Context
{
    const CHANNELS = array('web', 'rest', 'mcp', 'cli');

    /**
     * Name of the users column that holds the permission level compared with a capability's level (Auth::guard)
     */
    public static $levelAttribute = 'permissions';

    /** @var string web, rest, mcp or cli */
    public $channel;

    /** @var object|null the user model (signed in, or the owner of the token) */
    public $user;

    /** @var array|null the token row (id, name, scopes, user_id...) */
    public $token;

    /** @var bool true for the CLI and the legacy API key */
    protected $full = false;

    /** @var string|null the actor written to the audit log for a full-access context (cli) */
    protected $actor = null;

    public function __construct($channel = 'cli', $user = null, $token = null, $full = false)
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            throw new \InvalidArgumentException("Unknown channel '$channel'");
        }
        $this->channel = $channel;
        $this->user = $user;
        $this->token = $token;
        $this->full = (bool) $full;
    }

    /**
     * The context of the command line and of scripts: full access
     */
    public static function cli()
    {
        return new self('cli', null, null, true);
    }

    /**
     * Full access through another channel for a local process (php caligrafer.php mcp without a token)
     */
    public static function local($channel)
    {
        $context = new self($channel, null, null, true);
        $context->actor = 'cli';
        return $context;
    }

    /**
     * The context of nobody: only public capabilities
     */
    public static function guest($channel = 'rest')
    {
        return new self($channel);
    }

    /**
     * The context of a personal access token: its scopes, and its owner when it has one and authentication is on
     */
    public static function forToken(array $token, $channel = 'rest')
    {
        $user = null;
        if (!empty($token['user_id']) && Auth::active()) {
            $user = Auth::find($token['user_id']);
        }
        return new self($channel, $user, $token);
    }

    /**
     * The context of the current HTTP request: a token (Authorization: Bearer cq_...), else the legacy API key, else the
     * signed-in user, else a guest
     * @param Request $request the request
     * @param string $channel web, rest or mcp
     */
    public static function fromRequest(Request $request, $channel = 'rest')
    {
        $bearer = $request->bearer();
        if ($bearer !== null && Tokens::looksLikeToken($bearer)) {
            $token = $request->token ?? self::verifiedToken($bearer);
            return $token ? self::forToken($token, $channel) : self::guest($channel);
        }
        if ($bearer !== null && $request->isLegacyApiKey($bearer)) {
            return new self($channel, null, null, true);
        }
        if (Auth::active() && Auth::authorized()) {
            return new self($channel, Auth::user());
        }
        return self::guest($channel);
    }

    private static function verifiedToken($bearer)
    {
        try {
            return Tokens::verify($bearer);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Whether someone is acting (anything but a guest)
     */
    public function authenticated()
    {
        return $this->full || $this->user !== null || $this->token !== null;
    }

    /**
     * Whether this context may use a capability that needs a scope and a minimum user level
     * - the CLI and the legacy key: always
     * - a token: it must grant the scope, and its owner (if any) must have the level
     * - a signed-in user: the level (users have every scope their level allows)
     * @param string $scope e.g. books:write ('' = no scope needed)
     * @param int $level minimum value of the users permission column (0 = any signed-in user)
     */
    public function allows($scope, $level = 0)
    {
        if ($this->full) {
            return true;
        }
        if ($this->token !== null) {
            if (!Tokens::allows($this->token['scopes'] ?? array(), $scope)) {
                return false;
            }
            return $this->user === null ? (int) $level === 0 || empty($this->token['user_id']) : $this->level() >= (int) $level;
        }
        if ($this->user !== null) {
            return $this->level() >= (int) $level;
        }
        return false;
    }

    /**
     * The permission level of the user (0 when there is none)
     */
    public function level()
    {
        $attribute = self::$levelAttribute;
        return $this->user !== null ? (int) ($this->user->$attribute ?? 0) : 0;
    }

    /**
     * The user id, when a user acts (signed in or owner of the token)
     */
    public function userId()
    {
        return $this->user !== null ? ($this->user->id ?? null) : ($this->token['user_id'] ?? null);
    }

    /**
     * user, token, key, cli or guest (as written to the audit log)
     */
    public function actorType()
    {
        if ($this->token !== null) {
            return 'token';
        }
        if ($this->user !== null) {
            return 'user';
        }
        if ($this->full) {
            return $this->actor ?? ($this->channel === 'cli' ? 'cli' : 'key');
        }
        return 'guest';
    }

    /**
     * The token id or the user id, as a string (null for the CLI, the legacy key and guests)
     */
    public function actorId()
    {
        if ($this->token !== null) {
            return (string) $this->token['id'];
        }
        $id = $this->userId();
        return $id === null ? null : (string) $id;
    }
}
