<?php

namespace Zeus\IO\Stream;

/**
 * Interface FlushableStreamInterface
 * @package Zeus\IO\Stream
 * @internal
 */
interface FlushableStreamInterface
{
    public function flush() : bool;
}