<?php

declare(strict_types=1);

namespace App\Integrations\Tracking\Providers\Ssx\Exceptions;

use App\Integrations\Tracking\Exceptions\TrackingException;

/** Erro específico do provedor SSX/SystemSatX. Neutro para os consumidores. */
final class SsxException extends TrackingException {}
