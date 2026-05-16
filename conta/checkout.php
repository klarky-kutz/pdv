<?php
/**
 * Router: /conta/checkout.php
 *
 * Exibe o checkout interno (formulário + redirecionamento para gateway).
 * Reaproveita o painel Admin (store_select.php) com section=plans e tab=checkout.
 */

// Garante que os includes do store_select.php funcionem
chdir(__DIR__ . '/..');

$_GET['section'] = 'plans';
$_GET['tab'] = 'checkout';

require 'store_select.php';
