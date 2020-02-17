<?php
require_once 'const.inc.php';
require_once __DIR__ . DS . 'classes' . DS . 'SQLContentReader.php';
/**
 * Kopiert rekursiv alle Ordner und Dateien von Quellpfad in Zielpfad
 *
 * @param string $src Quellpfad
 * @param string $dest Zielpfad
 */
function copyRec($src, $dest)
{
	$srcHandle = opendir($src);
	mkdir($dest);
	while ($res = readdir($srcHandle))
	{
		if ($res == '.' || $res == '..')
		{
			continue;
		}
		if (is_dir($src.DS.$res))
		{
			copyRec($src.DS.$res, $dest.DS.$res);
		}
		else
		{
			copy($src.DS.$res, $dest.DS.$res);
		}
	}
	closedir($srcHandle);
}
/**
 * @param string 	$dep 			Extension- bzw. Dependency-Object
 * @param array 	$allUnsortedExt Die unsortierte Liste
 * @param array 	$sortedList 	Die sortierte Liste
 * @param array 	$path 			Hilfsvariable damit die Funktion weiss, wo sie sich befindet
 * @param int 		$cnt 			Hilfsvarialbe damit die richtige Platzierung in der sortierten Liste gefunden werden kann
 * @return mixed 					Bei Erfolg eine sortierte Liste, sonst false
 */
function createDependencyList($dep, array $allUnsortedExt, array &$sortedList = array(), array &$path = array(), &$cnt = 0)
{
	if (!in_array($dep, $path))
	{
		$path[] = $dep;
			
		if (!in_array($dep, $sortedList))
		{
			$exml = simplexml_load_file($dep);
			if ($exml->dependencies && $exml->dependencies->dependency)
			{
				foreach ($exml->dependencies->dependency as $beforeDep)
				{
					if ($beforeDep['before'] == 'true')
					{
						echo PHP_EOL . "Erweiterung $exml->name setzt $beforeDep unbedingt vorraus!" . PHP_EOL;
						$sortedList = createDependencyList(
								getManifestByName(trim($beforeDep), $allUnsortedExt),
								$allUnsortedExt,
								$sortedList,
								$path,
								$cnt
						);
					}
				}
			}
			array_splice($sortedList, $cnt, 0, array($dep));
			$cnt = array_search($dep, $sortedList) + 1;
		}
		else
		{
			$cnt = array_search($dep, $sortedList) + 1;	// braucht man bei dieser Konstellation: A vor C; B vor C;
		}
		array_splice($path, array_search($dep, $path), 1);
	}
	else
	{
		echo "loop detected! - Sortierung nicht möglich, denn $dep->name  und "
			. $path[count($path)-1]->name . " verursachten einen Loop." . PHP_EOL;
		exit(EXIT_FAILURE);
	}
	return $sortedList;
}
/**
 * Fügt ein neuen Ext-Knoten in die History-XML ein.
 *
 * @param SimpleXMLElement $sxml
 * @param unknown $id
 * @param unknown $build
 * @param unknown $version
 * @param unknown $branch
 * @param unknown $hash
 * @return SimpleXMLElement
 */
function createNodeInHistory(SimpleXMLElement $sxml, $id, $branch, $build, $version, $hash)
{
	$ext = $sxml->addChild('ext');
	$ext->addAttribute('id', 		$id);
	$ext->addAttribute('branch', 	$branch);
	// Bei einem neuen Knoten wird die Build-Nummer auf 0 gesetzt
	$ext->addAttribute('build', 	0);
	$ext->addAttribute('version', 	$version);
	$ext->addAttribute('oversion', 	$version);
	$ext->addAttribute('hash', 		$hash);
	$ext->addAttribute('ohash', 	$hash);
	return $sxml;
}
/**
 * Die Funktion erstellt eine XML-Datei aus der uebergebenen sortierten Liste von Erweiterungen und wird unter
 * build/temp/sortedextlist.xml gespeichert.
 * <extensions>
 * 	<extension id=1 xml=PfadZuDerManifestXml />
 * 	<extension id=2 xml=PfadZuDerManifestXml />
 * 	...
 * </extensions>
 *
 * @param array $allSortedExt Das Array mit allen Pfadangaben zu den einzelnen Manifest-XML Dateien
 * @return boolean true bei Erfolg, sonst false
 */
function createSortedExtXml($allSortedExt, $pathAndFilename)
{
	$retVal 		= true;
	$sortedExtXML 	= new SimpleXMLElement('<extensions></extensions>');
	reset($allSortedExt); // Zeiger aufs erste Element setzen
	$i = 1;
	foreach ($allSortedExt as $ext)
	{
		$extEntry = $sortedExtXML->addChild('extension');
		$extEntry->addAttribute('id', $i);
		$extEntry->addAttribute('xml', $ext);
		$i++;
	}
	if (!$sortedExtXML->asXML($pathAndFilename))
	{
		$retVal = false;
	}
	return $retVal;
}
//$extensions = simplexml_load_file(dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'temp'.DIRECTORY_SEPARATOR.'extensionslist.xml');
//foreach ($extensions as $extension)
/**
 * Funnktionen zum Erstellen der Update-XML-Dateien fuer die Erweiterungen.
 * 
 * @param unknown_type		$xml		Pfad von der Manifest-Datei von der Erweiterung
 * @param string		$downloadUrl	Name des ZIP-Archivs von der Erweiterung
 * 
 * @return mixed Ein Array mit den Schluessel-Wert-Paaren name, element, type, version, detailsurl, sonst false
 */
