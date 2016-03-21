<?php

require_once './vendor/autoload.php';

class MultibyteStringStreamTest extends PHPUnit_Framework_TestCase {

    public function testStreamFilterWasRegistered() {
        MultibyteStringStream::registerStreamFilter();

        $this->assertContains('convert.mbstring.*', stream_get_filters());
    }

    public function testValidConversionParams() {
        $stream     = fopen('data://text/plain;base64,', 'r');
        $filtername = 'convert.mbstring.US-ASCII/UTF-8';

        $success = (bool)stream_filter_append($stream, $filtername);

        $this->assertTrue(
            $success,
            'Failed to register valid conversion filter'
        );
    }

    public function testInvalidConversionParams() {
        $stream     = fopen('data://text/plain;base64,', 'r');
        $filtername = 'convert.mbstring.FAKE/UTF-8';

        $success = (bool)@stream_filter_append($stream, $filtername);

        $this->assertNotTrue(
            $success,
            'Incorrectly registered invalid conversion filter'
        );
    }

    public function testDefaultConversionParam() {
        $stream     = fopen('data://text/plain;base64,', 'r');
        $filtername = 'convert.mbstring.UTF-8';

        $success = (bool)stream_filter_append($stream, $filtername);

        $this->assertTrue(
            $success,
            'Failed to register conversion filter with default from encoding'
        );
    }

    public function testDefaultReplacementCharacterParam() {
        $stream     = fopen('data://text/plain;base64,Zvxy', 'r');
        $filtername = 'convert.mbstring.UTF-8/UTF-8';

        stream_filter_append($stream, $filtername);

        $expected = 'f?r';
        $result   = stream_get_contents($stream);

        $this->assertSame(
            $expected,
            $result,
            'String did not contain correct replacement character'
        );
    }

    public function testReplacementCharacterParam() {
        $stream     = fopen('data://text/plain;base64,Zvxy', 'r');
        $filtername = 'convert.mbstring.UTF-8/UTF-8';

        stream_filter_append($stream, $filtername, STREAM_FILTER_READ, 65533);

        $expected = 'f�r';
        $result   = stream_get_contents($stream);

        $this->assertSame(
            $result,
            'f�r',
            'String did not contain correct replacement character'
        );
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

        $expected = '🍩';
        $result   = stream_get_contents($output);

        $this->assertSame(
            $expected,
            $result,
            'Did not handle partial multibyte character'
        );
    }

    public function testCloseInvalidData() {
        $output     = fopen('php://output', 'w');
        $filtername = 'convert.mbstring.UTF-8/UTF-8';

        stream_filter_append($output, $filtername);

        ob_start();

        fwrite($output, substr("🍩", 0, 2));
        fclose($output);

        $expected = '?';
        $result   = ob_get_clean();

        $this->assertSame(
            $expected,
            $result,
            'Did not handle partial multibyte character'
        );
    }

    /**
     * @dataProvider unicodeMappingProvider
     */
    public function testCharsetConversion($unicode_string,
                                          $charset,
                                          $charset_string) {

        $input      = base64_encode($charset_string);
        $stream     = fopen('data://text/plain;base64,' . $input, 'r');
        $filtername = 'convert.mbstring.UTF-8/' . $charset;

        stream_filter_append($stream, $filtername);

        $expected = $unicode_string;
        $result   = stream_get_contents($stream);

        $this->assertSame(
            $expected,
            $result,
            'Failed to decode according to UCM file'
        );
    }

    public function unicodeMappingProvider() {
        foreach (glob(__DIR__ . '/data/*.ucm') as $ucm_filename) {
            yield basename($ucm_filename) => $this->parseUcmFile($ucm_filename);
        }
    }

    public function parseUcmFile($charset_filepath) {
        $charset_filename = basename($charset_filepath, '.ucm');
        $unicode_string   = '';
        $charset_string   = '';

        foreach (file($charset_filepath, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^<U(\w{4})> ((\\\\x\w{2})+)/', $line, $matches)) {
                $unicode_point = $matches[1];
                $hex_sequence  = $matches[2];

                preg_match_all('/\\\x(\w{2})/', $hex_sequence, $matches);

                $hex_codepoints = $matches[1];
                $char_sequence  = array_map('hex2bin', $hex_codepoints);

                $unicode_char = mb_convert_encoding("&#x$unicode_point;",
                                                    'UTF-8',
                                                    'HTML-ENTITIES');

                $unicode_string .= $unicode_char;
                $charset_string .= implode('', $char_sequence);
            }
        }

        return array($unicode_string, $charset_filename, $charset_string);
    }

}
