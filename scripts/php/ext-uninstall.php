<?php
/**
 * Das Skript deinstalliert alle installierten Erweiterungen.
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
jimport('joomla.database.table.extension');
jimport('joomla.database.table');
jimport('joomla.environment.request');
jimport('joomla.filesystem.path');
jimport('joomla.filter.input');
jimport('joomla.installer.installer');
jimport('joomla.installer.helper');
jimport('joomla.plugin.helper');
$config = JFactory::getConfig();
$config->set('debug', true);
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
// Sortiert ein Array nach Schlüsseln in umgekehrter Reihenfolge damit das letzt installierte als erstes deinstalliert wird
krsort($allSortedExt);
if (count($allSortedExt) < 1)
{
	echo 'ERROR: Es konnten keine Erweiterungen zum Deinstallieren gefunden werden!' . PHP_EOL;
	exit(EXIT_FAILURE);
}
// Erstellen einer Temp-Datei, falls Joomla! die Skriptausfuehrung vorzeitig beendet
$extInsTemFil = fopen(JPATH_BASE.DS.'build'.DS.'temp'.DS.'extuninstalltempfile', 'w');
fclose($extInsTemFil);
$db 			= JFactory::getDBO();
$jTableExtObj 	= new JTableExtension($db);
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
		echo PHP_EOL . "Beginn der Deinstallation von $exml->name ..." . PHP_EOL;
		$type 		= (string) $exml->attributes()->type;
		$element 	= getElementByManifest($ext);
		$options 	= array("element"=>$element);
		$extId 		= $jTableExtObj->find($options);
		$installer 	= new JInstaller();
		$uninstallationResult = $installer->uninstall($type, $extId);
		if (!$uninstallationResult)
		{
			// Leider beendet JError die Skriptausfuehrung intern mit einem für ant nicht nachvollziehbaren exit-Code,
			// so dass nachfolgender Code in ganz seltenen Fällen von nutzen sein koennte.
			echo 'ERROR: Deinstallation war nicht erfolgreich!' . PHP_EOL;
			echo "Extension information:" . PHP_EOL;
			var_dump($extinfo);
			echo "PHP error information:" . PHP_EOL;
			var_dump(error_get_last());
			echo "Database error information:" . PHP_EOL;
			var_dump($db->getErrorNum());
			var_dump($db->getErrorMsg());
			var_dump($db->stderr(true));
			$errors = JError::getErrors();
			$errors = JError::getErrors();
			foreach ($errors as $error)
			{
				echo $error;
			}
			exit(EXIT_FAILURE);
		}
		else
		{
			echo 'OK! Die Deinstallation war erfolgreich.' . PHP_EOL;
		}
	}
}
catch (Exception $e)
{
	var_dump($e);
}
unlink(JPATH_BASE.DS.'build'.DS.'temp'.DS.'extuninstalltempfile');
?>
