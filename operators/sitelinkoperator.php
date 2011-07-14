<?php

	class SiteLinkOperator
{
	var $Operators;

	function __construct(){
		$this->Operators = array("sitelink","sitelink_path");
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
				'user_parameters'=>false,
				'siteaccess'=>false
			),
			'sitelink_path'=>array(
				'absolute'=>false
			)
		);
		return $operatorName?$defaults[$operatorName]:$defaults;
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
		if(!isset($SiteLink->objectNode)){
			if(!$SiteLink->setObjectNode()){
				return $SiteLink->hyperlink($operatorValue);
			}
		}

		if(SiteLink::inClassArray($SiteLink)){
			$SiteLink->classSettings = SiteLink::classSettings($SiteLink->objectNode->ClassIdentifier);
			$NodeLink=$SiteLink->nodeLink();
			if(!$NodeLink['error'] && $NodeLink['result']){
				$RelinkNamedParameters=array('parameters'=>array(),'absolute'=>$SiteLink->isMultisite);
				$operatorValue=$NodeLink['result'];
				return self::sitelink($operatorValue, $RelinkNamedParameters);
			}
			return $SiteLink->hyperlink($operatorValue);
		}

		if($SiteLink->isMultisite){
			$HostMatchMapItems=SiteLink::hostMatchMapItems($SiteLink);
			$PathArray = $SiteLink->objectNode->pathArray();
			foreach($HostMatchMapItems as $Name=>$Host){
				$HostRootNode = SiteLink::configSetting('NodeSettings','RootNode','content.ini',"settings/siteaccess/$Name",true);
				if(!$HostRootNode){
					$HostRootNode = SiteLink::configSetting('NodeSettings','RootNode','content.ini');
				}
				if(array_search($HostRootNode,$PathArray)!==false){
					foreach(array_reverse($PathArray) as $PathNodeID){
						if($PathNodeID==$HostRootNode){
							$Match[$Host]=array(
							  'host'=>$Host,
							  'siteaccess'=>$Name,
							  'root_node_id'=>$HostRootNode,
							  'path_prefix'=>SiteLink::configSetting('SiteAccessSettings','PathPrefix','site.ini',"settings/siteaccess/$Name",true),
							  'locale'=>SiteLink::configSetting('RegionalSettings','Locale','site.ini',"settings/siteaccess/$Name",true)
							);
						}
					}
				}
			}
			if(!($UseMatch=isset($Match[$SiteLink->currentHost])?$Match[$SiteLink->currentHost]:false)){
				$Matchup=0;
				foreach($Match as $UseMatchItem){
					if($UseMatchItem['locale']==$SiteLink->currentLocale){
						if(($CheckMatch=similar_text($SiteLink->currentHost, $UseMatchItem['host'])) > $Matchup){
							$Matchup = $CheckMatch;
							$UseMatch = $UseMatchItem;
						}
					}
				}
				if(!$UseMatch){
					eZDebug::writeWarning('No host matches found have been found.','SiteLink Operator: PHP Class Warning');
				}
			}
			return $SiteLink->hyperlink($operatorValue,$UseMatch?$UseMatch['host']:false);
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