function createUpdateXml($extXmlPath, $downloadUrl)
{
	$result 		= FALSE;
	$ext 			= simplexml_load_file($extXmlPath);
	$updates 		= new SimpleXMLElement('<updates></updates>');
	$update 		= $updates->addChild('update');
	//required <name>Test Plugin</name>
	if ($ext->name)
	{
		$update->addChild('name', $ext->name);
	}
	//required <element>plg_my_test</element>
	$element = getElementByManifest($extXmlPath);
	if ($element) 
	{
		$update->addChild('element', $element);
	} 
	else 
	{
		echo 'Der element-Name der Erweiterung '.$ext->name.' konnte nicht bestimmt werden. Die Erstellung der Update-XML Datei ist fehlgeschlagen!'.PHP_EOL;
		exit(EXIT_FAILURE);
	}
	//required <type>plugin</type>
	if ($ext['type']) 
	{
		$update->addChild('type', (string) $ext['type']);
	} 
	else 
	{
		echo 'Der Typ der Erweiterung '.$ext->name.' konnte nicht bestimmt werden. Die Erstellung der Update-XML Datei ist fehlgeschlagen!'.PHP_EOL;
		exit(EXIT_FAILURE);
	}
	//required <version>1.0.3</version>
	if ($ext->version) 
	{
		$update->addChild('version', $ext->version);
	} 
	else 
	{
		echo 'Die Version der Erweiterung '.$ext->name.' konnte nicht bestimmt werden. Die Erstellung der Update-XML Datei ist fehlgeschlagen!'.PHP_EOL;
		exit(EXIT_FAILURE);
	}
	//required for plugins <folder>content</folder>
	if ((string) $ext['type'] == 'plugin') 
	{
		if ($ext['group']) 
		{
			$update->addChild('folder', (string) $ext['group']);
		} 
		else 
		{
			echo 'Die Erstellung der Update-XML Datei ist fehlgeschlagen! Beim Plugin '.$ext->name.' konnte das group Element nicht gefunden werden!'.PHP_EOL;
			exit(EXIT_FAILURE);
		}
	}
	//<description>Test Plugin fuer einen Aktualisierungstest</description>
	if ($ext->description) 
	{
		$update->addChild('description', $ext->description);
	}
	//<client>0</client>
	if ($ext['client']) 
	{
		$update->addChild('client', (string) $ext['client']);
	}
	//<infourl title="Joomla!">http://www.joomla.org/</infourl>
	$infourl = $update->addChild('infourl', 'http://www.mni.thm.de/');
	$infourl->addAttribute('title', 'MNI Homepage');
	//<targetplatform name="joomla" version="2.5"/>
	if ($ext['version']) 
	{
		$targetplatform = $update->addChild('targetplatform');
		$targetplatform->addAttribute('name', 'joomla');
		$targetplatform->addAttribute('version', (string) $ext['version']);
	}
	//<maintainer>Sam Moffatt</maintainer>
	if ($ext->author) 
	{
		$update->addChild('maintainer', $ext->author);
	}
	//<maintainerurl>http://sammoffatt.com.au</maintainerurl>
	if ($ext->authorUrl) 
	{
		$update->addChild('maintainerurl', $ext->authorUrl);
	}
	//<tags><tag>stable</tag></tags>
	$tags = $update->addChild('tags');
	$tags->addChild('tag', 'staging');
	//<downloads><downloadurl ...></downloadurl></downloads>
	$downloads = $update->addChild('downloads');
	//<downloadurl type="full" format="zip">http://joomlacode.org/Joomla_1.6.5_to_1.7.0_Package.zip</downloadurl>
	$downloadurl = $downloads->addChild('downloadurl', $downloadUrl);
	$downloadurl->addAttribute('type', 'full');
	$downloadurl->addAttribute('format', 'zip');
	$detailsUrl = JPATH_BASE . DS . 'updates' . DS . $element . '.xml';
	
	if (!$updates->asXML($detailsUrl))
	{
		echo 'Das Erstellen der Datei ' . $element . '.xml ist fehlgeschlagen!';
		exit(EXIT_FAILURE);
	}
	$updateCollEntry['name'] 		= $ext->name;
	$updateCollEntry['element'] 	= $element;
	$updateCollEntry['type'] 		= (string) $ext['type'];
	$updateCollEntry['version'] 	= $ext->version;
	//$updateColEntry['detailsurl'] = $urlToArchivs.$zipName;	//das ist falsch, muss auf update zeigen
	
	$result = $updateCollEntry;
	
	return $result;
}
/**
 * Die Funktion ueberprueft ob es sich um zwei gleiche Erweiterungen handelt. In den Vergleich sind enthalten,
 * der Ordnername, der Manifestname sowie das Element.
 * 
 * @param string $xmlFirst
 * @param string $xmlLast
 * @return boolean true wenn sie als gleich eingestuft wurden, bei ungleichheit false, bei einem Fehler null
 */
function extEqualCheck($xmlFirst, $xmlLast)
{
	$retVal = false;
	$infoFirst 	= pathinfo($xmlFirst);
	$infoLast 	= pathinfo($xmlLast);
	$dirnameFirst 	= basename($infoFirst['dirname']);
	$dirnameLast 	= basename($infoLast['dirname']);
	if($dirnameFirst == $dirnameLast)
	{
		$retVal = true;
	}
	
	return $retVal;
}
/**
 * Die Funktion prueft ob der uebergebene Name im uebergebenen Array auftaucht. Dabei wird davon ausgegangen, dass
 * das uebergebene Array eine Ansammlung von Manifest-XML-Dateien ist. In die Ueberpruefung ist der XML-Dateiname,
 * der Ordnername sowie der in der Manifest hinterlegte Elementname enthalten. Wenn der uebergebene Name mit mindestens
 * einem dieser drei Name uebereinstimmt wird der boolesche Wert true zurueckgegeben, sonst false.
 * 
 * @param unknown $name Der Name der Erweiterung nach der gesucht werden soll
 * @param unknown $manifestArray Ein Array mit Pfaden zu den einzelnen Manifest-XML-Dateien
 * @return boolean
 */
function extNameInManifestArrayCheck($name, $manifestArray)
{
	$retVal = false;
	foreach ($manifestArray as $manifest)
	{
		$extInfo 	= pathinfo($manifest);
		$filename 	= $extInfo['filename'];
		$dirname 	= basename($extInfo['dirname']);
		$element 	= getElementByManifest($manifest);
		if ($name == $filename || $name == $dirname || $name == $element)
		{
			$retVal = true;
			break;
		}
	}
	return $retVal;
}
/**
 * Die Funktion sucht ausgehend von dem angegeben Pfad rekrusiv nach allen Manifest-Install-Dateien
 *
 * @param string $path Der Pfad ab dem gesucht werden soll
 * @return array Gibt ein Array mit allen gefunden Dateien zurueck
 */
function findRecManifestFiles($dir)
{
	$retVal 	= array();
	$it 		= new RecursiveDirectoryIterator($dir);
	$display 	= array('xml');
	foreach (new RecursiveIteratorIterator($it) as $file)
	{
		//Gefunde Dateien beim Punkt teilen
		$fileParts = explode('.', $file);
		$fileParts = strtolower(array_pop($fileParts));
		if (in_array($fileParts, $display))
		{
			//XML einlesen
			$sxe = simplexml_load_file($file);
			//Pruefen ob extension oder install das erste Tag sind
			if ($sxe->getName() == 'extension' || $sxe->getName() == 'install')
			{
				$retVal[] = $file->getRealPath();
			}
		}
	}
	return $retVal;
}
/**
 * Die Methode bestimmt den richtigen Elementnamen aus der Installationsmanifest. Dies ist notwendig,
 * weil bei fast jeder Art von Erweiterung der Elementname anders bestimmt wird.
 *
 * @param String $xml Der komplette Pfad samt dem Dateinamen von der XML-Installationsdatei
 *
 * @return mixed element-Name als String, sonst FALSE
 */
