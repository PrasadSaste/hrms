<?php

namespace App\Support;

/**
 * The full permission catalogue. Grouped by module so the roles & permissions
 * screen can render checkboxes without hard-coding anything in a Blade view.
 */
final class Permissions
{
    /** @return array<string, array<string, string>> module => [permission => label] */
    public static function catalogue(): array
    {
        return [
            'Dashboard' => [
                'dashboard.view' => 'View dashboard',
                'dashboard.view-org-stats' => 'View organisation-wide statistics',
            ],
            'Companies' => [
                'companies.view' => 'View payroll companies',
                'companies.manage' => 'Manage payroll companies',
            ],
            'Branches' => [
                'branches.view' => 'View branches',
                'branches.create' => 'Create branches',
                'branches.update' => 'Update branches',
                'branches.delete' => 'Delete branches',
            ],
            'Departments' => [
                'departments.view' => 'View departments',
                'departments.create' => 'Create departments',
                'departments.update' => 'Update departments',
                'departments.delete' => 'Delete departments',
            ],
            'Designations' => [
                'designations.view' => 'View designations',
                'designations.create' => 'Create designations',
                'designations.update' => 'Update designations',
                'designations.delete' => 'Delete designations',
            ],
            'Background verification' => [
                'bgv.complete-own' => 'Complete my own background verification',
                'bgv.view' => 'View background verification cases',
                'bgv.manage' => 'Invite, review and clear background verification',
            ],
            'Employees' => [
                'employees.view' => 'View employees',
                'employees.view-any-branch' => 'View employees across all branches',
                'employees.create' => 'Create employees',
                'employees.update' => 'Update employees',
                'employees.delete' => 'Delete employees',
                'employees.manage-documents' => 'Manage employee documents',
                'employees.view-salary' => 'View employee salary details',
            ],
            'Assets' => [
                'assets.view-own' => 'View assets issued to me',
                'assets.view' => 'View the asset register',
                'assets.manage' => 'Add, edit and retire assets',
                'assets.assign' => 'Issue assets to people and take them back',
            ],
            'Attendance' => [
                'attendance.punch' => 'Check in and check out',
                'attendance.view-own' => 'View own attendance',
                'attendance.view-team' => 'View team attendance',
                'attendance.view-all' => 'View all attendance',
                'attendance.manage' => 'Add and edit attendance records',
                'attendance.approve-regularization' => 'Approve attendance regularisation',
            ],
            'Leave' => [
                'leave.apply' => 'Apply for leave',
                'leave.view-own' => 'View own leave',
                'leave.view-team' => 'View team leave',
                'leave.view-all' => 'View all leave',
                'leave.approve' => 'Approve or reject leave',
                'leave.manage-types' => 'Manage leave types',
                'leave.manage-allocations' => 'Manage leave allocations',
            ],
            'Payroll' => [
                'payroll.view' => 'View payroll runs',
                'payroll.create' => 'Create and generate payroll runs',
                'payroll.update' => 'Update payroll runs',
                'payroll.delete' => 'Delete payroll runs',
                'payroll.approve' => 'Approve payroll runs',
                'payroll.mark-paid' => 'Mark payroll as paid',
                'payroll.bank-file' => 'Download the bank payment file',
                'payroll.manage-components' => 'Manage salary components',
                'payroll.manage-structures' => 'Manage salary structures',
            ],
            'Income tax' => [
                'tax.declare' => 'Declare my own investments',
                'tax.view' => 'View everybody’s declarations and tax',
                'tax.verify' => 'Verify declarations against their proof',
            ],
            'Settlements' => [
                'settlements.view-own' => 'View my own settlement',
                'settlements.view' => 'View settlements',
                'settlements.manage' => 'Prepare and edit settlements',
                'settlements.approve' => 'Approve a settlement and mark it paid',
            ],
            'Payslips' => [
                'payslips.view-own' => 'View own payslips',
                'payslips.view-all' => 'View all payslips',
                'payslips.download' => 'Download payslip PDFs',
                'payslips.email' => 'Email payslips to employees',
            ],
            'Holidays' => [
                'holidays.view' => 'View holidays',
                'holidays.manage' => 'Manage holidays',
            ],
            'Shifts' => [
                'shifts.view' => 'View shifts',
                'shifts.manage' => 'Manage shifts',
            ],
            'Announcements' => [
                'announcements.view' => 'View announcements',
                'announcements.manage' => 'Manage announcements',
            ],
            'Letters' => [
                'letters.view-own' => 'View own letters',
                'letters.view' => 'View letters issued to anyone',
                'letters.issue' => 'Issue letters',
                'letters.manage-templates' => 'Edit the wording of letters',
            ],
            'Reports' => [
                'reports.attendance' => 'Attendance reports',
                'reports.leave' => 'Leave reports',
                'reports.payroll' => 'Payroll reports',
                'reports.employees' => 'Employee reports',
            ],
            'Administration' => [
                'users.view' => 'View user accounts',
                'users.manage' => 'Manage user accounts',
                'roles.view' => 'View roles and permissions',
                'roles.manage' => 'Manage roles and permissions',
                'settings.view' => 'View settings',
                'settings.manage' => 'Manage settings',
                'notifications.manage' => 'Manage email and in-app notifications',
                'automations.manage' => 'See what the system does on its own, and change it',
                'help.manage' => 'Write and edit the Self Assistance guides',
                'data.import' => 'Import data from a spreadsheet',
                'activity.view' => 'View activity log',
            ],
        ];
    }

