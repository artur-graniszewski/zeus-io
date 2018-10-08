<?php

namespace Zeus\IO\Stream;

abstract class AbstractStreamSelector extends AbstractSelector
{
    public abstract function register(SelectableStreamInterface $stream, int $operation = SelectionKey::OP_ALL) : SelectionKeyInterface;

    public abstract function unregister(SelectableStreamInterface $stream, int $operation = SelectionKey::OP_ALL);
}