# Schedule daily forecast runs
$Action = New-ScheduledTaskAction -Execute "C:\xampp\php\php.exe" -Argument "-f C:\xampp\htdocs\IntelliWare\api\ml\run_forecast.php"
$Trigger = New-ScheduledTaskTrigger -Daily -At 1AM
$Principal = New-ScheduledTaskPrincipal -UserId "NT AUTHORITY\SYSTEM" -LogonType ServiceAccount -RunLevel Highest

Register-ScheduledTask -TaskName "IntelliWare_DailyForecast" -Action $Action -Trigger $Trigger -Principal $Principal