function getElementByManifest($xml)
{
	$manifest 	= simplexml_load_file($xml);
	$element 	= '';
	switch ((string) $manifest['type'])
	{
		case 'component':
			//Element bei component
			$name = (string) preg_replace('/[^A-Z0-9_\.-]/i', '', (string) $manifest->name);
			$name = strtolower(ltrim($name, '.'));
			if (substr($name, 0, 4) == "com_")
			{
				$element = $name;
			}
			else
			{
				$element = "com_$name";
			}
			break;
		case 'module':
			//Element bei module
			if (count($manifest->files->children()))
			{
				foreach ($manifest->files->children() as $file)
				{
					if ((string) $file->attributes()->module)
					{
						$element = (string) $file->attributes()->module;
						break;
					}
				}
			}
			break;
		case 'plugin':
			//Element bei plugin
			if (count($manifest->files->children()))
			{
				foreach ($manifest->files->children() as $file)
				{
					if ((string) $file->attributes()->plugin)
					{
						$element = (string) $file->attributes()->plugin;
						break;
					}
				}
			}
			break;
		case 'template':
			//Element bei template
			$name 		= (string) preg_replace('/[^A-Z0-9_\.-]/i', '', (string) $manifest->name);
			$element 	= strtolower(str_replace(" ", "_", ltrim($name, '.')));
			break;
		case 'library':
			//Element bei library
			$element = str_replace('.xml', '', basename($xml));
			break;
		case 'language':
			//Element bei language
			if ($manifest->tag) 
			{
				$element = $manifest->tag;
			}
			break;
		case 'file':
			//Element bei file
			$element = preg_replace('/\.xml/', '', basename($xml));
			break;
		case 'package':
			//Element bei package
			$name 		= (string) preg_replace('/[^A-Z0-9_\.-]/i', '', (string) $manifest->name);
			$name 		= strtolower(ltrim($name, '.'));
			$element 	= 'pkg_' . $name;
			break;
		default:
			;
			break;
	}
	if (empty($element))
	{
		return FALSE;
	}
	else
	{
		return $element;
	}
}
/**
 * Gesucht wird der Name in Ordnernamen, Manifest-XML-Namen und dem in der Manifest definiertem Elementnamen.
 * 
 * @param string $name Der Name der Erweiterung nach der gesucht werden soll
 * @param array $manifestArray Ein Array mit Pfaden zu den einzelnen Manifest-XML-Dateien
 * @return Ambigous <boolean, string>
 */
function getManifestByName($name, $manifestArray)
{
	$retVal = false;
	
	foreach ($manifestArray as $manifest)
	{
		$extInfo 	= pathinfo($manifest);
		$filename 	= $extInfo['filename'];
		$dirname 	= basename($extInfo['dirname']);
		$element 	= getElementByManifest($manifest);
	
		if ($name == $filename || $name == $dirname || $name == $element)
		{
			$retVal = $manifest;
			break;
		}
	}
	return $retVal;
}
/**
 * Gibt ein SimpleXMLElement-Objcet der übergebenen XML-Datei zurück, falls sie nicht existiert wird ein neues Object erstellt.
 *
 * @param string $file
 * @return SimpleXMLElement
 */
function getHistory($file = 'extensionshistory.xml')
{
	$sxml = NULL;
	if (file_exists($file))
	{
		echo 'History unter ' . $file . ' gefunden!' . PHP_EOL;
		$sxml = simplexml_load_file($file);
	}
	else
	{
		echo 'History unter ' . $file . ' nicht gefunden, wird neu erstellt!' . PHP_EOL;
		$sxml = new SimpleXMLElement('<history></history>');
	}
	return $sxml;
}
/**
 * Gibt das erst gefundene SimpleXMLElement mit der entsprechenden ID zurück, sonst NULL
 *
 * @param unknown $sxml
 * @param unknown $id
 * @return NULL|unknown
 */
function getNodeById($sxml, $id)
{
	$nodes = $sxml->xpath(sprintf('/history/ext[@id="%s"]', $id));
	if (empty($nodes))
	{
		echo 'Knoten mit der ID ' . $id . ' nicht gefunden!' . PHP_EOL;
		return NULL;
	}
	else
	{
		echo 'Knoten mit der ID ' . $id . ' gefunden!' . PHP_EOL;
		return $nodes[0];
	}
}
function rrmdir($dir)
{
	if (!file_exists($dir))
	{
		return true;
	}
	if (!is_dir($dir) || is_link($dir))
	{
		return unlink($dir);
	}
	foreach (scandir($dir) as $item)
	{
		if ($item == '.' || $item == '..')
		{
			continue;
		}
		if (!rrmdir($dir . DS . $item))
		{
			chmod($dir . DS . $item, 0774);
			if (!rrmdir($dir . DS . $item))
			{
				return false;
			}
		}
	}
	return rmdir($dir);
}
/**
 * Die Funktion sortiert das uebergebene Array nach den Abhaengigkeiten der einzelnen Erweiterungen.
 * 
 * @param array $allUnsortedExt Ein Array mit Pfaden zu den einzelnen Manifest-XML-Dateien
 * @return mixed das sortierte Array, sonst false
 */
function sortManifestsByDependencies($allUnsortedExt)
{
	$retVal 		= false;
	$allSortedExt 	= array();
	// Zeiger aufs erste Element setzen
	reset($allUnsortedExt);
	foreach ($allUnsortedExt as $ext)
	{
		createDependencyList($ext, $allUnsortedExt, $allSortedExt);
	}
	
	if (!empty($allSortedExt))
	{
		$retVal = $allSortedExt;
	}
	
	return $retVal;
}
/**
 * Die Funktion sortiert das uebergebene Array nach dem Erweiterungstyp lib > com > plg > mod > tpl.
 * 
 * @param array $allUnsortedExt Ein Array mit Pfaden zu den einzelnen Manifest-XML-Dateien
 * @return mixed das sortierte Array, sonst false
 */
function sortManifestsByType($allUnsortedExt)
{
	$retVal 		= false;
	$allSortedExt 	= array();
	$libArray 		= array();
	$comArray 		= array();
	$plgArray 		= array();
	$modArray 		= array();
	$tplArray 		= array();
	$otherArray 	= array();
	
	// Zeiger auf erste Element setzen
	reset($allUnsortedExt);
	
	foreach ($allUnsortedExt as $ext)
	{
		if (substr_count($ext, 'lib_'))
		{
			$libArray[] = $ext;
		}
		elseif (substr_count($ext, 'com_'))
		{
			$comArray[] = $ext;
		}
		elseif (substr_count($ext, 'plg_'))
		{
			$plgArray[] = $ext;
		}
		elseif (substr_count($ext, 'mod_'))
		{
			$modArray[] = $ext;
		}
		elseif (substr_count($ext, 'tpl_'))
		{
			$tplArray[] = $ext;
		}
		else
		{
			$otherArray[] = $ext;
		}
	}
	
	$allSortedExt = array_merge($libArray, $comArray, $plgArray, $modArray, $tplArray, $otherArray);
	
	return $allSortedExt;
}
/**
 * Aktualisiert einen Ext-Knoten in der übergebenen History-XML.
 *
 * @param SimpleXMLElement $node
 * @param unknown $build
 * @param unknown $version
 * @param unknown $hash
 * @return SimpleXMLElement
 */
