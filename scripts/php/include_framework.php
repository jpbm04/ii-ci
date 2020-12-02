<?php
// Load system defines
if (file_exists(JPATH_BASE . '/defines.php'))
{
	require_once JPATH_BASE . '/defines.php';
}
if (!defined('_JDEFINES'))
{
	require_once JPATH_BASE . '/includes/defines.php';
}
// Get the framework.
require_once JPATH_LIBRARIES . '/import.legacy.php';
// Bootstrap the CMS libraries.
require_once JPATH_LIBRARIES . '/cms.php';
// Force library to be in JError legacy mode
JError::$legacy = true;
// Load the configuration
require_once JPATH_CONFIGURATION . DIRECTORY_SEPARATOR . 'configuration.php';
$config = new JConfig();
define('JDEBUG', $config->debug);
$mainframe = JFactory::getApplication('site');
$mainframe->initialise();
$user = JFactory::getUser();
?>
