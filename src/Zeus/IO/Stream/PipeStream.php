<?php

namespace Zeus\IO\Stream;

/**
 * Class PipeStream
 * @package Zeus\IO\Stream
 */
class PipeStream extends AbstractSelectableStream
{
    /**
     * PipeStream constructor.
     * @param resource $resource
     * @param string $peerName
     */
    public function __construct($resource, string $peerName = null)
    {
        parent::__construct($resource, $peerName);

        $this->writeCallback = 'fwrite';
        $this->readCallback = 'stream_get_contents';
    }
}