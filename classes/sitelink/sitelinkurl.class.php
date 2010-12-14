<?php

class SiteLinkURL implements SiteLinkDataTypeInterface
{
	public function modify($Attribute, $LinkType){
		if($Attribute->hasContent()){
			return $Attribute->content();
		}
		return false;
	}
}

?>