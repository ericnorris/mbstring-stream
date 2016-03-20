<?php

require_once './vendor/autoload.php';

class MultibyteStringStreamTest extends PHPUnit_Framework_TestCase {

    public function testStreamFilterWasRegistered() {
        $this->assertContains('convert.mbstring.*', stream_get_filters());
    }

    public function testValidConversionParams() {
        $dummy_stream = fopen('data://text/plain;base64,', 'r');
        $filtername   = 'convert.mbstring.US-ASCII/UTF-8';

        $success = (bool)stream_filter_append($dummy_stream, $filtername);

        $this->assertTrue($success, 'Failed to register valid conversion filter');
    }

    public function testInvalidConversionParams() {
        $dummy_stream = fopen('data://text/plain;base64,', 'r');
        $filtername   = 'convert.mbstring.FAKE/UTF-8';

        $success = (bool)@stream_filter_append($dummy_stream, $filtername);

        $this->assertNotTrue($success, 'Registered invalid conversion filter');
    }

    public function testDefaultConversionParam() {
        $dummy_stream = fopen('data://text/plain;base64,', 'r');
        $filtername   = 'convert.mbstring.UTF-8';

        $success = (bool)stream_filter_append($dummy_stream, $filtername);

        $this->assertTrue($success, 'Failed to register conversion filter using mb_internal_encoding() default');
    }

    public function testDefaultReplacementCharacterParam() {
        $dummy_stream = fopen('data://text/plain;base64,Zvxy', 'r');
        $filtername   = 'convert.mbstring.UTF-8/UTF-8';

        stream_filter_append($dummy_stream, $filtername);

        $result = stream_get_contents($dummy_stream);

        $this->assertSame($result, 'f?r', 'String did not contain correct replacement character');
    }

    public function testReplacementCharacterParam() {
        $dummy_stream = fopen('data://text/plain;base64,Zvxy', 'r');
        $filtername   = 'convert.mbstring.UTF-8/UTF-8';

        stream_filter_append($dummy_stream, $filtername, STREAM_FILTER_READ, 65533);

        $result = stream_get_contents($dummy_stream);

        $this->assertSame($result, 'f�r', 'String did not contain correct replacement character');
    }

    public function testMultibyteEdgeHandling() {
        $output     = fopen('php://memory', 'w+');
        $filtername = 'convert.mbstring.UTF-8/UTF-8';

        stream_filter_append($output, $filtername, STREAM_FILTER_WRITE);

        $donut_first_half  = substr("🍩", 0, 2);
        $donut_second_half = substr("🍩", 2);

        fwrite($output, $donut_first_half);
        flush($output);

        fwrite($output, $donut_second_half);
        flush($output);

        rewind($output);

        $contents = stream_get_contents($output);

        $this->assertSame($contents, '🍩', 'Did not handle partial multibyte character');
    }

}
