<?php

namespace Gforces\ActiveRecord;

use Gforces\ActiveRecord\Associations\BelongsTo;
use ReflectionException;
use SplObjectStorage;

/** @internal Coordinates one save operation; it is not an identity map or transaction. */
final class AssociationSaveContext
{
    private SplObjectStorage $checked;
    private SplObjectStorage $saving;
    private array $pending = [];

    public function __construct()
    {
        $this->checked = new SplObjectStorage();
        $this->saving = new SplObjectStorage();
    }

    /**
     * @throws ReflectionException
     */
    public function check(Base $object): void
    {
        if ($this->checked->offsetExists($object)) {
            return;
        }
        $this->checked->offsetSet($object);
        foreach (Association::getAll($object::class) as $association) {
            $association->check($object);
            foreach ($association->newRelatedObjects($object) as $related) {
                $this->check($related);
            }
        }
    }

    /**
     * @throws AssociationException
     */
    public function enter(Base $object): bool
    {
        if ($this->saving->offsetExists($object)) {
            if ($object->isNew) {
                throw new AssociationException('Circular dependency between unsaved records');
            }
            return false;
        }
        $this->saving->offsetSet($object);
        return true;
    }

    public function defer(Base $object, array $associations): void
    {
        foreach ($associations as $association) {
            if (!$association instanceof BelongsTo) {
                $this->pending[] = [$object, $association];
            }
        }
    }

    public function finish(): void
    {
        // Saving a new related object can append work. Its row exists before its
        // inverse collections are synchronized, avoiding recursive stale reads.
        for ($i = 0; $i < count($this->pending); $i++) {
            [$object, $association] = $this->pending[$i];
            $association->save($object);
        }
    }
}
