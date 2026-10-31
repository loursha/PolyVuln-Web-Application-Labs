<?php
require_once __DIR__ . '/includes/auth.php';
http_response_code(404);
if (is_logged_in()) {
    $pageTitle = 'Page not found'; $active = '';
    include __DIR__ . '/includes/header.php';
    echo '<div class="panel empty"><i class="far fa-compass"></i><h1 class="h4">We can\'t find that page</h1><p>Check the address, or head back to your overview.</p><a href="/dashboard.php" class="btn btn-primary">Back to overview</a></div>';
    include __DIR__ . '/includes/footer.php';
} else {
    echo '<!doctype html><html><head><title>Not found</title></head><body style="font-family:sans-serif;padding:3rem"><h1>Page not found</h1><p><a href="/">PolyVuln Bank home</a></p></body></html>';
}
