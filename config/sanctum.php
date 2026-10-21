<?php

declare(strict_types=1);

return [
    // Sanctum reads this while the application boots, outside every module: the tokens are iam's, the routes are not Sanctum's.
    'routes' => false,
];
