<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\DataImport;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveAllocation;
use App\Models\LeaveType;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Services\DataImportService;
use App\Support\ImportTypes;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Moving an old HRMS in from spreadsheets.
 */
class DataImportTest extends TestCase
{
    use RefreshDatabase;

    protected DataImportService $imports;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
        $this->imports = app(DataImportService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Write a CSV to a temporary file and hand back its path. */
    protected function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
        file_put_contents($path, $contents);

        return $path;
    }

    protected function import(string $type, string $contents, bool $commit = true): DataImport
    {
        $import = $this->imports->check($type, $this->csv($contents), 'test.csv');

        return $commit ? $this->imports->apply($import) : $import;
    }

    // -------------------------------------------------------- reading a file

    public function test_a_file_is_read_whatever_the_headers_look_like(): void
    {
        $this->seedReferenceData();

        // Mixed case, a space instead of an underscore, and the name the old
        // system happened to use.
        $import = $this->import(ImportTypes::BRANCHES, <<<'CSV'
            Branch Code,NAME,City
            MUM,Mumbai Office,Mumbai
            CSV);

        $this->assertSame(1, $import->rows_created);
        $this->assertSame('Mumbai Office', Branch::where('code', 'MUM')->value('name'));
    }

    public function test_the_columns_an_old_system_exports_are_recognised(): void
    {
        $this->seedReferenceData();
        $this->defaultCompany();
        $this->makeBranch(['code' => 'BLR']);

        $import = $this->import(ImportTypes::EMPLOYEES, <<<'CSV'
            Emp ID,First Name,Work Email,Branch,DOJ,Reports To
            OLD-1,Rekha,rekha@example.com,BLR,01/04/2023,
            CSV);

        $this->assertSame(1, $import->rows_created);
        $this->assertSame('2023-04-01', Employee::where('employee_code', 'OLD-1')->value('date_of_joining')->toDateString());
    }

    public function test_a_file_saved_by_excel_reads_the_same(): void
    {
        $this->seedReferenceData();

        // A byte-order mark, semicolons, and a blank line at the end.
        $import = $this->import(ImportTypes::BRANCHES, "\xEF\xBB\xBFcode;name\r\nMUM;Mumbai Office\r\n\r\n");

        $this->assertSame(1, $import->rows_total);
        $this->assertSame(1, $import->rows_created);
    }

    public function test_a_file_missing_a_required_column_is_refused_whole(): void
    {
        $this->seedReferenceData();

        $this->expectExceptionMessage('missing a column it needs: name');

        $this->imports->check(ImportTypes::BRANCHES, $this->csv("code\nMUM\n"), 'test.csv');
    }

    public function test_the_row_number_is_the_one_the_spreadsheet_shows(): void
    {
        $this->seedReferenceData();

        $import = $this->import(ImportTypes::BRANCHES, "code,name\nMUM,Mumbai\nBAD,\n", commit: false);

        // Header is row 1, so the empty name is on row 3.
        $this->assertSame(3, $import->errors[0]['line']);
    }

    // ------------------------------------------------------------- branches

    public function test_working_days_are_read_the_way_people_write_them(): void
    {
        $this->seedReferenceData();

        $this->import(ImportTypes::BRANCHES, <<<'CSV'
            code,name,working_days
            AAA,Range,Mon-Fri
            BBB,List,"1,2,3,4,5,6"
            CCC,Names,"Mon, Wed, Fri"
            CSV);

        $this->assertSame([1, 2, 3, 4, 5], Branch::where('code', 'AAA')->first()->workingDays());
        $this->assertSame([1, 2, 3, 4, 5, 6], Branch::where('code', 'BBB')->first()->workingDays());
        $this->assertSame([1, 3, 5], Branch::where('code', 'CCC')->first()->workingDays());
    }

    public function test_a_department_needs_a_branch_that_exists(): void
    {
        $this->seedReferenceData();
        $branch = $this->makeBranch(['code' => 'BLR']);

        $import = $this->import(ImportTypes::DEPARTMENTS, <<<'CSV'
            code,name,branch_code
            ENG,Engineering,BLR
            FIN,Finance,NOPE
            CSV);

        $this->assertSame(1, $import->rows_created);
        $this->assertStringContainsString('no branch with the code "NOPE"', $import->errors[0]['problems'][0]);
        $this->assertTrue(Department::where('branch_id', $branch->id)->where('code', 'ENG')->exists());
    }

    // ------------------------------------------------------------ employees

