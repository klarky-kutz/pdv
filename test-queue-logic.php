<?php
// Test script to verify campaign queue and requeue logic - browser accessible!

header('Content-Type: text/html; charset=utf-8');
require __DIR__ . '/_init.php';
require __DIR__ . '/_inc/helper/ai_groups_helper.php';
require __DIR__ . '/_inc/helper/ai_concierge.php';

$tenantId = 347; // From your test
$campaignId = 103; // From your test

echo "<h1>=== Testing Campaign Queue Logic ===</h1>";

echo "<h2>1. Testing ai_get_concierge_campaign($tenantId, $campaignId):</h2>";
$campaign = ai_get_concierge_campaign($tenantId, $campaignId);
echo "<pre>";
var_dump($campaign);
echo "</pre>";

echo "<h2>2. Testing ai_groups_get_group_dispatch_queue($tenantId, 33):</h2>";
$queue = ai_groups_get_group_dispatch_queue($tenantId, 33);
echo "<pre>";
var_dump($queue);
echo "</pre>";

echo "<h2>3. Testing ai_get_campaign_delivery_summary($tenantId, $campaignId):</h2>";
$summary = ai_get_campaign_delivery_summary($tenantId, $campaignId);
echo "<pre>";
var_dump($summary);
echo "</pre>";

echo "<h2>4. Testing ai_get_due_concierge_campaigns($tenantId, 10):</h2>";
$due = ai_get_due_concierge_campaigns($tenantId, 10);
echo "<pre>";
var_dump($due);
echo "</pre>";
?>
