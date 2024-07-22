<?php

if(!class_exists('JsonSerializable')){
	interface JsonSerializable {
		public function jsonSerialize();
	}
		
	class JsonSerializer{
		
		public static function serializeMe($object) {
			$reflectionClass = new \ReflectionClass($object);

			$properties = $reflectionClass->getProperties();

			$array = array();
			foreach ($properties as $property) {
				$property->setAccessible(true);
				$value = $property->getValue($object);
				if (is_object($value)) {
					$array[$property->getName()] = self::serializeMe($value);
				} else {
					$array[$property->getName()] = $value;
				}
			}
			return $array;
		}

	}
	
	abstract class APropertiedObject implements JsonSerializable {
		public function jsonSerialize(){
			$o=func_get_arg(0);
			return JsonSerializer::serializeMe($o);
		}
	}
}
