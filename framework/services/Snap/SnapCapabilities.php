<?php
/**
 * The Snap capabilities of every Caligrafy project (2.23.0): what the Snap shell (Route::snap()) and its pages call.
 * Cards (snap_card, snap_catalog, snap_open, snap_run), the assistant (snap_ask, snap_home, snap_conversation,
 * snap_history, snap_rate), shortcuts, tasks (work in the background), and what the assistant noticed (offers,
 * routines). The engine is framework/core/Snap; the tables are caligrafy_snap_* (php caligrafer.php platform install).
 * None is an MCP tool: agents use the app's own capabilities; these serve the shell of a person.
 * Each acts as the person (their permissions); a change only ever runs from its Confirm (Caligrafy\Snap\Consent).
 */

use Caligrafy\Capabilities;
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;
use Caligrafy\Web;
use Caligrafy\Snap\Cards;
use Caligrafy\Snap\Consent;
use Caligrafy\Snap\Conversation;
use Caligrafy\Snap\Flow;
use Caligrafy\Snap\Input;
use Caligrafy\Snap\Offers;
use Caligrafy\Snap\Planner;
use Caligrafy\Snap\Say;
use Caligrafy\Snap\Shell;
use Caligrafy\Snap\Shortcuts;
use Caligrafy\Snap\Tasks;


// snap_card: the Ask card (Form or Choice) of a capability, built from its rules; agents and programs get it too
#[Capability(
    name: 'snap_card',
    description: 'Returns the Snap Card that asks for the inputs of a capability this caller may use: an Ask: Form (fields with their type, limits, choices and help) or an Ask: Choice when one pick is all that is missing. values prefills it (an object, or its JSON).',
    readOnly: true,
    public: true,
    mcp: false
)]
class CaligrafySnapCard extends Service
{
    public $rules = array('capability' => 'required|max_len,64', 'values' => '', 'as' => 'contains_list,form');
    public $descriptions = array('capability' => 'The name of the capability, for example create_book', 'values' => 'Optional: inputs already known, as an object or its JSON');

    public function handle(array $input, Context $context)
    {
        return Say::on(Cards::ask($input['capability'], $context, Input::decode($input['values'] ?? array()), array(), null, ($input['as'] ?? '') === 'form'));
    }
}


// snap_catalog: one card per capability this caller may use: what it shows (Show) or what it asks for (Ask)
#[Capability(
    name: 'snap_catalog',
    description: 'Lists one Snap Card per capability this caller may use: a Show card (List or Answer) for what can be read without inputs, an Ask card (Form or Choice) for the rest.',
    readOnly: true,
    public: true,
    mcp: false
)]
class CaligrafySnapCatalog extends Service
{
    public function handle(array $input, Context $context)
    {
        return Cards::catalog($context);
    }
}


// snap_open: the card at an address (/snap/c/<capability>?in=<inputs>): a read runs and shows, anything else asks
#[Capability(
    name: 'snap_open',
    description: 'The Snap Card at an address: a read-only capability with its inputs complete runs and returns its Show card; anything else returns its Ask card, prefilled. A card at an address never changes data.',
    readOnly: true,
    public: true,
    mcp: false
)]
class CaligrafySnapOpen extends Service
{
    public $rules = array('capability' => 'required|max_len,64', 'in' => 'max_len,4000');

    public function handle(array $input, Context $context)
    {
        $name = $input['capability'];
        $values = Input::decode($input['in'] ?? '');
        $ask = Cards::ask($name, $context, $values);
        $complete = true;
        foreach ((array) ($ask['fields'] ?? array(array('required' => true, 'name' => $ask['field'] ?? ''))) as $field) {
            if ($field['required'] && ($values[$field['name']] ?? '') === '') { $complete = false; }
        }
        if ($ask['risk'] !== 'read' || !$complete) {
            return $ask;   // a link never changes data: a write always comes back as its card first
        }
        try {
            return Cards::show($name, Capabilities::call($name, $values, $context), $values, $context);
        } catch (ServiceError $e) {
            return $e->kind === 'invalid' ? Cards::ask($name, $context, $values, $e->fields, $e->getMessage()) : Cards::notice($name, $e->getMessage(), $e->kind);
        }
    }
}


