<?php
/**
 * Track: Task cards (2.23.0): work that runs in the background for one person.
 *
 * A capability declared #[Capability(async: true)] (or with the hint 'async' in application/config/snap.php) does not run
 * in the request: a read starts as a task at once, a change after its Ask: Confirm. The task is a row of snap_tasks and
 * a job of Caligrafy's queue (snap_task_run). The worker runs the capability AS THE PERSON who asked (their user, the
 * web channel: their permissions, not the queue's full access), keeps the card of what came out, and notifies them:
 *   done        -> the Show card of the result
 *   needs_input -> the Ask card of what the capability refused (the person answers it, a new task starts)
 *   failed      -> a notice, after the queue's retries
 * The Track card shows where it is: queued, running, then the card it ended with.
 */

namespace Caligrafy\Snap;

use Caligrafy\Capabilities;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Notify;
use Caligrafy\Queue;
use Caligrafy\ServiceError;

class Tasks
{
    const TABLE = 'caligrafy_snap_tasks';
    const LABELS = array('queued' => 'Waiting to start', 'running' => 'Working on it', 'needs_input' => 'Needs your answer',
        'done' => 'Done', 'failed' => 'Could not finish');

    public static function async($name)
    {
        // #[Capability(async: true)] (2.23.0), or the hint 'async' of application/config/snap.php
        $entry = Capabilities::get($name);
        return !empty(Hints::for($name)['async']) || (!empty($entry['capability']) && !empty($entry['capability']->async));
    }

    /**
     * Starts a task for this person: the row, then its job. Returns its Track card.
     */
    public static function start($name, array $inputs, Context $context)
    {
        if (!$context->userId()) {
            throw ServiceError::unauthenticated();
        }
        $id = Database::table(self::TABLE)->insert(array('user_id' => $context->userId(), 'capability' => $name,
            'inputs' => json_encode($inputs, JSON_UNESCAPED_UNICODE), 'status' => 'queued'));
        $job = Queue::push('snap_task_run', array('task' => (int) $id), array('attempts' => 3, 'timeout' => 120));
        Database::table(self::TABLE)->where('id', $id)->update(array('job_id' => $job));
        return self::card(self::row($id), $context);
    }

    /**
     * The worker (snap_task_run, in the queue): runs the task as its owner, keeps the card it ends with, notifies
     */
    public static function run($id)
    {
        $task = self::row($id);
        if (!$task || in_array($task['status'], array('done', 'needs_input', 'failed'), true)) {
            return array('task' => (int) $id, 'status' => $task['status'] ?? 'missing');
        }
        $owner = self::owner((int) $task['user_id']);
        if ($owner === null) {
            return self::finish($task, 'failed', Cards::notice($task['capability'], 'The person who asked for this no longer exists.', 'error'), null);
        }
        Database::table(self::TABLE)->where('id', $id)->update(array('status' => 'running', 'started_at' => date('Y-m-d H:i:s')));
        $inputs = json_decode((string) $task['inputs'], true) ?: array();
        try {
            $result = Capabilities::call($task['capability'], $inputs, $owner);
            Cards::forget();
            $card = Cards::show($task['capability'], $result, $inputs, $owner);
            return self::finish($task, 'done', $card, $owner);
        } catch (ServiceError $e) {
            if ($e->kind === 'invalid') {
                return self::finish($task, 'needs_input', Cards::ask($task['capability'], $owner, $inputs, $e->fields, $e->getMessage()), $owner);
            }
            if (in_array($e->kind, array('forbidden', 'unauthenticated', 'not_found', 'conflict'), true)) {
                return self::finish($task, 'failed', Cards::notice($task['capability'], $e->getMessage(), $e->kind), $owner);
            }
            throw $e;
        } catch (\Throwable $e) {
            // the queue tries again; on the last attempt the task is marked failed
            Database::table(self::TABLE)->where('id', $id)->update(array('status' => 'queued'));
            throw $e;
        }
    }

