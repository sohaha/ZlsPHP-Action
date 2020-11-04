<?php

namespace Zls\Action;

use Z;

class ResidentMemory {
	public static function restart() {
		$works = Z::isResidentMemory();
		$class = "";
		switch ($works) {
		case "saiyan":
			$class = "\Zls\Saiyan\Operation";
			break;
		case "swoole":
			$class = "\Zls\Swoole\Operation";
			break;
		}
		if (!$class) {
			return false;
		}
		$obj = Z::factory($class, true);

		return $obj->restart();
	}
}