// snap_run: a card was sent: answer with the next card (Snap\Flow). A change runs only here, with the token of the
// person's Ask: Confirm; then it answers with what changed and what undoes it.
#[Capability(
    name: 'snap_run',
    description: 'Sends a Snap Card as the signed-in person and answers with the next card: the Ask card while an input is missing or refused, an Ask: Confirm before any change, Show (List, Answer) once it ran.',
    webOnly: true,
    public: true,
    mcp: false
)]
class CaligrafySnapRun extends Service
{
    // the next card with the assistant's line, and what the person may want to do after it (the task space's suggestions)
    private static function next(array $card, Context $context)
    {
        $card = Shortcuts::mark(Say::on($card), $context);
        $card['next'] = Conversation::suggestions(array($card), $context);
        return $card;
    }

    public $rules = array('capability' => 'required|max_len,64', 'inputs' => '', 'confirm' => 'max_len,64');

    public function handle(array $input, Context $context)
    {
        $name = $input['capability'];
        $inputs = Input::decode($input['inputs'] ?? array());
        $token = (string) ($input['confirm'] ?? '');
        if ($token === '') {
            [$card, $status] = Flow::next($name, $inputs, $context);
            return Web::json(self::next($card, $context), $status);
        }
        // a confirmed change: the token must match this person, this capability and these exact inputs, once
        $kept = Consent::take($token, $name, $context, $inputs);
        if ($kept === null) {
            [$card] = Flow::next($name, $inputs, $context);
            if ($card['type'] === 'confirm') {
                $card['message'] = 'That confirmation expired or no longer matches: please confirm again.';
            }
            return Web::json(self::next($card, $context), 409);
        }
        Offers::record($context, $name, $inputs);   // a confirmed change (only if noticing is on)
        if (Tasks::async($name)) {
            return Web::json(self::next(Tasks::start($name, $inputs, $context), $context), 202);   // confirmed: it runs in the background
        }
        try {
            $result = $this->capability($name, $inputs, $context);
            Cards::forget();   // what changed may be in a choice
        } catch (ServiceError $e) {
            [$card, $status] = Flow::refused($name, $context, $inputs, $e);
            return Web::json(self::next($card, $context), $status);
        }
        if (Cards::risk(Capabilities::available($context)[$name]['capability']) === 'destroy') {
            // what is gone is told by its name, kept from before the change
            $before = $kept['before'] ?? array();
            $gone = $before['title'] ?? $before['name'] ?? $before['username'] ?? null;
            $card = Cards::notice($name, $gone !== null ? 'Deleted "' . $gone . '".' : 'Deleted.', 'info');
            $card['undo'] = null;
            return Web::json(self::next($card, $context));
        }
        $card = Cards::show($name, $result, $inputs, $context);
        $card['done'] = 'Done.';
        $card['undo'] = Consent::undo($name, $inputs, Capabilities::plain($result), $kept, $context);
        if ($card['type'] === 'answer' && $card['variant'] === 'fact' && $card['heading'] === 'Done') {
            $card['heading'] = $card['done'];
        }
        return Web::json(self::next($card, $context));
    }
}


// snap_ask: what the person typed or said in the Snap shell: the fast path first (no model), then the planner (Claude).
// Each answer is a turn of a conversation (kept), with its prompt suggestions. again: the id of a turn to answer differently.
// open: the key of a Home card, shown in the task space. run: a suggestion's card ({capability, input}), at once.
#[Capability(
    name: 'snap_ask',
    description: 'Answers what the person typed or said in the Snap shell with Snap Cards and prompt suggestions, as a turn of a conversation: the menu, a shortcut or a card by its name at once; anything else through the planner when it is on. Also opens a Home card (open) or runs a suggestion (run).',
    webOnly: true,
    public: true,
    mcp: false
)]
class CaligrafySnapAsk extends Service
{
    public $rules = array('text' => 'max_len,500', 'conversation' => 'alpha_numeric|exact_len,16', 'again' => 'integer', 'open' => 'max_len,80', 'run' => '', 'files' => '', 'context' => '');
    public $filters = array('text' => 'trim');

