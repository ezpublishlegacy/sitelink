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
		return array(
			'sitelink' => array(
				'quotes' => array('type'=>'mixed', 'required'=>false, 'default'=>'yes'),
				'absolute' => array('type'=>'mixed', 'required'=>false, 'default'=>0)
				),
			'sitelink_path'=>array(
				'absolute'=>array('type'=>'mixed', 'required'=>false, 'default'=>0)
				)
			);
	}

	// Currently a URI in the form: content/view/full/43, will not be converted into a correct path and therefore node.
	function modify(&$tpl, &$operatorName, &$operatorParameters, &$rootNamespace, &$currentNamespace, &$operatorValue, &$namedParameters){
		switch($operatorName){
			case 'sitelink':{
				if(is_string($operatorValue) && strpos($operatorValue, 'http')===0){return true;}
				return self::sitelink($tpl, $operatorName, $operatorParameters, $rootNamespace, $currentNamespace, $operatorValue, $namedParameters);
			}
			case 'sitelink_path':{
				return self::sitelink_path($tpl, $operatorName, $operatorParameters, $rootNamespace, $currentNamespace, $operatorValue, $namedParameters);
			}
		}
		return false;
	}

	private static function sitelink_path(&$tpl, &$operatorName, &$operatorParameters, &$rootNamespace, &$currentNamespace, &$operatorValue, &$namedParameters){
		$SiteLink = new SiteLink($operatorValue,$namedParameters);
		if(!isset($SiteLink->objectNode)){
			if(!$SiteLink->setObjectNode()){
				return false;
			}
		}
		$operatorValue = $SiteLink->path();
		return true;
	}

	private static function sitelink(&$tpl, &$operatorName, &$operatorParameters, &$rootNamespace, &$currentNamespace, &$operatorValue, &$namedParameters){
		$SiteLink = new SiteLink($operatorValue,$namedParameters);
		if(!isset($SiteLink->objectNode)){
			if(!$SiteLink->setObjectNode()){
				return $SiteLink->hyperlink($operatorValue);
			}
		}
		if(SiteLink::inClassArray($SiteLink)){
			$SiteLink->classSettings = SiteLink::classSettings($SiteLink->objectNode->ClassIdentifier);
			$NodeLink=$SiteLink->nodeLink($SiteLink->classSettings);
			if($NodeLink['error']){
				return false;
			}
			if($NodeLink['result']){$SiteLink->relink();}
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
}

?>