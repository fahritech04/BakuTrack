param(
  [string]$Source = "hybrid"
)

Set-Location "$PSScriptRoot\..\scraper"
.\.venv\Scripts\Activate.ps1
python -m src.runner --source $Source
