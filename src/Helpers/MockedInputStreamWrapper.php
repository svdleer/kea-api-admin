<?php

namespace App\Helpers;

/**
 * Stream wrapper to mock php://input for testing API controllers
 */
class MockedInputStreamWrapper
{
    private static $data = '';
    private $position = 0;

    public static function setData($data)
    {
        self::$data = $data;
    }

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->position = 0;
        return true;
    }

    public function stream_read($count)
    {
        $ret = substr(self::$data, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_write($data)
    {
        self::$data = $data;
        return strlen($data);
    }

    public function stream_eof()
    {
        return $this->position >= strlen(self::$data);
    }

    public function stream_tell()
    {
        return $this->position;
    }

    public function stream_stat()
    {
        return [];
    }

    public function url_stat($path, $flags)
    {
        return [];
    }
}
