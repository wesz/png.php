<?php

define('MAX_DEFLATE', 0xffff);

class png
{
	public static function rgb($r, $g, $b)
	{
		return chr($r).chr($g).chr($b);
	}

	public static function rgba($r, $g, $b, $a)
	{
		return chr($r).chr($g).chr($b).chr($a);
	}

	public static function be32($n)
	{
		return chr(($n >> 24) & 0xFF).chr(($n >> 16) & 0xFF).chr(($n >>  8) & 0xFF).chr(($n >>  0) & 0xFF);
	}

	public static function chunk($ty, $data)
	{
		return self::be32(strlen($data)).$ty.$data.self::be32(crc32($ty.$data));
	}

	public static function header($width, $height)
	{
		return self::chunk("IHDR", self::be32($width).self::be32($height).chr(8).chr(2).chr(0).chr(0).chr(0));
	}

	public static function deflate_block($data, $last = false)
	{
		$n = strlen($data);

		return chr($last ? 1 : 0).pack('vv', $n, 0xffff ^ $n).$data;
	}

	public static function pieces($seq, $n)
	{
		$s = array();
		$l = strlen($seq);

		for ($i = 0; $i < $l; $i += $n)
		{
			$s[] = substr($seq, $i, $n);
		}

		return $s;
	}

	public static function zlib_stream($data)
	{
		$segments = self::pieces($data, MAX_DEFLATE);
		$blocks = '';
		$c = count($segments);

		for ($i = 0; $i < $c - 1; $i++)
		{
			$blocks .= self::deflate_block($segments[$i]);
		}

		$blocks .= self::deflate_block($segments[$c - 1], true);

		return "\x78\x01".$blocks.self::be32(self::adler32($data));
	}

	public static function adler32($data)
	{
		$s1 = 1;
		$s2 = 0;
		$l = strlen($data);

		for ($i = 0; $i < $l; $i++)
		{
			$s1 = ($s1 + ord($data[$i])) % 65521;
			$s2 = ($s2 + $s1) % 65521;
		}

		return ($s2 << 16) + $s1;
	}

	public static function make($width, $height, $data)
	{
		$pieces = self::pieces($data, 3 * $width);
		$lines = '';

		foreach ($pieces as $p)
		{
			$lines .= "\0".$p;
		}

		return "\x89PNG\r\n\x1a\n".self::header($width, $height).self::chunk("IDAT", self::zlib_stream($lines)).self::chunk("IEND", '');
	}
}

?>
