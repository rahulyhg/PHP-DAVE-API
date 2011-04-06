<?php

/***********************************************
DAVE PHP API
https://github.com/evantahler/PHP-DAVE-API
Evan Tahler | 2011

Set this page to be fired off every minute by your cron process, and then put the logic inside this page.
The output of this page will be added to the CRON_LOG.txt file

***********************************************/
// Cron example: */1 * * * * /usr/bin/php /path/to/CRON.php > /path/to/CRON_LOG.txt

// show errors on scrern
ini_set("display_errors","1");
error_reporting (E_ALL ^ E_NOTICE);

// working directory
$path = substr(__FILE__,0,(strlen(__FILE__) - strlen("CRON.php")));
chdir($path); unset($path);

// setup
require("ConnectToDatabase.php");
require("CONFIG.php");
require("DAVE.php");
require("CACHE.php");
require("CommonFunctions.php");
date_default_timezone_set($CONFIG['systemTimeZone']);

$CRON_OUTPUT = "";

$CRON_OUTPUT .= date("m-d-Y H:i:s")." \r\n";

/////////////////////////////////////////////////////////////////////////
// Check the CACHE DB table for old entries, and remove them
if($CONFIG['CacheType'] == "DB")
{
	$SQL= 'DELETE FROM `'.$CONFIG['DB'].'`.`'.$CONFIG['CacheTable'].'` WHERE (`ExpireTime` < "'.(time() - $CONFIG['CacheTime']).'") ;';
	$Status = $DBObj->GetStatus();
	if ($Status === true)
	{
		$DBObj->Query($SQL);
		$CRON_OUTPUT .= 'Deleted '.$DBObj->NumRowsEffected()." entries from the CACHE DB. \r\n";
	}
}
/////////////////////////////////////////////////////////////////////////
// Check the CACHE Folder table for old entries, and remove them
if($CONFIG['CacheType'] == "FlatFile")
{
	$files = scandir($CONFIG['CacheFolder']);
	$counter = 0;
	foreach ($files as $num => $fname)
	{
		$ThisFile = $CONFIG['CacheFolder'].$fname;
		if (file_exists($ThisFile) && ((time() - filemtime($ThisFile)) > $CONFIG['CacheTime']) && $fname != "." && $fname != ".." && $fname != ".svn") 
		{
			unlink($ThisFile);
			$counter++;
		}
	}
	$CRON_OUTPUT .= 'Deleted '.$counter." files from the CACHE direcotry. \r\n";
}

/////////////////////////////////////////////////////////////////////////
// Clear the LOG of old LOG entries, acording to $CONFIG['LogAge']
$Status = $DBObj->GetStatus();
if ($Status === true)
{
	$SQL= 'DELETE FROM `'.$CONFIG['LogTable'].'` WHERE (`TimeStamp` < "'.date('Y-m-d H:i:s',(time() - $CONFIG['LogAge'])).'") ;'; 	
	$DBObj->Query($SQL);
	$CRON_OUTPUT .= 'Deleted '.$DBObj->NumRowsEffected()." entries from the LOG. \r\n";
}

/////////////////////////////////////////////////////////////////////////
// Clear the LOG of old LOG entries, acording to $CONFIG['SessionAge']
$Status = $DBObj->GetStatus();
if ($Status === true)
{
	$SQL= 'DELETE FROM `SESSIONS` WHERE (`created_at` < "'.date('Y-m-d H:i:s',(time() - $CONFIG['SessionAge'])).'") ;'; 	
	$DBObj->Query($SQL);
	$CRON_OUTPUT .= 'Deleted '.$DBObj->NumRowsEffected()." expired Sessions. \r\n";
}

/////////////////////////////////////////////////////////////////////////
// Delete Big Log Files, list set in CONFIG
clearstatcache();
$i = 0;
while ($i < count($CONFIG['LogsToCheck']))
{
	if (@filesize($CONFIG['LogsToCheck'][$i]) > $CONFIG['MaxLogFileSize'])
	{
		$CRON_OUTPUT .= 'Log: '.$CONFIG['LogsToCheck'][$i].'is too big, killing'."\r\n";
		unlink($CONFIG['LogsToCheck'][$i]);
		$fh = fopen($CONFIG['LogsToCheck'][$i], 'w');
		fclose($fh);
		chmod($Logs[$i], 0777);
	}
	$i++;
}

/////////////////////////////////////////////////////////////////////////
// Do something else.....


/////////////////////////////////////////////////////////////////////////
// End the log output
$CRON_OUTPUT .= "\r\n\r\n";
echo $CRON_OUTPUT;
$fh = fopen($CONFIG['App_dir'].$CONFIG['CronLogFile'], 'a');
fwrite($fh, $CRON_OUTPUT);
fclose($fh);

$DBObj->close();

exit;
?>