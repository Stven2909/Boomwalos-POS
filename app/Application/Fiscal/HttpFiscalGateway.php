<?php

namespace App\Application\Fiscal;

/**
 * Explicit infrastructure name for the HTTP fiscal provider. FiscalClient is
 * kept as a backwards-compatible alias for existing integrations.
 */
class HttpFiscalGateway extends FiscalClient {}
