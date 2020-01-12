<?php

	require_once dirname(__FILE__)."/../../../vars.php";

	$Time = new mod_time();
	$execution_events = $Time->isAnyEventInNeedOfExecution();

	// If there are events that need to be executed, loop through them and execute them
	if(isSizedArray($execution_events))
	{

		foreach($execution_events as $k => $event)
			$Time->executeEvent($event);

	}
