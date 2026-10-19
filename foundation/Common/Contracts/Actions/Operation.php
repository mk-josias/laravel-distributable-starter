<?php

declare(strict_types=1);

namespace Foundation\Common\Contracts\Actions;

/**
 * Marker: a reusable mutation step, invoked only by Actions or other Operations — never by a
 * delivery surface. Extract one only on real reuse (two callers or more); by default the logic
 * lives inline in the Action.
 */
interface Operation {}
