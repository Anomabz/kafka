<?php

namespace Tests\Unit;

use App\Support\PersonName;
use PHPUnit\Framework\TestCase;

class PersonNameTest extends TestCase
{
    public function test_all_caps_name_becomes_title_case(): void
    {
        $this->assertSame('Admin Sapto Unsri', PersonName::tidy('ADMIN SAPTO UNSRI'));
    }

    public function test_dotted_initials_and_titles_stay_capitalised(): void
    {
        $this->assertSame('Dr. H.M. Irsan Saleh', PersonName::tidy('DR. H.M. IRSAN SALEH'));
    }

    public function test_mixed_case_name_is_left_as_the_owner_spelled_it(): void
    {
        $this->assertSame('Dr. Ir. Siti Aminah, M.T.', PersonName::tidy('Dr. Ir. Siti Aminah, M.T.'));
        $this->assertSame('Muhammad al-Farabi', PersonName::tidy('Muhammad al-Farabi'));
    }

    public function test_empty_and_whitespace_input_is_safe(): void
    {
        $this->assertSame('', PersonName::tidy(''));
        $this->assertSame('', PersonName::tidy('   '));
    }

    public function test_surrounding_whitespace_is_trimmed(): void
    {
        $this->assertSame('Budi Santoso', PersonName::tidy("  BUDI SANTOSO \n"));
    }
}
