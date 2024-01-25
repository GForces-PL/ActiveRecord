<?php

namespace Fixtures;

use DateTime;
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;

class WithDateTimeProperty extends Base
{
    #[Column]
    public int $id;

    #[Column]
    public DateTime $date;

    #[Column]
    public ?DateTime $nullableDate;

    #[Column]
    public ?DateTime $dateDefaultNull = null;
}
