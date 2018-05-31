<?php
abstract class Home_Component {

	const HEADSTART_IN_MINS = 10;

	abstract protected function fadeIn();
	abstract protected function fadeOut();
	abstract protected function turnOn();
	abstract protected function turnOff();

	public static function get_headstart() {
		return self::HEADSTART_IN_MINS * MINUTE_IN_SECONDS;
	}




}