<?php

namespace Zeus\IO\Stream;

use function fseek;
use function ftell;
use function stream_get_meta_data;

use Zeus\IO\Exception\IOException;
use Zeus\IO\Exception\UnsupportedOperationException;

/**
 * Class FileStream
 * @package Zeus\IO\Stream
 */
class FileStream extends AbstractSelectableStream implements SeekableInterface
{
    /** @var bool */
    private $isSeekable = null;

    private function isSeekable() : bool
    {
        if (null === $this->isSeekable) {
            $meta = stream_get_meta_data($this->resource);
            $this->isSeekable = (bool) $meta['seekable'];
        }

        return $this->isSeekable;
    }

    public function setPosition(int $position)
    {
        if ($this->isClosed()) {
            throw new IOException("Stream is closed");
        }

        if (!$this->isSeekable()) {
            throw new UnsupportedOperationException("Stream is not seekable");
        }

        $success = fseek($this->resource, $position, $position >= 0 ? \SEEK_SET : \SEEK_END);

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