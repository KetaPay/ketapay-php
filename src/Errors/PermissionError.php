<?php

declare(strict_types=1);

namespace KetaPay\Errors;

/** The key lacks a scope, or the customer token is not allowed to do this (403). */
class PermissionError extends KetaPayError {}
