<?php
ob_start();
session_start();
include realpath(__DIR__.'/../../').'/_init.php';

echo '<h1>Debug do bloco Próximo Disparo</h1>';
echo '<h2>Arquivo concierge_grupos.php - linhas 200-220:</h2>';
$file = file_get_contents(__DIR__.'/admin/Grupo/concierge_grupos.php');
$lines = explode("\n", $file);
for($i=199; $i<=220; $i++) {
    echo '<div style="background:#f8f9fa;padding:5px 10px;border-bottom:1px solid #e2e8f0;font-family:monospace;font-size:12px">';
    echo ($i+1).': '.htmlspecialchars($lines[$i] ?? '');
    echo '</div>';
}

echo '<hr><h2>CSS atualizado:</h2>';
$cssFile = file_get_contents(__DIR__.'/admin/Grupo/CSS/concierge_grupos.css');
echo '<div style="max-height:300px;overflow:auto;background:#fff;border:1px solid #e2e8f0;padding:10px;font-family:monospace;font-size:11px">';
echo htmlspecialchars(substr($cssFile, 950, 150));
echo '</div>';

echo '<hr><h2>JS atualizado (função miaUpdateNextScheduled):</h2>';
$jsFile = file_get_contents(__DIR__.'/admin/Grupo/JS/concierge_grupos.js');
echo '<div style="max-height:300px;overflow:auto;background:#fff;border:1px solid #e2e8f0;padding:10px;font-family:monospace;font-size:11px">';
$jsStart = strpos($jsFile, 'function miaUpdateNextScheduled');
$jsEnd = strpos($jsFile, 'function miaGetGroupVisualMeta');
echo htmlspecialchars(substr($jsFile, $jsStart, $jsEnd - $jsStart));
echo '</div>';
?>