<?php
declare(strict_types=1);

/**
 * Class log is static class for logging
 *
 * It handles various log levels. Currently, outputs everything to stdout
 *
 * @author Jan Krpes <jan.krpes@cdn77.com>
 */
class log
{
    /**
     * List of severity levels.
     */
    const EMERGENCY = 0; // It's an emergency
    const ALERT = 1; // Immediate action required
    const CRITICAL = 2; // Critical conditions
    const ERROR = 3; // An error occurred
    const WARNING = 4; // Something unexpected happening
    const NOTICE = 5; // Something worth noting
    const INFO = 6; // Information, not an error
    const DEBUG = 7; // Debugging messages

    /**
     * @var int
     */
    private static int $default_level = 3;
    /**
     * @var int
     */
    private static int $display_level = 7;
    /**
     * @var string
     */
    private static string $proc_name = '';

    /**
     * @param int $level
     * @throws Exception if incorrect input is supplied
     */
    public static function setLevel(int $level): void
    {
        if ($level < self::EMERGENCY) throw new Exception("Log level must not be negative. Lowest log level is 0.");
        if ($level > self::DEBUG) throw new Exception("Log level can not be higher than " . self::DEBUG . ".");

        self::$display_level = $level;
    }

    /**
     * @return int
     */
    public static function getLevel(): int
    {
        return self::$display_level;
    }

    /**
     * @param string $name
     */
    public static function setProcName(string $name): void
    {
        self::$proc_name = $name;
    }

    /**
     * @param string $message
     * @param int|null $level
     *
     * @return void
     */
    public static function logMsg(string $message, int $level = null): void
    {
        $level = ($level !== null) ? $level : self::$default_level;
        $procname = (self::$proc_name !== "") ? '[' . str_pad(self::$proc_name, 14) . ']' : '';
        $pid = "[" . date("H:i:s d.m.y") . "]{$procname}[" . str_pad((string)getmypid(), 5) . "]";
        if ($level <= self::$display_level)
            fwrite(STDERR, $pid . "[" . str_pad(self::lvl2text($level), 8) . "] " . $message . PHP_EOL);
    }

    /* shortcut functions */
    /**
     * @param string $message
     *
     * @return void
     */
    public static function emergency(string $message): void
    {
        self::logMsg($message, self::EMERGENCY);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function alert(string $message): void
    {
        self::logMsg($message, self::ALERT);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function critical(string $message): void
    {
        self::logMsg($message, self::CRITICAL);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function error(string $message): void
    {
        self::logMsg($message, self::ERROR);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function warning(string $message): void
    {
        self::logMsg($message, self::WARNING);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function notice(string $message): void
    {
        self::logMsg($message, self::NOTICE);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function info(string $message): void
    {
        self::logMsg($message, self::INFO);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function debug(string $message): void
    {
        self::logMsg($message, self::DEBUG);
    }

    /**
     * @param int $level
     *
     * @return string
     */
    public static function lvl2text(int $level): string
    {
        return match ($level) {
            self::EMERGENCY => "EMERGENCY",
            self::ALERT => "ALERT",
            self::CRITICAL => "CRITICAL",
            self::ERROR => "ERROR",
            self::WARNING => "WARNING",
            self::NOTICE => "NOTICE",
            self::INFO => "INFO",
            self::DEBUG => "DEBUG",
            default => "N/A",
        };
    }
}