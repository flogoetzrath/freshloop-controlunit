<?php

	/**
	 * Auth Mod specific routes
	 */

	require_once(LIBRARY_PATH.'/Routing.class.php');

	$routing = new Routing();

	$routing->addRoute("/register", "/views/mods/mod_auth/register.phtml");
	$routing->addRoute("/login", "/views/mods/mod_auth/login.phtml");
	$routing->addRoute("/logout", "/views/mods/mod_auth/logout.phtml");
	$routing->addRoute("/reset_password", "/views/mods/mod_auth/reset_password.phtml");
	$routing->addRoute("/verify_email", "/views/mods/mod_auth/verify_email.phtml");