function updateNodeInHistory(SimpleXMLElement $node, $build, $version, $hash)
{
	echo 'Die alte Version lautet: ' . $node['version'] . PHP_EOL;
	echo 'Die neue Version lautet: ' . $version . PHP_EOL;
	echo 'Der alte Hash lautet: ' . $node['hash'] . PHP_EOL;
	echo 'Der neue Hash lautet: ' . $hash . PHP_EOL;
	$node['oversion'] 	= $node['version'];
	$node['ohash'] 		= $node['hash'];
	if ((string) $node['version'] != $version)
	{
		echo 'Der Versionsvergleich wurden als ungleich eingestuft.' . PHP_EOL;
		$build = 0;
	}
	elseif ((string) $node['hash'] == $hash)
	{
		echo 'Der Hashvergleich wurde als gleich eingestuft.' . PHP_EOL;
		$build = (string) $node['build'];
	}
	$node['build'] 		= $build;
	$node['version'] 	= $version;
	$node['hash'] 		= $hash;
	return $node;
}
/**
 *
 * @param unknown $src Der Pfad zum Ordner der archiviert werden soll
 * @param unknown $srcPath Der Pfad in dem sich $src befindet
 * @param ZipArchive $zip Das ZipArchive in das die Dateien archiviert werden sollen
 */
function zipDirRec($src, $srcPath, ZipArchive &$zip)
{
	$src 		= preg_replace('/[\/]{2,}/', '/', $src);
	$iterator 	= new DirectoryIterator($src);
	foreach ($iterator as $entry)
	{
		if ($entry->isDot())
		{
			continue;
		}
		elseif ($entry->isDir())
		{
			if ($zip->addEmptyDir(str_replace($srcPath, '', $entry->getPathname())))
			{
				//echo 'Created a new root directory'.PHP_EOL;
			}
			else
			{
				echo 'Verzeichnis konnte nicht angelegt werden' . PHP_EOL;
			}
			//echo 'Dir:  '.$entry->getPathname().PHP_EOL;
			zipDirRec($entry->getPathname(), $srcPath, $zip);
		}
		elseif($entry->isFile())
		{
			$zip->addFile($entry->getPathname(), str_replace($srcPath, '', $entry->getPathname()));
			//echo 'File: '.$entry->getFilename().PHP_EOL;
		}
	}
}
/**
 * Gibt ein Array mit allen absoluten Pfaden von gefunden PHPUnit Konfigurations-XML-Dateien zurueck.
 * 
 * @param string $directory Ort ab dem gesucht werden soll
 * @return array Array mit allen absoluten Pfaden der gefundenen XML Dateien
 */
function getAllPHPUnitConfigXMLs($directory, $type = "unit") {
	
	$retVal = array();
	$it 	= new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
	while($it->valid()) {
		// Handelt es sich um eine Datei mit der Endung .xml ?
		if (!$it->isDot() 
			&& is_file($it->key()) 
			&& !empty(pathinfo($it->key())['extension'])
			&& pathinfo($it->key())['extension'] == 'xml'
            && substr_count($it->getPathname(), $type.'-tests')
		) {		
			// Lautet das root-tag phpunit ?
			if (simplexml_load_file($it->key())->getName() == 'phpunit') {
				$retVal[] = $it->key();
			}
		}
		$it->next();
	}
	
	return $retVal;
}
/**
 * Gibt ein Array mit allen Pfaden zu den testsuite.php Dateien zurueck.
 *
 * @param string $src Der Pfad ab dem gesucht werden soll
 * @param string $type Der Typ der Tests, also in welchem Ordner sie abgelegt sind. unit oder gui. unit ist default
 * @param string $suites Das Array, welches bei den rekrusiven Aufrufen weitergereicht wird.
 * @return array
 */
function findRecSuites($src, $type = 'unit', &$suites = array())
{
	$src 		= preg_replace('/[\/]{2,}/', '/', $src);
	$iterator 	= new DirectoryIterator($src);
	foreach ($iterator as $entry)
	{
		if ($entry->isDot())
		{
			continue;
		}
		elseif ($entry->isDir())
		{
			findRecSuites($entry->getPathname(), $type, $suites);
		}
		elseif ($entry->isFile()
				&& $entry->getFilename() == 'testsuite.php'
				&& substr_count($entry->getPathname(), $type.'-tests'))
		{
			$suites[] = $entry->getPathname();
		}
	}
	return $suites;
}
/**
 * Bestimmt den Namen fuer den coverage-Ordner, in den phpunit den HTML Ouptut reinschreibt.
 *
 * Beispiel: Fuer com_thm_groups mit den beiden Testsuiten im site und admin Ordner, wuerde fuer die admin Testsuite
 * der Name com_thm_groups_admin und fuer die site Testsuite com_thm_groups_site lauten.
 *
 * @param string $testsuitepath Pfad zur der Testsuite
 * @return string Name fuer coverage-HTML-Ordner
 */
