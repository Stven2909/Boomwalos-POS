<?php

namespace App\Models;

use App\Enums\EstadoDocumentoFiscal;
use App\Enums\TipoDocumento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoFiscal extends Model
{
    protected $table = 'documento_fiscales';

    protected $fillable = [
        'pedido_id',
        'tipo_documento',
        'numero_control',
        'codigo_generacion',
        'sello_recepcion',
        'estado',
        'datos_solicitante',
    ];

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
            'estado' => EstadoDocumentoFiscal::class,
            'datos_solicitante' => 'array',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
