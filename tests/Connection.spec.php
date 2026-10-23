<?php

use Gforces\ActiveRecord\Connection;
use Gforces\ActiveRecord\Connection\Drivers\Mysql;
use Gforces\ActiveRecord\Connection\Drivers\Pgsql;

describe('Identifier quoting', function () {
    it('escapes delimiter characters in every identifier component for both drivers', function () {
        $reflection = new ReflectionClass(Connection::class);
        foreach ([new Mysql(), new Pgsql()] as $driver) {
            $connection = $reflection->newInstanceWithoutConstructor();
            $reflection->getProperty('driver')->setValue($connection, $driver);
            $q = $driver->getIdentifierQuotingCharacter();
            expect($connection->quoteIdentifier('name'))->toBe($q . 'name' . $q);
            expect($connection->quoteIdentifier("name$q = 1 -- "))->toBe($q . "name$q$q = 1 -- " . $q);
            expect($connection->quoteIdentifier(["table$q", "column$q"]))->toBe($q . "table$q$q$q.$q" . "column$q$q$q");
            expect($connection->quoteIdentifier('table.column'))->toBe($q . 'table.column' . $q);
        }
    });
});
