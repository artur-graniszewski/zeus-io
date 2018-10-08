<?php

namespace Zeus\IO\Stream;

use function stream_select;
use function array_search;
use function array_values;
use function count;
use function strstr;
use function error_get_last;
use function error_clear_last;
use function function_exists;
use function json_encode;

use InvalidArgumentException;
use Zeus\IO\Exception\IOException;

class Selector extends AbstractStreamSelector implements SelectorInterface
{
    /** @var SelectionKeyInterface[] */
    private $selectionKeys = [];

    /** @var SelectionKeyInterface[] */
    private $selectedKeys = [];

    /** @var mixed[] */
    private $streams = [];

    private $streamResources = [SelectionKey::OP_READ => [], SelectionKey::OP_WRITE => [], SelectionKey::OP_ACCEPT => []];

    /** @var mixed[] */
    private $selectedResources = [SelectionKey::OP_READ => [], SelectionKey::OP_WRITE => [], SelectionKey::OP_ACCEPT => []];

    public function register(SelectableStreamInterface $stream, int $operation = SelectionKey::OP_ALL) : SelectionKeyInterface
    {
        if ($operation < 0 || $operation > SelectionKey::OP_ALL) {
            throw new InvalidArgumentException("Invalid operation type: " . json_encode($operation));
        }

        if ($operation & SelectionKey::OP_READ && !$stream->isReadable()) {
            throw new IOException("Unable to register: stream is not readable");
        }

        if ($operation & SelectionKey::OP_WRITE && !$stream->isWritable()) {
            throw new IOException("Unable to register: stream is not writable");
        }

        if ($stream->isClosed()) {
            throw new IOException("Unable to register: stream is closed");
        }

        $resource = $stream->getResource();
        $resourceId = $stream->getResourceId();

        if (isset($this->selectionKeys[$resourceId])) {
            $selectionKey = $this->selectionKeys[$resourceId];
        } else {
            $selectionKey = new SelectionKey($stream, $this);
        }

        $this->selectionKeys[$resourceId] = $selectionKey;
        $this->streams[$resourceId] = $stream;

        $interestOps = $selectionKey->getInterestOps();
        // @todo: forbid to register already registered operation?
        if ($operation & SelectionKey::OP_READ) {
            $interestOps |= SelectionKey::OP_READ;
            $this->streamResources[SelectionKey::OP_READ][$resourceId] = $resource;
        }

        if ($operation & SelectionKey::OP_WRITE) {
            $interestOps |= SelectionKey::OP_WRITE;
            $this->streamResources[SelectionKey::OP_WRITE][$resourceId] = $resource;
        }

        if ($operation & SelectionKey::OP_ACCEPT) {
            $interestOps |= SelectionKey::OP_ACCEPT;
            $this->streamResources[SelectionKey::OP_ACCEPT][$resourceId] = $resource;
        }

        $selectionKey->setInterestOps($interestOps);

        return $selectionKey;
    }

    public function unregister(SelectableStreamInterface $stream, int $operation = SelectionKey::OP_ALL)
    {
        if ($operation < 0 || $operation > SelectionKey::OP_ALL) {
            throw new InvalidArgumentException("Invalid operation type: " . json_encode($operation));
        }

        $resourceId = array_search($stream, $this->streams);

        if ($resourceId === false && !$stream->isClosed()) {
            throw new IOException("No such stream registered: " . $stream->getResourceId());
        }

        if ($operation & SelectionKey::OP_READ) {
            unset ($this->streamResources[SelectionKey::OP_READ][$resourceId]);
            unset ($this->selectedResources[SelectionKey::OP_READ][$resourceId]);
        }

        if ($operation & SelectionKey::OP_WRITE) {
            unset ($this->streamResources[SelectionKey::OP_WRITE][$resourceId]);
            unset ($this->selectedResources[SelectionKey::OP_WRITE][$resourceId]);
        }

        if ($operation & SelectionKey::OP_ACCEPT) {
            unset ($this->streamResources[SelectionKey::OP_ACCEPT][$resourceId]);
            unset ($this->selectedResources[SelectionKey::OP_ACCEPT][$resourceId]);
        }

        if (($operation === SelectionKey::OP_ALL)
            ||
            (
                !isset($this->streamResources[SelectionKey::OP_READ][$resourceId])
                &&
                !isset($this->streamResources[SelectionKey::OP_WRITE][$resourceId])
                &&
                !isset($this->selectedResources[SelectionKey::OP_ACCEPT][$resourceId])
            )
        ) {
            //$this->selectionKeys[$resourceId]->cancel();
            unset ($this->streamResources[SelectionKey::OP_READ][$resourceId]);
            unset ($this->selectedResources[SelectionKey::OP_READ][$resourceId]);
            unset ($this->streamResources[SelectionKey::OP_WRITE][$resourceId]);
            unset ($this->selectedResources[SelectionKey::OP_WRITE][$resourceId]);
            unset ($this->streamResources[SelectionKey::OP_ACCEPT][$resourceId]);
            unset ($this->selectedResources[SelectionKey::OP_ACCEPT][$resourceId]);
            unset ($this->selectionKeys[$resourceId]);
            unset ($this->selectedKeys[$resourceId]);
            unset ($this->streams[$resourceId]);
        }
    }

