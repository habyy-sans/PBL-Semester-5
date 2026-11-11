<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports
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

        $from = $validated['from']
            ?? now()->startOfMonth()->toDateString();

        $to = $validated['to']
            ?? now()->toDateString();

        $cabangId = (int) $request->user()->cabang_id;

        $dailyRows = collect(
            $this->reports->dailyProfit($cabangId, $from, $to)
        );

        $stockRows = collect(
            $this->reports->stockMutations($cabangId, $from, $to)
        );

        $menuRows = collect(
            $this->reports->menuSales($cabangId, $from, $to)
        );

        $totals = [
            'transactions' => (int) $dailyRows->sum('count'),
            'omzet' => (float) $dailyRows->sum('omzet'),
            'hpp' => (float) $dailyRows->sum('hpp'),
            'waste' => (float) $dailyRows->sum('waste'),
            'laba' => (float) $dailyRows->sum('laba'),
        ];

        $totals['margin'] = $totals['omzet'] > 0
            ? ($totals['laba'] / $totals['omzet']) * 100
            : 0;

        if ($request->boolean('export')) {
            return $this->exportCsv(
                $dailyRows,
                $stockRows,
                $menuRows,
                $from,
                $to
            );
        }

        return view('manager.reports.index', [
            'from' => $from,
            'to' => $to,
            'totals' => $totals,

            'dailyRows' => $this->paginateRows(
                $dailyRows,
                'daily_page',
                $request,
                10
            ),

            'stockRows' => $this->paginateRows(
                $stockRows,
                'stock_page',
                $request,
                10
            ),

            'menuRows' => $this->paginateRows(
                $menuRows,
                'menu_page',
                $request,
                10
            ),
        ]);
    }

    private function paginateRows(
        Collection $rows,
        string $pageName,
        Request $request,
        int $perPage
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
            ]
        );
    }

    
    private function sanitizeCsvCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * @param array<int, mixed> $row
     * @return array<int, mixed>
     */
    private function sanitizeCsvRow(array $row): array
    {
        return array_map(fn ($cell) => $this->sanitizeCsvCell($cell), $row);
    }

    private function exportCsv(
        Collection $dailyRows,
        Collection $stockRows,
        Collection $menuRows,
        string $from,
        string $to
    ): StreamedResponse {
        return response()->streamDownload(
            function () use ($dailyRows, $stockRows, $menuRows, $from, $to): void {
                $output = fopen('php://output', 'w');

                // BOM agar UTF-8 terbaca baik di Excel.
                fwrite($output, "\xEF\xBB\xBF");

                // Informasi laporan
                fputcsv($output, $this->sanitizeCsvRow(['LAPORAN CABANG KOPSYNC']), ';');
                fputcsv($output, $this->sanitizeCsvRow(['Periode', $from . ' s.d. ' . $to]), ';');
                fputcsv($output, [], ';');

                // ==========================================
                // 1. LAPORAN LABA RUGI HARIAN
                // ==========================================
                fputcsv($output, $this->sanitizeCsvRow(['LAPORAN LABA RUGI HARIAN']), ';');

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
                fputcsv($output, [], ';');

                // ==========================================
                // 2. LAPORAN MUTASI STOK
                // ==========================================
                fputcsv($output, $this->sanitizeCsvRow(['LAPORAN MUTASI STOK']), ';');

                fputcsv($output, $this->sanitizeCsvRow([
                    'ID Bahan',
                    'Nama Bahan',
                    'Total Stok Masuk',
                    'Total Stok Keluar',
                    'Total Waste',
                    'Stok Saat Ini',
                ]), ';');

                foreach ($stockRows as $row) {
                    fputcsv($output, $this->sanitizeCsvRow([
                        $row['bahan_id'],
                        $row['name'],
                        round($row['masuk'], 2),
                        round($row['keluar'], 2),
                        round($row['waste'], 2),
                        round($row['stok_saat_ini'], 2),
                    ]), ';');
                }

                fputcsv($output, [], ';');
                fputcsv($output, [], ';');

                // ==========================================
                // 3. LAPORAN PENJUALAN MENU
                // ==========================================
                fputcsv($output, $this->sanitizeCsvRow(['LAPORAN PENJUALAN MENU']), ';');

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
            "laporan-cabang-{$from}-{$to}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

}