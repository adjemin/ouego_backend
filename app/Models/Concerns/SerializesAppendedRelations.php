<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Pour les modèles dont les attributs ajoutés ($appends) sont des relations.
 *
 * Avant, chaque attribut ajouté lançait sa propre requête à la sérialisation (N+1 en cascade).
 * Désormais, la sérialisation la plus externe charge en lot les relations de serializedRelations(),
 * et les accesseurs les réutilisent. Hors sérialisation, les accesseurs interrogent toujours la base,
 * comme avant, pour ne jamais renvoyer une relation périmée.
 */
trait SerializesAppendedRelations
{
    /**
     * Relations (chemins imbriqués compris) exposées dans le JSON du modèle.
     */
    abstract public function serializedRelations(): array;

    public function toArray()
    {
        return SerializationScope::run(
            fn () => $this->exists && $this->load($this->serializedRelations()),
            fn () => parent::toArray()
        );
    }

    public function newCollection(array $models = [])
    {
        return new SerializableCollection($models);
    }

    protected function serializedRelation(string $relation)
    {
        if (SerializationScope::active() && $this->relationLoaded($relation)) {
            return $this->getRelation($relation);
        }

        return $this->$relation()->getResults();
    }

    /**
     * Les relations déjà exposées par un attribut ajouté ne sont pas sérialisées une seconde fois.
     */
    protected function getArrayableRelations()
    {
        $appends = $this->getArrayableAppends();

        return array_filter(
            parent::getArrayableRelations(),
            fn ($relation) => !in_array(Str::snake($relation), $appends, true),
            ARRAY_FILTER_USE_KEY
        );
    }
}
