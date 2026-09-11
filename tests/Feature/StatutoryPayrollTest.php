<?php

namespace Tests\Feature;

use App\Models\SalaryComponent;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rules that make a statutory deduction right.
 *
 * A flat percentage is not what any of the three are, and the difference only
 * shows on somebody well paid — which is exactly who was being over-charged.
 */
class StatutoryPayrollTest extends TestCase
{
    use RefreshDatabase;

    /** A deduction read from a slab table. */
    protected function make(string $code, ?array $slabs): SalaryComponent
    {
        return SalaryComponent::create([
            'name' => 'Test '.$code,
            'code' => strtoupper($code),
            'type' => 'deduction',
            'calculation_type' => 'slab',
            'percentage_of' => 'gross',
            'default_value' => 0,
            'slabs' => $slabs,
            'sequence' => 90,
            'status' => 'active',
        ]);
    }

    /** Named for the rule, not "component": TestCase already has that one. */
    protected function statutory(string $code): SalaryComponent
    {
        $this->seedReferenceData();

        return SalaryComponent::where('code', $code)->firstOrFail();
    }

    // ------------------------------------------------------- provident fund

    public function test_provident_fund_is_taken_on_a_capped_wage(): void
    {
        $pf = $this->statutory('PF');

        // Below the cap, the whole basic is used.
        $this->assertSame(1200.0, $pf->resolveAmount(12, 'percentage', ['basic' => 10000]));

        // At and above it, everybody contributes on the cap.
        $this->assertSame(1800.0, $pf->resolveAmount(12, 'percentage', ['basic' => 15000]));
        $this->assertSame(1800.0, $pf->resolveAmount(12, 'percentage', ['basic' => 40000]));
        $this->assertSame(1800.0, $pf->resolveAmount(12, 'percentage', ['basic' => 184000]));
    }

    public function test_a_component_with_no_ceiling_is_unchanged(): void
    {
        $basic = $this->statutory('BASIC');

        // The rules are opt-in: an ordinary component behaves as it always did.
        $this->assertNull($basic->wage_ceiling);
        $this->assertSame(20000.0, $basic->resolveAmount(50, 'percentage', ['ctc' => 40000]));
    }

    // ------------------------------------------------ employee state insurance

    public function test_state_insurance_stops_applying_above_its_ceiling(): void
    {
        $esi = $this->statutory('ESI');

        $this->assertSame(112.5, $esi->resolveAmount(0.75, 'percentage', ['gross' => 15000]));
        $this->assertSame(157.5, $esi->resolveAmount(0.75, 'percentage', ['gross' => 21000]));

        // A rupee over and they are outside the scheme — not charged on a
        // capped wage, which is the mistake a wage ceiling would make here.
        $this->assertSame(0.0, $esi->resolveAmount(0.75, 'percentage', ['gross' => 21001]));
        $this->assertSame(0.0, $esi->resolveAmount(0.75, 'percentage', ['gross' => 90000]));
    }

    // ------------------------------------------------------ professional tax

    public function test_professional_tax_reads_its_slab_table(): void
    {
        $pt = $this->statutory('PT');

        $this->assertSame('slab', $pt->calculation_type);
        $this->assertSame(0.0, $pt->resolveAmount(0, 'slab', ['gross' => 15000]));
        $this->assertSame(0.0, $pt->resolveAmount(0, 'slab', ['gross' => 24999]));
        $this->assertSame(200.0, $pt->resolveAmount(0, 'slab', ['gross' => 25000]));
        $this->assertSame(200.0, $pt->resolveAmount(0, 'slab', ['gross' => 370622]));
    }

    public function test_the_top_band_has_no_ceiling(): void
    {
        $component = $this->make('slab', [
            ['up_to' => 10000, 'amount' => 0],
            ['up_to' => 20000, 'amount' => 150],
            ['up_to' => null, 'amount' => 300],
        ]);

        $this->assertSame(0.0, $component->fromSlabs(9999));
        $this->assertSame(150.0, $component->fromSlabs(20000));
        $this->assertSame(300.0, $component->fromSlabs(20001));
        $this->assertSame(300.0, $component->fromSlabs(9999999));
    }

    public function test_a_slab_table_with_no_bands_charges_nothing(): void
    {
        $component = $this->make('nobands', null);

        $this->assertSame(0.0, $component->resolveAmount(0, 'slab', ['gross' => 50000]));
    }

    // ------------------------------------------------------------ the screen

    public function test_the_bands_are_stored_lowest_first_whatever_order_they_are_typed(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        // The first band a wage does not exceed decides the amount, so an
        // unsorted table would charge the wrong one.
        $this->actingAs($admin->user)->post(route('salary-components.store'), [
            'name' => 'State tax', 'code' => 'STX', 'type' => 'deduction',
            'calculation_type' => 'slab', 'percentage_of' => 'gross', 'default_value' => 0,
            'sequence' => 20, 'status' => 'active',
            'slabs' => [
                ['up_to' => '', 'amount' => 300],
                ['up_to' => 10000, 'amount' => 0],
                ['up_to' => 20000, 'amount' => 150],
            ],
        ])->assertRedirect();

        $slabs = SalaryComponent::where('code', 'STX')->firstOrFail()->slabs;

        $this->assertSame([10000, 20000, null], array_map(
            fn ($value) => $value === null ? null : (int) $value,
            array_column($slabs, 'up_to'),
        ));
        $this->assertSame([0, 150, 300], array_map('intval', array_column($slabs, 'amount')));
    }

    public function test_empty_rows_are_not_stored(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->post(route('salary-components.store'), [
            'name' => 'State tax', 'code' => 'STX2', 'type' => 'deduction',
            'calculation_type' => 'slab', 'percentage_of' => 'gross', 'default_value' => 0,
            'sequence' => 21, 'status' => 'active',
            'slabs' => [
                ['up_to' => 10000, 'amount' => 0],
                ['up_to' => '', 'amount' => ''],
            ],
        ])->assertRedirect();

        $this->assertCount(1, SalaryComponent::where('code', 'STX2')->firstOrFail()->slabs);
    }

    public function test_a_slab_component_needs_a_base_to_read(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->post(route('salary-components.store'), [
            'name' => 'State tax', 'code' => 'STX3', 'type' => 'deduction',
            'calculation_type' => 'slab', 'default_value' => 0,
            'sequence' => 22, 'status' => 'active',
        ])->assertSessionHasErrors('percentage_of');
    }

    public function test_the_ceilings_can_be_set_from_the_screen(): void
    {
        $admin = $this->makeEmployee(Roles::SUPER_ADMIN);

        $this->actingAs($admin->user)->post(route('salary-components.store'), [
            'name' => 'Pension', 'code' => 'PEN', 'type' => 'deduction',
            'calculation_type' => 'percentage', 'percentage_of' => 'basic', 'default_value' => 8,
            'wage_ceiling' => 15000, 'eligibility_ceiling' => 50000,
            'statutory_note' => 'Checked against the 2026 rules.',
            'sequence' => 23, 'status' => 'active',
        ])->assertRedirect();

        $component = SalaryComponent::where('code', 'PEN')->firstOrFail();

        $this->assertSame(15000.0, $component->wage_ceiling);
        $this->assertSame(50000.0, $component->eligibility_ceiling);
        $this->assertTrue($component->hasStatutoryRules());
    }
}
