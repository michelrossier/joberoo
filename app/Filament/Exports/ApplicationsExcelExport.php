<?php

namespace App\Filament\Exports;

use App\Models\Application;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ApplicationsExcelExport extends ExcelExport
{
    public function setUp(): void
    {
        $this->model = Application::class;

        $this->withFilename(fn (): string => 'bewerbungen-' . now()->format('Y-m-d-His'));
        $this->withWriterType(Excel::XLSX);

        $this->withColumns([
            Column::make('first_name')->heading('Vorname'),
            Column::make('last_name')->heading('Nachname'),
            Column::make('email')->heading('E-Mail'),
            Column::make('phone')->heading('Telefon'),
            Column::make('linkedin_url')->heading('LinkedIn-URL'),
            Column::make('portfolio_url')->heading('Portfolio-URL'),
            Column::make('cover_letter_text')->heading('Anschreiben'),
        ]);

        $this->modifyQueryUsing(static function (Builder $query): Builder {
            $tenant = Filament::getTenant();

            return $query
                ->when($tenant, function (Builder $query) use ($tenant): Builder {
                    return $query->whereHas(
                        'campaign',
                        fn (Builder $campaignQuery): Builder => $campaignQuery->where('organization_id', $tenant->id)
                    );
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id');
        });
    }

    public function getModelClass(): ?string
    {
        return Application::class;
    }
}
