<?php

interface SiteLinkDataTypeInterface
{
	/**
		* Fetches and returns content
		*
		* @param object $Attribute
		* @param string $LinkType
	*/
	public function modify($Attribute,$LinkType);
}

?>