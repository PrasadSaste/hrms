<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayslipResource;
use App\Models\Payslip;
use App\Services\PayslipPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PayslipApiController extends Controller
{
    public function __construct(protected PayslipPdfService $pdf) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payslip::class);

        $user = $request->user();
        $canViewAll = $user->can('payslips.view-all');

        $payslips = Payslip::with(['employee', 'payroll'])
            ->when(! $canViewAll, fn ($q) => $q->where('employee_id', $user->employee?->id ?? 0)->published())
            ->when($request->integer('year'), fn ($q, $v) => $q->whereYear('period_start', $v))
            ->when($request->integer('employee_id') && $canViewAll, fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->orderByDesc('period_start')
            ->paginate($request->integer('per_page') ?: 12);

        return response()->json([
            'data' => PayslipResource::collection($payslips->items()),
            'meta' => [
                'current_page' => $payslips->currentPage(),
                'last_page' => $payslips->lastPage(),
                'total' => $payslips->total(),
            ],
        ]);
    }

    public function show(Payslip $payslip): JsonResponse
    {
        $this->authorize('view', $payslip);

        return response()->json([
            'data' => new PayslipResource($payslip->load(['employee', 'earnings', 'deductions'])),
        ]);
    }

    public function download(Payslip $payslip): Response
    {
        $this->authorize('download', $payslip);

        return $this->pdf->make($payslip)->download($this->pdf->filename($payslip));
    }
}
