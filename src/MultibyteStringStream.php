<?php declare(strict_types=1);

class MultibyteStringStream extends php_user_filter {

    const NON_CHARACTER = "\xFF\xFF";

    // @var string
    public $filtername;

    // @var mixed
    public $params;

    // @var resource
    public $stream;

    // @var string
    private $to_encoding;

    // @var string
    private $from_encoding;

    // @var int
    private $prev_mb_substitute_character;

    // @var string
    private $buffer;

    public function onCreate() {
        $conversion_part = substr($this->filtername, 17);
        $conversion_part = explode('/', $conversion_part);

        $to_encoding   = $conversion_part[0];
        $from_encoding = $conversion_part[1] ?? mb_internal_encoding();

        $encodings = mb_list_encodings();
        $aliases   = array_map('mb_encoding_aliases', $encodings);

        $valid_encodings = array_merge($encodings, ...$aliases);

        if (!in_array($to_encoding, $valid_encodings) ||
            !in_array($from_encoding, $valid_encodings)) {

            return false;
        }

        $this->prev_mb_substitute_character = mb_substitute_character();

        if (is_int($this->params) || is_string($this->params)) {
            mb_substitute_character($this->params);
        }

        $this->to_encoding   = $to_encoding;
        $this->from_encoding = $from_encoding;
    }

    public function onClose() {
        mb_substitute_character($this->prev_mb_substitute_character);
    }

    public function filter($in, $out, &$consumed, $closing) {
        $buffer  = &$this->buffer;
        $pass_on = false;

        while ($bucket = stream_bucket_make_writeable($in)) {
            $encoded_data = $buffer . $bucket->data;
            $valid_chars  = $this->truncateInvalidCharacters($encoded_data);
            $buffer       = substr($encoded_data, strlen($valid_chars));

            $consumed += $bucket->datalen;

            if (!empty($valid_chars)) {
                $bucket->data = $this->convert($valid_chars, $this->to_encoding);

                stream_bucket_append($out, $bucket);

                $pass_on = true;
            }
        }

        if ($closing && !empty($buffer)) {
            $stream = isset($this->stream) && is_resource($this->stream) ?
                $this->stream : fopen('php://memory', 'r');

            $remaining = $this->convert($buffer);
            $bucket    = stream_bucket_new($stream, $remaining);
            $buffer    = '';

            stream_bucket_append($out, $bucket);

            $pass_on = true;
        }

        return $pass_on ? PSFS_PASS_ON : PSFS_FEED_ME;
    }

    private function truncateInvalidCharacters($data) {
        $padded_data = $data . self::NON_CHARACTER;

        return mb_strcut($padded_data, 0, strlen($data), $this->from_encoding);
    }

    private function convert($data) {
        return mb_convert_encoding(
            $data,
            $this->to_encoding,
            $this->from_encoding
        );
    }

    public static function registerStreamFilter() {
        stream_filter_register('convert.mbstring.*', __CLASS__);
    }

}
