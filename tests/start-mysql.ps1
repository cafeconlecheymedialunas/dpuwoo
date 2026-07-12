$json = Get-Content 'C:\Users\dex360\AppData\Roaming\Local\sites.json' | ConvertFrom-Json
$site = $json.S2QNysdl6
Write-Host "Name: $($site.name)"
Write-Host "MySQL Port: $($site.mysql.port)"
Write-Host "DataDir: $($site.mysql.datadir)"
Write-Host "BindAddress: $($site.mysql.bindAddress)"
