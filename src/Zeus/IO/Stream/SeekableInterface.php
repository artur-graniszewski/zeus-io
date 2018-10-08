<?php

namespace Zeus\IO\Stream;

/**
 * Class FileStream
 * @package Zeus\IO\Stream
 */
interface SeekableInterface
{
    public function setPosition(int $position);

    public function getPosition() : int;
}