function getCoverageHtmlName($testsuitepath)
{
	$coverageName 	= '';
	$pathArray 		= explode(DS, $testsuitepath);
	$pathArrayMin 	= array_slice($pathArray, -4, 4);
	foreach ($pathArrayMin as $value)
	{
		if (substr_count($value, 'thm')) {
			$coverageName = $value . '_' . $coverageName;
		} elseif (substr_count($value, 'admin')) {
			$coverageName = $coverageName . '_' . $value;
		} elseif (substr_count($value, 'site')) {
			$coverageName = $coverageName . '_' . $value;
		}
	}
	// Sicher gehen, dass nur ein Bindestrich immer vorhanden ist
	$coverageName = str_replace('__', '_', $coverageName);
	$coverageName = trim($coverageName, '_');
	return $coverageName;
}
function parseCommandLineArguments(array $arguments)
{
	$user = $pass = $host = $file = $dump = null;
	foreach ($arguments as $i => $arg)
	{
		if ($arg === '--user')
		{
			$user = getNextArgument($arguments, $i);
		}
		if ($arg === '--pass')
		{
			$pass = getNextArgument($arguments, $i);
			if (strpos($pass, '--') !== false)
			{
				// Es wurde ein leeres Passwort angegeben, daher haben wir zuvor den nächsten Parameter als PW bekommen.
				$pass = "";
			}
		}
		if ($arg === '--host')
		{
			$host = getNextArgument($arguments, $i);
		}
		if ($arg === '--file')
		{
			$file = getNextArgument($arguments, $i);
		}
		if ($arg === '--dump')
		{
			$dump = getNextArgument($arguments, $i);
		}
	}
	return array($user, $pass, $host, $file, $dump);
}
function getNextArgument($argument, $index)
{
	if (isset($argument[$index+1]))
	{
		return $argument[$index+1];
	}
	else
	{
		echo 'Parameter wurden falsch übergeben.' . PHP_EOL;
		exit(EXIT_FAILURE);
	}
}
function checkCommandLineArguments(array $arguments, $mode = 'create')
{
	list($user, $pass, $host, $file, $dump) = $arguments;
	if ($user === null || $pass === null || $host === null || $file == null || $dump == null)
	{
		if ($mode == 'create' && $dump == null)
		{
			echo 'Zu wenig Parameter übergeben.' . PHP_EOL;
			exit(EXIT_FAILURE);
		}
	}
	return $arguments;
}
function connectToDatabase($dbhost, $dbport, $dbuser, $dbpass)
{
	try
	{
		$dsn = "mysql:host=$dbhost";
		$db = new PDO($dsn, $dbuser, $dbpass);
	}
	catch (\PDOException $e)
	{
		echo $e->getMessage() . PHP_EOL;
		exit(EXIT_FAILURE);
	}
	return $db;
}
function createDatabase($db, $file, $jdbname, $jdbuser, $jdbpass)
{
	$sqlContent = new SQLContentReader(file_get_contents($file));
	$statements = $sqlContent->content();
	foreach ($statements as $stmt)
	{
		$stmt 	= preg_replace("/@@DBNAME@@/", $jdbname, $stmt);
		$stmt 	= preg_replace("/@@DBUSER@@/", $jdbuser, $stmt);
		$stmt 	= preg_replace("/@@DBPASS@@/", $jdbpass, $stmt);
		$result = $db->exec($stmt);
		if ($result === false)
		{
			$errorInfo = $db->errorInfo();
			echo $errorInfo[2] . PHP_EOL;
			exit(EXIT_FAILURE);
		}
	}
}
function writeConfigFile($options)
{
	if (file_exists(JPATH_BASE . DS . 'configuration.php'))
	{
		echo "INFO: Loaded " . JPATH_BASE . DS . 'configuration.php';
		require_once JPATH_BASE . DS . 'configuration.php';
	}
	elseif (file_exists(JPATH_BASE . DS . 'installation' . DS . 'configuration.php-dist'))
	{
		echo "INFO: Loaded " . JPATH_BASE . DS . 'installation' . DS . 'configuration.php-dist';
		require_once JPATH_BASE . DS . 'installation' . DS . 'configuration.php-dist';
	}
	elseif (file_exists(JPATH_BASE . DS . 'build' . DS . 'config' . DS . 'configuration.php.dist'))
	{
		echo "INFO: Loaded " . JPATH_BASE . DS . 'build' . DS . 'config' . DS . 'configuration.php.dist';
		require_once JPATH_BASE . DS . 'build' . DS . 'config' . DS . 'configuration.php.dist';
	}
	else
	{
		echo 'ERROR: Es wurde keine Joomla! Konfigurationsvorlage gefunden!';
		exit(EXIT_FAILURE);
	}
	$class_vars = get_class_vars(get_class(new JConfig));
	$contents = '<?php' . PHP_EOL;
	$contents .= 'class JConfig {' . PHP_EOL;
	foreach ($class_vars as $name => $value)
	{
		switch ($name)
		{
			case 'dbtype':
				$contents .= 'public $'.$name."='".$options['db_type']."';".PHP_EOL;
				break;
			case 'db':
				$contents .= 'public $'.$name."='".$options['db_name']."';".PHP_EOL;
				break;
			case 'host':
				$contents .= 'public $'.$name."='".$options['db_host']."';".PHP_EOL;
				break;
			case 'user':
				$contents .= 'public $'.$name."='".$options['db_user']."';".PHP_EOL;
				break;
			case 'password':
				$contents .= 'public $'.$name."='".$options['db_pass']."';".PHP_EOL;
				break;
			case 'dbprefix':
				$contents .= 'public $'.$name."='".$options['db_prefix']."';".PHP_EOL;
				break;
			case 'log_path':
				$contents .= 'public $'.$name."='".JPATH_BASE.DS.'logs'."';".PHP_EOL;
				break;
			case 'tmp_path':
				$contents .= 'public $'.$name."='".JPATH_BASE.DS.'tmp'."';".PHP_EOL;
				break;
			default:
				$contents .= 'public $'.$name."='".$value."';".PHP_EOL;
				break;
		}
	}
	$contents .= '}' . PHP_EOL;
	//$root_user Variable aus der Konfiguration entfernen!
	$pattern 	= '/public\s*\$root_user[^;]*;/';
	$contents 	= preg_replace($pattern, '', $contents);
	$file 		= JPATH_BASE . DS . 'configuration.php';
	echo "Write new configuration to " . $file . PHP_EOL;
	$bytesWritten = file_put_contents($file, $contents);
	if($bytesWritten === false)
	{
		echo "Contents: " . PHP_EOL;
		var_dump($contents);
		echo "Options: " . PHP_EOL;
		var_dump($options);
	}
	$changedPermissions = chmod($file, 0744);
	if(!$changedPermissions)
	{
		echo "ERROR: Could not change the permissions for file: " . $file . PHP_EOL;
		return false;
	}
	else
	{
		echo "Successfully changed permissions for file: " . $file . PHP_EOL;
	}
	return $bytesWritten;
}
function writeSeleniumConfigFile($options)
{
    if (file_exists(JPATH_BASE . DS . 'build' . DS . 'config' . DS . 'selenium' . DS . 'configdef.php.dist'))
    {
        require_once JPATH_BASE . DS . 'build' . DS . 'config' . DS . 'selenium' . DS . 'configdef.php.dist';
    }
    else
    {
        echo 'ERROR: Es wurde keine Selenium! Konfigurationsvorlage gefunden!';
        exit(EXIT_FAILURE);
    }
    $class_vars = get_class_vars(get_class(new SeleniumConfig));
    $contents = '<?php' . PHP_EOL;
    $contents .= 'class SeleniumConfig {' . PHP_EOL;
    foreach ($class_vars as $name => $value)
    {
        switch ($name)
        {
            case 'seleniumHubHost':
                $contents .= 'public $'.$name."='".$options['selenium_hubHost']."';".PHP_EOL;
                break;
            case 'seleniumHubPort':
                $contents .= 'public $'.$name."='".$options['selenium_hubPort']."';".PHP_EOL;
                break;
            case 'host':
                $contents .= 'public $'.$name."='".$options['selenium_host']."';".PHP_EOL;
                break;
            case 'path':
                $contents .= 'public $'.$name."='".$options['selenium_path']."';".PHP_EOL;
                break;
            case 'username':
                $contents .= 'public $'.$name."='".$options['selenium_user']."';".PHP_EOL;
                break;
            case 'password':
                $contents .= 'public $'.$name."='".$options['selenium_pass']."';".PHP_EOL;
                break;
            default:
                if(is_array(($value)))
                {
                    $value = 'array(' . implode(', ', $value) . ')';
                    $contents .= 'public $'.$name."=".$value.";".PHP_EOL;
                }
                else
                {
                    $contents .= 'public $'.$name."='".$value."';".PHP_EOL;
                }
                break;
        }
    }
    $contents .= 'public function __construct() {
                    $this->baseURI = $this->folder . $this->path;
                  }'.PHP_EOL;
    $contents .= '}' . PHP_EOL;
    $file 		= JPATH_BASE . DS . 'tests' . DS . 'seleniumConfig.php';
    file_put_contents($file, $contents);
}
function writeConfigFileXml($host, $dbName, $dbUser, $dbPass)
{
	if(file_exists(JPATH_BASE.DIRECTORY_SEPARATOR.'configuration.php'))
	{
		$dbxml 	= new SimpleXMLElement('<jdbconfig></jdbconfig>');
		$dbxml->addChild('db', $dbName);
		$dbxml->addChild('host', $host);
		$dbxml->addChild('user', $dbUser);
		$dbxml->addChild('pass', $dbPass);
		if(!$dbxml->asXML(JPATH_BASE.DS.'build'.DS.'temp'.DS.'dbconfig.xml'))
		{
			echo 'Das Erstellen der Datei dbconfig.xml ist fehlgeschlagen!';
			exit(EXIT_FAILURE);
			return FALSE;
		}
	}
}
function restoreDatabaseDump($db, $dump, $jdbname)
{
	$result = $db->exec("USE $jdbname;");
	if ($result === false)
	{
		$errorInfo = $db->errorInfo();
		echo $errorInfo[2] . PHP_EOL;
		exit(EXIT_FAILURE);
	}
	// Disable foreign key checks
	$db->exec("SET foreign_key_checks = 0");
	$templine = '';
	$lines    = file($dump);
	foreach ($lines as $line)
	{
		if (substr($line, 0, 2) == '--' || $line == '')
		{
			continue;
		}
		$templine .= $line;
		if (substr(trim($line), -1, 1) == ';')
		{
			$result = $db->exec($templine);
			if ($result === false)
			{
				$errorInfo = $db->errorInfo();
				echo $errorInfo[2] . PHP_EOL;
				// Enable foreign key checks
				$db->exec("SET foreign_key_checks = 1");
				exit(EXIT_FAILURE);
			}
			$templine = '';
		}
	}
	// Enable foreign key checks
	$db->exec("SET foreign_key_checks = 1");
}
function readCurrentDatabaseInfo()
{
	$jConfigFile = JPATH_BASE . DS . 'configuration.php';
	if (file_exists($jConfigFile))
	{
		require_once $jConfigFile;
		$config = new JConfig;
		echo 'Alte Joomla!-Konfigurationsdatei wurde ermittelt.' . PHP_EOL;
		echo 'Name der Datenbank: ' . $config->db . PHP_EOL;
		echo 'Name des Datenbankbenutzers: ' . $config->user . PHP_EOL;
		return array($config->db, $config->user);
	}
	else
	{
		echo "INFO: Eine alte Joomla!-Konfigurationsdatei konnte nicht ermittelt werden." . PHP_EOL;
		return null;
	}
}
function isTestDatabase($dbName)
{
	return preg_match("/joomla_test_[a-zA-Z0-9]{32,}/", $dbName);
}
function removeTestDatabase($dbhost, $dbport, $dbuser, $dbpass, $jdbname, $jdbuser, $file)
{
	$db = connectToDatabase($dbhost, $dbport, $dbuser, $dbpass);
	$sqlContent = new SQLContentReader(file_get_contents($file));
	foreach ($sqlContent->content() as $stmt)
	{
		$stmt 	= preg_replace("/@@DBNAME@@/", $jdbname, $stmt);
		$stmt 	= preg_replace("/@@DBUSER@@/", $jdbuser, $stmt);
		$result = $db->exec($stmt);
		if ($result === false)
		{
			$errorInfo = $db->errorInfo();
			echo $errorInfo[2] . PHP_EOL;
			exit(EXIT_FAILURE);
		}
	}
}
function createNewDBName()
{
	$dbname = 'joomla_test_' . md5(uniqid(mt_rand(), true));
	return $dbname;
}
function createNewDBUserName()
{
	$dbusername = uniqid(mt_rand(0, 999));
	return $dbusername;
}
function getSeleniumOptions()
{
    $options = array();
    $options['selenium_hubHost'] = "http://localhost";
    $options['selenium_hubPort'] = "4444";
    $options['selenium_host'] = "http://localhost";
    $options['selenium_path'] = "joomla";
    $options['selenium_user'] = "admin";
    $options['selenium_pass'] = "admin";
    $options = setOptions($options);
    return $options;
}
function getOptions()
{
	$retVal = array();
	$retVal['language'] 			= 'de-DE';
	$retVal['db_type'] 				= 'mysqli';
	$retVal['db_host'] 				= 'localhost';
	$retVal['db_user'] 				= 'root';
	$retVal['db_pass'] 				= '';
	$retVal['db_name']				= '';
	$retVal['db_old'] 				= 'backup';
	$retVal['db_prefix'] 			= 'cimni';
	$retVal['sample_installed'] 	= '0';
	$retVal['ftp_enable'] 			= 0;
	$retVal['ftp_user'] 			= '';
	$retVal['ftp_pass'] 			= '';
	$retVal['ftp_root'] 			= '';
	$retVal['ftp_host'] 			= '127.0.0.1';
	$retVal['ftp_port'] 			= 21;
	$retVal['ftp_save'] 			= 0;
	$retVal['site_name'] 			= 'Joomla CI';
	//$retVal['admin_email'] 			= 'webmedia@mni.thm.de';
	//$retVal['admin_user'] 			= 'admin';
	//$retVal['admin_password'] 		= 'admin';
	$retVal['site_metadesc'] 		= '';
	$retVal['site_metakeys'] 		= '';
	$retVal['site_offline'] 		= 0;
	$retVal = setOptions($retVal);
	//var_dump($retVal);
	return $retVal;
}
function setOptions($options)
{
	foreach ($_SERVER['argv'] as $argValue)
	{
		$argValue = ltrim($argValue, '-');
		if (substr_count($argValue, 'db_host'))
		{
			$db_host = str_ireplace('db_host=', '', $argValue);
			if (strlen(trim($db_host)) > 0)
			{
				$options['db_host'] = $db_host;
			}
		}
		elseif (substr_count($argValue, 'db_user'))
		{
			$db_user = str_ireplace('db_user=', '', $argValue);
			if (strlen(trim($db_user)) > 0)
			{
				$options['db_user'] = $db_user;
			}
		}
		elseif (substr_count($argValue, 'db_pass'))
		{
			$db_pass = str_ireplace('db_pass=', '', $argValue);
			if (strlen(trim($db_pass)) > 0)
			{
				$options['db_pass'] = $db_pass;
			}
		}
		elseif (substr_count($argValue, 'db_name'))
		{
			$db_name = str_ireplace('db_name=', '', $argValue);
			if (strlen(trim($db_name)) > 0)
			{
				$options['db_name']	= $db_name;
			}
		}
		elseif (substr_count($argValue, 'db_prefix'))
		{
			$db_prefix = str_ireplace('db_prefix=', '', $argValue);
			if (strlen(trim($db_prefix)) > 0)
			{
				$options['db_prefix']	= $db_prefix;
			}
		}
        elseif (substr_count($argValue, 'selenium_host'))
        {
            $selenium_host = str_ireplace('selenium_host=', '', $argValue);
            if (strlen(trim($selenium_host)) > 0)
            {
                $options['selenium_host']	= $selenium_host;
            }
        }
        elseif (substr_count($argValue, 'selenium_hubHost'))
        {
            $selenium_hubHost = str_ireplace('selenium_hubHost=', '', $argValue);
            if (strlen(trim($selenium_hubHost)) > 0)
            {
                $options['selenium_hubHost']	= $selenium_hubHost;
            }
        }
        elseif (substr_count($argValue, 'selenium_hubPort'))
        {
            $selenium_hubPort = str_ireplace('selenium_hubPort=', '', $argValue);
            if (strlen(trim($selenium_hubPort)) > 0)
            {
                $options['selenium_hubPort']	= $selenium_hubPort;
            }
        }
        elseif (substr_count($argValue, 'selenium_port'))
        {
            $selenium_port = str_ireplace('selenium_port=', '', $argValue);
            if (strlen(trim($selenium_port)) > 0)
            {
                $options['selenium_port']	= $selenium_port;
            }
        }
        elseif (substr_count($argValue, 'selenium_path'))
        {
            $selenium_path = str_ireplace('selenium_path=', '', $argValue);
            if (strlen(trim($selenium_path)) > 0)
            {
                $options['selenium_path']	= $selenium_path;
            }
        }
        elseif (substr_count($argValue, 'selenium_user'))
        {
            $selenium_user = str_ireplace('selenium_user=', '', $argValue);
            if (strlen(trim($selenium_user)) > 0)
            {
                $options['selenium_user']	= $selenium_user;
            }
        }
        elseif (substr_count($argValue, 'selenium_pass'))
        {
            $selenium_pass = str_ireplace('selenium_pass=', '', $argValue);
            if (strlen(trim($selenium_pass)) > 0)
            {
                $options['selenium_pass']	= $selenium_pass;
            }
        }
	}
	return $options;
}
function getVersion()
{
	$version = '';
	if ($handle = opendir(JPATH_BASE.DS.'administrator'.DS.'components'.DS.'com_admin'.DS.'sql'.DS.'updates'.DS.'mysql'))
	{
		while (false !== ($file = readdir($handle)))
		{
			if ($file != "." && $file != ".." && preg_match("/.sql/i", $file))
			{
				if (version_compare($version, preg_replace('#\.[^.]*$#', '', $file)) < 0)
				{
					$version = preg_replace('#\.[^.]*$#', '', $file);
				}
			}
		}
		closedir($handle);
	}
	return $version;
}
/**
 * Prueft ob die angegebene Version groesser oder kleiner 3 ist.
 * @param unknown $version
 * @return Gibt true oder false als string zurueck, damit ant damit was anfangen konnte
 */
