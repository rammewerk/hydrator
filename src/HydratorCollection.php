<?php

namespace Rammewerk\Component\Hydrator;


/**
 * @template TEntity as object
 * @extends Collection<TEntity>
 */
final class HydratorCollection extends Collection {

    /**
     * @param Hydrator<TEntity> $hydrator
     * @param array<int, array<string, mixed>|object> $source
     */
    public function __construct(
        private readonly Hydrator $hydrator,
        array $source,
    ) {
        $this->source = array_map(static fn($i) => is_object($i) ? (array)$i : $i, $source);
    }



    /**
     * @param int $position
     *
     * @return TEntity
     */
    protected function getEntity(int $position) {
        return $this->hydrator->hydrate($this->source[$position]);
    }


}