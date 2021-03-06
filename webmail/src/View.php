<?php

/**
 * Simple view rendering class. Uses output buffer and native
 * PHP templates.
 */

namespace App;

use DateTime;
use Exception;
use DateTimeZone;
use App\Model\Meta;

class View
{
    private $data = [];

    private static $config;
    private static $timezone;

    const UTC = 'UTC';
    const TIME = 'g:i a';
    const DATE_SHORT = 'M j';
    const DATE_FULL = 'Y-m-d';
    const DEFAULT_TZ = 'America/New_York';

    public static function setConfig(array $config)
    {
        self::$config = $config;
        self::setTimezone($config['TIMEZONE'] ?? self::DEFAULT_TZ);
    }

    public static function setTimezone(string $timezone)
    {
        self::$timezone = $timezone;
    }

    /**
     * Add data to the internal variables. This is chainable,
     * and it will permanently store this data across renders.
     *
     * @param array $data
     *
     * @return self
     */
    public function setData(array $data)
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * Render the requested view via echo. This will clear the data
     * array unless told not to.
     *
     * @param bool $return Whether to return the string
     *
     * @throws Exception
     */
    public function render(string $view, array $data = [], bool $return = false)
    {
        $viewPath = VIEWDIR.DIR.$view.VIEWEXT;

        if (! file_exists($viewPath)) {
            throw new Exception('View not found! '.$viewPath);
        }

        ob_start();
        extract(array_merge($this->data, $data));

        include $viewPath;

        if ($return) {
            return ob_get_clean();
        }

        echo ob_get_clean();
    }

    /**
     * Send HTML headers and optionally start the session.
     */
    public function htmlHeaders(bool $startSession = true)
    {
        if ($startSession) {
            session_start();
        }

        header('Content-Type: text/html');
        header('Cache-Control: private, max-age=0, no-cache, no-store');
    }

    /**
     * Sanitizes and prints a value for a view.
     */
    public function clean(string $value, bool $return = false)
    {
        if ($return) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public function raw(string $value)
    {
        echo $value;
    }

    public function env(string $key, $default = null)
    {
        return isset(self::$config[$key])
            ? self::$config[$key]
            : $default;
    }

    /**
     * Renders a date, formatted for the timezone.
     */
    public function date(string $dateString, string $format)
    {
        echo self::getDate($date, $format);
    }

    /**
     * Formats a date according to the timezone and format.
     *
     * @return string
     */
    public static function getDate(
        string $dateString = null,
        string $format = self::DATE_SHORT)
    {
        $utc = new DateTimeZone(self::UTC);
        $tz = new DateTimeZone(self::$timezone);

        $date = $dateString
            ? new DateTime($dateString, $utc)
            : new DateTime;
        $date->setTimezone($tz);

        return $date->format($format);
    }

    /**
     * Prepares a data URI attribute for an element. Escapes the
     * HTML to comply with a data:TYPE attribute.
     *
     * @throws Exception
     *
     * @return string
     */
    public function dataUri(string $view, array $data = [])
    {
        $html = $this->render($view, $data, true);
        $search = ['%', '&', '#', '"', "'"];
        $replace = ['%25', '%26', '%23', '%22', '%27'];
        $html = preg_replace('/\s+/', ' ', $html);

        return str_replace($search, $replace, $html);
    }

    /**
     * Returns a readable version of a file size.
     *
     * @param int $size
     *
     * @return string
     */
    public function humanFileSize(int $size)
    {
        if ($size >= 1073741824) {
            $fileSize = round($size / 1024 / 1024 / 1024, 1).' GB';
        } elseif ($size >= 1048576) {
            $fileSize = round($size / 1024 / 1024, 1).' MB';
        } elseif($size >= 1024) {
            $fileSize = round($size / 1024, 1).' KB';
        } else {
            $fileSize = $size.' bytes';
        }

        return $fileSize;
    }

    /**
     * Returns the best string representation of a time span.
     * i.e. "20 minutes ago", "1 day ago", or the date time.
     *
     * @param int $timestamp
     *
     * @return string
     */
    public function timeSpan(int $timestamp)
    {
        if (! $timestamp) {
            return 'Never';
        }

        $diff = time() - $timestamp;

        if ($diff < 60) {
            $noun = 'second';
            $count = $diff;
        } elseif ($diff < 3600) {
            $noun = 'minute';
            $count = round($diff / 60);
        } elseif ($diff < 86400) {
            $noun = 'hour';
            $count = round($diff / 60 / 60);
        } else {
            return date('j F Y H:i', $timestamp);
        }

        return $count.' '.$noun.(1 == $count ? '' : 's').' ago';
    }

    /**
     * Retrieves a value from the meta table by key.
     */
    public function meta(string $key, $default = null)
    {
        return Meta::get($key, $default);
    }
}
