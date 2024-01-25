<?php

namespace Fixtures;

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;

class WithBackedEnumProperty extends Base
{
    #[Column]
    public int $id;

    #[Column]
    public HttpStatusCode $status;

    #[Column]
    public ?HttpStatusCode $nullableStatus;

    #[Column]
    public ?HttpStatusCode $statusDefaultNull = null;

    #[Column]
    public HttpStatusCode $statusDefaultOk = HttpStatusCode::ok;
}
