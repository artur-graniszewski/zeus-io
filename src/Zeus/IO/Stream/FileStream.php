<?php

namespace Zeus\IO\Stream;

use function fseek;
use function ftell;

use Zeus\IO\Exception\IOException;

/**
 * Class FileStream
 * @package Zeus\IO\Stream
 */
class FileStream extends AbstractSelectableStream implements SeekableInterface
{
    public function setPosition(int $position)
    {
        if ($this->isClosed()) {
            throw new IOException("Stream is closed");
        }

        $success = fseek($this->resource, SEEK_SET, $position);

        if (-1 === $success) {
            throw new IOException("Unable to set stream position");
        }
    }

    public function getPosition() : int
    {
        if ($this->isClosed()) {
            throw new IOException("Stream is closed");
        }

        $position = @ftell($this->resource);

        if ($position === false) {
            throw new IOException("Unable to get stream position");
        }

        return $position;
    }
}