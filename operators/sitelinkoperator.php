<?php

	class SiteLinkOperator
{
	var $Operators;

	function __construct(){
		$this->Operators = array("sitelink","sitelink_path", "sitelink_roots", "sitelink_siteaccess");
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
			'sitelink_roots' => array(),
			'sitelink_siteaccess' => array(),
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
			case 'sitelink_siteaccess':{
				$SiteLink = self::sitelink($operatorValue, $namedParameters);
				$out = '';
				$topDepth = 0;
				foreach ($namedParameters['UseMatch'] as $UseMatch) {
				    if (strpos($UseMatch['siteaccess'], 'admin') !== false) $UseMatch['depth'] = $UseMatch['depth'] - 0.5;
				    if ($UseMatch['depth'] >= $topDepth) {
				        $topDepth = $UseMatch['depth'];
				        $out = $UseMatch['siteaccess'];
				    }
				}
				$operatorValue = $out;
				return true;
			}
			case 'sitelink_roots':{
				$SiteLink = new SiteLink(2,$namedParameters);
				$HostMatchMapItems=SiteLink::hostMatchMapItems($SiteLink);
				$HostRootNodes = array();
				$sitelink_ini = eZINI::instance('sitelink.ini');
				$siteaccess_path = ($sitelink_ini->hasSection('OperatorSettings')&&$sitelink_ini->hasVariable('OperatorSettings', 'SiteaccesDirPath'))?trim($sitelink_ini->variable('OperatorSettings','SiteaccesDirPath'), '/'):'settings/siteaccess';
				
				foreach($HostMatchMapItems as $Name=>$Host){
					$HostRootNodes[] = SiteLink::configSetting('NodeSettings','RootNode','content.ini',"$siteaccess_path/$Name",true);
				}
				$operatorValue = $HostRootNodes;
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
				$RelinkNamedParameters=array('parameters'=>$namedParameters['parameters'],'absolute'=>$SiteLink->isMultisite);
				$operatorValue=$NodeLink['result'];
				return self::sitelink($operatorValue, $RelinkNamedParameters);
			}
			return $SiteLink->hyperlink($operatorValue);
		}

		if($SiteLink->isMultisite){
			$HostMatchMapItems=SiteLink::hostMatchMapItems($SiteLink);
			//match domain from current siteaccess if no server vars exist, ie if this is run from script
			if (!$SiteLink->currentHost && $GLOBALS['eZCurrentAccess']) {
				$SiteLink->currentHost = $HostMatchMapItems[$GLOBALS['eZCurrentAccess']["name"]];
			}
			$PathArray = $SiteLink->objectNode->pathArray();
			
			$sitelink_ini = eZINI::instance('sitelink.ini');
			$siteaccess_path = ($sitelink_ini->hasSection('OperatorSettings')&&$sitelink_ini->hasVariable('OperatorSettings', 'SiteaccesDirPath'))?trim($sitelink_ini->variable('OperatorSettings','SiteaccesDirPath'), '/'):'settings/siteaccess';
			
			foreach($HostMatchMapItems as $Name=>$Host){
				$HostRootNode = SiteLink::configSetting('NodeSettings','RootNode','content.ini',"$siteaccess_path/$Name",true);
				if(!$HostRootNode){
					$HostRootNode = SiteLink::configSetting('NodeSettings','RootNode','content.ini');
				}
				if(array_search($HostRootNode,$PathArray)!==false){
					foreach(array_reverse($PathArray) as $loopCount => $PathNodeID){
						if($PathNodeID==$HostRootNode){
							$Match[$Host]=array(
							  'host'=>$Host,
							  'siteaccess'=>$Name,
							  'depth' => count($PathArray) - $loopCount,
							  'root_node_id'=>$HostRootNode,
							  'path_prefix'=>SiteLink::configSetting('SiteAccessSettings','PathPrefix','site.ini',"$siteaccess_path/$Name",true),
							  'locale'=>SiteLink::configSetting('RegionalSettings','Locale','site.ini',"$siteaccess_path/$Name",true)
							);
						}
					}
				}
			}
			if(!($UseMatch=isset($Match[$SiteLink->currentHost])?$Match[$SiteLink->currentHost]:false)){
				$Matchup=0;
				foreach($Match as $UseMatchItem){
					if($UseMatchItem['locale']==$SiteLink->currentLocale){
						if ($SiteLink->siteAccess['name'] == $UseMatchItem['siteaccess']) {
							$UseMatchItem['host'] = '';
							$UseMatch = $UseMatchItem;
							break;
						}
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
			
			$res = $SiteLink->hyperlink($operatorValue,$UseMatch?$UseMatch['host']:false);
			$namedParameters['UseMatch'] = $Match;
			return $res;
		}
        
		$res = $SiteLink->hyperlink($operatorValue);
		$namedParameters['UseMatch'] = $Match;
		return $res;
		
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