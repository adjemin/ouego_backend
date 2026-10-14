<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Collection;

/**
 * Charge en une requête par relation, pour toute la collection, les relations
 * que ses modèles exposent dans leur JSON.
 */
class SerializableCollection extends Collection
{
    public function toArray()
    {
        return SerializationScope::run(fn () => $this->loadSerializedRelations(), fn () => parent::toArray());
    }

    public function jsonSerialize(): array
    {
        return SerializationScope::run(fn () => $this->loadSerializedRelations(), fn () => parent::jsonSerialize());
    }

    private function loadSerializedRelations(): void
    {
        $first = $this->first();

        if ($first && method_exists($first, 'serializedRelations')) {
            $this->load($first->serializedRelations());
        }
    }
}
