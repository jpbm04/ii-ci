<?php
// Set expected server variables.
if (!isset($_SERVER['HTTP_HOST']))
{
    $_SERVER['HTTP_HOST'] = 'localhost';
}
if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', dirname(dirname(dirname(__DIR__))));
}
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
if (!defined('EXIT_SUCCESS')) {
    define('EXIT_SUCCESS', 0);
}
if (!defined('EXIT_FAILURE')) {
    define('EXIT_FAILURE', 1);
}
if (!defined('PATH_TESTS')) {
    define('PATH_TESTS', JPATH_BASE . DS . 'tests');
}
if (!defined('PATH_UPDATE_XMLS')) {
    define('PATH_UPDATE_XMLS', JPATH_BASE . DS . 'updates');
}
if (!defined('PATH_UPDATE_ZIPS')) {
    define('PATH_UPDATE_ZIPS', JPATH_BASE . DS . 'zips');
}
if (!defined('PATH_EXTENSIONS')) {
    define('PATH_EXTENSIONS', JPATH_BASE . DS . 'extensions');
}
if (!defined('PATH_SQL_FILES')) {
    define('PATH_SQL_FILES', JPATH_BASE . DS . 'build' . DS . 'scripts' . DS . 'sql');
}
if (!defined('PATH_REPORTS')) {
    define('PATH_REPORTS', JPATH_BASE . DS . 'build' . DS . 'reports');
}
if (!defined('JPATH_PLATFORM')) {
    define('JPATH_PLATFORM', JPATH_BASE . DS . 'libraries');
}
if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}
?>
