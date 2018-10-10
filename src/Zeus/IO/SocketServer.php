<?php

namespace Zeus\IO;

use function stream_socket_accept;
use function stream_socket_server;
use function stream_socket_get_name;
use function stream_context_create;
use function end;
use function explode;
use function current;
use function defined;

use Zeus\IO\Exception\UnsupportedOperationException;
use Zeus\IO\Exception\SocketException;
use Zeus\IO\Exception\SocketTimeoutException;
use Zeus\IO\Stream\NetworkStreamInterface;
use Zeus\IO\Stream\SelectableStreamInterface;
use Zeus\IO\Stream\SelectionKeyInterface;
use Zeus\IO\Stream\SelectorInterface;
use Zeus\IO\Stream\SocketStream;

/**
 * Class SocketServer
 */
class SocketServer implements SocketServerInterface
{
    /** @var resource */
    private $resource;

    /** @var int */
    private $port = -1;

    /** @var string */
    private $host;

    /** @var int */
    private $backlog = 5;

    /** @var bool */
    private $reuseAddress = false;

    /** @var bool */
    private $isClosed = false;

    /** @var bool */
    private $isBound = false;

    private $soTimeout = 0;

    /** @var bool */
    private $tcpNoDelay = false;

    /** @var SelectableStreamInterface */
    private $socketObject;

    /**
     * SocketServer constructor.
     * @param int $port
     * @param int $backlog
     * @param string|null $host
     */
    public function __construct(int $port = -1, int $backlog = null, string $host = null)
    {
        $this->host = $host;

        if ($backlog) {
            $this->backlog = $backlog;
        }

        if ($port >= 0) {
            $this->port = $port;
            $this->createServer();
        }
    }

    public function setReuseAddress(bool $reuse)
    {
        if (defined("HHVM_VERSION")) {
            throw new UnsupportedOperationException("Reuse address feature is not supported by HHVM");
        }
        
        if ($this->isBound()) {
            throw new SocketException("Socket already bound");
        }
        
        if ($this->isClosed()) {
            throw new SocketException("Server already stopped");
        }
        
        $this->reuseAddress = $reuse;

        return $this;
    }

    public function getReuseAddress() : bool
    {
        return $this->reuseAddress;
    }

    public function getTcpNoDelay(): bool
    {
        return $this->tcpNoDelay;
    }

    public function setTcpNoDelay(bool $tcpNoDelay)
    {
        if ($this->isBound()) {
            throw new SocketException("Socket already bound");
        }
        
        if ($this->isClosed()) {
            throw new SocketException("Server already stopped");
        }
        
        $this->tcpNoDelay = $tcpNoDelay;
    }

    /**
     * @param string $host
     * @param int $backlog
     * @param int $port
     */
    public function bind(string $host, int $backlog = null, int $port = -1)
    {
        if ($this->isBound()) {
            throw new SocketException("Server already bound");
        }

        $this->host = $host;
        if ($backlog) {
            $this->backlog = $backlog;
        }

        if ($port >= 0) {
            $this->port = $port;
        } else if ($this->port < 0) {
            throw new SocketException("Can't bind to $host: no port specified");
        }

        $this->createServer();
    }

    private function createServer()
    {
        $opts = [
            'socket' => [
                'backlog' => $this->backlog,
                'so_reuseport' => $this->getReuseAddress(),
                'tcp_nodelay' => $this->tcpNoDelay,
            ],
        ];

        $context = stream_context_create($opts);

        if (!$this->host) {
            $this->host = 'tcp://0.0.0.0';
        }

        $uri = $this->host . ':' . $this->port;

        $this->resource = @stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $context);
        if (false === $this->resource) {
            throw new SocketException("Could not bind to $uri: $errstr", $errno);
        }

        if ($this->port === 0) {
            $socketName = stream_socket_get_name($this->resource, false);
            $parts = explode(":", $socketName);

            end($parts);

            $this->port = (int) current($parts);
        }

        $this->isBound = true;
    }

    /**
     * @param float $milliseconds
     * @return float Seconds
     */
    private function convertMillisecondsToSeconds(float $milliseconds) : float
    {
        return $milliseconds > 0 ? $milliseconds / 1000 : 0.0;
    }

    public function accept() : NetworkStreamInterface
    {
        $timeout = $this->convertMillisecondsToSeconds($this->getSoTimeout());

        $newSocket = @stream_socket_accept($this->resource, $timeout, $peerName);
        if (!$newSocket) {
            throw new SocketTimeoutException('Socket timed out');
        }

        if (defined("HHVM_VERSION")) {
            // HHVM sets invalid peer name in stream_socket_accept function, for example: "\u0018:35180"
            $peerName = @stream_socket_get_name($newSocket, true);
        }
        $connection = new SocketStream($newSocket, $peerName);

        return $connection;
    }

    /**
     * @param int $option
     * @param mixed $value
     */
    public function setOption(int $option, $value)
    {
        if (!$this->isBound()) {
            throw new SocketException("Socket must be bound first");
        }
        
        if ($this->isClosed()) {
            throw new SocketException("Server already stopped");
        }

        $this->getSocket()->setOption($option, $value);
    }

    public function close()
    {
        if (!$this->isBound()) {
            throw new SocketException("Socket must be bound first");
        }
        
        if ($this->isClosed()) {
            throw new SocketException("Server already stopped");
        }

        $this->getSocket()->close();
        $this->resource = null;
        $this->isClosed = true;
    }
    
    public function isClosed() : bool
    {
        return $this->isClosed || ($this->socketObject && $this->getSocket()->isClosed());
    }

    public function isBound() : bool
    {
        return $this->isBound;
    }

    public function getLocalPort() : int
    {
        return $this->port;
    }

    public function getLocalAddress() : string
    {
        return $this->host . ($this->port ? ':' . $this->port : '');
    }

    public function getSoTimeout() : int
    {
        return $this->soTimeout;
    }

    public function setSoTimeout(int $soTimeout)
    {
        if (!$this->isBound()) {
            throw new SocketException("Socket is already bound");
        }
        
        if ($this->getSocket()->isClosed()) {
            throw new SocketException("Server already stopped");
        }
        
        $this->soTimeout = $soTimeout;
    }

    public function getSocket() : NetworkStreamInterface
    {
        if (!$this->socketObject) {
            $this->socketObject = new SocketStream($this->resource);
        }

        return $this->socketObject;
    }

    /**
     * @param SelectorInterface $selector
     * @param int $operation See SelectionKey::OP_READ, SelectionKey::OP_WRITE, SelectionKey::OP_ACCEPT
     * @return SelectionKeyInterface
     */
    public function register(SelectorInterface $selector, int $operation) : SelectionKeyInterface
    {
        return $selector->register($this->getSocket(), $operation);
    }
}