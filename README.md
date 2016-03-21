# Multibyte String Stream Filter [![Build Status](https://travis-ci.org/ericnorris/mbstring-stream.svg?branch=master)](https://travis-ci.org/ericnorris/mbstring-stream)
A `php_user_filter` implementation for quickly and safely converting stream character sets.

## Example
```php
// Not required if the file was autoloaded (e.g. using composer)
MultibyteStringStream::registerStreamFilter();

$native_file = fopen('iso-8859-1-file.txt', 'r');

stream_filter_append($native_file, 'convert.mbstring.UTF-8/ISO-8859-1');

$unicode_file = fopen('utf8-file.txt', 'w');

stream_copy_to_stream($native_file, $unicode_file);
```

mbstring-stream also works as a write filter:
```php
$native_file  = fopen('sjis-file.txt', 'r');
$unicode_file = fopen('utf8-file.txt', 'w');

stream_filter_append($unicode_file, 'convert.mbstring.UTF-8/SJIS');

stream_copy_to_stream($native_file, $unicode_file);
```

## Usage
```php
/**
 * resource   $stream`        The stream to filter.
 * string     $to_encoding`   The encoding to convert to.
 * string     $from_encoding` The encoding to convert from. Optional, defaults to mb_internal_encoding()
 * int        $read_write`    See http://php.net/manual/en/function.stream-filter-append.php
 * string|int $sub_char`      The substitute character to use. Optional, defaults to mb_substitute_character()
 */
stream_filter_append($stream, "convert.mbstring.$to_encoding/$from_encoding", $read_write, $sub_char);
```
Note: Be careful when using on streams in 'r+' or 'w+' (or similar) modes; by default PHP will assign the filter to both the reading and writing chain. This means it will attempt to convert the data twice - once when writing to the stream, and once when reading from it.
