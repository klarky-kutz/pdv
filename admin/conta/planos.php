<?php
/**
 * Router: /conta/planos
 * 
 * Redireciona para o sistema de painel com section=plans
 * Suporta sub-rotas: /conta/planos/upgrade, /conta/planos/historico
 */

// Garante que os includes do store_select.php funcionem
chdir(__DIR__ . '/..');

// Define a seção como plans
$_GET['section'] = 'plans';

// Detecta sub-rota (upgrade, historico, etc.)
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

if (strpos($requestUri, '/planos/upgrade') !== false) {
    $_GET['tab'] = 'upgrade';
} elseif (strpos($requestUri, '/planos/historico') !== false) {
    $_GET['tab'] = 'historico';
} elseif (strpos($requestUri, '/planos/cancelar') !== false) {
    $_GET['tab'] = 'cancelar';
} elseif (strpos($requestUri, '/planos/estorno') !== false) {
    $_GET['tab'] = 'estorno';
} else {
    $_GET['tab'] = 'plano_atual';
}

// Inclui o router principal
require 'store_select.php';
