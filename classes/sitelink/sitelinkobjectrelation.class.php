<?php

class SiteLinkObjectRelation implements SiteLinkDataTypeInterface
{
	public function modify($Attribute,$LinkType,$SiteLink){
		if($Attribute->hasContent()){
			$OperatorValue=$Attribute->content();
			$NamedParameters=array('parameters'=>false,'absolute'=>true);
			SiteLinkOperator::sitelink($OperatorValue,$NamedParameters);
			return $OperatorValue;
		}
		return false;
	}
}

?>