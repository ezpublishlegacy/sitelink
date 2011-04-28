<?php

class SiteLinkResult
{

	private $parameters=false;
	private $operatorValue=false;

	function __construct($operatorValue, $parameters){
//		self::parseParameters($this, $parameters);
	}

	private static function parseParameters(&$object, $parameters){
		if(is_array($parameters['parameters'])){
			foreach($parameters['parameters'] as $key=>$value){
				$parameters['parameters'][$key]=((is_string($value)&&in_array($key,array('quotes','absolute')))?($value=='yes'):$value);
			}
		}else{
			$parameters['parameters']=array('content'=>$parameters['parameters']);
		}
		$object->parameters=array_merge(SiteLinkOperator::operatorDefaults('sitelink_anchor'),$parameters['parameters']);
	}

}

?>