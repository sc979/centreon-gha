<?php
/*
 * Centreon is developped with GPL Licence 2.0 :
 * http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
 * Developped by : Julien Mathis - Romain Le Merlus 
 * 
 * The Software is provided to you AS IS and WITH ALL FAULTS.
 * Centreon makes no representation and gives no warranty whatsoever,
 * whether express or implied, and without limitation, with regard to the quality,
 * any particular or intended purpose of the Software found on the Centreon web site.
 * In no event will Centreon be liable for any direct, indirect, punitive, special,
 * incidental or consequential damages however they may arise and even if Centreon has
 * been previously advised of the possibility of such damages.
 * 
 * For information : contact@centreon.com
 */
 

	if (!isset ($oreon))
		exit ();
	
	function testServiceGroupExistence ($name = NULL)	{
		global $pearDB;
		global $form;
		$id = NULL;
		if (isset($form))
			$id = $form->getSubmitValue('sg_id');
		$DBRESULT =& $pearDB->query("SELECT sg_name, sg_id FROM servicegroup WHERE sg_name = '".htmlentities($name, ENT_QUOTES)."'");
		if (PEAR::isError($DBRESULT))
			print "DB Error : ".$DBRESULT->getDebugInfo()."<br />";
		$sg =& $DBRESULT->fetchRow();
		#Modif case
		if ($DBRESULT->numRows() >= 1 && $sg["sg_id"] == $id)	
			return true;
		#Duplicate entry
		else if ($DBRESULT->numRows() >= 1 && $sg["sg_id"] != $id)
			return false;
		else
			return true;
	}

	function enableServiceGroupInDB ($sg_id = null)	{
		if (!$sg_id) return;
		global $pearDB;
		$DBRESULT =& $pearDB->query("UPDATE servicegroup SET sg_activate = '1' WHERE sg_id = '".$sg_id."'");
		if (PEAR::isError($DBRESULT))
			print "DB Error : ".$DBRESULT->getDebugInfo()."<br />";
	}
	
	function disableServiceGroupInDB ($sg_id = null)	{
		if (!$sg_id) return;
		global $pearDB;
		$DBRESULT =& $pearDB->query("UPDATE servicegroup SET sg_activate = '0' WHERE sg_id = '".$sg_id."'");
		if (PEAR::isError($DBRESULT))
			print $DBRESULT->getDebugInfo()."<br />";
	}
	
	function deleteServiceGroupInDB ($serviceGroups = array())	{
		global $pearDB;
		foreach($serviceGroups as $key=>$value)
			$DBRESULT =& $pearDB->query("DELETE FROM servicegroup WHERE sg_id = '".$key."'");
			if (PEAR::isError($DBRESULT))
				print $DBRESULT->getDebugInfo()."<br />";
	}
	
	function multipleServiceGroupInDB ($serviceGroups = array(), $nbrDup = array())	{
		global $pearDB, $oreon, $is_admin;
		foreach($serviceGroups as $key=>$value)	{
			$DBRESULT =& $pearDB->query("SELECT * FROM servicegroup WHERE sg_id = '".$key."' LIMIT 1");
			if (PEAR::isError($DBRESULT))
				print $DBRESULT->getDebugInfo()."<br />";
			$row = $DBRESULT->fetchRow();
			$row["sg_id"] = '';
			for ($i = 1; $i <= $nbrDup[$key]; $i++)	{
				$val = NULL;
				$rq = NULL;
				foreach ($row as $key2=>$value2)	{
					$key2 == "sg_name" ? ($sg_name = $value2 = $value2."_".$i) : null;
					$val ? $val .= ($value2!=NULL?(", '".$value2."'"):", NULL") : $val .= ($value2!=NULL?("'".$value2."'"):"NULL");
				}
				if (testServiceGroupExistence($sg_name))	{
					$val ? $rq = "INSERT INTO servicegroup VALUES (".$val.")" : $rq = null;
					$DBRESULT =& $pearDB->query($rq);
					if (PEAR::isError($DBRESULT))
						print $DBRESULT->getDebugInfo()."<br />";
					$DBRESULT =& $pearDB->query("SELECT MAX(sg_id) FROM servicegroup");
					if (PEAR::isError($DBRESULT))
						print $DBRESULT->getDebugInfo()."<br />";
					$maxId =& $DBRESULT->fetchRow();
					if (isset($maxId["MAX(sg_id)"]))	{
						/*
						 *  Update LCA
						 */
						if (!$is_admin){
							$group_list = getGroupListofUser($pearDB);
							$resource_list = getResourceACLList($group_list);
							if (count($resource_list)){
								foreach ($resource_list as $res_id)	{			
									$DBRESULT3 =& $pearDB->query("INSERT INTO `acl_resources_sg_relations` (acl_res_id, sg_id) VALUES ('".$res_id."', '".$maxId["MAX(sg_id)"]."')");
									if (PEAR::isError($DBRESULT3))
										print "DB Error : ".$DBRESULT3->getDebugInfo()."<br />";
								}
								unset($resource_list);
							}
						}

						$DBRESULT->free();
						$DBRESULT =& $pearDB->query("SELECT DISTINCT sgr.host_host_id, sgr.hostgroup_hg_id, sgr.service_service_id FROM servicegroup_relation sgr WHERE sgr.servicegroup_sg_id = '".$key."'");
						if (PEAR::isError($DBRESULT))
								print $DBRESULT->getDebugInfo()."<br />";
						while($service =& $DBRESULT->fetchRow())	{
							$val = null;
							foreach ($service as $key2=>$value2)
								$val ? $val .= ($value2!=NULL?(", '".$value2."'"):", NULL") : $val .= ($value2!=NULL?("'".$value2."'"):"NULL");
							$DBRESULT2 =& $pearDB->query("INSERT INTO servicegroup_relation (host_host_id, hostgroup_hg_id, service_service_id, servicegroup_sg_id) VALUES (".$val.", '".$maxId["MAX(sg_id)"]."')");
							if (PEAR::isError($DBRESULT2))
								print $DBRESULT2->getDebugInfo()."<br />";
						}						
					}
				}
			}
		}
	}
		
	function insertServiceGroupInDB ($ret = array())	{
		$sg_id = insertServiceGroup($ret);
		updateServiceGroupServices($sg_id, $ret);
		return $sg_id;
	}
	
	function updateServiceGroupInDB ($sg_id = NULL)	{
		if (!$sg_id) return;
		updateServiceGroup($sg_id);
		updateServiceGroupServices($sg_id);
	}
		
	function insertServiceGroup($ret = array())	{
		global $form, $pearDB, $oreon, $is_admin;
		if (!count($ret))
			$ret = $form->getSubmitValues();
		$rq = "INSERT INTO servicegroup (sg_name, sg_alias, sg_comment, sg_activate) ";
		$rq .= "VALUES (";
		isset($ret["sg_name"]) && $ret["sg_name"] != NULL ? $rq .= "'".htmlentities($ret["sg_name"], ENT_QUOTES)."', " : $rq .= "NULL, ";
		isset($ret["sg_alias"]) && $ret["sg_alias"] != NULL ? $rq .= "'".htmlentities($ret["sg_alias"], ENT_QUOTES)."', " : $rq .= "NULL, ";
		isset($ret["sg_comment"]) && $ret["sg_comment"] != NULL ? $rq .= "'".htmlentities($ret["sg_comment"], ENT_QUOTES)."', " : $rq .= "NULL, ";
		isset($ret["sg_activate"]["sg_activate"]) && $ret["sg_activate"]["sg_activate"] != NULL ? $rq .= "'".$ret["sg_activate"]["sg_activate"]."'" : $rq .= "'0'";
		$rq .= ")";
		$DBRESULT =& $pearDB->query($rq);
		if (PEAR::isError($DBRESULT))
			print $DBRESULT->getDebugInfo()."<br />";
			
		$DBRESULT =& $pearDB->query("SELECT MAX(sg_id) FROM servicegroup");
		if (PEAR::isError($DBRESULT))
			print $DBRESULT->getDebugInfo()."<br />";
		$sg_id = $DBRESULT->fetchRow();
		
		/*
		 *  Update LCA
		 */
		if (!$is_admin){
			$group_list = getGroupListofUser($pearDB);
			$resource_list = getResourceACLList($group_list);
			if (count($resource_list)){
				foreach ($resource_list as $res_id)	{			
					$DBRESULT3 =& $pearDB->query("INSERT INTO `acl_resources_sg_relations` (acl_res_id, sg_id) VALUES ('".$res_id."', '".$sg_id["MAX(sg_id)"]."')");
					if (PEAR::isError($DBRESULT3))
						print "DB Error : ".$DBRESULT3->getDebugInfo()."<br />";
				}
				unset($resource_list);
			}
		}

		$DBRESULT->free();
		return ($sg_id["MAX(sg_id)"]);
	}
	
	function updateServiceGroup($sg_id)	{
		if (!$sg_id) return;
		global $form;
		global $pearDB;
		$ret = array();
		$ret = $form->getSubmitValues();
		$rq = "UPDATE servicegroup SET ";
		isset($ret["sg_name"]) && $ret["sg_name"] != NULL ? $rq .= "sg_name = '".htmlentities($ret["sg_name"], ENT_QUOTES)."', " : $rq .= "sg_name = NULL,";
		isset($ret["sg_alias"]) && $ret["sg_alias"] != NULL ? $rq.=	"sg_alias = '".htmlentities($ret["sg_alias"], ENT_QUOTES)."', " : $rq .= "sg_alias = NULL";
		isset($ret["sg_comment"]) && $ret["sg_comment"] != NULL ? $rq .= "sg_comment = '".htmlentities($ret["sg_comment"], ENT_QUOTES)."', " : $rq .= "sg_comment = NULL,";
		isset($ret["sg_activate"]["sg_activate"]) && $ret["sg_activate"]["sg_activate"] != NULL ? $rq .= "sg_activate = '".$ret["sg_activate"]["sg_activate"]."' " : $rq .= "sg_activate = '0'";
		$rq .= "WHERE sg_id = '".$sg_id."'";
		$DBRESULT =& $pearDB->query($rq);
		if (PEAR::isError($DBRESULT))
			print $DBRESULT->getDebugInfo()."<br />";
	}
	
	function updateServiceGroupServices($sg_id, $ret = array())	{
		if (!$sg_id) return;
		global $pearDB, $form;
		$rq  = 	"DELETE FROM servicegroup_relation ";
		$rq .= 	"WHERE servicegroup_sg_id = '".$sg_id."'";
		$DBRESULT =& $pearDB->query($rq);
		if (PEAR::isError($DBRESULT)) 
			print $DBRESULT->getDebugInfo()."<br />";
		isset($ret["sg_hServices"]) ? $ret = $ret["sg_hServices"] : $ret = $form->getSubmitValue("sg_hServices");
		for($i = 0; $i < count($ret); $i++)	{
			if (isset($ret[$i]) && $ret[$i]){
				$t = split("\-", $ret[$i]);
				$rq = "INSERT INTO servicegroup_relation (host_host_id, service_service_id, servicegroup_sg_id) VALUES ('".$t[0]."', '".$t[1]."', '".$sg_id."')";
				$DBRESULT =& $pearDB->query($rq);
				if (PEAR::isError($DBRESULT))
					print $DBRESULT->getDebugInfo()."<br />";
			}
		}
		isset($ret["sg_hgServices"]) ? $ret = $ret["sg_hgServices"] : $ret = $form->getSubmitValue("sg_hgServices");
		for($i = 0; $i < count($ret); $i++)	{
			$t = split("\-", $ret[$i]);
			$rq = "INSERT INTO servicegroup_relation (hostgroup_hg_id, service_service_id, servicegroup_sg_id) VALUES ('".$t[0]."', '".$t[1]."', '".$sg_id."')";
			$DBRESULT =& $pearDB->query($rq);
			if (PEAR::isError($DBRESULT))
				print $DBRESULT->getDebugInfo()."<br />";
		}
	}
?>