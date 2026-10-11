<?php

declare(strict_types=1);

namespace KetaPay\Errors;

/** The object is in a state that does not allow this, for example `dispute_open` (409). */
class ConflictError extends InvalidRequestError {}
