<?php

declare(strict_types=1);

namespace App\Exception;

use Exception;

class InvalidConfigurationException extends Exception implements OrigamiExceptionInterface
{
}
