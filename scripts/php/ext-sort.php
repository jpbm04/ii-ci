<?php
/**
 * Das Skript erstellt eine XML Datei deren Inhalt eine sortierte Liste aller Erweiterungen darstellt. Die Sortierung
 * richtet sich nach den in den Erweiterungen angegebenen Abhängigkeiten.
 */
require_once 'const.inc.php';
require_once 'helper.php';
// 1 - Alle Erweiterungen von der ext-install.xml ermitteln
// 2 - Alle Erweiterungen vom extensions Ordner ermitteln
// 3 - Erweiterungen sortieren
// 4 - XML Datei aus der sortierten Liste erstellen
$extInstallCollection 	= JPATH_BASE . DS . 'build' . DS . 'config' . DS . 'ext-install.xml.dist';
$allUnsortedExt 		= array();
$allSortedExt 			= array();
// Uebergebene Skriptparameter auslesen
$shortopts 	= '';
$longopts 	= array(
		'extxmlfile:',	// Required value
);
$options = getopt($shortopts, $longopts);
foreach ($options as $key => $value)
{
	//echo 'key: ' . $key . PHP_EOL . 'value: ' . $value . PHP_EOL;
	switch ($key)
	{
		case 'extxmlfile':
			$extInstallCollection = $value;
			break;
		default:
			break;
	}
}
//--------------------------------------------------------------------------------------------------------------------------
//-----------------------------------------------                 1              -------------------------------------------
//--------------------------------------------------------------------------------------------------------------------------
if (file_exists($extInstallCollection))
{
	$exml = simplexml_load_file($extInstallCollection);
	foreach ($exml->extension as $ext)
	{
		// Es werden nur die Ext eingetragen, die auch einen path-Tag haben, alle anderen mittels pruefung extensions-Ordner
		if (!empty($ext->path))
		{
			$manifestArray = findRecManifestFiles($ext->path);
			if (empty($manifestArray))
			{
				echo "INFO: Angegebene extra Erweiterung '$ext->name' mit dem Pfad '$ext->path' wurde nicht berücksichtigt, "
				. "da keine Installation-Manifest-XMl gefunden wurde." . PHP_EOL;
			}
			else
			{
				// auch wenn mehrere Manifest-Dateien gefunden werden, kann eine Erweiterung grundsaetzlich nur eine haben!
				$allUnsortedExt[] = $manifestArray[0];
					
				/* Wenn die Erweiterung lokal auf der Platte liegen aber auch reportet werden sollen
				 * bspw. bei einem lokal installierten Jenkins dann sollte das if-statement wieder einkommentiert werden!
				if(strlen(trim($ext->path)) > 0)
				{
				$dest = dirname(__FILE__).DS.'extensions'.DS.$ext->name;
				copyRec($ext->path, $dest);
				}
				*/
			}
		}
	}
}
else
{
	echo 'INFO: Es wurde keine Datei für extra Erweiterungen gefunden.' . PHP_EOL;
}
//--------------------------------------------------------------------------------------------------------------------------
//-----------------------------------------------                 2              -------------------------------------------
//--------------------------------------------------------------------------------------------------------------------------
if (file_exists(PATH_EXTENSIONS))
{
	$availableExtensions = scandir(PATH_EXTENSIONS);
	foreach ($availableExtensions as $ext)
	{
		if ($ext === '.' || $ext === '..')
		{
			continue;
		}
		$manifestArray = findRecManifestFiles(PATH_EXTENSIONS . DS . $ext);
		if (empty($manifestArray))
		{
			echo 'ERROR: Im Pfad ' . PATH_EXTENSIONS . DS . $ext . ' konnte keine Manifest gefunden werden.' . PHP_EOL;
			exit(EXIT_FAILURE);
		}
		else
		{
			$addExtInList = true;
				
			foreach ($allUnsortedExt as $alreadyExt)
			{
				if (extEqualCheck($manifestArray[0], $alreadyExt))
				{
					$addExtInList = false;
					break;
				}
			}
			if ($addExtInList)
			{
				$allUnsortedExt[] = $manifestArray[0];
			}
			else
			{
				echo 'INFO: Die Erweiterung vom Pfad ' . $manifestArray[0] . ' wird nicht verwendet, '
						. 'da eine entsprechende Erweiterung bereits in der ext-install.xml definiert wurde.' . PHP_EOL;
			}
		}
	}
}
else
{
	// Auch wenn alle Erweiterungen über andere Pfade installiert werden, wird dieser
	// Ordner während des builds angelegt, ergo ist seine Existenz Pflicht!
	echo 'ERROR: Abbruch aufgrund fehlendem extensions-Ordner!' . PHP_EOL;
	exit(EXIT_FAILURE);
}
//--------------------------------------------------------------------------------------------------------------------------
echo 'Alle Erweiterungen die zur Verfuegung stehen:' . PHP_EOL;
print_r($allUnsortedExt);
//--------------------------------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------------------------------
//-----------------------------------------------                 3              -------------------------------------------
//--------------------------------------------------------------------------------------------------------------------------
// jetzt schauen wir mal in die dependencies der einzelnen Erweiterungen und vergleichen ob sie in unserem Array der
// unsortierten Erweiterungen auftauchen, falls nicht mega fail! :D
foreach ($allUnsortedExt as $ext)
{
	$exml = simplexml_load_file($ext);
	if ($exml->dependencies && $exml->dependencies->dependency)
	{
		// auslesen der dependencies tags in der xml
		echo PHP_EOL . "Erweiterung $exml->name erwartet, dass folgende Pakete auch installiert werden: " . PHP_EOL;
		foreach ($exml->dependencies->dependency as $dep)
		{
			$dep = (string) preg_replace('/[^A-Z0-9_\.-]/i', '', $dep);
			$dep = strtolower(ltrim($dep, '.'));
			if (extNameInManifestArrayCheck($dep, $allUnsortedExt))
			{
				// Hier muss noch die Versionsprüfung rein. Es reicht also nicht, dass die Erweiterung nur
				// im Array gelistet ist sondern es muss noch nachgeschaut werden ob die zur Verfügung
				// stehende Version passt. Dabei gibt es zwei tag-attribute maxversion und minversion.
				echo "OK! Erweiterung $dep wird von $exml->name verlangt und ist vorhanden." . PHP_EOL;
			}
			else
			{
				echo "ERROR: Die Erweiterung $exml->name verlangt eine Mitinstallation von $dep. "
				. "$dep konnte jedoch nicht ermittelt werden." . PHP_EOL;
				exit(EXIT_FAILURE);
			}
		}
	}
	else
	{
		echo "Bei der Erweiterung '$exml->name' wurden keine Abhängigkeiten ermittelt." . PHP_EOL;
	}
}
//--------------------------------------------------------------------------------------------------------------------------
//-----------------------------------------------                 4              -------------------------------------------
//--------------------------------------------------------------------------------------------------------------------------
// So nun haben wir ein Array mit allen Erweiterungen und wir haben geckeckt ob wir alle Dependencies zur Verfügung haben.
// Jetzt gilt es die richtige Reihnfolge der Dependencies zu ermitteln. Aber Vorsicht bei Sachen wie
// A need first B,
// B need first C,
// C need first A <-FAIL!
/*
  if (count($allUnsortedExt) > 0)
  {
    // Zeiger aufs erste Element setzen
    reset($allUnsortedExt);
    foreach ($allUnsortedExt as $ext)
    {
      createDependencyList($ext, $allUnsortedExt, $allSortedExt);
    }
  }
*/
// Vorsortierung nach lib > com > plg > mod > tpl
$allUnsortedExt = sortManifestsByType($allUnsortedExt);
//echo 'Alle Erweiterungen nach der Vorsortierung:' . PHP_EOL;
//print_r($allUnsortedExt);
krsort($allUnsortedExt);
// Sotierung nach Abhaengigkeiten
$allSortedExt = sortManifestsByDependencies($allUnsortedExt);
echo PHP_EOL . 'Alle Erweiterungen die nach der Sortierung zur Verfügung stehen.' . PHP_EOL;
print_r($allSortedExt);
$pathAndFilename = JPATH_BASE . DS . 'build' . DS . 'temp' . DS . 'sortedextlist.xml';
if (!createSortedExtXml($allSortedExt, $pathAndFilename))
{
	echo 'ERROR: Das Erstellen der Datei build/temp/sortedextlist.xml ist fehlgeschlagen!' . PHP_EOL;
	exit(EXIT_FAILURE);
}
?>
