<?php
// Fix Apache AH01276 for /conta/ (Options -Indexes + no DirectoryIndex).
// This file becomes the DirectoryIndex for the physical /conta/ directory.
// It dispatches to the existing account router (store_select.php) with section=overview.

// Ensure relative includes inside store_select.php resolve from /modernpos (root).
chdir(__DIR__ . '/..');

if (!isset($_GET['section']) || $_GET['section'] === '') {
    $_GET['section'] = 'overview';
}

require 'store_select.php';
