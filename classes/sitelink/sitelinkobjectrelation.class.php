<?php

class SiteLinkObjectRelation implements SiteLinkDataTypeInterface
{
	public function modify($Attribute,$LinkType,$SiteLink){
		if($Attribute->hasContent()){
			return $Attribute->content()->mainNode()->urlAlias();
		}
		return false;
	}
}

?>