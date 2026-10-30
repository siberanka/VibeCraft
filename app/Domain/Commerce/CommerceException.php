<?php

namespace App\Domain\Commerce;

use RuntimeException;

/** A business rule rejected the operation; the message is safe to show to the user. */
class CommerceException extends RuntimeException {}
