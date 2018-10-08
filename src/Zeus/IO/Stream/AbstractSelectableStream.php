<?php

namespace Zeus\IO\Stream;

use function stream_socket_get_name;

/**
 * Class AbstractSelectableStream
 * @package Zeus\IO\Stream
 */
abstract class AbstractSelectableStream extends AbstractStream implements SelectableStreamInterface
{
    /** @var string */
    private $localAddress;

    /**
     * @return string Server address (IP) or null if unknown
     */
    public function getLocalAddress() : string
    {
        return $this->localAddress ? $this->localAddress : $this->localAddress = @stream_socket_get_name($this->resource, false);
    }

    /**
     * @param SelectorInterface $selector
     * @param int $operation See SelectionKey::OP_READ, SelectionKey::OP_WRITE, SelectionKey::OP_ACCEPT
     * @return SelectionKeyInterface
     */
    public function register(SelectorInterface $selector, int $operation) : SelectionKeyInterface
    {
        return $selector->register($this, $operation);
    }
}