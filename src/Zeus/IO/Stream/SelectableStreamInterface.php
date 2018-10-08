<?php

namespace Zeus\IO\Stream;

/**
 * Interface SelectableStreamInterface
 * @package Zeus\IO\Stream
 * @internal
 */
interface SelectableStreamInterface extends StreamInterface
{
    public function register(SelectorInterface $selector, int $operation) : SelectionKeyInterface;
}