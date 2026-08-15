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
        'venta_fiscal_pos_id',
        'tipo_documento',
        'numero_control',
        'codigo_generacion',
        'sello_recepcion',
        'estado',
        'datos_solicitante',
        'solicitado_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
            'estado' => EstadoDocumentoFiscal::class,
            'datos_solicitante' => 'array',
            'solicitado_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function ventaFiscalPos(): BelongsTo
    {
        return $this->belongsTo(VentaFiscalPos::class);
    }

    public function isSolicitudExpirada(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(now());
    }

    public function isSolicitable(): bool
    {
        return $this->estado === EstadoDocumentoFiscal::PENDIENTE && ! $this->isSolicitudExpirada();
    }
}
