<?php
/**
 * Das Skript installiert alle Erweiterungen, die sich im extensions-Ordner befinden sowie alle die, welche in der 
 * ext-install.xml angegeben wurden.
 * 
 * //1. ext-install.xml durchgehen
 * //2. extensions Ordner durchgehen
 * 1.2. sortedextlist.xml durchgehen
 * 3. ueberpruefen ob alle dependencies erfuellt sind
 * 4. Reihenfolge der Installationen definieren
 * 5. Installation der Erweiterungen
 * //6. Test-Ordner an die richtige Stelle kopieren
 */
require_once 'const.inc.php';
require_once 'helper.php';
require_once 'include_framework.php';


jimport('joomla.methods');
jimport('joomla.log.log');
jimport('joomla.application.application');
jimport('joomla.application.component.controller');
jimport('joomla.application.component.helper');
jimport('joomla.database.database');
jimport('joomla.database.table');
jimport('joomla.environment.request');
jimport('joomla.filesystem.path');
jimport('joomla.filter.input');
jimport('joomla.installer.installer');
jimport('joomla.installer.helper');
jimport('joomla.plugin.helper');

$shortopts 	= '';
$longopts 	= array(
    'user:',
    'password:'
);

$options = getopt($shortopts, $longopts);

foreach ($options as $key => $value)
{
    switch ($key)
    {
        case 'user':
            $user = $value;
            break;
        case 'password':
            $password = $value;
            break;
        default:
            echo 'Zur Verfuegung stehen: user, password';
            break;
    }
}

$config = JFactory::getConfig();
$config->set('debug', true);

if(JFactory::getUser()->id == null || JFactory::getUser()->id == "0"){
    // Kein eingeloggter Nutzer
    $mainframe = JFactory::getApplication();

    // speichere den Benutzernamen in einem Array
    $data['username'] = $user;
    // speichere das Passwort in einem Array
    $data['password'] = $password;

    // Setze true/false ob sich Joomla! an den Benutzer "erinnern" soll
    $option['remember'] = true;
    // Setze true/false ob bei Fehlgeschlagenem Login eine Warnung ausgegeben werden soll
    $option['silent'] = true;

    // Logge den Benutzer ein
    $mainframe->login($data, $option);

    echo 'Nun eingeloggt als Benutzer: ' . JFactory::getUser()->username;
}

//$db =& JFactory::getDBO();

// Select the right Database
//$db->select('joomla');

// Load Library language
$lang = JFactory::getLanguage();
$lang->load('lib_joomla', JPATH_ADMINISTRATOR, $lang->getDefault(), true);

$sortedextfile = JPATH_BASE . DS . 'build' . DS . 'temp' . DS . 'sortedextlist.xml';

if (!file_exists($sortedextfile))
{
	echo "ERROR: Datei mit der sortierten Liste aller Erweiterungen $sortedextfile konnte nicht gefunden werden." . PHP_EOL;
	exit(EXIT_FAILURE);
}

$sxml 			= simplexml_load_file($sortedextfile);
$allSortedExt 	= array();

foreach ($sxml->extension as $ext)
{
	$allSortedExt[] = $ext->attributes()->xml;
}

if (count($allSortedExt) < 1)
{
	echo 'ERROR: Es konnten keine Erweiterungen zum Installieren gefunden werden!' . PHP_EOL;
	exit(EXIT_FAILURE);
}

// Erstellen einer Temp-Datei, falls Joomla! die Skriptausfuehrung vorzeitig beendet
$extInsTemFil = fopen(JPATH_BASE.DS.'build'.DS.'temp'.DS.'extinstalltempfile', 'w');
fclose($extInsTemFil);
$db = JFactory::getDbo();
try
{
	JLog::addLogger(
		array(
			// Sets file name
			'text_file' => JPATH_BASE . DS . 'build' . DS . 'reports' . DS . 'ext-install_logs.php'
		),
		// Sets critical and emergency log level messages to be sent to the file
		JLog::ALL
	);

	foreach ($allSortedExt as $ext)
	{
		$exml = simplexml_load_file($ext);
		echo PHP_EOL . "Beginn der Installation von $exml->name ..." . PHP_EOL;

		$extinfo 	= pathinfo($ext);
		$installer 	= new JInstaller();

		$installer->setPath("source", $extinfo['dirname']);

		$manifestFileFound = $installer->findManifest();
		if ($manifestFileFound === false)
		{
			echo 'ERROR: Manifest-Datei für ' . $exml->name . ' konnte nicht gefunden werden. (' . $ext . ')' . PHP_EOL;
			var_dump($isValidManifest);
			exit(EXIT_FAILURE);
		}

		$isValidManifest = $installer->isManifest($ext);
		if ($isValidManifest === null)
		{
			echo 'ERROR: Manifest-Datei für ' . $exml->name . ' konnte nicht verarbeitet werden. (' . $ext . ')' . PHP_EOL;
			var_dump($isValidManifest);
			exit(EXIT_FAILURE);
		}

		$installationResult = $installer->install($extinfo['dirname']);

		if (!$installationResult)
		{
			// Leider beendet JError die Skriptausfuehrung intern mit einem fuer ant nicht nachvollziehbaren exit-Code,
			// so dass nachfolgender Code in ganz seltenen Faellen von nutzen sein koennte.
			echo 'ERROR: Installation war nicht erfolgreich!' . PHP_EOL;
			echo "Extension information:" . PHP_EOL;
			var_dump($extinfo);
			echo "PHP error information:" . PHP_EOL;
			var_dump(error_get_last());
			echo "Database error information:" . PHP_EOL;
			var_dump($db->getErrorNum());
			var_dump($db->getErrorMsg());
			var_dump($db->stderr(true));
			$errors = JError::getErrors();
			foreach ($errors as $error)
			{
				echo $error;
			}
			exit(EXIT_FAILURE);
		}
		else
		{
			echo 'OK! Die Installation war erfolgreich.' . PHP_EOL;
		}
	}
}
catch (Exception $e)
{
	var_dump($e);
}

unlink(JPATH_BASE.DS.'build'.DS.'temp'.DS.'extinstalltempfile');
?>