    /**
     * @return SelectionKeyInterface[]
     */
    public function getKeys() : array
    {
        return array_values($this->selectionKeys);
    }

    /**
     * @param float $milliseconds
     * @return float Microseconds
     */
    private static function convertMillisecondsToMicroseconds(float $milliseconds) : float
    {
        return $milliseconds > 0 ? $milliseconds * 1000 : 0.0;
    }

    /**
     * @param int $timeout Timeout in milliseconds
     * @return int
     */
    public function select(int $timeout = 0) : int
    {
        foreach($this->streams as $key => $stream) {
            if ($stream->isClosed()) {
                unset ($this->streamResources[SelectionKey::OP_READ][$key]);
                unset ($this->streamResources[SelectionKey::OP_WRITE][$key]);
                unset ($this->streamResources[SelectionKey::OP_ACCEPT][$key]);
                unset ($this->selectionKeys[$key]);
                unset ($this->selectedKeys[$key]);
                unset ($this->streams[$key]);
            }
        }

        $read = $this->streamResources[SelectionKey::OP_READ] + $this->streamResources[SelectionKey::OP_ACCEPT];
        $write = $this->streamResources[SelectionKey::OP_WRITE];

        if (!$read && !$write) {
            return 0;
        }

        $except = [];

        if (function_exists('error_clear_last')) {
            error_clear_last();
        };
        $streamsChanged = @stream_select($read, $write, $except, 0, $this->convertMillisecondsToMicroseconds($timeout));

        if ($streamsChanged === false) {
            $error = error_get_last();

            if (strstr($error['message'], 'Interrupted system call')) {
                return 0;
            }

            throw new IOException("Select failed: " . $error['message']);
        }
        if ($streamsChanged === 0) {
            return 0;
        }

        if ($read && $write) {
            $uniqueStreams = [];

            foreach ($read as $resource) {
                $uniqueStreams[(int) $resource] = 1;
            }

            foreach ($write as $resource) {
                $uniqueStreams[(int) $resource] = 1;
            }
        } else {
            $uniqueStreams = $read ? $read : $write;
        }

        $this->selectedResources = [SelectionKey::OP_READ => $read, SelectionKey::OP_WRITE => $write];

        $streamsChanged = count($uniqueStreams);

        $this->computeSelectedKeys();

        return $streamsChanged;
    }

    private function computeSelectedKeys()
    {
        $result = [];

        foreach ($this->selectionKeys as $selectionKey) {
            $selectionKey->setAcceptable(false);
            $selectionKey->setReadable(false);
            $selectionKey->setWritable(false);
        }

        foreach ($this->selectedResources as $type => $pool) {
            foreach ($pool as $resource) {
                $resourceId = (int)$resource;
                $selectionKey = $this->selectionKeys[$resourceId];

                if (isset($this->streamResources[SelectionKey::OP_ACCEPT][$resourceId])) {
                    $selectionKey->setAcceptable(true);
                    $result[$resourceId] = $selectionKey;
                    continue;
                }

                if ($type & SelectionKey::OP_WRITE) {
                    $selectionKey->setWritable(true);
                }

                if ($type & SelectionKey::OP_READ) {
                    $selectionKey->setReadable(true);
                }

                $result[$resourceId] = $selectionKey;
            }
        }

        $this->selectedKeys = array_values($result);
    }

    /**
     * @return SelectionKeyInterface[]
     */
    public function getSelectionKeys() : array
    {
        return $this->selectedKeys;
    }

    /**
     * @param SelectionKeyInterface[] $keys
     */
    protected function setSelectionKeys(array $keys)
    {
        $this->selectedKeys = $keys;
    }
}