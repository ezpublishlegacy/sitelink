<?php

class SiteLinkURL implements SiteLinkDataTypeInterface
{
	public function modify($Attribute,$LinkType,$SiteLink){
		if($Attribute->hasContent()){
			return $Attribute->content();
		}
		return false;
	}
}

?>