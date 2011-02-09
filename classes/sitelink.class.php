<?php

class SiteLink
{

	const HOST_REGEXP = '/^[a-zA-Z0-9]+[a-zA-Z0-9\-\.]+?\.[a-zA-Z0-9]{2,6}(\/.*)*$/';
	const ANCHOR_REGEXP = '/^\#.*$/';

	private $urlComponents=false;
	private $parameters=false;
	private $operatorValue=false;
	private $nodeID=false;
	private $rootNodeID=false;

	function __construct($operatorValue, $parameters){
		$this->isMultisite=self::isMultisite($this);
		$this->currentHost=eZSys::hostname();
		$this->siteAccess=isset($GLOBALS['eZCurrentAccess']['name'])?$GLOBALS['eZCurrentAccess']:false;
		$this->rootNodeID=self::configSetting('NodeSettings','RootNode','content.ini');
		$this->operatorValue=empty($operatorValue)?(string)$this->rootNodeID:$operatorValue;
		$this->parameters=$parameters;
		$this->classSettings=false;
		if(is_bool($parameters['absolute']) && $parameters['absolute']){
			$this->forceAbsolute=true;
		}else if(!$parameters['absolute'] && self::configSetting('OperatorSettings','ForceAbsoluteURL')==='enabled'){
			$this->forceAbsolute=true;
		}else{
			$this->forceAbsolute=false;
		}
		if(is_object($this->operatorValue)){
			$this->objectNode=$this->objectNode($this->operatorValue);
			$this->urlComponents=self::URLComponents($this->objectNode->pathWithNames());
			$this->nodeID=$this->objectNode->NodeID;
		}else{
			$this->urlComponents=self::URLComponents($this->operatorValue);
			if(stripos($operatorValue,'rss') !== false){
				$this->urlComponents['host']=parse_url(eZRSSExport::fetchByName(substr($operatorValue,strrpos($operatorValue,'/')+1))->URL,PHP_URL_HOST);
			}
			$this->nodeID=$this->urlComponents['path']?eZURLAliasML::fetchNodeIDByPath($this->urlComponents['path']):false;
			$this->normalize();
		}
	}

	function hyperlink(&$operatorValue=false, $host=false){
		if($this->urlComponents){
			$urlComponents = array_merge($this->urlComponents,array(
					'host'=>$host?$host:$this->urlComponents['host'],
					'path'=>preg_replace('/^([^\/].*)|^$/','/$1',preg_replace('/^'.str_replace('/','\\/',$this->pathPrefix).'\/*/','/',$this->urlComponents['path']))
				));
			if($this->siteAccess && isset($this->siteAccess['uri_part']) && count($this->siteAccess['uri_part']) && !$urlComponents['host']){
				if(stripos($urlComponents['path'],implode('/',$this->siteAccess['uri_part']))===false){
					$urlComponents['path']='/'.implode('/',$this->siteAccess['uri_part']).$urlComponents['path'];
				}
			}
			if(($urlComponents['host'] && $urlComponents['host']!=$this->currentHost) || $this->forceAbsolute){
				$operatorValue=$urlComponents['scheme'].'://'.($urlComponents['host']?$urlComponents['host']:$this->currentHost).$urlComponents['path'];
			}else{
				$operatorValue=$urlComponents['path'];
			}
			if(isset($urlComponents['user_parameters']) && $urlComponents['user_parameters']){
				foreach($urlComponents['user_parameters'] as $key=>$value){
					$operatorValue.="/($key)/$value";
				}
			}
			if($urlComponents['query']){$operatorValue.='?'.$urlComponents['query'];}
			if($urlComponents['fragment']){$operatorValue.='#'.$urlComponents['fragment'];}
			if($this->classSettings&&isset($this->classSettings['SelfLinking'])&&$this->classSettings['SelfLinking']=='disabled'){
				if(strripos(str_replace($urlComponents['scheme'].'://'.$urlComponents['host'],'',$operatorValue),'/'.$this->objectNode->urlAlias())===0 && $this->currentHost == $urlComponents['host']){$operatorValue='';}
			}
		}
		if($this->parameters['quotes']=='yes'){$operatorValue = "\"$operatorValue\"";}
		return true;
	}

