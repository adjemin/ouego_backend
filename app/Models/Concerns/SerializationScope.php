<?php

namespace App\Models\Concerns;

/**
 * Délimite une sérialisation JSON (toArray) de modèles.
 *
 * Seule la sérialisation la plus externe prépare les données (chargement en lot des relations) ;
 * les modèles imbriqués réutilisent ce qui a été chargé. Pendant la sérialisation, remember()
 * partage les recherches identiques (ex. le même chauffeur sur plusieurs commandes).
 */
final class SerializationScope
{
    private static int $depth = 0;

    private static array $memo = [];

    public static function active(): bool
    {
        return self::$depth > 0;
    }

    public static function run(callable $prepare, callable $serialize): mixed
    {
        if (self::$depth === 0) {
            $prepare();
        }

        self::$depth++;

        try {
            return $serialize();
        } finally {
            if (--self::$depth === 0) {
                self::$memo = [];
            }
        }
    }

    /**
     * Hors sérialisation, la valeur est recalculée à chaque appel.
     */
    public static function remember(string $key, callable $resolve): mixed
    {
        if (self::$depth === 0) {
            return $resolve();
        }

        if (!array_key_exists($key, self::$memo)) {
            self::$memo[$key] = $resolve();
        }

        return self::$memo[$key];
    }
}
