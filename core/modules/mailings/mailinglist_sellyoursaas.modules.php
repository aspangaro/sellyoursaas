<?php
/* Copyright (C) 2005-2018 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This file is an example to follow to add your own email selector inside
 * the Dolibarr email tool.
 * Follow instructions given in README file to know what to change to build
 * your own emailing list selector.
 * Code that need to be changed in this file are marked by "CHANGE THIS" tag.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/mailings/modules_mailings.php';
include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formadmin.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';


/**
 * mailing_mailinglist_sellyousaas
 */
class mailing_mailinglist_sellyoursaas extends MailingTargets
{
	// CHANGE THIS: Put here a name not already used
	var $name='mailinglist_sellyoursaas';
	// CHANGE THIS: Put here a description of your selector module
	var $desc='Clients DoliCloud';
	// CHANGE THIS: Set to 1 if selector is available for admin users only
	var $require_admin=0;

	var $enabled=0;
	var $require_module=array();
	var $picto='sellyoursaas@sellyoursaas';
	var $db;


	/**
     *	Constructor
     *
     * 	@param	DoliDB	$db		Database handler
     */
	function __construct($db)
	{
		global $conf;

		$this->db=$db;
		if (is_array($conf->modules))
		{
			$this->enabled=in_array('sellyoursaas',$conf->modules);
		}
	}


    /**
     *   Affiche formulaire de filtre qui apparait dans page de selection des destinataires de mailings
     *
     *   @return     string      Retourne zone select
     */
    function formFilter()
    {
        global $langs;
        $langs->load("members");

        $form=new Form($this->db);
        $formcompany=new FormCompany($this->db);

        $arraystatus=array('processing'=>'Processing','done'=>'Done','undeployed'=>'Undeployed');

        $s='';
        $s.=$langs->trans("Type").': ';
        $s.=$formcompany->selectProspectCustomerType(GETPOST('client', 'alpha'), 'client');

        $s.=' ';

        $s.=$langs->trans("Status").': ';
        $s.='<select name="filter" class="flat">';
        $s.='<option value="none">&nbsp;</option>';
        foreach($arraystatus as $key => $status)
        {
	        $s.='<option value="'.$key.'">'.$status.'</option>';
        }
        $s.='</select>';

        $s.='<br> ';

        $s.=$langs->trans("Language").': ';
        $formother=new FormAdmin($db);
        $s.=$formother->select_language(GETPOST('lang_id', 'alpha'), 'lang_id', 0, 'null', 1, 0, 0, '', 0, 0, 1);

        $s.=$langs->trans("NotLanguage").': ';
        $formother=new FormAdmin($db);
        $s.=$formother->select_language(GETPOST('not_lang_id', 'alpha'), 'not_lang_id', 0, 'null', 1, 0, 0, '', 0, 0, 1);

        $s.='<br> ';

        $s.=$langs->trans("Country").': ';
        $formother=new FormAdmin($db);
        $s.=$form->select_country(GETPOST('country_id', 'alpha'), 'country_id');

        return $s;
    }


    /**
	 *  Renvoie url lien vers fiche de la source du destinataire du mailing
	 *
	 *  @param		int			$id		ID
	 *  @return     string      		Url lien
	 */
	function url($id)
	{
		return '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$id.'">'.img_object('',"company").'</a>';
	}


