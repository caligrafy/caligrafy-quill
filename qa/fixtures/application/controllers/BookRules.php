<?php
// QA fixture: rules kept next to the controllers and reused by a service (as the course's Bookshelf\BookForm).
// The CLI must load it before it reads CreateBook's $rules (php caligrafer.php capabilities, client, mcp).

class BookRules
{
    const TITLE = 'required|max_len,255';
}
