<?php

namespace Gforces\ActiveRecord\Connection;

class Logger
{
    const DESTINATION_NONE = 0;
    const DESTINATION_FILE = 1;

    public static int $destination = self::DESTINATION_NONE;

    public static function logQuery(string $query, array $params = []): void
    {
        $message = $query . ($params ? ' [' . implode(',', $params) . ']' : '') . "\n";
        switch (self::$destination) {
            case self::DESTINATION_FILE:
                file_put_contents('/tmp/sql.log', $message, FILE_APPEND);
                break;
        }
        //error_log($query . ($params ? ' [' . implode(',', $params) . ']' : ''), E_USER_NOTICE);
    }
}
