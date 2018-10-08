<?php

namespace Zeus\IO;

use Zeus\IO\Stream\NetworkStreamInterface;
use Zeus\IO\Stream\SelectionKeyInterface;
use Zeus\IO\Stream\SelectorInterface;

/**
 * Class SocketServerInterface
 */
interface SocketServerInterface
{
    public function setReuseAddress(bool $reuse);

    public function getReuseAddress() : bool;

    /**
     * @return bool
     */
    public function getTcpNoDelay() : bool;

    public function setTcpNoDelay(bool $tcpNoDelay);

    /**
     * @param string $host
     * @param int $backlog
     * @param int $port
     */
    public function bind(string $host, int $backlog = null, int $port = -1);

    public function accept() : NetworkStreamInterface;

    /**
     * @param int $option
     * @param mixed $value
     */
    public function setOption(int $option, $value);

    public function close();

    public function isBound() : bool;

    public function getLocalPort() : int;

    public function getLocalAddress() : string;

    public function isClosed() : bool;

    public function isIsBound() : bool;

    public function getSoTimeout() : int;

    public function setSoTimeout(int $soTimeout);

    public function getSocket() : NetworkStreamInterface;

    /**
     * @param SelectorInterface $selector
     * @param int $operation See SelectionKey::OP_READ, SelectionKey::OP_WRITE, SelectionKey::OP_ACCEPT
     * @return SelectionKeyInterface
     */
    public function register(SelectorInterface $selector, int $operation) : SelectionKeyInterface;
}