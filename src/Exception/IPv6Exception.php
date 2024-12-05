<?php
namespace App\Exception;

class IPv6Exception extends \Exception
{
    public static function invalidAddress(string $address): self
    {
        return new self("Invalid IPv6 address: $address");
    }

    public static function invalidPrefix(string $prefix, int $length): self
    {
        return new self("Invalid IPv6 prefix: $prefix/$length");
    }

    public static function invalidRange(string $start, string $end): self
    {
        return new self("Invalid IPv6 range: $start - $end");
    }

    public static function prefixMismatch(string $subnet, string $prefix): self
    {
        return new self("Subnet $subnet is not within prefix $prefix");
    }
}