	function nodeLink($classSettings){
		if(isset($classSettings['LinkTypeList']) && $classSettings['LinkTypeList']){
			$DataMap=$this->objectNode->dataMap();
			$DataTypeClassList=self::configSetting('DataTypeSettings','ClassList');
			if(array_key_exists($classSettings['DefaultLinkType'],$classSettings['LinkTypeList'])){
				$LoopSettings = array(
					'LinkType'=>$classSettings['DefaultLinkType'],
					'AttributeIdentifier'=>$classSettings['LinkTypeList'][$classSettings['DefaultLinkType']]
				);
				unset($classSettings['LinkTypeList'][$LoopSettings['LinkType']]);
			}
			do{
				if(!isset($LoopSettings)){
					$LoopSettings= array(
						'LinkType'=>key($classSettings['LinkTypeList']),
						'AttributeIdentifier'=>current($classSettings['LinkTypeList'])
					);
					unset($classSettings['LinkTypeList'][$LoopSettings['LinkType']]);
				}
				if(!$LoopSettings['AttributeIdentifier']){
					return array('error'=>false,'result'=>false);
				}
				$Attribute = array_key_exists($LoopSettings['AttributeIdentifier'],$DataMap)?$DataMap[$LoopSettings['AttributeIdentifier']]:false;
				if(!$Attribute){
					eZDebug::writeError($LoopSettings['AttributeIdentifier']." does not exist.",'SiteLink Operator: PHP Class Error');
					return array('error'=>true,'result'=>false);
				}
				if($classSettings['DataTypeClass']){
					$SelectedDataTypeClass=$classSettings['DataTypeClass'];
				}else{
					$AttributeDataType=$Attribute->attribute('data_type_string');
					$SelectedDataTypeClass=array_key_exists($AttributeDataType,$DataTypeClassList)?$DataTypeClassList[$AttributeDataType]:false;
				}
				if($SelectedDataTypeClass && class_exists($SelectedDataTypeClass)){
					$NodeLink=call_user_func(array(new $SelectedDataTypeClass(),'modify'),$Attribute,$LoopSettings['LinkType']);
					unset($LoopSettings);
				}else{
					eZDebug::writeError("$SelectedDataTypeClass class does not exist for attribute typeof ".$Attribute->attribute('data_type_string').".",'SiteLink Operator: PHP Class Error');
					return array('error'=>true,'result'=>false);
				}
			}while(!$NodeLink);
			$this->operatorValue = $NodeLink;
			if($this->urlComponents){$this->urlComponents['path']=$NodeLink;}	// may not be needed
			return array('error'=>false,'result'=>$NodeLink);
		}
		return array('error'=>true,'result'=>false);
	}

	function normalize(){
		if($this->urlComponents && !$this->nodeID){
			if($this->isMultisite && $this->urlComponents['path'] && !$this->urlComponents['host']){
				if($this->urlComponents['path']){
					foreach(self::pathPrefixList() as $PathPrefix){
						if(stripos($this->urlComponents['path'],$PathPrefix)!==false){
							$this->pathPrefix=$PathPrefix;
							break;
						}
					}
					if(stripos($this->urlComponents['path'],$this->pathPrefix)===false){
						$this->urlComponents['path']=$this->pathPrefix.'/'.$this->urlComponents['path'];
					}
				}
				return true;
			}
		}elseif(!$this->nodeID && is_numeric($this->operatorValue)){
			$this->nodeID=$this->operatorValue;
			return true;
		}
		return false;
	}

	function objectNode($object){
		if(get_class($object)=='eZContentObject'){
			foreach($object->assignedNodes() as $node){
				if(in_array($this->rootNodeID,$node->pathArray())){return $node;}
			}
			return $object->mainNode();
		}
		return $object;
	}

	function path(){
		$PathArray = $this->objectNode->pathArray();
		$SiteLinkOperator=new SiteLinkOperator();
		$namedParameters = array('quotes'=>'no','absolute'=>$this->parameters['absolute']);
		$operatorName='sitelink';
		foreach(array_reverse($PathArray) as $key=>$value){
			$NodeObject = eZContentObjectTreeNode::fetch($value);
			$operatorValue=$NodeObject->pathWithNames();
			$SiteLinkOperator->modify($tpl, $operatorName, $operatorParameters, $rootNamespace, $currentNamespace, $operatorValue, $namedParameters);
			$PathArray[$key]=array(
					'node_id'=>$NodeObject->NodeID,
					'text'=>$NodeObject->Name,
					'url_alias'=>$operatorValue,
					'current'=>$this->objectNode->NodeID==$value
				);
			if($this->rootNodeID==$value){
				$PathArray[$key]['text']='Home';
				break;
			}
		}
		return array_reverse(array_slice($PathArray,0,++$key));
	}

	function relink(){
		$SiteLinkOperator=new SiteLinkOperator();
		$namedParameters = array('quotes'=>'no','absolute'=>$this->parameters['absolute']);
		$operatorName='sitelink';
		$SiteLinkOperator->modify($tpl, $operatorName, $operatorParameters, $rootNamespace, $currentNamespace, $this->operatorValue, $namedParameters);
		$this->urlComponents = self::URLComponents($this->operatorValue);
		return 'true';
	}

