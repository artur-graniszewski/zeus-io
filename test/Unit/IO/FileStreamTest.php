<?php

namespace ZeusIoTest\Unit\IO;

use Zeus\IO\Stream\FileStream;
use Zeus\IO\SocketServer;

class FileStreamTest extends AbstractIOTest
{
    /** @var SocketServer */
    protected $server;
    protected $port;
    protected $client;
    
    public function setUp()
    {
        $this->port = rand(7000, 8000);
        $this->server = new SocketServer($this->port);
        $this->server->setSoTimeout(1000);
    }
    
    public function tearDown()
    {
        $this->server->close();
        
        if (is_resource($this->client)) {
            fclose($this->client);
        }
    }
    
    public function getFileChunks() : array
    {
        $originalFile = file_get_contents(__FILE__);

        $data = [
            [0, 10, substr($originalFile, 0, 10)],
            [3, 12, substr($originalFile, 3, 12)],
        ];

        return $data;
    }

    /**
     * @param $offset
     * @param $length
     * @param $expectedData
     * @dataProvider getFileChunks
     */
    public function testPositionSet($offset, $length, $expectedData)
    {
        $file = FileStream::open(__FILE__, "r");
        $currentPos = $file->getPosition();
        $this->assertEquals(0, $currentPos);

        $file->setPosition($offset);
        $currentPos = $file->getPosition();
        $this->assertEquals($offset, $currentPos);
        $chunk = $file->read($length);
        $this->assertEquals($expectedData, $chunk);
        $file->setPosition($offset);
        $chunk2 = $file->read($length);
        $this->assertEquals($chunk, $chunk2);
        $file->close();
    }
    
    /**
     * @expectedException \Zeus\IO\Exception\IOException
     * @expectedExceptionMessage Stream is closed
     */
    public function testSeekingOnClosedStream()
    {
        $file = FileStream::open(__FILE__, "r");
        $file->close();
        
        $file->setPosition(10);
    }
    
    /**
     * @expectedException \Zeus\IO\Exception\IOException
     * @expectedExceptionMessage Stream is closed
     */
    public function testPositionGetOnClosedStream()
    {
        $file = FileStream::open(__FILE__, "r");
        $file->close();
        
        $file->getPosition();
    }
    
    /**
     * @expectedException \Zeus\IO\Exception\UnsupportedOperationException
     * @expectedExceptionMessage Stream is not seekable
     */
    public function testSeekingOnNonSeekableStream()
    {
        $this->client = stream_socket_client('tcp://localhost:' . $this->port);
        stream_set_blocking($this->client, false);
        $file = new FileStream($this->client);
        
        $file->setPosition(10);
    }
}