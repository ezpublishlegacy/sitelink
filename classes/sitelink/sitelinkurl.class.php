<?php

class SiteLinkURL implements SiteLinkDataTypeInterface
{
	public function modify($Attribute,$LinkType,$SiteLink){
		if($Attribute->hasContent() || $Attribute->content()){
			return $Attribute->content();
		}
		return false;
	}
}

?>