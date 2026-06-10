<?php

/**
 * Adlaire Ecosystem - Integration Core lifecycle metadata
 *
 * @version v0.277
 * @php     >= 8.3
 */

declare(strict_types=1);

final class AdlaireCoreLifecycle
{
    public static function stages(): array
    {
        return [
            'specification',
            'implementation_plan',
            'implementation',
            'verification',
            'release',
        ];
    }
}
