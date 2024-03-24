<?php

namespace Gforces\ActiveRecord\Connection;

use Gforces\ActiveRecord\Connection\Logger\Output;

class Logger
{
    public static Output|string $output = Output::none;

    public static function logQuery(string $query, array $params = []): void
    {
        $message = $query . ($params ? ' [' . implode(',', $params) . ']' : '') . "\n";
        switch (self::$output) {
            case Output::none:
                break;
            case Output::echo:
                echo $message;
                break;
            case Output::stdout:
                file_put_contents('php://stdout', $message, FILE_APPEND);
                break;
            default:
                file_put_contents(self::$output, $message, FILE_APPEND);
        }
        //error_log($query . ($params ? ' [' . implode(',', $params) . ']' : ''), E_USER_NOTICE);
    }
}
