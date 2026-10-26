<?php

namespace Fixtures;

class RelationVisibilityChild extends RelationVisibility
{
    public function readInChildClass(string $name): array
    {
        return $this->$name;
    }
}
