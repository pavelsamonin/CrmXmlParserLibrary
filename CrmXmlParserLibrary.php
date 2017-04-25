<?php
/**
 * Created by PhpStorm.
 * User: samonin
 * Date: 25.04.2017
 * Time: 10:32
 */
if (!defined('BASEPATH')) exit('No direct script access allowed');

class CrmXmlParserLibrary{
    function parseIt($entities){
        $listArray = [];
        $iii = 0;
        for ($length=$entities->length; $iii < $length; $iii++) {
            $entityObject = $this->deserialize($entities->item($iii));
            $entity = $this->businessEntity($entityObject->logicalName, $entityObject->id, $entityObject->attributes);
            array_push($listArray,$entity);
        }
        return $listArray;
    }

    function deserialize($entity){
        $objArray = new stdClass();
        $obj = array();
        $resultNodes = $entity->childNodes;
        $j = 0;
        for ($lenj=$resultNodes->length; $j < $lenj; $j++){
            $sKey = null;
            switch ($resultNodes->item($j)->nodeName) {
                case "b:Attributes":
                    $attr = $resultNodes->item($j);
                    $k = 0;
                    for ($lenk=$attr->childNodes->length; $k < $lenk; $k++) {
                        // Establish the Key for the Attribute
                        $sKey = $attr->childNodes->item($k)->firstChild->textContent;
                        $sType = '';
                        // Determine the Type of Attribute value we should expect
                        $l = 0;
                        for ($lenl = $attr->childNodes->item($k)->childNodes->item(1)->attributes->length; $l < $lenl; $l++) {
                            if ($attr->childNodes->item($k)->childNodes->item(1)->attributes->item($l)->nodeName === 'i:type') {
                                $sType = $attr->childNodes->item($k)->childNodes->item(1)->attributes->item($l)->nodeValue;
                            }
                        }
                        $ntRef = null;
                        $entCv = null;
                        switch ($sType) {
                            case "b:OptionSetValue":
                                $value = intval($attr->childNodes->item($k)->childNodes->item(1)->textContent);
                                $entOsv = $this->xrmOptionSetValue($value);
                                $obj[$sKey] = $entOsv;
                                break;
                            case "b:EntityReference":
                                $id = $attr->childNodes->item($k)->childNodes->item(1)->childNodes->item(0)->textContent;
                                $logicalName = $attr->childNodes->item($k)->childNodes->item(1)->childNodes->item(1)->textContent;
                                $name = $attr->childNodes->item($k)->childNodes->item(1)->childNodes->item(2)->textContent;
                                $entRef = $this->xrmEntityReference($id,$logicalName,$name);
                                $obj[$sKey] = $entRef;
                                break;
                            case "b:EntityCollection":
                                $items = [];
                                $y = 0;
                                for ($leny = $attr->childNodes->item($k)->childNodes->item(1)->childNodes->item(0)->childNodes->length; $y < $leny; $y++) {
                                    $itemNodes = $attr->childNodes->item($k)->childNodes->item(1)->childNodes->item(0)->childNodes->item($y)->childNodes->item(0)->childNodes;
                                    $z = 0;
                                    for ($lenz = $itemNodes->length; $z < $lenz; $z++) {
                                        if ($itemNodes->item($z)->childNodes->item(0)->textContent === "partyid") {
                                            $id = $itemNodes->item($z)->childNodes->item(1)->childNodes->item(0)->textContent;
                                            $logicalName = $itemNodes->item($z)->childNodes->item(1)->childNodes->item(1)->textContent;
                                            $name = $itemNodes->item($z)->childNodes->item(1)->childNodes->item(2)->textContent;
                                            $itemRef = $this->xrmEntityReference($id,$logicalName,$name);
                                            $obj[$y] = $itemRef;
                                        }
                                    }
                                }
                                $entRef = $this->xrmEntityCollection($items);
                                $obj[$sKey] = $entRef;
                                break;
                            case "b:Money":
                                $sType = str_replace ('b:', '', $sType);
                                $sValue = floatval($attr->childNodes->item($k)->childNodes->item(1)->textContent);
                                $entCv = $this->xrmValue($sType,$sValue);
                                $obj[$sKey] = $entCv;
                                break;
                            default:
                                $sType = str_replace ('d:', '', $sType);
                                $sType = str_replace ('b:', '', $sType);
                                if ($sType === "int") {
                                    $sValue = intval($attr->childNodes->item($k)->childNodes->item(1)->textContent);
                                }
                                elseif ($sType === "decimal" || $sType === "double") {
                                    $sValue = floatval($attr->childNodes->item($k)->childNodes->item(1)->textContent);
                                }
                                elseif ($sType === "dateTime") {
                                    $sValue = $this->stringToDate($attr->childNodes->item($k)->childNodes->item(1)->textContent);
                                }
                                elseif ($sType === "boolean") {
                                    $sValue = $attr->childNodes->item($k)->childNodes->item(1)->textContent ? false : true;
                                }
                                elseif ($sType === "AliasedValue") {
                                    $sValue = $attr->childNodes->item($k)->childNodes->item(1)->childNodes->item(2)->textContent;
                                    if ($attr->childNodes->item($k)->childNodes->item(1)->childNodes->item(2)->getAttribute("i:type") === "b:EntityReference") {
                                        $id = $attr->childNodes->item($k)->childNodes->item(1)->childNodes->item(2)->childNodes->item(0)->textContent;
                                        $logicalName = $attr->childNodes->item($k)->childNodes->item(1)->childNodes->item(2)->childNodes->item(1)->textContent;
                                        $name = $attr->childNodes->item($k)->childNodes->item(1)->childNodes->item(2)->childNodes->item(2)->textContent;
                                        $entCv = $this->xrmEntityReference($id, $logicalName, $name);
                                        $obj[$sKey] = $entCv;
                                        break;
                                    }
                                }
                                else {
                                    $sValue = $attr->childNodes->item($k)->childNodes->item(1)->textContent;
                                }
                                $entCv = $this->xrmValue($sType,$sValue);
                                $obj[$sKey] = $entCv;
                                break;
                        }
                    }
                    $objArray->attributes = $obj;
                    break;
                case "b:Id":
                    $objArray->id = $resultNodes->item($j)->textContent;
                    break;
                case "b:LogicalName":
                    $objArray->logicalName = $resultNodes->item($j)->textContent;
                    break;
                case "b:FormattedValues":
                    $foVal = $resultNodes->item($j);
                    $o = 0;
                    for ($leno = $foVal->childNodes->length; $o < $leno; $o++) {
                        // Establish the Key, we are going to fill in the formatted value of the already found attribute
                        $sKey = $foVal->childNodes->item($o)->firstChild->textContent;
                        $objArray->attributes[$sKey]->formattedValue = $foVal->childNodes->item($o)->childNodes->item(1)->textContent;
                        if (is_nan($objArray->attributes[$sKey]->value) && $objArray->attributes[$sKey]->type === "dateTime") {
                            $objArray->attributes[$sKey]->value = date("D M d Y H:i:s eO", $objArray->attributes[$sKey]->formattedValue);
                        }
                    }
                    break;
            }
        }
        return $objArray;
    }

