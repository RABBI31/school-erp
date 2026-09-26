<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule refused the action. The message is shown to the user as-is,
 * so pass an already-translated string.
 */
class BusinessRuleException extends RuntimeException {}
