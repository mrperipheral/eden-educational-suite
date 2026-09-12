<?php

namespace App\Services\Promotion;

use RuntimeException;

/**
 * A single student's promotion or graduation could not proceed — caught by
 * the caller and recorded as `skipped`/`failed` rather than aborting an
 * entire batch. See `docs/promotion.md`.
 */
class PromotionException extends RuntimeException {}
