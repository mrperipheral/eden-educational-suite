<?php

namespace App\Services\Cbt;

use RuntimeException;

/**
 * A student's attempt action could not proceed — e.g. the exam isn't open,
 * they've already attempted it, or their attempt already expired/finished.
 * Caught by the controller and shown as a friendly message, never a 500.
 * See `docs/cbt.md` §7–8.
 */
class ExamAttemptException extends RuntimeException {}
