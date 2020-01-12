<?php

	require_once dirname(__FILE__)."/../../../vars.php";

	$SensorReponder = new mod_sensorResponder();
	$SensorReponder->captureSongHistory(true);
