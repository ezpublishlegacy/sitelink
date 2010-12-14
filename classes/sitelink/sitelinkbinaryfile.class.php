<?php

class SiteLinkBinaryFile implements SiteLinkDataTypeInterface
{
	public function modify($Attribute,$LinkType){
		$ContentObjectID = $Attribute->ContentObjectID;
		$ContentObjectAttributeID = $Attribute->ID;
		$FileName = urlencode($Attribute->content()->OriginalFilename);
		return "content/download/$ContentObjectID/$ContentObjectAttributeID/$FileName";
	}
}

?>