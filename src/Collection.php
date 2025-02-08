<?php /** @noinspection PhpUnused */

namespace Rammewerk\Component\Hydrator;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;
use Override;
use RuntimeException;

/**
 * @template TEntity
 * @template-implements Iterator<int, TEntity>
 * @template-implements ArrayAccess<int, TEntity>
 */
abstract class Collection implements Countable, Iterator, ArrayAccess, JsonSerializable {

    protected int $position = 0;
    /** @var array<int, mixed> */
    protected array $source = [];


    /**
     * @param int $position
     *
     * @return TEntity
     */
    abstract protected function getEntity(int $position);


    /**
     * Implementation of method declared in \Countable
     *
     * @return int The number of elements in the source array
     */
    #[Override] public function count(): int {
        return count( $this->source );
    }


    /**
     * Implementation of method declared in \Iterator
     * Resets the internal cursor to the beginning of the array
     */
    #[Override] public function rewind(): void {
        $this->position = 0;
    }


    /**
     * Implementation of method declared in \Iterator
     * Used to get the current key (as for instance in a foreach()-structure
     * @return int
     */
    #[Override] public function key(): int {
        return $this->position;
    }


    /**
     * Implementation of method declared in \Iterator
     * Used to get the value at the current cursor position
     *
     * @return TEntity
     */
    #[Override] public function current(): mixed {
        return $this->getEntity( $this->position );
    }


    /**
     * Implementation of method declared in \Iterator
     * Used to move the cursor to the next position
     */
    #[Override] public function next(): void {
        $this->position++;
    }


    /**
     * Implementation of method declared in \Iterator
     * Checks if the current cursor position is valid
     */
    #[Override] public function valid(): bool {
        return isset( $this->source[$this->position] );
    }


    /**
     * Implementation of method declared in \ArrayAccess
     * Used to be able to use functions like isset()
     */
    #[Override] public function offsetExists($offset): bool {
        return isset( $this->source[$offset] );
    }


    /**
     * Implementation of method declared in \ArrayAccess
     * Used for direct access array-like ($collection[$offset]);
     *
     * @return TEntity
     */
    #[Override] public function offsetGet($offset): mixed {
        return $this->getEntity( $offset );
    }


    /**
     * Implementation of method declared in \ArrayAccess
     *
     * @param int $offset
     * @param TEntity $value
     */
    #[Override] public function offsetSet($offset, $value): void {
        throw new RuntimeException( 'Not allowed to set new model' );
    }


    /**
     * Implementation of method declared in \ArrayAccess
     * Used for unset()
     * @param $offset int
     */
    #[Override] public function offsetUnset($offset): void {
        unset( $this->source[$offset] );
    }


    #[Override] public function jsonSerialize(): string {
        return "Cannot serialize a model collection";
    }

    /**
     * Will hydrate all entities in the collection, and return them as an array
     *
     * @return TEntity[]
     */
    public function toArray(): array {
        return array_map( [$this, 'getEntity'], array_keys( $this->source ) );
    }

}