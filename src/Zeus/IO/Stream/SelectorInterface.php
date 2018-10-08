<?php

namespace Zeus\IO\Stream;

interface SelectorInterface
{
    public function register(SelectableStreamInterface $stream, int $operation = SelectionKey::OP_ALL) : SelectionKeyInterface;

    public function unregister(SelectableStreamInterface $stream, int $operation = SelectionKey::OP_ALL);

    /**
     * @return SelectionKeyInterface[]
     */
    public function getKeys() : array;

    /**
     * @param int $timeout Timeout in milliseconds
     * @return int
     */
    public function select(int $timeout = 0) : int;

    /**
     * @return SelectionKeyInterface[]
     */
    public function getSelectionKeys() : array;
}