    public function test_employees_arrive_with_their_branch_and_a_login(): void
    {
        $this->seedReferenceData();
        $this->defaultCompany();
        $this->makeBranch(['code' => 'BLR']);

        $import = $this->import(ImportTypes::EMPLOYEES, <<<'CSV'
            employee_code,first_name,last_name,email,branch_code,date_of_joining
            OLD-1,Rekha,Nair,rekha@example.com,BLR,2023-04-01
            CSV);

        $this->assertSame(1, $import->rows_created);

        $employee = Employee::where('employee_code', 'OLD-1')->first();

        $this->assertSame('Rekha Nair', $employee->full_name);
        $this->assertSame('2023-04-01', $employee->date_of_joining->toDateString());
        $this->assertNotNull($employee->user, 'An imported employee needs a login.');
        $this->assertTrue($employee->user->must_change_password);
    }

    public function test_nobody_is_emailed_during_an_import(): void
    {
        Mail::fake();
        $this->seedReferenceData();
        $this->defaultCompany();
        $this->makeBranch(['code' => 'BLR']);

        $this->import(ImportTypes::EMPLOYEES, <<<'CSV'
            employee_code,first_name,email,branch_code,date_of_joining
            OLD-1,Rekha,rekha@example.com,BLR,2023-04-01
            CSV);

        // Six hundred welcome emails in the middle of a migration is nobody's idea
        // of a good morning.
        Mail::assertNothingQueued();
    }

    public function test_a_manager_may_appear_below_their_reports(): void
    {
        $this->seedReferenceData();
        $this->defaultCompany();
        $this->makeBranch(['code' => 'BLR']);

        $this->import(ImportTypes::EMPLOYEES, <<<'CSV'
            employee_code,first_name,email,branch_code,date_of_joining,manager_code
            OLD-1,Rekha,rekha@example.com,BLR,2023-04-01,OLD-9
            OLD-9,Fatima,fatima@example.com,BLR,2019-06-03,
            CSV);

        $report = Employee::where('employee_code', 'OLD-1')->first();
        $manager = Employee::where('employee_code', 'OLD-9')->first();

        $this->assertSame($manager->id, $report->reporting_to);
    }

    public function test_dates_are_read_day_first(): void
    {
        $this->seedReferenceData();
        $this->defaultCompany();
        $this->makeBranch(['code' => 'BLR']);

        $this->import(ImportTypes::EMPLOYEES, <<<'CSV'
            employee_code,first_name,email,branch_code,date_of_joining,date_of_birth
            OLD-1,Rekha,rekha@example.com,BLR,01/04/2023,15/08/1992
            CSV);

        $employee = Employee::where('employee_code', 'OLD-1')->first();

        $this->assertSame('2023-04-01', $employee->date_of_joining->toDateString());
        $this->assertSame('1992-08-15', $employee->date_of_birth->toDateString());
    }

    public function test_importing_the_same_file_again_updates_rather_than_duplicates(): void
    {
        $this->seedReferenceData();
        $this->defaultCompany();
        $this->makeBranch(['code' => 'BLR']);

        $csv = <<<'CSV'
            employee_code,first_name,email,branch_code,date_of_joining,city
            OLD-1,Rekha,rekha@example.com,BLR,2023-04-01,Bengaluru
            CSV;

        $this->import(ImportTypes::EMPLOYEES, $csv);
        $second = $this->import(ImportTypes::EMPLOYEES, str_replace('Bengaluru', 'Mumbai', $csv));

        $this->assertSame(0, $second->rows_created);
        $this->assertSame(1, $second->rows_updated);
        $this->assertSame(1, Employee::where('employee_code', 'OLD-1')->count());
        $this->assertSame('Mumbai', Employee::where('employee_code', 'OLD-1')->value('city'));
    }

    public function test_the_same_email_twice_in_one_file_is_caught_before_anything_is_written(): void
    {
        $this->seedReferenceData();
        $this->defaultCompany();
        $this->makeBranch(['code' => 'BLR']);

        $import = $this->import(ImportTypes::EMPLOYEES, <<<'CSV'
            employee_code,first_name,email,branch_code,date_of_joining
            OLD-1,Rekha,same@example.com,BLR,2023-04-01
            OLD-2,Sunil,same@example.com,BLR,2023-04-01
            CSV, commit: false);

        $this->assertSame(1, $import->rows_invalid);
        $this->assertStringContainsString('also on row 2', $import->errors[0]['problems'][0]);
    }

