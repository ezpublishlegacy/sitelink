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
		self::parseParameters($this, $parameters);

		$this->isMultisite=self::isMultisite($this);
		$this->currentHost=eZSys::hostname();
		$this->siteAccess=isset($GLOBALS['eZCurrentAccess']['name'])?$GLOBALS['eZCurrentAccess']:false;
		$this->classSettings=false;

		$this->rootNodeID=self::configSetting('NodeSettings','RootNode','content.ini');
		$this->operatorValue=empty($operatorValue)?(int)$this->rootNodeID:$operatorValue;

		if(is_object($this->operatorValue)){
			$this->objectNode=$this->findObjectNode($this->operatorValue);
			$this->nodeID=$this->objectNode->NodeID;
			$this->urlComponents=self::URLComponents($this->objectNode->pathWithNames());
			$this->operatorValue=serialize($this->operatorValue);
		}else{
			$this->urlComponents=self::URLComponents($this->operatorValue);
			if(stripos($operatorValue,'rss/') !== false){
				$this->urlComponents['host']=parse_url(eZRSSExport::fetchByName(substr($operatorValue,strrpos($operatorValue,'/')+1))->URL,PHP_URL_HOST);
			}
			if($this->normalize()){
 				$this->nodeID=$this->urlComponents['path']?eZURLAliasML::fetchNodeIDByPath($this->urlComponents['path']):false;
			}
		}

	}
	function debug($message, $label, $level=eZDebug::LEVEL_DEBUG){
		if(isset($this->parameters['debug']) && $this->parameters['debug']){
			switch($level){
				case eZDebug::LEVEL_NOTICE:{
					eZDebug::writeNotice($message,$label);
					break;
				}
				case eZDebug::LEVEL_WARNING:{
					eZDebug::writeWarning($message,$label);
					break;
				}
				case eZDebug::LEVEL_ERROR:{
					eZDebug::writeError($message,$label);
					break;
				}
				case eZDebug::LEVEL_DEBUG:{
					eZDebug::writeDebug($message,$label);
					break;
				}
				default:{
					return false;
				}
			}
			return true;
		}
		return false;
	}

	function findObjectNode($object){
		if(get_class($object)=='eZContentObject'){
			foreach($object->assignedNodes() as $node){
				if(in_array($this->rootNodeID,$node->pathArray())){return $node;}
			}
			return $object->mainNode();
		}
		return $object;
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
			if(($urlComponents['host'] && $urlComponents['host']!=$this->currentHost) || $this->parameters['absolute']){
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
				if(strripos(str_replace($urlComponents['scheme'].'://'.$urlComponents['host'],'',$operatorValue),'/'.$this->objectNode->urlAlias())===0 && (!$urlComponents['host'] || $this->currentHost==$urlComponents['host'])){$operatorValue='';}
			}
		}
		if($this->parameters['quotes']){$operatorValue="\"$operatorValue\"";}
		return true;
	}

	function nodeLink(){
		$ClassSettings=$this->classSettings;
		if(isset($ClassSettings['LinkTypeList']) && $ClassSettings['LinkTypeList']){
			$DataMap=$this->objectNode->dataMap();
			$DataTypeClassList=self::configSetting('DataTypeSettings','ClassList');
			if(array_key_exists($ClassSettings['DefaultLinkType'],$ClassSettings['LinkTypeList'])){
				$LoopSettings = array(
					'LinkType'=>$ClassSettings['DefaultLinkType'],
					'AttributeIdentifier'=>$ClassSettings['LinkTypeList'][$ClassSettings['DefaultLinkType']]
				);
				unset($ClassSettings['LinkTypeList'][$LoopSettings['LinkType']]);
			}
			do{
				if(!isset($LoopSettings)){
					$LoopSettings= array(
						'LinkType'=>key($ClassSettings['LinkTypeList']),
						'AttributeIdentifier'=>current($ClassSettings['LinkTypeList'])
					);
					unset($ClassSettings['LinkTypeList'][$LoopSettings['LinkType']]);
				}
				if(!$LoopSettings['AttributeIdentifier']){
					return array('error'=>false,'result'=>false,'message'=>'AttributeIdentifier can not be determined.');
				}
				$Attribute = array_key_exists($LoopSettings['AttributeIdentifier'],$DataMap)?$DataMap[$LoopSettings['AttributeIdentifier']]:false;
				if(!$Attribute){
					eZDebug::writeError($LoopSettings['AttributeIdentifier']." does not exist.",'SiteLink Operator: PHP Class Error');
					return array('error'=>true,'result'=>false,'message'=>$LoopSettings['AttributeIdentifier']." does not exist.");
				}
				if($ClassSettings['DataTypeClass']){
					$SelectedDataTypeClass=$ClassSettings['DataTypeClass'];
				}else{
					$AttributeDataType=$Attribute->attribute('data_type_string');
					$SelectedDataTypeClass=array_key_exists($AttributeDataType,$DataTypeClassList)?$DataTypeClassList[$AttributeDataType]:false;
				}
				if($SelectedDataTypeClass && class_exists($SelectedDataTypeClass)){
					$NodeLink=call_user_func(array(new $SelectedDataTypeClass(),'modify'),$Attribute,$LoopSettings['LinkType'],$this);
					unset($LoopSettings);
				}else{
					eZDebug::writeError("$SelectedDataTypeClass class does not exist for attribute typeof ".$Attribute->attribute('data_type_string').".",'SiteLink Operator: PHP Class Error');
					return array('error'=>true,'result'=>false,'message'=>"$SelectedDataTypeClass class does not exist for attribute typeof ".$Attribute->attribute('data_type_string').".");
				}
			}while(!$NodeLink && current($ClassSettings['LinkTypeList']));
			return array('error'=>false,'result'=>$NodeLink,'message'=>false);
		}
		return array('error'=>true,'result'=>false,'message'=>'Unable to find a valid NodeLink');
	}

	function normalize(){
		if($this->urlComponents){
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
		}
		return false;
	}

	function path(){
		$PathArray = $this->objectNode->pathArray();
		$SiteLinkOperator=new SiteLinkOperator();
		$namedParameters = array('parameters'=>false,'absolute'=>$this->parameters['absolute']);
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

	function setObjectNode($object=false){
		if($this->nodeID){
			$this->objectNode=$this->findObjectNode( $object ? $object : eZContentObjectTreeNode::fetch($this->nodeID)->object() );
			if($this->nodeID!=$this->objectNode->NodeID){
				$this->nodeID=$this->objectNode->NodeID;
				$this->urlComponents=self::URLComponents($this->objectNode->pathWithNames());
				$this->normalize();
			}
			if(!$this->urlComponents){
				$pathWithNames=$this->objectNode->pathWithNames();
				$this->urlComponents=self::URLComponents((empty($pathWithNames) && $this->isMultisite)?$this->pathPrefix:$pathWithNames);
			}
			return true;
		}elseif(is_numeric($this->operatorValue) || is_integer($this->operatorValue)){
			$this->nodeID=(int)$this->operatorValue;
			return $this->setObjectNode(call_user_func(array($this->parameters['node_id']?'eZContentObjectTreeNode':'eZContentObject','fetch'),$this->nodeID));
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
		if(is_string($value) && !is_numeric($value) && !parse_url($value,PHP_URL_SCHEME)){
			$DefaultURL = array('scheme'=>'http','host'=>false,'user'=>false,'pass'=>false,'path'=>false,'query'=>false,'fragment'=>false);
			if(preg_match_all(self::ANCHOR_REGEXP,$value,$Matches)){$value = ltrim(eZSys::requestURI().$value,'/');}
			$ParsedURL = array_merge($DefaultURL,parse_url($value));
			if($ParsedURL['path']){
				$URI=eZURI::instance($ParsedURL['path']);
				$ParsedURL['path']=$URI->uriString();
				$ParsedURL['user_parameters']=count($URI->userParameters())?$URI->userParameters():false;
				// Fixes bug where when no scheme is specified the host is retured as a path.
				if(!$ParsedURL['host'] && $MatchValue=preg_match_all(self::HOST_REGEXP,$ParsedURL['path'],$Matches)){
					return array_merge($DefaultURL,parse_url('http://'.$ParsedURL['path']));
				}
			}
			return $ParsedURL;
		}
		return false;
	}

	private static function parseParameters(&$object, $parameters){
		if(is_array($parameters['parameters'])){
			foreach($parameters['parameters'] as $key=>$value){
				$parameters['parameters'][$key]=((is_string($value)&&in_array($key,array('quotes','absolute')))?(($value=='yes')?true:false):$value);
			}
		}else{
			$parameters['parameters']=array('quotes'=>(is_string($parameters['parameters'])?(($parameters['parameters']=='yes')?true:false):$parameters['parameters']));
		}
		if(isset($parameters['absolute']) && !isset($parameters['parameters']['absolute'])){
			$parameters['parameters']['absolute']=(bool)$parameters['absolute'];
		}
		$object->parameters=array_merge(SiteLinkOperator::operatorDefaults('sitelink'),$parameters['parameters']);
	}

}

?>