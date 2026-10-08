<?php

declare(strict_types=1);

return [
    'errors' => [
        'missing_token' => 'Please complete the security check.',
        'rejected' => 'The security check failed. Please try again.',
        'low_score' => 'The security check could not confirm that you are human. Please try again.',
        'wrong_action' => 'The security check does not match this form. Reload the page and try again.',
        'wrong_hostname' => 'The security check was solved on a different site. Reload the page and try again.',
        'unavailable' => 'The security check is temporarily unavailable. Please try again in a moment.',
        'not_configured' => 'The security check is not configured. Contact the site administrator.',
    ],
    'widget' => [
        'load_failed' => 'The security check could not be loaded. Reload the page and try again.',
        'failed' => 'The security check failed. Please try again.',
        'noscript' => 'Enable JavaScript to complete the security check.',
    ],
];
