<?php
require_once 'const.inc.php';
require_once 'helper.php';
//list($user, $pass, $host, $file, $dump, $jdbpass) = checkCommandLineArguments(parseCommandLineArguments($_SERVER['argv']));
$sqlCreateFile 	= PATH_SQL_FILES . DS . 'db-create.sql';
// Datenbank-Passwort fuer u.a. XAMPP Nutzer leer vordefinieren
$dbpass = '';
$shortopts 		= '';
$longopts 		= array(
	'dbhost:',
	'dbport:',
	'dbuser:',
	'dbpass:',
	'dump:',
	'jdbpass:',
	'jdbprefix:',
);
$options = getopt($shortopts, $longopts);
foreach ($options as $key => $value)
{
	switch ($key)
	{
		case 'dbhost':
			$dbhost = $value;
			break;
		case 'dbport':
			$dbport = $value;
			break;
		case 'dbuser':
			$dbuser = $value;
			break;
		case 'dbpass':
			$dbpass = $value;
			break;
		case 'dump':
			$dump = $value;
			break;
		case 'jdbpass':
			$jdbpass = $value;
			break;
		case 'jdbprefix':
			$jdbprefix = $value;
			break;
		default:
			echo 'Zur Verfuegung stehen: dbhost, dbport, dbuser, dbpass, dump, jdbpass, jdbprefix' . PHP_EOL;
			exit(EXIT_FAILURE);
			break;
	}
}
$jdbNameToCreate = createNewDBName();
$jdbUserToCreate = createNewDBUserName();
$db = connectToDatabase($dbhost, $dbport, $dbuser, $dbpass);
echo 'Datenbankverbindung erfolgreich aufgebaut.' . PHP_EOL;
createDatabase($db, $sqlCreateFile, $jdbNameToCreate, $jdbUserToCreate, $jdbpass);
echo "Datenbank namens $jdbNameToCreate erfolgreich erstellt." . PHP_EOL;
echo "Datenbankbenutzer namens $jdbUserToCreate erfolgreich erstellt." . PHP_EOL;
restoreDatabaseDump($db, $dump, $jdbNameToCreate);
echo "Datenbank-Dump erfolgreich in $jdbNameToCreate eingespielt." . PHP_EOL;
$options = array(
	'db_type' 	=> 'mysqli',
	'db_name' 	=> $jdbNameToCreate,
	'db_host' 	=> $dbhost,
	'db_user' 	=> $jdbUserToCreate, 
	'db_pass' 	=> $jdbpass,
	'db_prefix' => $jdbprefix,
);
$bytesWritten = writeConfigFile($options);
if($bytesWritten === false)
{
	echo 'ERROR: Konfiguration der Joomla! Instanz konnte nicht aktualisiert werden.' . PHP_EOL;
	exit(EXIT_FAILURE);
}
else
{
	echo 'Konfiguration der Joomla! Instanz erfolgreich aktualisiert. (' . $bytesWritten . ')' . PHP_EOL;
}
//writeConfigFileXml($host, $dbNameToCreate, $dbUserToCreate, $dbPass);
?>