	function setObjectNode(){
		if($this->nodeID){
			$this->objectNode=eZContentObjectTreeNode::fetch($this->nodeID);
			if(!$this->urlComponents){
				$pathWithNames=$this->objectNode->pathWithNames();
				$this->urlComponents=self::URLComponents((empty($pathWithNames) && $this->isMultisite)?$this->pathPrefix:$pathWithNames);
				$this->operatorValue=$this->urlComponents['path'];
			}
			return true;
		}
		return false;
	}

	// Expand to have the default settings be pulled from an ini block.
	static function classSettings($identifier){
		$ClassSettings = self::configSettingBlock($identifier);
		if($ClassSettings){
			$ClassSettings=array_merge(array(
				'DefaultLinkType'=>self::configSetting('OperatorSettings','DefaultLinkType'),
				'LinkTypeList'=>false,
				'DataTypeClass'=>false,
				'SelfLinking'=>true
				),$ClassSettings);
		}
		return $ClassSettings;
	}

	static function configSetting($blockName, $varName, $fileName='sitelink.ini', $rootDir='settings', $directAccess=false){
		$ini = eZINI::instance($fileName, $rootDir, null, null, null, $directAccess);
		return ($ini->hasSection($blockName)&&$ini->hasVariable($blockName, $varName))?$ini->variable($blockName,$varName):false;
	}
	
	static function configSettingBlock($blockName, $fileName='sitelink.ini', $rootDir='settings', $directAccess=false){
		$ini = eZINI::instance($fileName, $rootDir, null, null, null, $directAccess);
		return $ini->hasGroup($blockName)?$ini->group($blockName):false;
	}

	static function inClassArray($instance){
		$ClassArray=self::configSetting('OperatorSettings','SiteLinkClassList');
		return ($ClassArray && in_array($instance->objectNode->ClassIdentifier,$ClassArray));
	}

	static function hostMatchMapItems($object=false){
		$HostMatchMapItems=array();
		if(($MapItems=SiteLink::configSetting('SiteAccessSettings','HostMatchMapItems','site.ini')) && is_array($MapItems)){
			foreach($MapItems as $HostItem){
				$HostItemArray=explode(';',$HostItem);
				if(!array_key_exists($HostItemArray[1],$HostMatchMapItems) || ($object && $object->currentHost==$HostItemArray[0] && $object->siteAccess['name']==$HostItemArray[1])){
					$HostMatchMapItems[$HostItemArray[1]] = $HostItemArray[0];
				}
			}
		}
		return $HostMatchMapItems;
	}

	static function isMultisite(&$object=false){
		$isMultisite=in_array('host',explode(';',self::configSetting('SiteAccessSettings','MatchOrder','site.ini')));
		if($object){
			$object->pathPrefix=self::configSetting('SiteAccessSettings','PathPrefix','site.ini');
		}
		return $isMultisite;
	}

	static function pathPrefixList(){
		$PathPrefixList=array();
		foreach(eZSiteAccess::siteAccessList() as $key=>$value){
			$PathPrefixList[]=self::configSetting('SiteAccessSettings','PathPrefix','site.ini','settings/siteaccess/'.$value['name'],true);
		}
		return $PathPrefixList;
	}

	// Currently a URI in the form: content/view/full/43, will not be converted into a correct path.
	static function URLComponents($value){
		if(is_string($value) && !is_numeric($value) && parse_url($value,PHP_URL_SCHEME)!='mailto'){
			$default_url_array = array('scheme'=>'http','host'=>false,'user'=>false,'pass'=>false,'path'=>false,'query'=>false,'fragment'=>false);
			if(preg_match_all(self::ANCHOR_REGEXP,$value,$matches)){$value = ltrim(eZSys::requestURI().$value,'/');}
			$parsedURL = array_merge($default_url_array,parse_url($value));
			if($parsedURL['path']){
				$uri=eZURI::instance($parsedURL['path']);
				$parsedURL['path']=$uri->uriString();
				$parsedURL['user_parameters']=count($uri->userParameters())?$uri->userParameters():false;
				// Fixes bug where when no scheme is specified the host is retured as a path.
				if(!$parsedURL['host'] && $matchValue=preg_match_all(self::HOST_REGEXP,$parsedURL['path'],$matches)){
					return array_merge($default_url_array,parse_url('http://'.$parsedURL['path']));
				}
			}
			return $parsedURL;
		}
		return false;
	}

}

?>