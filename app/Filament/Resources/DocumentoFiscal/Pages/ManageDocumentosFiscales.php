<?php

namespace App\Filament\Resources\DocumentoFiscal\Pages;

use App\Filament\Resources\DocumentoFiscal\DocumentoFiscalResource;
use Filament\Resources\Pages\ManageRecords;

class ManageDocumentosFiscales extends ManageRecords
{
    protected static string $resource = DocumentoFiscalResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
