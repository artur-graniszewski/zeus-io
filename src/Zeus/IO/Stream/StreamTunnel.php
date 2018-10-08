<?php

namespace Zeus\IO\Stream;

use LogicException;
use Zeus\IO\Exception\EOFException;

class StreamTunnel
{
    /** @var SelectionKeyInterface  */
    private $srcSelectionKey;

    /** @var SelectionKeyInterface */
    private $dstSelectionKey;

    /** @var bool */
    private $isSaturated = false;

    /** @var int */
    private $id;

    public function __construct(SelectionKeyInterface $srcSelectionKey, SelectionKeyInterface $dstSelectionKey)
    {
        $this->srcSelectionKey = $srcSelectionKey;
        $this->dstSelectionKey = $dstSelectionKey;
    }

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getId() : int
    {
        if (null === $this->id) {
            throw new LogicException('Tunnel ID is not set');
        }
        return $this->id;
    }

    public function tunnel()
    {
        if ($this->isSaturated()) {
            // try to flush existing data
            $this->write("");

            return;
        }

        $srcSelectionKey = $this->srcSelectionKey;

        if (!$srcSelectionKey->isReadable()) {
            return;
        }

        if (!$srcSelectionKey->getStream()->isReadable()) {
            $srcSelectionKey->cancel(SelectionKey::OP_READ);
            return;
        }

        $data = $srcSelectionKey->getStream()->read();

        if ('' === $data) {
            $stream = $srcSelectionKey->getStream();
            if ($stream instanceof NetworkStreamInterface) {
                $stream->shutdown(STREAM_SHUT_RD);
            }
            // EOF
            throw new EOFException("Stream reached EOF mark");
        }

        $this->write($data);
    }

    private function write(string $data)
    {
        $srcSelectionKey = $this->srcSelectionKey;
        $dstSelectionKey = $this->dstSelectionKey;

        $dstStream = $dstSelectionKey->getStream();
        $srcStream = $srcSelectionKey->getStream();

        if (!$this->isSaturated() || $dstSelectionKey->isWritable()) {
            $stream = $dstStream;
            if ($data !== '') {
                $stream->write($data);
            }

            if ($stream->flush()) {
                if (!$this->isSaturated()) {
                    return;
                }

                $this->setSaturated(false);
                if ($srcStream->isReadable()) {
                    $srcStream->register($srcSelectionKey->getSelector(), SelectionKey::OP_READ);
                }
                $srcSelectionKey->cancel(SelectionKey::OP_WRITE);

                return;
            }

            if ($this->isSaturated()) {
                return;
            }

            $this->setSaturated(true);
            if ($dstStream->isWritable()) {
                $dstStream->register($dstSelectionKey->getSelector(), SelectionKey::OP_WRITE);
            }

            $srcSelectionKey->cancel(SelectionKey::OP_READ);
        }
    }

    public function isSaturated() : bool
    {
        return $this->isSaturated;
    }

    public function setSaturated(bool $isSaturated)
    {
        $this->isSaturated = $isSaturated;
    }
}