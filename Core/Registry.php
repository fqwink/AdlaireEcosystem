<?php

/**
 * Adlaire Ecosystem - Integration Core registry metadata
 *
 * @version v0.277
 * @php     >= 8.3
 */

declare(strict_types=1);

final class AdlaireCoreRegistry
{
    public static function families(): array
    {
        return [
            'deployment',
            'backend',
            'frontend',
            'css',
            'javascript',
        ];
    }
}
