<?php

namespace Psalm\Spirit;

class Sender
{
	public static function send(array $github_data, array $psalm_data) : void
	{
		error_log('Would send data');
	}
}