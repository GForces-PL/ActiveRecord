<?php

namespace Fixtures;

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;

class PrivateTracked extends Base
{
    protected static bool $keepAttributeChanges = true;

    #[Column] public int $id;
    #[Column] private ?string $value;
    #[Column] private string $fresh;

    public function changed(string $attribute): bool
    {
        return $this->isAttributeChanged($attribute);
    }

    public function assign(string $attribute, ?string $value): void
    {
        $this->$attribute = $value;
    }

    public function clear(string $attribute): void
    {
        unset($this->$attribute);
    }

    public function originals(): array
    {
        return $this->getOriginalValues();
    }
}
