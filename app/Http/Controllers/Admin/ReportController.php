<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
    ) {}

    public function index(Request $request): View|StreamedResponse
    {
        $validated = Validator::make($request->query(), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'daily_page' => ['nullable', 'integer', 'min:1'],
            'stock_page' => ['nullable', 'integer', 'min:1'],
            'menu_page' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $branches = Cabang::query()->orderBy('nama_cabang')->get(['cabang_id', 'nama_cabang']);
        $rawCabangId = $request->query('cabang_id');
        $selectedBranch = is_string($rawCabangId) && ctype_digit($rawCabangId)
            ? $branches->firstWhere('cabang_id', (int) $rawCabangId)
            : null;
        $cabangId = $selectedBranch?->cabang_id;

        $branchComparison = collect($this->reports->branchComparison($from, $to))
            ->when($cabangId !== null, fn (Collection $rows) => $rows->where('cabang_id', $cabangId))
            ->values();

        $dailyRows = collect($this->reports->dailyProfit($cabangId, $from, $to));
        $stockRows = collect($this->reports->stockMutations($cabangId, $from, $to));
        $menuRows = collect($this->reports->menuSales($cabangId, $from, $to));

        $totals = [
            'transactions' => (int) $dailyRows->sum('count'),
            'omzet' => (float) $dailyRows->sum('omzet'),
            'hpp' => (float) $dailyRows->sum('hpp'),
            'waste' => (float) $dailyRows->sum('waste'),
            'laba' => (float) $dailyRows->sum('laba'),
        ];
        $totals['margin'] = $totals['omzet'] > 0
            ? ($totals['laba'] / $totals['omzet']) * 100
            : 0.0;

        if ($request->boolean('export')) {
            return $this->exportCsv(
                $branchComparison,
                $dailyRows,
                $stockRows,
                $menuRows,
                $selectedBranch?->nama_cabang ?? 'Semua Cabang',
                $from,
                $to,
            );
        }

        return view('admin.laporan.index', [
            'branches' => $branches,
            'selectedCabangId' => $cabangId,
            'selectedBranchName' => $selectedBranch?->nama_cabang ?? 'Semua Cabang',
            'from' => $from,
            'to' => $to,
            'totals' => $totals,
            'branchRows' => $branchComparison,
            'dailyRows' => $this->paginateRows($dailyRows, 'daily_page', $request, 10),
            'stockRows' => $this->paginateRows($stockRows, 'stock_page', $request, 10),
            'menuRows' => $this->paginateRows($menuRows, 'menu_page', $request, 10),
        ]);
    }

    private function paginateRows(
        Collection $rows,
        string $pageName,
        Request $request,
        int $perPage,
    ): LengthAwarePaginator {
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->except($pageName),
                'pageName' => $pageName,
            ],
        );
    }

    private function sanitizeCsvCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    private function sanitizeCsvRow(array $row): array
    {
        return array_map(fn (mixed $cell): mixed => $this->sanitizeCsvCell($cell), $row);
    }

    private function exportCsv(
        Collection $branchRows,
        Collection $dailyRows,
        Collection $stockRows,
        Collection $menuRows,
        string $scopeName,
        string $from,
        string $to,
    ): StreamedResponse {
        $slugScope = str($scopeName)->slug()->toString() ?: 'semua-cabang';

        return response()->streamDownload(
            function () use ($branchRows, $dailyRows, $stockRows, $menuRows, $scopeName, $from, $to): void {
                $output = fopen('php://output', 'w');

                fwrite($output, "\xEF\xBB\xBF");

                fputcsv($output, $this->sanitizeCsvRow(['LAPORAN OPERASIONAL ADMIN KOPSYNC']), ';');
                fputcsv($output, $this->sanitizeCsvRow(['Cakupan', $scopeName]), ';');
                fputcsv($output, $this->sanitizeCsvRow(['Periode', $from.' s.d. '.$to]), ';');
                fputcsv($output, [], ';');

                fputcsv($output, $this->sanitizeCsvRow(['PERBANDINGAN CABANG']), ';');
                fputcsv($output, $this->sanitizeCsvRow([
                    'Cabang',
                    'Jumlah Transaksi',
                    'Omzet',
                    'HPP',
                    'Waste',
                    'Estimasi Laba',
                    'Margin (%)',
                ]), ';');

                foreach ($branchRows as $row) {
                    fputcsv($output, $this->sanitizeCsvRow([
                        $row['nama_cabang'],
                        $row['count'],
                        round($row['omzet'], 2),
                        round($row['hpp'], 2),
                        round($row['waste'], 2),
                        round($row['laba'], 2),
                        round($row['margin'], 2),
                    ]), ';');
                }

                fputcsv($output, [], ';');
                fputcsv($output, $this->sanitizeCsvRow(['LABA RUGI HARIAN']), ';');
                fputcsv($output, $this->sanitizeCsvRow([
                    'Tanggal',
                    'Jumlah Transaksi',
                    'Omzet',
                    'HPP',
                    'Waste',
                    'Jumlah Kejadian Waste',
                    'Estimasi Laba',
                    'Margin (%)',
                ]), ';');

                foreach ($dailyRows as $row) {
                    fputcsv($output, $this->sanitizeCsvRow([
                        $row['date'],
                        $row['count'],
                        round($row['omzet'], 2),
                        round($row['hpp'], 2),
                        round($row['waste'], 2),
                        $row['waste_count'],
                        round($row['laba'], 2),
                        round($row['margin'], 2),
                    ]), ';');
                }

                fputcsv($output, [], ';');
                fputcsv($output, $this->sanitizeCsvRow(['MUTASI STOK PER BAHAN']), ';');
                fputcsv($output, $this->sanitizeCsvRow([
                    'ID Bahan',
                    'Nama Bahan',
                    'Satuan',
                    'Total Stok Masuk',
                    'Total Stok Keluar',
                    'Total Waste',
                    'Stok Saat Ini',
                ]), ';');

                foreach ($stockRows as $row) {
                    fputcsv($output, $this->sanitizeCsvRow([
                        $row['bahan_id'],
                        $row['name'],
                        $row['satuan'],
                        round($row['masuk'], 3),
                        round($row['keluar'], 3),
                        round($row['waste'], 3),
                        round($row['stok_saat_ini'], 3),
                    ]), ';');
                }

                fputcsv($output, [], ';');
                fputcsv($output, $this->sanitizeCsvRow(['PENJUALAN PER MENU']), ';');
                fputcsv($output, $this->sanitizeCsvRow([
                    'ID Menu',
                    'Nama Menu',
                    'Jumlah Terjual',
                    'Total Omzet',
                    'Total HPP',
                    'Estimasi Laba',
                ]), ';');

                foreach ($menuRows as $row) {
                    fputcsv($output, $this->sanitizeCsvRow([
                        $row['menu_id'],
                        $row['name'],
                        $row['qty'],
                        round($row['omzet'], 2),
                        round($row['hpp'], 2),
                        round($row['laba'], 2),
                    ]), ';');
                }

                fclose($output);
            },
            "laporan-admin-{$slugScope}-{$from}-{$to}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