    /** @return array<int, string> */
    public static function all(): array
    {
        return collect(self::catalogue())->flatMap(fn (array $group) => array_keys($group))->values()->all();
    }

    public static function label(string $permission): string
    {
        foreach (self::catalogue() as $group) {
            if (isset($group[$permission])) {
                return $group[$permission];
            }
        }

        return $permission;
    }

    /** Default permission set granted to each role on install. */
    public static function forRole(string $role): array
    {
        return match ($role) {
            Roles::SUPER_ADMIN => self::all(),

            Roles::HR_MANAGER => array_values(array_diff(self::all(), [
                'roles.manage',
                'payroll.approve',
                'settings.manage',
                'notifications.manage',
                'automations.manage',
            ])),

            Roles::BRANCH_MANAGER => [
                'dashboard.view',
                'bgv.complete-own', 'bgv.view',
                'companies.view',
                'branches.view',
                'departments.view',
                'designations.view',
                'employees.view', 'employees.update', 'employees.manage-documents',
                'assets.view-own', 'assets.view', 'assets.assign',
                'settlements.view-own',
                'attendance.punch', 'attendance.view-own', 'attendance.view-team',
                'attendance.manage', 'attendance.approve-regularization',
                'letters.view-own',
                'leave.apply', 'leave.view-own', 'leave.view-team', 'leave.approve',
                'payslips.view-own', 'payslips.download',
                'tax.declare',
                'holidays.view', 'shifts.view',
                'announcements.view', 'announcements.manage',
                'reports.attendance', 'reports.leave', 'reports.employees',
            ],

            Roles::ACCOUNTANT => [
                'dashboard.view', 'dashboard.view-org-stats',
                'bgv.complete-own',
                'companies.view',
                'branches.view', 'departments.view', 'designations.view',
                'employees.view', 'employees.view-any-branch', 'employees.view-salary',
                'assets.view-own', 'assets.view',
                'attendance.punch', 'attendance.view-own', 'attendance.view-all',
                'leave.apply', 'leave.view-own', 'leave.view-all',
                'letters.view-own', 'letters.view',
                'payroll.view', 'payroll.create', 'payroll.update', 'payroll.delete',
                'payroll.mark-paid', 'payroll.bank-file',
                'payroll.manage-components', 'payroll.manage-structures',
                'settlements.view-own', 'settlements.view', 'settlements.manage',
                'payslips.view-own', 'payslips.view-all', 'payslips.download', 'payslips.email',
                'tax.declare', 'tax.view', 'tax.verify',
                'holidays.view', 'shifts.view', 'announcements.view',
                'reports.attendance', 'reports.payroll', 'reports.employees',
            ],

            Roles::EMPLOYEE => [
                'dashboard.view',
                'bgv.complete-own',
                'assets.view-own',
                'settlements.view-own',
                'attendance.punch', 'attendance.view-own',
                'leave.apply', 'leave.view-own',
                'letters.view-own',
                'payslips.view-own', 'payslips.download',
                'tax.declare',
                'holidays.view',
                'announcements.view',
            ],

            default => [],
        };
    }
}
