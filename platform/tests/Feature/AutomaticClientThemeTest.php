<?php

namespace Tests\Feature;

use App\Services\ClientTheme;
use PHPUnit\Framework\TestCase;

class AutomaticClientThemeTest extends TestCase
{
    public function test_shared_fixtures_match_and_all_roles_meet_contrast(): void
    {
        $theme = new ClientTheme;
        $fixtures = json_decode(file_get_contents(__DIR__.'/../Fixtures/client-palette-v1.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertEqualsWithDelta(21, $theme->contrast('#000000', '#ffffff'), 0.000001);
        foreach ($fixtures as $source => $expected) {
            $this->assertSame($expected, $theme->generate($source));
            $this->assertSame($expected, $theme->generate(strtoupper($source)));
            foreach ($expected as $palette) {
                foreach (['background', 'card', 'raised', 'hover', 'soft'] as $surface) {
                    foreach (['primary' => 4.5, 'focus' => 4.5, 'text' => 7, 'muted' => 4.5, 'line' => 3] as $role => $minimum) {
                        $this->assertGreaterThanOrEqual($minimum, $theme->contrast($palette[$role], $palette[$surface]), "$source $role / $surface");
                    }
                }
                $this->assertLessThan($theme->contrast($palette['line'], $palette['card']), $theme->contrast($palette['decorative'], $palette['card']));
                foreach (['primary', 'primary-hover'] as $button) {
                    $this->assertGreaterThanOrEqual(4.5, $theme->contrast($palette['on-primary'], $palette[$button]), "$source button text");
                }
            }
        }
    }

    public function test_representative_colour_cube_generates_accessible_button_states(): void
    {
        $theme = new ClientTheme;
        foreach ([0, 64, 128, 192, 255] as $red) {
            foreach ([0, 64, 128, 192, 255] as $green) {
                foreach ([0, 64, 128, 192, 255] as $blue) {
                    foreach ($theme->generate(sprintf('#%02x%02x%02x', $red, $green, $blue)) as $palette) {
                        $this->assertGreaterThanOrEqual(4.5, $theme->contrast($palette['primary'], $palette['card']));
                        $this->assertGreaterThanOrEqual(4.5, $theme->contrast($palette['on-primary'], $palette['primary-hover']));
                    }
                }
            }
        }
    }
}
