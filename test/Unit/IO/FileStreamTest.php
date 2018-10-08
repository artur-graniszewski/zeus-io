<?php

namespace ZeusIoTest\Unit\IO;

use Zeus\IO\Stream\FileStream;

class FileStreamTest extends AbstractIOTest
{
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
}