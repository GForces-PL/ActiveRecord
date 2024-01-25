<?php

namespace Fixtures;

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;

class WithUnitEnumProperty extends Base
{
    #[Column]
    public int $id;

    #[Column]
    public State $state;

    #[Column]
    public ?State $nullableState;

    #[Column]
    public ?State $stateDefaultNull = null;

    #[Column]
    public State $stateDefaultOff = State::off;
}