	/**
	 *  This is the main function that returns the array of emails
	 *
	 *  @param	int		$mailing_id    	Id of emailing
	 *  @param	array	$filtersarray   Requete sql de selection des destinataires
	 *  @return int           			<0 if error, number of emails added if ok
	 */
	function add_to_target($mailing_id, $filtersarray=array())
	{
		global $conf;

		$target = array();
		$cibles = array();
		$j = 0;

		foreach($_POST['lang_id'] as $key => $val) {
		    if (empty($val)) unset($_POST['lang_id'][$key]);
		}
		foreach($_POST['not_lang_id'] as $key => $val) {
		    if (empty($val)) unset($_POST['not_lang_id'][$key]);
		}

		$sql = " SELECT s.rowid as id, email, nom as lastname, '' as firstname, s.default_lang, c.code as country_code, c.label as country_label";
		$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields as se on se.fk_object = s.rowid";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_country as c on s.fk_pays = c.rowid";
		if (! empty($_POST['filter']) && $_POST['filter'] != 'none')
		{
			$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."contrat as co on co.fk_soc = s.rowid";
			$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as coe on coe.fk_object = co.rowid";
		}
		$sql.= ", ".MAIN_DB_PREFIX."categorie_societe as cs";
		$sql.= " WHERE email IS NOT NULL AND email <> ''";
		$sql.= " AND cs.fk_soc = s.rowid AND cs.fk_categorie = ".$conf->global->SELLYOURSAAS_DEFAULT_CUSTOMER_CATEG;
		/*if (! empty($_POST['options_dolicloud']) && $_POST['options_dolicloud'] != 'none')
		{
			$sql.= " AND se.dolicloud = '".$this->db->escape($_POST['options_dolicloud'])."'";
		}*/
		if (! empty($_POST['lang_id']) && $_POST['lang_id'] != 'none') $sql.= natural_search('default_lang', join(',', $_POST['lang_id']), 3);
		if (! empty($_POST['not_lang_id']) && $_POST['not_lang_id'] != 'none') $sql.= natural_search('default_lang', join(',', $_POST['not_lang_id']), -3);
		if (! empty($_POST['country_id']) && $_POST['country_id'] != 'none') $sql.= " AND fk_pays IN ('".$this->db->escape($_POST['country_id'])."')";
		if (! empty($_POST['filter']) && $_POST['filter'] != 'none')
		{
			$sql.= " AND coe.deployment_status = '".$this->db->escape($_POST['filter'])."'";
		}
		if (! empty($_POST['client']) && $_POST['client'] != '-1')
		{
		    $sql.= ' AND s.client IN ('.$this->db->escape($_POST['client']).')';
		}
		$sql.= " ORDER BY email";

		// Stocke destinataires dans cibles
		$result=$this->db->query($sql);
		if ($result)
		{
			$num = $this->db->num_rows($result);
			$i = 0;

			dol_syslog("mailinglist_sellyoursaas.modules.php: mailing $num target found");

			$old = '';
			while ($i < $num)
			{
				$obj = $this->db->fetch_object($result);
				if ($old <> $obj->email)
				{
					$cibles[$j] = array(
						'email' => $obj->email,
						'lastname' => $obj->lastname,
						'id' => $obj->id,
						'firstname' => $obj->firstname,
						'other' => 'lang='.$obj->default_lang.';country_code='.$obj->country_code,
						'source_url' => $this->url($obj->id),
						'source_id' => $obj->id,
						'source_type' => 'thirdparty'
					);
					$old = $obj->email;
					$j++;
				}

				$i++;
			}
		}
		else
		{
			dol_syslog($this->db->error());
			$this->error=$this->db->error();
			return -1;
		}

		// You must fill the $target array with record like this
		// $target[0]=array('email'=>'email_0','name'=>'name_0','firstname'=>'firstname_0');
		// ...
		// $target[n]=array('email'=>'email_n','name'=>'name_n','firstname'=>'firstname_n');

		// Example: $target[0]=array('email'=>'myemail@mydomain.com','name'=>'Doe','firstname'=>'John');

		// ----- Your code end here -----

		return parent::addTargetsToDatabase($mailing_id, $cibles);
	}


	/**
	 *	On the main mailing area, there is a box with statistics.
	 *	If you want to add a line in this report you must provide an
	 *	array of SQL request that returns two field:
	 *	One called "label", One called "nb".
	 *
	 *	@return		array
	 */
	function getSqlArrayForStats()
	{
		// CHANGE THIS: Optionnal

		//var $statssql=array();
		//$this->statssql[0]="SELECT field1 as label, count(distinct(email)) as nb FROM mytable WHERE email IS NOT NULL";

		return array();
	}


	/**
	 *	Return here number of distinct emails returned by your selector.
	 *	For example if this selector is used to extract 500 different
	 *	emails from a text file, this function must return 500.
	 *
	 *	@param	string	$filter		Filter
	 *	@param	string	$option		Options
	 *	@return	int					Nb of recipients
	 */
	function getNbOfRecipients($filter=1,$option='')
	{
		$a=parent::getNbOfRecipients("select count(distinct(email)) as nb from ".MAIN_DB_PREFIX."societe as s where email IS NOT NULL AND email != ''");
		if ($a < 0) return -1;
		return $a;
	}

}