function isJoomlaThree($version)
{
	if (version_compare($version, '3') >= 0)
	{
		// mindestens 3
		return 'true';
	}
	else
	{
		// unter 3
		return 'false';
	}
}
/**
 * Verschluesselt das uebergebene Passwort mittels den Joomla-Funktionen und gibt es zurueck.
 *
 * @param string $pass
 * @return string
 */
function cryptPass($pass)
{
	require_once JPATH_BASE . DS . 'includes' . DS . 'defines.php';
	require_once JPATH_LIBRARIES . DS . 'import.legacy.php';
	require_once JPATH_LIBRARIES . DS . 'cms.php';
	// System includes.
	require_once JPATH_PLATFORM . DS . 'vendor' . DS . 'paragonie' . DS . 'random_compat' . DS . 'lib' . DS . 'byte_safe_strings.php';
	require_once JPATH_PLATFORM . DS . 'vendor' . DS . 'paragonie' . DS . 'random_compat' . DS . 'lib' . DS . 'cast_to_int.php';
	if (!function_exists('random_bytes'))
	{
		require_once JPATH_PLATFORM . DS . 'vendor' . DS . 'paragonie' . DS . 'random_compat' . DS . 'lib' . DS . 'random_bytes_mcrypt.php';
	}
	// Create random salt/password for the admin user
	$salt 		= JUserHelper::genRandomPassword(32);
	$crypt 		= JUserHelper::getCryptedPassword($pass, $salt);
	$cryptpass 	= $crypt.':'.$salt;
	return $cryptpass;
}
/*
 * Gibt die UserID fuer den Administrator zurueck, welche in die DB eingetragen werden soll
 * 
 * @return unknown
 *
function getUserId()
{
	$randUserId = mt_rand(1, 1000);
	return $randUserId;
}
*/
function getDBName()
{
	if (file_exists(JPATH_BASE . DS . 'configuration.php'))
	{
		require_once JPATH_BASE . DS . 'configuration.php';
		$jconfig = new JConfig();
		return $jconfig->db;
	}
	else
	{
		echo 'ERROR: Joomla! Konfigurationsdatei konnte nicht geladen werden!' . PHP_EOL;
		exit(EXIT_FAILURE);
	}
}
/**
 * Gibt ein Array mit allen Dateien im angegeben Ordner zurueck, die das Suffix enthalten.
 *
 * @param string $dir
 * @param string $suffix
 * @return array
 */
