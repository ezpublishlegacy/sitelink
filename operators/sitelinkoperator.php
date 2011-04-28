<?php

	class SiteLinkOperator
{
	var $Operators;

	function __construct(){
		$this->Operators = array("sitelink","sitelink_anchor","sitelink_path");
	}

	function &operatorList(){
		return $this->Operators;
	}

	function namedParameterPerOperator(){
		return true;
	}

	function namedParameterList(){
		$ForceAbsolute=SiteLink::configSetting('OperatorSettings','ForceAbsoluteURL')==='enabled';
		return array(
			'sitelink' => array(
				'parameters' => array('type'=>'mixed', 'required'=>false, 'default'=>true),
				'absolute' => array('type'=>'mixed', 'required'=>false, 'default'=>$ForceAbsolute?true:false)
				),
			'sitelink_anchor'=>array(
				'parameters' => array('type'=>'mixed', 'required'=>false, 'default'=>false)
				),
			'sitelink_path'=>array(
				'absolute'=>array('type'=>'mixed', 'required'=>false, 'default'=>$ForceAbsolute?true:false)
				)
		);
	}

	// Currently a URI in the form: content/view/full/43, will not be converted into a correct path and therefore node.
	function modify(&$tpl, &$operatorName, &$operatorParameters, &$rootNamespace, &$currentNamespace, &$operatorValue, &$namedParameters){
		switch($operatorName){
			case 'sitelink':{
				return self::sitelink($operatorValue, $namedParameters);
			}
			case 'sitelink_anchor':{
				return self::sitelink_anchor($tpl, $operatorValue, $namedParameters);
			}
			case 'sitelink_path':{
				return self::sitelink_path($operatorValue, $namedParameters);
			}
		}
		return false;
	}

	static function operatorDefaults($operatorName=false){
		$defaults=array(
			'sitelink'=>array(
				'quotes'=>true,
				'absolute'=>false,
				'hash'=>false,
				'query'=>false,
				'debug'=>false,
				'node_id'=>true,
				'as_object'=>false
			),
			'sitelink_anchor'=>array(
				'content'=>false,
				'title'=>false,
				'new_window'=>false,
				'class'=>false,
				'sitelink'=>array()
			),
			'sitelink_path'=>array(
				'absolute'=>false
			)
		);
		return $operatorName?$defaults[$operatorName]:$defaults;
	}

	static function sitelink_anchor(&$tpl, &$operatorValue, &$namedParameters){
		$SiteLinkAnchor=new SiteLinkAnchor($operatorValue, $namedParameters);
		$SetHyperlink=$SiteLinkAnchor->setHyperlink();
		if($SetHyperlink['error']){
			if($SetHyperlink['error_code']==SiteLinkAnchor::ERROR_RESTRICTED){
				$operatorValue='';
			}
			return false;
		}
eZDebug::writeDebug($SiteLinkAnchor,'$SiteLinkAnchor');
		return $SiteLinkAnchor->anchor($tpl,$operatorValue);
	}

/*
	sitelink()
	sitelink(boolean_value)
	sitelink(boolean_value,boolean_value)
	sitelink(parameters)

	boolean_value:
		['yes'|'no'], [1|0], [true|false]
	parameters:
		[quotes, absolute, hash, query, debug, node_id]
*/
	static function sitelink(&$operatorValue, &$namedParameters){
		$SiteLink = new SiteLink($operatorValue,$namedParameters);
if(is_array($namedParameters['parameters']) && isset($namedParameters['parameters']['as_object']) && $namedParameters['parameters']['as_object']){
	eZDebug::writeNotice($SiteLink,'$SiteLink');
}
		if(!isset($SiteLink->objectNode)){
			if(!$SiteLink->setObjectNode()){
				return $SiteLink->hyperlink($operatorValue);
			}
		}

		if(SiteLink::inClassArray($SiteLink)){
			$SiteLink->classSettings = SiteLink::classSettings($SiteLink->objectNode->ClassIdentifier);
			$NodeLink=$SiteLink->nodeLink();
			if(!$NodeLink['error'] && $NodeLink['result']){
				$RelinkNamedParameters=array('parameters'=>false,'absolute'=>$SiteLink->isMultisite);
				$operatorValue=$NodeLink['result'];
				return self::sitelink($operatorValue, $RelinkNamedParameters);
			}
			return $SiteLink->hyperlink($operatorValue);
		}

		if($SiteLink->isMultisite){
			$Match=false;
			$PathArray = $SiteLink->objectNode->pathArray();
			foreach(SiteLink::hostMatchMapItems($SiteLink) as $Name=>$Host){
				$HostRootNode = SiteLink::configSetting('NodeSettings','RootNode','content.ini',"settings/siteaccess/$Name",true);
				if(array_search($HostRootNode,$PathArray)!==false){
					foreach(array_reverse($PathArray) as $PathNodeID){
						if($PathNodeID==$HostRootNode){
							$Match=true;
							$SiteLink->pathPrefix=SiteLink::configSetting('SiteAccessSettings','PathPrefix','site.ini',"settings/siteaccess/$Name",true);
							break 2;
						}
					}
				}
			}
			// Use host override
			$HostOverride=SiteLink::configSetting('OperatorSettings','HostOverride','sitelink.ini');
			if(!empty($HostOverride) && $HostOverride=='enabled'){
				if($Match && $SiteAccess=SiteLink::configSetting('OperatorSettings','SiteAccess','sitelink.ini')){
					if(array_key_exists($Name,$SiteAccess)){
						$Host=$SiteAccess[$Name];
					}
				}
			}
			return $SiteLink->hyperlink($operatorValue,$Match?$Host:false);
		}

		return $SiteLink->hyperlink($operatorValue);
	}

	static function sitelink_path(&$operatorValue, &$namedParameters){
		$SiteLink = new SiteLink($operatorValue,array_merge($namedParameters,array('parameters'=>'false')));
		if(!isset($SiteLink->objectNode)){
			if(!$SiteLink->setObjectNode()){
				return false;
			}
		}
		$operatorValue = $SiteLink->path();
		return true;
	}

}

?>