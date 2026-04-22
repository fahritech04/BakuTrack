param(
  [string]$Model = "qwen2.5:1.5b"
)

$ErrorActionPreference = "Stop"

function Test-OllamaReady {
  try {
    $null = Invoke-RestMethod -Uri "http://127.0.0.1:11434/api/tags" -Method Get -TimeoutSec 5
    return $true
  } catch {
    return $false
  }
}

function Resolve-OllamaExe {
  $cmd = Get-Command ollama -ErrorAction SilentlyContinue
  if ($null -ne $cmd) {
    return $cmd.Source
  }

  $candidates = @(
    "$env:LOCALAPPDATA\Programs\Ollama\ollama.exe",
    "$env:ProgramFiles\Ollama\ollama.exe"
  )

  foreach ($candidate in $candidates) {
    if (Test-Path -LiteralPath $candidate) {
      return $candidate
    }
  }

  return $null
}

Write-Host "[1/4] Checking Ollama installation..."
$ollamaExe = Resolve-OllamaExe
if ($null -eq $ollamaExe) {
  Write-Host "Ollama not found. Installing with winget..."
  winget install -e --id Ollama.Ollama --accept-package-agreements --accept-source-agreements
  $env:Path = [System.Environment]::GetEnvironmentVariable("Path", "Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path", "User")
  $ollamaExe = Resolve-OllamaExe
}

if ($null -eq $ollamaExe) {
  throw "Ollama executable not found after installation."
}

Write-Host "[2/4] Starting Ollama service (if not running)..."
if (-not (Test-OllamaReady)) {
  Start-Process -FilePath $ollamaExe -ArgumentList "serve" -WindowStyle Hidden
  Start-Sleep -Seconds 4
}

if (-not (Test-OllamaReady)) {
  throw "Ollama service is not reachable at http://127.0.0.1:11434"
}

Write-Host "[3/4] Pulling model $Model ..."
& $ollamaExe pull $Model

Write-Host "[4/4] Verifying model..."
& $ollamaExe list | Out-Host

Write-Host "Done. Local LLM is ready."
