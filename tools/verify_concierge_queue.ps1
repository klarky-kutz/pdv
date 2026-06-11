param(
    [Parameter(Mandatory = $true)][string]$BaseUrl,
    [Parameter(Mandatory = $true)][int]$TenantId,
    [Parameter(Mandatory = $true)][int]$CampaignId,
    [Parameter(Mandatory = $true)][int]$GroupId,
    [Parameter(Mandatory = $true)][string]$Token,
    [string]$CallbackStatus = "sent",
    [switch]$RunCallback = $true
)

$ErrorActionPreference = "Stop"

function Write-Step([string]$msg) {
    Write-Host "`n==> $msg" -ForegroundColor Cyan
}

function Parse-DateOrNull([string]$value) {
    if ([string]::IsNullOrWhiteSpace($value)) { return $null }
    try { return [datetime]::Parse($value) } catch { return $null }
}

$base = $BaseUrl.TrimEnd("/")
$headers = @{
    "X-Concierge-Token" = $Token
}

if ($RunCallback.IsPresent) {
    Write-Step "Executando callback de status da campanha"
    $callbackUrl = "$base/api/concierge/campaign_status_webhook.php?tenant_id=$TenantId"
    $callbackBody = @{
        tenant_id           = $TenantId
        campaign_id         = $CampaignId
        group_id            = $GroupId
        status              = $CallbackStatus
        external_message_id = "verify-$CampaignId-$GroupId-$([DateTimeOffset]::UtcNow.ToUnixTimeSeconds())"
        error_message       = ""
    } | ConvertTo-Json -Depth 6

    $callbackResp = Invoke-RestMethod -Method Post -Uri $callbackUrl -Headers $headers -Body $callbackBody -ContentType "application/json"
    $cbData = $callbackResp.data
    Write-Host ("Callback => campaign_status={0} next_scheduled_at={1}" -f $cbData.campaign_status, $cbData.next_scheduled_at)
}

Write-Step "Consultando campanha e resumo"
$campaignUrl = "$base/api/concierge/campaigns.php?tenant_id=$TenantId&id=$CampaignId"
$campaignResp = Invoke-RestMethod -Method Get -Uri $campaignUrl -Headers $headers
$campaign = $campaignResp.data.campaign

Write-Step "Consultando fila do grupo"
$queueUrl = "$base/api/concierge/groups.php?tenant_id=$TenantId&action=queue&group_id=$GroupId"
$queueResp = Invoke-RestMethod -Method Get -Uri $queueUrl -Headers $headers
$queue = $queueResp.data.queue
$nextItems = @($queue.next_items)
$nextCampaign = $nextItems | Where-Object { [int]$_.id -eq $CampaignId } | Select-Object -First 1

$summarySent = [int]($campaign.summary.sent)
$campaignStatus = [string]$campaign.status
$scheduledAt = [string]$campaign.scheduled_at
$nextDispatchAt = if ($nextCampaign) { [string]$nextCampaign.next_dispatch_at } else { "" }

$scheduledAtDt = Parse-DateOrNull $scheduledAt
$nextDispatchAtDt = Parse-DateOrNull $nextDispatchAt
$scheduleMatches = $false
if ($scheduledAtDt -and $nextDispatchAtDt) {
    $delta = [math]::Abs(($scheduledAtDt - $nextDispatchAtDt).TotalSeconds)
    $scheduleMatches = $delta -le 60
}

$checks = @(
    [pscustomobject]@{
        check  = "Resumo mostra enviados > 0"
        result = ($summarySent -gt 0)
        value  = "summary.sent=$summarySent"
    },
    [pscustomobject]@{
        check  = "Campanha em estado válido após callback"
        result = ($campaignStatus -in @("scheduled", "sent", "completed"))
        value  = "status=$campaignStatus"
    },
    [pscustomobject]@{
        check  = "Campanha aparece na fila do grupo"
        result = ($null -ne $nextCampaign)
        value  = if ($nextCampaign) { "queue_item_id=$($nextCampaign.id)" } else { "não encontrada" }
    },
    [pscustomobject]@{
        check  = "Fila usa horário alinhado ao scheduled_at"
        result = $scheduleMatches
        value  = "scheduled_at=$scheduledAt | next_dispatch_at=$nextDispatchAt"
    }
)

Write-Step "Resultado da verificação"
$checks | ForEach-Object {
    $statusText = if ($_.result) { "PASS" } else { "FAIL" }
    $color = if ($_.result) { "Green" } else { "Red" }
    Write-Host ("[{0}] {1} -> {2}" -f $statusText, $_.check, $_.value) -ForegroundColor $color
}

$failed = @($checks | Where-Object { -not $_.result }).Count
if ($failed -gt 0) {
    Write-Host "`nVerificação finalizada com inconsistências: $failed" -ForegroundColor Yellow
    exit 1
}

Write-Host "`nVerificação finalizada com sucesso." -ForegroundColor Green
exit 0
