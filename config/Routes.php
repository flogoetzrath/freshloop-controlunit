<?php
	/**
	 * Core Routes
	 */

	require_once(realpath(__DIR__).'/../vars.php');
	require_once(LIBRARY_PATH.'/Routing.class.php');

	$routing = new Routing();

	$routing->addRoute('', '/views/home.phtml');
	$routing->addRoute('/', '/views/home.phtml');
	$routing->addRoute('/unauthorized', '/views/unauthorized.phtml');
	$routing->addRoute('/home', '/views/home.phtml');

	$routing->addAuthorizedRoute('/dashboard', '/views/admin/dashboard/dashboard.phtml');
	$routing->addAuthorizedRoute('/dashboard/home', '/views/admin/dashboard/dashboard.phtml');
	$routing->addAuthorizedRoute('/dashboard/settings', '/views/admin/dashboard/settings.phtml');
	$routing->addDynamicRoute('/dashboard/mod_{{mod_name}}', ['mod_name' => '/views/admin/dashboard/mods'], 55);

	$routing->addFailureRoute('/views/arrangement.phtml');