    public function handle(array $input, Context $context)
    {
        $text = (string) ($input['text'] ?? '');
        $conversation = (string) ($input['conversation'] ?? '');
        $instead = null;
        if (!empty($input['again'])) {
            $previous = Conversation::last($context, (int) $input['again']);
            $text = $previous['text'];
            $conversation = $previous['conversation'];
            $instead = trim($previous['caption'] . ' Cards: ' . implode(', ', array_map(function ($c) { return ($c['title'] ?? '') . ' (' . ($c['type'] ?? '') . ')'; }, $previous['cards'])));
        }
        $answer = null;
        if (!empty($input['open'])) {
            $answer = Conversation::open($context, (string) $input['open']);
            $text = $answer['text'];
        } elseif (!empty($input['run'])) {
            $run = is_string($input['run']) ? (json_decode($input['run'], true) ?: array()) : (array) $input['run'];
            $name = (string) ($run['capability'] ?? '');
            if ($name === '' || preg_match(Cards::HIDDEN, $name)) {
                throw ServiceError::invalid(array('run' => 'Not a card you can run'), 'Not a card you can run');
            }
            [$card] = Flow::next($name, Input::decode($run['input'] ?? array()), $context);
            $answer = array('caption' => '', 'cards' => array($card), 'via' => 'fast');
        }
        // what came before: the earlier turns, the screen, the person's selections, what it is about
        $memory = $instead === null ? Conversation::memory($context, $conversation, $input['context'] ?? array()) : array('focus' => null, 'text' => '');
        $files = empty($input['files']) ? array() : Planner::files($input['files']);
        if ($files) {
            $text = $text !== '' ? $text : 'What can you do with ' . (count($files) > 1 ? 'these' : 'this') . '?';
            $answer = Planner::on() ? Planner::plan($text, $context, null, $files, $memory['text'])
                : array('caption' => 'I need the assistant\'s model to read files, and it is not set up here. Here is what you can do.',
                    'cards' => array(Shell::menuCard($context)), 'via' => 'none');
            $text .= ' · ' . implode(', ', array_column($files, 'name'));   // the history says what was attached, never its content
        }
        if ($answer === null && $instead === null) {
            $answer = Shell::onFocus($text, $context, $memory['focus']) ?? Shell::fast($text, $context);
        }
        if ($answer === null) {
            $answer = Planner::on() ? Planner::plan($text, $context, $instead, array(), $memory['text'])
                : array('caption' => 'I only know the names of the cards and your shortcuts for now (the assistant has no model key). Here is everything you can do.',
                    'cards' => array(Shell::menuCard($context)), 'via' => 'none');
        }
        $answer['suggestions'] = Conversation::suggestions($answer['cards'] ?? array(), $context);
        if ($context->userId()) {
            if ($conversation === '') {
                $conversation = bin2hex(random_bytes(8));
            }
            $answer['turn'] = Conversation::save($context, $conversation, $text, $answer);
            $answer['conversation'] = $conversation;
        }
        $answer['text'] = $text;
        return Web::json($answer);
    }
}


// snap_home: the Snap shell's Home for this person now: its message, prompt suggestions and card sections
#[Capability(name: 'snap_home', description: 'Your Snap Home now: a message for the situation, prompt suggestions, and the card sections that have something (Needs you, For you, In progress, Just done, Your shortcuts).', readOnly: true, mcp: false)]
class CaligrafySnapHome extends Service
{
    public function handle(array $input, Context $context)
    {
        return Conversation::home($context);
    }
}


// snap_conversation: the turns of one of your conversations, oldest first
#[Capability(name: 'snap_conversation', description: 'The turns of one of your Snap conversations, oldest first: what you asked, the answer, its cards and suggestions.', readOnly: true, mcp: false)]
class CaligrafySnapConversation extends Service
{
    public $rules = array('conversation' => 'required|alpha_numeric|exact_len,16');

    public function handle(array $input, Context $context)
    {
        return Conversation::turns($context, $input['conversation']);
    }
}


// snap_history: your Snap conversations, newest first
#[Capability(name: 'snap_history', description: 'Your Snap conversations, newest first, each titled by its first question.', readOnly: true, mcp: false)]
class CaligrafySnapHistory extends Service
{
    public function handle(array $input, Context $context)
    {
        return Conversation::history($context);
    }
}