    /**
     * One task's Track card, for its owner only
     */
    public static function get($id, Context $context)
    {
        $task = self::row($id);
        if (!$task || (int) $task['user_id'] !== (int) $context->userId()) {
            throw ServiceError::notFound('No such task');
        }
        return self::card($task, $context);
    }

    /**
     * This person's latest tasks, newest first ("Coming up" and what just finished)
     */
    public static function of(Context $context, $limit = 6)
    {
        if (!$context->userId()) {
            return array();
        }
        $cards = array();
        foreach (Database::table(self::TABLE)->where('user_id', $context->userId())->orderBy('id', 'desc')->limit($limit)->get() as $row) {
            $cards[] = self::card((array) $row, $context);
        }
        return $cards;
    }

    // ---- inside ----

    private static function finish(array $task, $status, array $card, $owner)
    {
        Database::table(self::TABLE)->where('id', $task['id'])->update(array('status' => $status, 'finished_at' => date('Y-m-d H:i:s'),
            'card' => json_encode($card, JSON_UNESCAPED_UNICODE)));
        if ($owner !== null) {
            // the follow-up: every channel the person turned on (push, email); in-page, the Track card refreshes itself
            Notify::user((int) $task['user_id'], array(
                'title' => self::LABELS[$status] . ': ' . Cards::title($task['capability']),
                'body' => (string) ($card['glance'] ?? $card['heading'] ?? ''),
                'url' => Cards::url('snap_task', array('id' => (int) $task['id'])),
                'tag' => 'snap-task-' . $task['id'],
            ));
        }
        return array('task' => (int) $task['id'], 'status' => $status);
    }

    private static function card(array $task, Context $context)
    {
        $status = $task['status'];
        $steps = array(
            array('label' => 'Asked', 'at' => $task['created_at'], 'done' => true),
            array('label' => 'Started', 'at' => $task['started_at'], 'done' => $task['started_at'] !== null),
            array('label' => self::LABELS[in_array($status, array('done', 'needs_input', 'failed'), true) ? $status : 'done'], 'at' => $task['finished_at'], 'done' => $task['finished_at'] !== null),
        );
        $result = $task['card'] ? json_decode($task['card'], true) : null;
        return array('snap' => Cards::VERSION, 'family' => 'track', 'type' => 'task', 'capability' => 'snap_task', 'id' => (int) $task['id'],
            'of' => $task['capability'], 'icon' => Cards::icon($task['capability']), 'title' => Cards::title($task['capability']), 'lead' => null, 'input' => (object) array('id' => (int) $task['id']),
            'status' => $status, 'statusLabel' => self::LABELS[$status], 'steps' => $steps, 'result' => $result,
            'working' => (array) (Hints::for($task['capability'])['working'] ?? array()),   // what it says it is doing, in turn
            'ago' => self::ago($task['finished_at'] ?? $task['created_at']),
            'live' => in_array($status, array('queued', 'running'), true),
            'glance' => self::LABELS[$status] . ($result && $status === 'done' ? ' · ' . ($result['glance'] ?? '') : ''),
            'url' => Cards::url('snap_task', array('id' => (int) $task['id'])), 'full' => null);
    }

    // "2 min ago": the times of tasks are in the app's time zone (the web, the queue worker and MySQL agree here)
    private static function ago($at)
    {
        if (!$at) {
            return '';
        }
        $seconds = time() - strtotime($at);
        if ($seconds < 60) return 'just now';
        if ($seconds < 3600) return floor($seconds / 60) . ' min ago';
        if ($seconds < 86400) return floor($seconds / 3600) . ' h ago';
        return floor($seconds / 86400) . ' d ago';
    }

    private static function row($id)
    {
        $row = Database::table(self::TABLE)->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    // the person who asked, as the request would know them: their permissions, the web channel
    private static function owner($userId)
    {
        $user = Database::table('users')->where('id', $userId)->first();
        return $user ? new Context('web', (object) (array) $user) : null;
    }
}