    public function test_a_role_is_matched_however_it_is_spelled(): void
    {
        $this->seedReferenceData();
        $this->defaultCompany();
        $this->makeBranch(['code' => 'BLR']);

        $this->import(ImportTypes::EMPLOYEES, <<<'CSV'
            employee_code,first_name,email,branch_code,date_of_joining,role
            OLD-1,Fatima,fatima@example.com,BLR,2019-06-03,HR Manager
            CSV);

        $employee = Employee::where('employee_code', 'OLD-1')->first();

        $this->assertTrue($employee->user->hasRole(Roles::HR_MANAGER));
    }

    // -------------------------------------------------------- leave balances

    public function test_opening_leave_balances_are_loaded(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['employee_code' => 'OLD-1']);
        $type = LeaveType::where('code', 'CL')->firstOrFail();

        $this->import(ImportTypes::LEAVE_BALANCES, <<<'CSV'
            employee_code,leave_type_code,year,allocated_days,carried_forward_days,used_days
            OLD-1,CL,2026,12,2,5
            CSV);

        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', 2026)
            ->first();

        $this->assertSame(12.0, $allocation->allocated_days);
        $this->assertSame(2.0, $allocation->carried_forward_days);
        $this->assertSame(5.0, $allocation->used_days);
        $this->assertSame(9.0, $allocation->remainingDays());
    }

    public function test_a_balance_that_would_go_negative_is_refused(): void
    {
        $this->makeEmployee(Roles::EMPLOYEE, ['employee_code' => 'OLD-1']);

        $import = $this->import(ImportTypes::LEAVE_BALANCES, <<<'CSV'
            employee_code,leave_type_code,year,allocated_days,used_days
            OLD-1,CL,2026,12,14
            CSV, commit: false);

        $this->assertSame(1, $import->rows_invalid);
        $this->assertStringContainsString('negative balance', $import->errors[0]['problems'][0]);
    }

    // ------------------------------------------------------ salary structures

    public function test_pay_arrives_with_its_components(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['employee_code' => 'OLD-1']);
        $basic = SalaryComponent::where('code', 'BASIC')->firstOrFail();

        $this->import(ImportTypes::SALARY_STRUCTURES, <<<'CSV'
            employee_code,effective_from,ctc_annual,basic_salary,component:basic,component:hra
            OLD-1,2026-04-01,"9,90,000","33,000",33000,13200
            CSV);

        $structure = SalaryStructure::where('employee_id', $employee->id)->first();

        // The separators people type into a spreadsheet are not the user's problem.
        $this->assertSame(990000.0, $structure->ctc_annual);
        $this->assertSame(33000.0, $structure->basic_salary);
        $this->assertSame(46200.0, $structure->gross_monthly);
        $this->assertSame(33000.0, $structure->components
            ->firstWhere('salary_component_id', $basic->id)->computed_amount);
    }

    public function test_a_basic_larger_than_the_ctc_is_refused(): void
    {
        $this->makeEmployee(Roles::EMPLOYEE, ['employee_code' => 'OLD-1']);

        $import = $this->import(ImportTypes::SALARY_STRUCTURES, <<<'CSV'
            employee_code,effective_from,ctc_annual,basic_salary
            OLD-1,2026-04-01,300000,90000
            CSV, commit: false);

        $this->assertSame(1, $import->rows_invalid);
        $this->assertStringContainsString('more than the annual CTC', $import->errors[0]['problems'][0]);
    }

    public function test_a_second_structure_closes_the_one_before_it(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, ['employee_code' => 'OLD-1']);

        $this->import(ImportTypes::SALARY_STRUCTURES, "employee_code,effective_from,ctc_annual,basic_salary\nOLD-1,2025-04-01,600000,20000\n");
        $this->import(ImportTypes::SALARY_STRUCTURES, "employee_code,effective_from,ctc_annual,basic_salary\nOLD-1,2026-04-01,720000,24000\n");

        $structures = SalaryStructure::where('employee_id', $employee->id)->orderBy('effective_from')->get();

        $this->assertCount(2, $structures);
        $this->assertSame('2026-03-31', $structures->first()->effective_to->toDateString());
        $this->assertSame('inactive', $structures->first()->status);
        $this->assertNull($structures->last()->effective_to);
    }

    // ---------------------------------------------------- attendance history

    public function test_past_days_are_rebuilt_into_sessions(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE, [
            'employee_code' => 'OLD-1',
            'date_of_joining' => '2023-04-01',
        ]);

        $this->import(ImportTypes::ATTENDANCE, <<<'CSV'
            employee_code,date,check_in,check_out
            OLD-1,2026-06-01,09:28,18:35
            OLD-1,2026-06-02,9:45 AM,6:40 PM
            CSV);

        $day = Attendance::where('employee_id', $employee->id)->whereDate('date', '2026-06-01')->first();

        $this->assertSame('09:28', $day->check_in->format('H:i'));
        $this->assertSame('18:35', $day->check_out->format('H:i'));
        $this->assertSame(1, $day->sessions()->count(), 'An imported day is made of sessions like any other.');
        $this->assertSame('import', $day->source);

        $second = Attendance::where('employee_id', $employee->id)->whereDate('date', '2026-06-02')->first();
        $this->assertSame('09:45', $second->check_in->format('H:i'));
    }

    public function test_a_day_before_somebody_joined_is_refused(): void
    {
        $this->makeEmployee(Roles::EMPLOYEE, [
            'employee_code' => 'OLD-1',
            'date_of_joining' => '2023-04-01',
        ]);

        $import = $this->import(ImportTypes::ATTENDANCE, "employee_code,date,check_in\nOLD-1,2018-01-01,09:00\n", commit: false);

        $this->assertSame(1, $import->rows_invalid);
        $this->assertStringContainsString('before', $import->errors[0]['problems'][0]);
    }

    // ------------------------------------------------------------ the screens

    public function test_the_check_writes_nothing(): void
    {
        $this->seedReferenceData();

        $this->imports->check(ImportTypes::BRANCHES, $this->csv("code,name\nMUM,Mumbai Office\n"), 'test.csv');

        $this->assertFalse(Branch::where('code', 'MUM')->exists(), 'Checking a file must not write anything.');
    }

    public function test_a_file_cannot_be_imported_twice(): void
    {
        $this->seedReferenceData();

        $import = $this->import(ImportTypes::BRANCHES, "code,name\nMUM,Mumbai Office\n");

        $this->expectExceptionMessage('already been imported');
        $this->imports->apply($import);
    }

    public function test_hr_can_upload_check_and_import_from_the_screen(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $file = UploadedFile::fake()->createWithContent('branches.csv', "code,name\nMUM,Mumbai Office\n");

        $response = $this->actingAs($hr->user)
            ->post(route('data-import.store', ImportTypes::BRANCHES), ['file' => $file]);

        $import = DataImport::latest()->first();
        $response->assertRedirect(route('data-import.review', $import));

        $this->assertFalse(Branch::where('code', 'MUM')->exists(), 'Uploading must not import.');

        $this->actingAs($hr->user)
            ->post(route('data-import.commit', $import), ['skip_invalid' => 1])
            ->assertRedirect();

        $this->assertTrue(Branch::where('code', 'MUM')->exists());
    }

    public function test_an_employee_may_not_reach_the_import_screens(): void
    {
        $employee = $this->makeEmployee(Roles::EMPLOYEE);

        $this->actingAs($employee->user)->get(route('data-import.index'))->assertForbidden();
        $this->actingAs($employee->user)
            ->get(route('data-import.show', ImportTypes::EMPLOYEES))
            ->assertForbidden();
    }

    public function test_the_template_carries_every_column_and_an_example(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $response = $this->actingAs($hr->user)
            ->get(route('data-import.template', ImportTypes::EMPLOYEES))
            ->assertOk();

        $body = $response->getContent();

        foreach (ImportTypes::requiredColumns(ImportTypes::EMPLOYEES) as $column) {
            $this->assertStringContainsString($column, $body);
        }
    }

    public function test_the_failed_rows_come_back_as_a_file_to_fix(): void
    {
        $hr = $this->makeEmployee(Roles::HR_MANAGER);

        $import = $this->import(ImportTypes::BRANCHES, "code,name\nMUM,Mumbai\nBAD,\n", commit: false);

        $body = $this->actingAs($hr->user)
            ->get(route('data-import.errors', $import))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('BAD', $body);
        $this->assertStringContainsString('name field is required', $body);
        $this->assertStringNotContainsString('Mumbai', $body, 'Only the rows that need fixing belong in the file.');
    }

    public function test_every_type_in_the_catalogue_has_a_working_importer(): void
    {
        foreach (ImportTypes::keys() as $type) {
            $importer = $this->imports->importer($type);

            $this->assertSame($type, $importer->type());
            $this->assertNotEmpty(ImportTypes::requiredColumns($type), $type.' has no required columns.');
            $this->assertNotEmpty($this->imports->template($type), $type.' has no template.');
        }
    }
}