// snap_rate: was this answer helpful (1) or not (-1)
#[Capability(name: 'snap_rate', description: 'Rates one answer of the Snap assistant: 1 helpful, -1 not helpful.', mcp: false)]
class CaligrafySnapRate extends Service
{
    public $rules = array('turn' => 'required|integer', 'rating' => 'required|contains_list,1;-1');

    public function handle(array $input, Context $context)
    {
        return Conversation::rate($context, (int) $input['turn'], (int) $input['rating']);
    }
}


// snap_shortcuts: the person's Go: Shortcut cards
#[Capability(name: 'snap_shortcuts', description: 'Lists your Snap shortcuts as Go: Shortcut cards: a capability with the inputs you saved.', readOnly: true, mcp: false)]
class CaligrafySnapShortcuts extends Service
{
    public function handle(array $input, Context $context)
    {
        return Shortcuts::of($context);
    }
}


// snap_shortcut_save: keeps a card as a Go: Shortcut (a personal preference: it changes nothing else)
#[Capability(name: 'snap_shortcut_save', description: 'Saves a Snap Card (a capability you may use, with its inputs) as one of your shortcuts, under a title.', mcp: false)]
class CaligrafySnapShortcutSave extends Service
{
    public $rules = array('title' => 'required|max_len,80', 'capability' => 'required|max_len,64', 'inputs' => '');
    public $filters = array('title' => 'trim');

    public function handle(array $input, Context $context)
    {
        return Shortcuts::save($context, $input['title'], $input['capability'], Input::decode($input['inputs'] ?? array()));
    }
}


// snap_shortcut_delete: removes one of your shortcuts
#[Capability(name: 'snap_shortcut_delete', description: 'Deletes one of your Snap shortcuts, by its id.', destructive: true, mcp: false)]
class CaligrafySnapShortcutDelete extends Service
{
    public $rules = array('id' => 'required|integer');

    public function handle(array $input, Context $context)
    {
        return Shortcuts::delete($context, (int) $input['id']);
    }
}


// snap_task: one of your tasks, as its Track: Task card
#[Capability(name: 'snap_task', description: 'One of your Snap tasks (work running in the background) as its Track: Task card: where it is, and the card it ended with.', readOnly: true, mcp: false)]
class CaligrafySnapTask extends Service
{
    public $rules = array('id' => 'required|integer');

    public function handle(array $input, Context $context)
    {
        $card = Say::on(Tasks::get((int) $input['id'], $context));
        $card['next'] = Conversation::suggestions(array($card), $context);   // what the person may do once it is done
        return $card;
    }
}


// snap_tasks: your latest tasks, as Track: Task cards
#[Capability(name: 'snap_tasks', description: 'Your latest Snap tasks, newest first, as Track: Task cards.', readOnly: true, mcp: false)]
class CaligrafySnapTasks extends Service
{
    public function handle(array $input, Context $context)
    {
        return Tasks::of($context);
    }
}


// snap_task_run: the queue's side of a Track: Task (Queue::push from Tasks::start); runs it as its owner
#[Capability(name: 'snap_task_run', description: 'Runs one Snap task in the background, as the person who asked for it.', scope: 'caligrafy:queue', level: 9, mcp: false)]
class CaligrafySnapTaskRun extends Service
{
    public $rules = array('task' => 'required|integer');

    public function handle(array $input, Context $context)
    {
        return Tasks::run((int) $input['task']);
    }
}


// snap_offers: what the assistant offers you now (Offer: Suggestion, Offer: Routine), at most two, each with its reason
#[Capability(name: 'snap_offers', description: 'Your Snap offers now: a Suggestion when it is the moment you usually do something, a Routine to do it for you on its rhythm. Only if you turned noticing on.', readOnly: true, mcp: false)]
class CaligrafySnapOffers extends Service
{
    public function handle(array $input, Context $context)
    {
        return Offers::offers($context);
    }
}


