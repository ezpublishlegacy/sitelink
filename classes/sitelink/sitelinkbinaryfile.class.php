<?php

class SiteLinkBinaryFile implements SiteLinkDataTypeInterface
{
	public function modify($Attribute,$LinkType,$SiteLink){
		$ContentObjectID = $Attribute->ContentObjectID;
		$ContentObjectAttributeID = $Attribute->ID;
		$FileName = urlencode($Attribute->content()->OriginalFilename);
		return "content/download/$ContentObjectID/$ContentObjectAttributeID/".urlencode($FileName);
	}
}

?>