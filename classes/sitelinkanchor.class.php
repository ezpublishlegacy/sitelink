<?php

class SiteLinkAnchor
{

	const ERROR_RESTRICTED=1;

//	private $parameters=false;
	private $hyperlink=false;

	function __construct($operatorValue, $parameters){
		self::parseParameters($this, $parameters);
//		$this->operatorValue=$operatorValue;
		$this->sitelink=$operatorValue;
	}

	function anchor(&$tpl, &$operatorValue){
		$tpl->setVariable('attributes',$this->attributes);
		$tpl->setVariable('content',$this->label);
		$operatorValue = $tpl->fetch('design:sitelink/anchor.tpl');
		$tpl->unsetVariable('content');
		$tpl->unsetVariable('attributes');

		return true;
	}

	function setHyperlink(){
		if(!isset($this->parameters['restriction']) || (isset($this->parameters['restriction']) && $this->parameters['restriction']!='yes')){
			$NamedParameters=array('parameters'=>$this->sitelinkParameters,'absolute'=>SiteLink::isMultisite());
			if(!SiteLinkOperator::sitelink($this->sitelink, $NamedParameters)){
				return array('error'=>true,'message'=>'Unable to create hyperlink');
			}
			$this->attributes=array_merge($this->attributes,array('href'=>$this->sitelink->hyperlink));
			if(!$this->label && $this->sitelink->objectNode){
				$this->label=htmlentities($this->sitelink->objectNode->Name);
			}
			return array('error'=>false);
		}
		return array('error'=>true,'error_code'=>self::ERROR_RESTRICTED,'message'=>'This link is restricted');
	}

	static function attributeList(){
		$AttributeList=SiteLink::configSetting('AnchorSettings','AttributeList');
		if(sort($AttributeList)){
			return $AttributeList;
		}
		return false;
	}

	static function sitelinkParameters($parameters){
		$Parameters=$parameters?$parameters:false;
		if(is_array($Parameters) && !isset($Parameters['quotes'])){
			$Parameters['quotes']=false;
		}elseif(!is_array($Parameters)){
			$Parameters=array('quotes'=>$Parameters);
		}
		return array_merge($Parameters,array('as_object'=>true));
	}

	private static function parseParameters(&$object, $parameters){
		$AttributeList=array_fill_keys(SiteLinkAnchor::attributeList(),false);
		$DefaultParameters=SiteLinkOperator::operatorDefaults('sitelink_anchor');
		if(is_string($parameters['parameters'])){
			$parameters['parameters']=array('content'=>$parameters['parameters']);
		}
		$parameters=array_merge($DefaultParameters,$parameters['parameters']);

		$object->label=$parameters['content'];
		$object->sitelinkParameters=SiteLinkAnchor::sitelinkParameters($parameters['sitelink']);
		$object->newWindow=isset($parameters['new_window'])?$parameters['new_window']:false;

		$object->attributes=array_intersect_key($parameters,$AttributeList);
		$object->parameters=array_diff_key($parameters,$AttributeList,$DefaultParameters);

		foreach($object->attributes as $name=>$value){
			if(is_array($value)){
				$object->attributes[$name]=implode(' ',$value);
			}else if(!$value){
				unset($object->attributes[$name]);
			}
		}

		if(!count($object->parameters)){$object->parameters=false;}

	}

}

?>