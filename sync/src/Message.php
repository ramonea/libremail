<?php

namespace App;

use Fn;
use Exception;
use Monolog\Logger;
use App\Server\StatsServer;
use App\Message\PidMessage;
use App\Message\TaskMessage;
use App\Message\ErrorMessage;
use App\Message\StatsMessage;
use App\Message\HealthMessage;
use App\Message\AccountMessage;
use App\Message\AbstractMessage;
use App\Message\NoAccountsMessage;
use App\Message\DiagnosticsMessage;
use App\Message\NotificationMessage;
use App\Exceptions\Validation as ValidationException;

class Message
{
    // Valid messages
    const PID = 'pid';
    const TASK = 'task';
    const STATS = 'stats';
    const ERROR = 'error';
    const HEALTH = 'health';
    const ACCOUNT = 'account';
    const NO_ACCOUNTS = 'no_accounts';
    const DIAGNOSTICS = 'diagnostics';
    const ACCOUNT_INFO = 'account_info';
    const NOTIFICATION = 'notification';

    // Injected during service registration
    protected static $log;

    private static $validTypes = [
        self::PID,
        self::TASK,
        self::STATS,
        self::ERROR,
        self::HEALTH,
        self::ACCOUNT,
        self::NO_ACCOUNTS,
        self::DIAGNOSTICS,
        self::ACCOUNT_INFO,
        self::NOTIFICATION
    ];

    public static function setLog(Logger $log)
    {
        static::$log = $log;
    }

    /**
     * Determines if a message is valid. This just means it's a JSON
     * string, that it has a "type" property, and that the type is one
     * of the internal message constants.
     *
     * @param string $json
     *
     * @return bool
     */
    public static function isValid($json)
    {
        $message = @json_decode($json);

        if (! $message
            || ! is_object($message)
            || ! isset($message->type)
            || ! in_array($message->type, self::$validTypes))
        {
            return false;
        }

        if (@json_encode($message) !== $json) {
            return false;
        }

        return true;
    }

    /**
     * If a server is provided, broadcast the message directly.
     */
    public static function send(AbstractMessage $message, StatsServer $server = null)
    {
        if ($server) {
            $server->broadcast(json_encode($message->toArray()));
        } else {
            self::writeJson($message->toArray());
        }
    }

    public static function writeJson($json)
    {
        fwrite(STDOUT, self::packJson($json));
        flush();
    }

    public static function packJson($json)
    {
        $encoded = json_encode($json);

        return sprintf(
            '%s%s%s',
            JSON_HEADER_CHAR,
            pack('i', strlen($encoded)),
            $encoded);
    }

    /**
     * Takes in a JSON object (should be validated first!) and
     * creates a new Message based off the type.
     *
     * @param string $json
     *
     * @throws Exception
     *
     * @return AbstractMessage
     */
    public static function make($json)
    {
        if (! self::isValid($json)) {
            $error = 'Invalid message object passed to Message::make';
            self::$log->error($error);

            throw new Exception($error);
        }

        $m = @json_decode($json);

        // Check that message contains the required fields.
        try {
            Fn\expects($m)->toHave(['type']);

            switch ($m->type) {
                case self::PID:
                    Fn\expects($m)->toHave(['pid']);

                    return new PidMessage($m->pid);
                case self::TASK:
                    Fn\expects($m)->toHave(['task', 'data']);

                    return new TaskMessage($m->task, $m->data);
                case self::STATS:
                    Fn\expects($m)->toHave([
                        'active', 'asleep', 'account', 'running',
                        'uptime', 'accounts'
                    ]);

                    return new StatsMessage(
                        $m->active,
                        $m->asleep,
                        $m->account,
                        $m->running,
                        $m->uptime,
                        $m->accounts);
                case self::ERROR:
                    Fn\expects($m)->toHave([
                        'error_type', 'message', 'suggestion'
                    ]);

                    return new ErrorMessage(
                        $m->error_type,
                        $m->message,
                        $m->suggestion);
                case self::HEALTH:
                    Fn\expects($m)->toHave([
                        'tests', 'procs', 'no_accounts'
                    ]);

                    return new HealthMessage(
                        $m->tests,
                        $m->procs,
                        $m->no_accounts);
                case self::ACCOUNT:
                    Fn\expects($m)->toHave(['updated', 'email']);

                    return new AccountMessage($m->updated, $m->email);
                case self::NO_ACCOUNTS:
                    return new NoAccountsMessage;
                case self::ACCOUNT_INFO:
                    Fn\expects($m)->toHave(['account']);

                    return new AccountInfoMessage($m->account);
                case self::NOTIFICATION:
                    Fn\expects($m)->toHave(['status', 'message']);

                    return new NotificationMessage($m->status, $m->message);
                case self::DIAGNOSTICS:
                    Fn\expects($m)->toHave(['tests']);

                    return new DiagnosticsMessage($m->tests);
            }
        }
        catch (ValidationException $e) {
            self::$log->error($e->getMessage());

            throw new Exception($e->getMessage());
        }

        $error = 'Invalid message type passed to Message::make';
        self::$log->error($error);

        throw new Exception($error);
    }

    /**
     * Generic helper for writing to the server log file.
     *
     * @param string $message
     */
    public static function debug($message)
    {
        self::$log->debug($message);
    }
}