    function businessEntity($logicalName, $id, $attributes) {
        $mainObj = new stdClass();
        $mainObj->id = (!$id) ? "00000000-0000-0000-0000-000000000000" : $id;
        $mainObj->logicalName = $logicalName;
        $mainObj->attributes = $attributes;
        return $mainObj;
    }

    function stringToDate($s) {
        $b = explode('/\D/', $s);
        return date("D M d Y H:i:s eO", mktime($b[0], --$b[1], $b[2], $b[3], $b[4], $b[5]));
    }

    function xrmOptionSetValue($iValue, $sFormattedValue = null) {
        $obj = new stdClass();
        $obj->value = $iValue;
        $obj->formattedValue = $sFormattedValue;
        $obj->type = 'OptionSetValue';
        return $obj;
    }

    function xrmValue($sType, $sValue) {
        $obj = new stdClass();
        $obj->type = $sType;
        $obj->value = $sValue;
        return $obj;
    }

    function xrmEntityReference($gId, $sLogicalName, $sName) {
        $obj = new stdClass();
        $obj->id = $gId;
        $obj->type = 'EntityReference';
        $obj->logicalName = $sLogicalName;
        $obj->name = $sName;
        return $obj;
    }

    function xrmEntityCollection($items) {
        $obj = new stdClass();
        $obj->value = $items;
        $obj->type = 'EntityCollection';
        return $obj;
    }
}