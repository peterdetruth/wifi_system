# ============================================
#  Local Dev Starter (CodeIgniter + Ngrok)
# ============================================

Write-Host "Starting local dev environment..." -ForegroundColor Cyan
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass -Force | Out-Null

# Start PHP server
Write-Host "`nStarting PHP local server on http://localhost:8080 ..." -ForegroundColor Green
Start-Process powershell -ArgumentList "php -S localhost:8080 -t public" -WindowStyle Minimized
Start-Sleep -Seconds 3

# Start ngrok
$ngrokPath = Join-Path $PSScriptRoot "ngrok.exe"
if (-Not (Test-Path $ngrokPath)) {
    Write-Host "`n ngrok.exe not found in project directory!" -ForegroundColor Red
    Write-Host "Please download from https://ngrok.com/download and place it in this folder." -ForegroundColor Yellow
    exit
}

Write-Host "`nStarting ngrok tunnel..." -ForegroundColor Green
Start-Process powershell -ArgumentList ".\ngrok.exe http 8080" -WindowStyle Minimized
Start-Sleep -Seconds 5

# Fetch the ngrok public URL
try {
    $response = Invoke-RestMethod -Uri "http://127.0.0.1:4040/api/tunnels"
    $publicUrl = $response.tunnels[0].public_url
    if ($publicUrl) {
        Set-Clipboard $publicUrl
        Write-Host "`n Ngrok is live!" -ForegroundColor Cyan
        Write-Host " Public URL: $publicUrl" -ForegroundColor Yellow
        Write-Host " (Copied to clipboard automatically!)" -ForegroundColor Green
        Write-Host "`nUse this for your .env callback URL, e.g.:" -ForegroundColor Cyan
        Write-Host "MPESA_CALLBACK_URL=${publicUrl}/mpesa/callback" -ForegroundColor Gray
    } else {
        Write-Host "`n Unable to fetch ngrok public URL automatically." -ForegroundColor Red
    }
} catch {
    Write-Host "`n Failed to connect to ngrok API. Please check if it's running." -ForegroundColor Red
}

Write-Host "`n✅ All systems running!" -ForegroundColor Cyan
Write-Host "Visit your site locally: http://localhost:8080" -ForegroundColor Yellow
