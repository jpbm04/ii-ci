<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
require_once 'const.inc.php';
require_once 'helper.php';
$shortopts 	= '';
$longopts 	= array(
	'dbname',
	'dbuser',
	'config',
    'configSelenium',
	'version',
	'cryptpass:',
	'jthree:',
);
$options = getopt($shortopts, $longopts);
foreach ($options as $key => $value)
{
	switch ($key)
	{
		case 'dbname':
			echo createNewDBName();
			break;
		case 'dbuser':
			echo createNewDBUserName();
			break;
		case 'config':
			$options = getOptions();
			writeConfigFile($options);
			break;
        case 'configSelenium':
            $options = getSeleniumOptions();
            writeSeleniumConfigFile($options);
            break;
		case 'version':
			echo getVersion();
			break;
		case 'cryptpass':
			echo cryptPass($value);
			break;
		case 'jthree':
			echo isJoomlaThree($value);
			break;
		default:
			echo 'Zur Verfügung stehen: dbname, dbuser, config, version, cryptpass, jthree';
			break;
	}
}
?>
