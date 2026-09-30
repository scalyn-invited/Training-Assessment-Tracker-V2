param([ValidatePattern('^D(0[1-9]|1[01])$')][string]$Diagram='D01',[string]$Node='C:\Users\Admin\AppData\Local\hermes\node\node.exe',[string]$SkillRoot='C:/Users/Admin/.codex/skills/archify')
$ErrorActionPreference='Stop'
Set-Location -LiteralPath $PSScriptRoot
$spec = Get-Content -Raw -Encoding UTF8 "$Diagram/candidate.json" | ConvertFrom-Json
$stamp=Get-Date -Format 'yyyyMMdd-HHmmss'
foreach($operation in @('validate','deliver','visual-check')) {
  if($operation -eq 'visual-check') { $cliArgs=@('visual-check',"$Diagram/diagram.html",'--json') } else { $cliArgs=@($operation,$spec.diagram_type,"$Diagram/candidate.json"); if($operation -eq 'deliver'){$cliArgs+= "$Diagram/diagram.html"};$cliArgs+=@('--quality','showcase','--json') }
  $result = & $Node "$SkillRoot/bin/archify.mjs" @cliArgs 2>&1
  $status=$LASTEXITCODE
  [IO.File]::WriteAllText((Join-Path $PSScriptRoot "$Diagram/$operation-$stamp.json"),($result -join [Environment]::NewLine),[Text.UTF8Encoding]::new($false))
  if($status -ne 0){throw "$operation failed with exit $status; inspect receipt before continuing"}
}
