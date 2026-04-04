<?php
// Hostinger/shared-hosting friendly entrypoint
// Keep Laravel’s original front controller in /public
// and forward all requests here to it without changing paths.

require __DIR__ . '/public/index.php';

