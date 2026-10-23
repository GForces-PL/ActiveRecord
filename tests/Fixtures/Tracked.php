<?php
namespace Fixtures;

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;

class Tracked extends Base
{
    protected static bool $keepAttributeChanges = true;
    #[Column] public int $id;
    #[Column] public string $name = 'default';
    #[Column] public bool $enabled = false;
    #[Column] public ?string $optional = null;
    #[Column] public \DateTime $date;
    #[Column] public array $data;
    #[Column] public MutableString $label;

    public function changed(string $name): bool { return $this->isAttributeChanged($name); }
    public function originals(): array { return $this->getOriginalValues(); }
}
