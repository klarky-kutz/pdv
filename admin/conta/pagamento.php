<?php
/**
 * Router: /conta/pagamento.php
 *
 * Exibe a tela interna de pagamento (Pix/Boleto/Cartão) com visual do painel.
 */

chdir(__DIR__ . '/..');

$_GET['section'] = 'plans';
$_GET['tab'] = 'payment';

require 'store_select.php';
