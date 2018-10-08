<?php

namespace Zeus\IO\Stream;

use LogicException;

interface SelectionKeyInterface
{
    public function getStream() : SelectableStreamInterface;

    public function isReadable() : bool;

    public function isWritable() : bool;

    public function isAcceptable() : bool;

    public function setReadable(bool $true);

    public function setWritable(bool $true);

    public function setAcceptable(bool $true);

    /**
     * @param mixed $attachment
     */
    public function attach($attachment);

    /**
     * @return mixed
     */
    public function getAttachment();

    public function getSelector() : SelectorInterface;

    public function cancel($operation = SelectionKey::OP_ALL);

    public function getReadyOps() : int;

    public function getInterestOps() : int;

    /**
     * @param int $ops
     * @throws LogicException
     */
    public function setInterestOps(int $ops);
}