// snap_noticed: everything the assistant noticed about you, and your routines: nothing hidden
#[Capability(name: 'snap_noticed', description: 'What the Snap assistant noticed about you (whether noticing is on, each pattern with its reason) and your routines.', readOnly: true, mcp: false)]
class CaligrafySnapNoticed extends Service
{
    public function handle(array $input, Context $context)
    {
        $routines = array_map(function ($r) { return $r + array('rhythm' => Offers::rhythmText($r)); }, Offers::routines($context));
        return array('noticing' => Offers::noticing($context), 'patterns' => Offers::patterns($context), 'routines' => $routines);
    }
}


// snap_noticing_set: turns noticing on or off (off forgets everything noticed)
#[Capability(name: 'snap_noticing_set', description: 'Turns on or off whether the Snap assistant notices what you do again and again. Turning it off forgets what it noticed.', mcp: false)]
class CaligrafySnapNoticingSet extends Service
{
    public $rules = array('on' => 'required|boolean');

    public function handle(array $input, Context $context)
    {
        return Offers::setNoticing($context, filter_var($input['on'], FILTER_VALIDATE_BOOLEAN));
    }
}


// snap_forget: forgets one pattern the assistant noticed, or all of them
#[Capability(name: 'snap_forget', description: 'Forgets one pattern the Snap assistant noticed (by its fingerprint), or everything it noticed.', destructive: true, mcp: false)]
class CaligrafySnapForget extends Service
{
    public $rules = array('fingerprint' => 'alpha_numeric|exact_len,40');

    public function handle(array $input, Context $context)
    {
        return Offers::forget($context, ($input['fingerprint'] ?? '') === '' ? null : $input['fingerprint']);
    }
}


// snap_dismiss: Not now (until tomorrow) or Don't suggest this again, for one offer
#[Capability(name: 'snap_dismiss', description: 'Dismisses one Snap offer: today (Not now) or never (Do not suggest this again).', mcp: false)]
class CaligrafySnapDismiss extends Service
{
    public $rules = array('fingerprint' => 'required|alpha_numeric|exact_len,40', 'kind' => 'required|contains_list,today;never');

    public function handle(array $input, Context $context)
    {
        return Offers::dismiss($context, $input['fingerprint'], $input['kind']);
    }
}


// snap_routine_save: keeps a routine: a request done for you on a rhythm. Sent through a card, it asks for its Confirm.
#[Capability(name: 'snap_routine_save', description: 'Sets up a Snap routine: a capability with its inputs, done for you every week on a weekday (or every day) around an hour, at a level: suggest, confirm or auto.', mcp: false)]
class CaligrafySnapRoutineSave extends Service
{
    public $rules = array(
        'capability' => 'required|max_len,64',
        'inputs' => 'max_len,2000',
        'weekday' => 'integer|min_numeric,1|max_numeric,7',
        'hour' => 'required|integer|min_numeric,0|max_numeric,23',
        'level' => 'required|contains_list,suggest;confirm;auto',
    );
    public $descriptions = array('capability' => 'What to do', 'inputs' => 'Its inputs, as JSON', 'weekday' => 'Optional: 1 Monday to 7 Sunday; every day without it',
        'hour' => 'Around which hour, 0 to 23', 'level' => 'suggest (remind me), confirm (prepare it for me to confirm) or auto (do it and tell me)');

    public function handle(array $input, Context $context)
    {
        return Offers::saveRoutine($context, $input['capability'], Input::decode($input['inputs'] ?? ''), $input['weekday'] ?? null, (int) $input['hour'], $input['level']);
    }
}


// snap_routine_delete: stops one of your routines
#[Capability(name: 'snap_routine_delete', description: 'Stops one of your Snap routines, by its id.', destructive: true, mcp: false)]
class CaligrafySnapRoutineDelete extends Service
{
    public $rules = array('id' => 'required|integer');

    public function handle(array $input, Context $context)
    {
        return Offers::deleteRoutine($context, (int) $input['id']);
    }
}


// snap_routines_due: the scheduler's side of routines at the auto level: each due one runs as a task, as its owner
#[Capability(name: 'snap_routines_due', description: 'Runs the Snap routines at the auto level that are due, each as a task of its owner.', scope: 'caligrafy:queue', level: 9, mcp: false)]
class CaligrafySnapRoutinesDue extends Service
{
    public function handle(array $input, Context $context)
    {
        return Offers::runDue();
    }
}
