<?php

namespace Tests\Unit;

use App\Services\Facts\Normalize;
use PHPUnit\Framework\TestCase;

class NormalizeTest extends TestCase
{
    public function test_same_indian_number_in_different_formats_is_one_number(): void
    {
        $k = Normalize::phoneKey('+91 98290 12345');
        $this->assertSame($k, Normalize::phoneKey('098290-12345'));
        $this->assertSame($k, Normalize::phoneKey('9829012345'));
        $this->assertSame('+91 98290 12345', Normalize::phone('09829012345'));
    }

    public function test_landline_with_std_code(): void
    {
        $this->assertSame('+91 141 2565599', Normalize::phone('0141-2565599'));
    }
}
