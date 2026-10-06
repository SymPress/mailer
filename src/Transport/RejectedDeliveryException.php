<?php

declare(strict_types=1);

namespace SymPress\Mailer\Transport;

use Symfony\Component\Mailer\Exception\HttpTransportException;

/** The provider explicitly confirms that no recipient was accepted. */
final class RejectedDeliveryException extends HttpTransportException
{
}
