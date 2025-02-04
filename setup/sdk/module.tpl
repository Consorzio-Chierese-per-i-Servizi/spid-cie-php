<?php

/**
 * This web page receives requests for web-pages hosted by modules, and directs them to
 * the process() handler in the Module class.
 */

require_once('_include.php');
require_once("{{SDKHOME}}/lib/CcsContainer.php");

use SAML2\Compat\ContainerSingleton;

// Logs custom - gestisce inserimento logs su database
$container = new CcsContainer();
ContainerSingleton::setContainer($container);

\SimpleSAML\Module::process()->send();
