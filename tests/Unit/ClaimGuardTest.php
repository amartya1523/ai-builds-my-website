<?php

namespace Tests\Unit;

use App\Services\Build\ClaimGuard;
use PHPUnit\Framework\TestCase;

class ClaimGuardTest extends TestCase
{
    public function test_keeps_supported_sentences_and_drops_invented_ones(): void
    {
        $guard = new ClaimGuard("- about: Family-owned sweet shop in Jaipur\n- price_item: Ghewar — Rs 450");
        $r = $guard->filter('A family-owned sweet shop in Jaipur. Our ghewar costs Rs 450. Rated 4.8 by 2,000 customers. The best in town.');

        $this->assertSame('A family-owned sweet shop in Jaipur. Our ghewar costs Rs 450.', $r['text']);
        $this->assertCount(2, $r['removed']);
    }

    public function test_never_lets_contact_details_through_free_text(): void
    {
        $guard = new ClaimGuard('- email: a@b.com');
        $this->assertFalse($guard->supported('Write to a@b.com for orders.'));
    }
}