function getAllFilesWithSuffix($dir, $suffix = '')
{
	$cloverFiles 	= array();
	$files 			= scandir($dir);
	if (strlen($suffix))
	{
		foreach ($files as $file)
		{
			if (substr_count($file, $suffix))
			{
				$cloverFiles[] = $dir . DS . $file;
			}
		}
	}
	else
	{
		// Nichts zu tun...
		// $cloverFiles = $files;
	}
	return $cloverFiles;
}
/**
 * Verschmelzen von zwei PHPUnit Clover-XML Dateien.
 *
 * @param DOMDocument $into
 * @param DOMDocument $from
 * @return DOMDocument
 */
function mergePhpUnitClover(DOMDocument $into, DOMDocument $from)
{
	// Schritt 1: Uebertragen der file-Tags
	$files = $from->getElementsByTagName('file');
	foreach ($files as $file)
	{
		$project = $into->getElementsByTagName('project')->item(0);
		$file = $into->importNode($file, true);
		$project->appendChild($file);
	}
	// Schritt 2: metrics-Tag zusammenfuehren
	$metricsInto = $into->getElementsByTagName('metrics');
	$metricsFrom = $from->getElementsByTagName('metrics');
	$metricInto;
	foreach ($metricsInto as $metric)
	{
		if ($metric->hasAttribute('files'))
		{
			$metricInto = $metric;
			break;
		}
	}
	foreach ($metricsFrom as $metric)
	{
		if ($metric->hasAttribute('files'))
		{
			$metricInto->setAttribute('files', 					$metricInto->getAttribute('files') 					+ $metric->getAttribute('files'));
			$metricInto->setAttribute('loc', 					$metricInto->getAttribute('loc') 					+ $metric->getAttribute('loc'));
			$metricInto->setAttribute('ncloc', 					$metricInto->getAttribute('ncloc') 					+ $metric->getAttribute('ncloc'));
			$metricInto->setAttribute('classes', 				$metricInto->getAttribute('classes') 				+ $metric->getAttribute('classes'));
			$metricInto->setAttribute('methods', 				$metricInto->getAttribute('methods') 				+ $metric->getAttribute('methods'));
			$metricInto->setAttribute('coveredmethods', 		$metricInto->getAttribute('coveredmethods') 		+ $metric->getAttribute('coveredmethods'));
			$metricInto->setAttribute('conditionals', 			$metricInto->getAttribute('conditionals') 			+ $metric->getAttribute('conditionals'));
			$metricInto->setAttribute('coveredconditionals', 	$metricInto->getAttribute('coveredconditionals') 	+ $metric->getAttribute('coveredconditionals'));
			$metricInto->setAttribute('statements', 			$metricInto->getAttribute('statements') 			+ $metric->getAttribute('statements'));
			$metricInto->setAttribute('coveredstatements', 		$metricInto->getAttribute('coveredstatements') 		+ $metric->getAttribute('coveredstatements'));
			$metricInto->setAttribute('elements', 				$metricInto->getAttribute('elements') 				+ $metric->getAttribute('elements'));
			$metricInto->setAttribute('coveredelements', 		$metricInto->getAttribute('coveredelements') 		+ $metric->getAttribute('coveredelements'));
			break;
		}
	}
	return $into;
}
/**
 * Liefert ein DOMDocument-Objekt eines PHPUnit Clover Templates.
 *
 * @return DOMDocument
 */
function getPhpUnitCloverTemplate()
{
	$dom 				= new DOMDocument('1.0', 'UTF-8');
	//$doc->formatOutput = true;
	$timestamp 			= time();
	$coverage 			= $dom->createElement('coverage');
	$coverage->setAttribute('generated', $timestamp);
	$coverageNode 		= $dom->appendChild($coverage);
	$project 			= $dom->createElement('project');
	$project->setAttribute('timestamp', $timestamp);
	$projectNode 		= $coverageNode->appendChild($project);
	$metrics 			= $dom->createElement('metrics');
	$metricsNode 		= $projectNode->appendChild($metrics);
	$metricsNode->setAttribute('files', 				0);
	$metricsNode->setAttribute('loc', 					0);
	$metricsNode->setAttribute('ncloc', 				0);
	$metricsNode->setAttribute('classes', 				0);
	$metricsNode->setAttribute('methods', 				0);
	$metricsNode->setAttribute('coveredmethods', 		0);
	$metricsNode->setAttribute('conditionals', 			0);
	$metricsNode->setAttribute('coveredconditionals', 	0);
	$metricsNode->setAttribute('statements', 			0);
	$metricsNode->setAttribute('coveredstatements', 	0);
	$metricsNode->setAttribute('elements', 				0);
	$metricsNode->setAttribute('coveredelements', 		0);
	return $dom;
}
/**
 * Liefert ein DOMDocument-Objekt eines JUnit Testsuite Templates.
 *
 * @return DOMDocument
 */
function getJUnitTestSuiteTemplate()
{
	$dom 				= new DOMDocument('1.0', 'UTF-8');
	$testsuite 			= $dom->createElement('testsuites');
	$coverageNode 		= $dom->appendChild($testsuite);
	return $dom;
}
/**
 * Merget zwei, als DOMDocument uebergebene Testsuiten zusammen.
 *
 * @param DOMDocument $into
 * @param DOMDocument $from
 * @return DOMDocument
 */
function mergeTestSuites(DOMDocument $into, DOMDocument $from)
{
	$iSuites = $into->getElementsByTagName('testsuites')->item(0);
	$fSuites = $from->getElementsByTagName('testsuites')->item(0);
	foreach ($fSuites->childNodes as $tSuite)
	{
		$tSuiteItem = $into->importNode($tSuite, true);
		$iSuites->appendChild($tSuiteItem);
	}
	return $into;
}
function mergePHPUnitConfigs($phpunitFilePath, $phpunitGlobalFilePath)
{
    // Ist noetig da im Funktionskopf keine Punkte fuer die Zeichenkettenverbindung erlaubt sind
    if (empty($phpunitGlobalFilePath)) {
        $phpunitGlobalFilePath = JPATH_BASE . DS . 'build' . DS . 'config' . DS . 'phpunit' . DS . 'phpunit.xml';
    }
    $phpunitSchemaFilePath = JPATH_BASE . DS . 'build' . DS . 'config' . DS . 'phpunit' . DS . 'phpunit.xsd';
    if (file_exists($phpunitFilePath) && file_exists($phpunitGlobalFilePath))
    {
        $phpunitDocument = DOMDocument::load($phpunitFilePath);
        $phpunitDocument->formatOutput = true;
        $phpunitGlobalDocument = DOMDocument::load($phpunitGlobalFilePath);
        $phpunitElement = $phpunitDocument->getElementsByTagName('phpunit')->item(0);
        $filterElement = $phpunitGlobalDocument->getElementsByTagName('filter')->item(0);
        $filterElement = $phpunitDocument->importNode($filterElement, true);
        $phpunitElement->appendChild($filterElement);
        if (file_exists($phpunitSchemaFilePath) && $phpunitDocument->schemaValidate($phpunitSchemaFilePath))
        {   
            if ($phpunitDocument->save($phpunitFilePath) === false)
            {
                return FALSE;
            }
            else
            {
                return TRUE;
            }
        }
        else
        {
            return FALSE;
        }
    }
    return FALSE;
}
?>
