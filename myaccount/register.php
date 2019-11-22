<?php
/* Copyright (C) 2017-2019 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

//if (! defined('NOREQUIREUSER'))  define('NOREQUIREUSER','1');
//if (! defined('NOREQUIREDB'))    define('NOREQUIREDB','1');
//if (! defined('NOREQUIRESOC'))   define('NOREQUIRESOC','1');
//if (! defined('NOREQUIRETRAN'))  define('NOREQUIRETRAN','1');
//if (! defined('NOCSRFCHECK'))    define('NOCSRFCHECK','1');			// Do not check anti CSRF attack test
//if (! defined('NOIPCHECK'))      define('NOIPCHECK','1');				// Do not check IP defined into conf $dolibarr_main_restrict_ip
//if (! defined('NOSTYLECHECK'))   define('NOSTYLECHECK','1');			// Do not check style html tag into posted data
//if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL','1');		// Do not check anti POST attack test
//if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU','1');			// If there is no need to load and show top and left menu
//if (! defined('NOREQUIREHTML'))  define('NOREQUIREHTML','1');			// If we don't need to load the html.form.class.php
//if (! defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX','1');
if (! defined("NOLOGIN"))        define("NOLOGIN",'1');				    // If this page is public (can be called outside logged session)
if (! defined('NOIPCHECK'))      define('NOIPCHECK','1');				// Do not check IP defined into conf $dolibarr_main_restrict_ip

// Add specific definition to allow a dedicated session management
include ('./mainmyaccount.inc.php');

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include($_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php");
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include(substr($tmp, 0, ($i+1))."/main.inc.php");
if (! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php")) $res=include(dirname(substr($tmp, 0, ($i+1)))."/main.inc.php");
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
dol_include_once('/sellyoursaas/class/packages.class.php');

// Re set variables specific to new environment
$conf->global->SYSLOG_FILE_ONEPERSESSION=1;
$langs=new Translate('', $conf);
$langs->setDefaultLang(GETPOST('lang','aZ09')?GETPOST('lang','aZ09'):'auto');

$langsen=new Translate('', $conf);
$langsen->setDefaultLang('en_US');

$langs->loadLangs(array("main","companies","sellyoursaas@sellyoursaas","errors"));
$langsen->loadLangs(array("main","companies","sellyoursaas@sellyoursaas","errors"));


$partner=GETPOST('partner','int');
$partnerkey=GETPOST('partnerkey','alpha');
$plan=GETPOST('plan','alpha');
$sldAndSubdomain=GETPOST('sldAndSubdomain','alpha');
$tldid=GETPOST('tldid','alpha');
$origin = GETPOST('origin','aZ09');

$socid=GETPOST('socid','int')?GETPOST('socid','int'):GETPOST('reusesocid','int');
$reusecontractid = GETPOST('reusecontractid','int');
$reusesocid = GETPOST('reusesocid','int');
$fromsocid = GETPOST('fromsocid','int');
$disablecustomeremail = GETPOST('disablecustomeremail', 'alpha');
$extcss=GETPOST('extcss','alpha');

$productid=GETPOST('service','int');
$productref=(GETPOST('productref','alpha')?GETPOST('productref','alpha'):'');
if (empty($productid) && empty($productref))
{
	$productref = $plan;
	if (empty($productref))
	{
	    include_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

	    // SERVER_NAME here is myaccount.mydomain.com (we can exploit only the part mydomain.com)
	    $domainname = getDomainFromURL($_SERVER["SERVER_NAME"], 1);

		// Take first plan found
		$sqlproducts = 'SELECT p.rowid, p.ref, p.label, p.price, p.price_ttc, p.duration, pa.restrict_domains';
		$sqlproducts.= ' FROM '.MAIN_DB_PREFIX.'product as p, '.MAIN_DB_PREFIX.'product_extrafields as pe';
		$sqlproducts.= ' LEFT JOIN '.MAIN_DB_PREFIX.'packages as pa ON pe.package = pa.rowid';
		$sqlproducts.= ' WHERE p.tosell = 1 AND p.entity = '.$conf->entity;
		$sqlproducts.= " AND pe.fk_object = p.rowid AND pe.app_or_option = 'app'";
		$sqlproducts.= " AND p.ref NOT LIKE '%DolibarrV1%'";
		// restict_domains can be empty (it's ok), can be mydomain.com or can be with.mydomain.com
		$sqlproducts.= " AND (pa.restrict_domains IS NULL OR pa.restrict_domains = '".$db->escape($domainname)."' OR pa.restrict_domains LIKE '%.".$db->escape($domainname)."')";
		$sqlproducts.= " ORDER BY p.datec";
		//print $_SERVER["SERVER_NAME"].' - '.$sqlproducts;
		$resqlproducts = $db->query($sqlproducts);
		if ($resqlproducts)
		{
			$num = $db->num_rows($resqlproducts);

			$tmpprod = new Product($db);
			$obj = $db->fetch_object($resqlproducts);
			if ($obj)
			{
				$productref = $obj->ref;
			}
		}
		else
		{
		    dol_print_error($db);
		}
	}
}

$tmpproduct = new Product($db);
$tmppackage = new Packages($db);

// Load main product
if ($productref != 'none')
{
	$result = $tmpproduct->fetch($productid, $productref);
	if (empty($tmpproduct->id))
	{
		print 'Service/Plan (Product id / ref) '.$productid.' / '.$productref.' was not found.';
		exit;
	}
	// We have the main product, we are searching the package
	if (empty($tmpproduct->array_options['options_package']))
	{
		print 'Service/Plan (Product id / ref) '.$tmpproduct->id.' / '.$productref.' has no package defined on it.';
		exit;
	}
	$productref = $tmpproduct->ref;

	$tmppackage->fetch($tmpproduct->array_options['options_package']);
	if (empty($tmppackage->id))
	{
		print "Package with id '".$tmpproduct->array_options['options_package']." was not found.";
		exit;
	}
}

if ($conf->global->CONTRACT_ADDON == 'mod_contract_olive')
{
    print "You must configure the module Contract to a numbering module that is able to generate new number for new contracts.";
    exit;
}


// Check partner exists if provided
if ($partner)
{
	$partnerthirdparty=new Societe($db);
	$partnerthirdparty->fetch($partner);
	if (! $partnerthirdparty->id || (md5($partnerthirdparty->name_alias) != GETPOST('partnerkey','alpha') && $partnerthirdparty->name_alias != GETPOST('partnerkey','alpha')))
	{
		print 'Bad partner keys.';
		exit;
	}
}


if ($reusecontractid)
{
	$contract = new Contrat($db);
	$contract->fetch($reusecontractid);
	$socid = $contract->fk_soc;
	$tmparray=explode('.', $contract->ref_customer);
	$sldAndSubdomain=$tmparray[0];
	$tldid=$tmparray[1];
}

$mythirdparty = new Societe($db);
if ($socid > 0)
{
	$mythirdparty->fetch($socid);
}



/*
 * Action
 */

// Nothing



/*
 * View
 */

$form = new Form($db);

$conf->dol_hide_topmenu = 1;
$conf->dol_hide_leftmenu = 1;

$favicon=getDomainFromURL($_SERVER['SERVER_NAME'], 0);
if (! preg_match('/\.(png|jpg)$/', $favicon)) $favicon.='.png';
if (! empty($conf->global->MAIN_FAVICON_URL)) $favicon=$conf->global->MAIN_FAVICON_URL;

if ($favicon) $head.='<link rel="icon" href="img/'.$favicon.'">'."\n";
$head.='<!-- Bootstrap core CSS -->
<!--<link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.css" rel="stylesheet">-->
<link href="dist/css/bootstrap.css" rel="stylesheet">';
if ($extcss)
{
    $head.='<link href="'.$extcss.'" rel="stylesheet">';
}
else
{
    $head.='<link href="dist/css/myaccount.css" rel="stylesheet">';
}

// Javascript code on logon page only to detect user tz, dst_observed, dst_first, dst_second
$arrayofjs=array(
    '/includes/jstz/jstz.min.js'.(empty($conf->dol_use_jmobile)?'':'?version='.urlencode(DOL_VERSION)),
    '/core/js/dst.js'.(empty($conf->dol_use_jmobile)?'':'?version='.urlencode(DOL_VERSION))
);

$title = $langs->trans("Registration").($tmpproduct->label?' ('.$tmpproduct->label.')':'');

llxHeader($head, $title, '', '', 0, 0, $arrayofjs, array(), '', 'register');

$prefix=dol_getprefix('');
$cookieregistrationa='DOLREGISTERA_'.$prefix;
if (empty($_COOKIE[$cookieregistrationa])) setcookie($cookieregistrationa, 1, 0, "/", null, false, true);	// Cookie to count nb of registration from this computer

?>

<div id="waitMask" style="display:none;">
    <font size="3em" style="color:#888; font-weight: bold;"><?php echo $langs->trans("InstallingInstance") ?><br><?php echo $langs->trans("PleaseWait") ?><br></font>
    <img id="waitMaskImg" width="100px" src="<?php echo 'ajax-loader.gif'; ?>" alt="Loading" />
</div>


<div class="large">
        <?php
        $sellyoursaasdomain = $conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME;
        $sellyoursaasname = $conf->global->SELLYOURSAAS_NAME;

        $domainname=getDomainFromURL($_SERVER['SERVER_NAME'], 1);
        $constforaltname = 'SELLYOURSAAS_NAME_FORDOMAIN-'.$domainname;
        if (! empty($conf->global->$constforaltname))
        {
            $sellyoursaasdomain = $domainname;
            $sellyoursaasname = $conf->global->$constforaltname;
            //var_dump($constforaltname.' '.$sellyoursaasdomain.' '.$sellyoursaasname);   // Example: 'SELLYOURSAAS_NAME_FORDOMAIN-glpi-network.cloud glpi-network.cloud GLPI-Network'
        }

        $linklogo = '';
        if ($partnerthirdparty->id > 0)     // Show logo of partner
        {
        	require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
        	$ecmfile=new EcmFiles($db);
        	$relativepath = $conf->societe->multidir_output[$conf->entity]."/".$partnerthirdparty->id."/logos/".$partnerthirdparty->logo;
        	$relativepath = preg_replace('/^'.preg_quote(DOL_DATA_ROOT,'/').'/', '', $relativepath);
        	$relativepath = preg_replace('/[\\/]$/', '', $relativepath);
        	$relativepath = preg_replace('/^[\\/]/', '', $relativepath);

        	$ecmfile->fetch(0, '', $relativepath);
        	if ($ecmfile->id > 0)
        	{
        		$linklogo = DOL_URL_ROOT.'/viewimage.php?modulepart=societe&hashp='.$ecmfile->share;
        	}
        }
        if (empty($linklogo))               // Show main logo of Cloud service
        {
            // Show logo (search in order: small company logo, large company logo, theme logo, common logo)
            $linklogo = '';
            $constlogo = 'SELLYOURSAAS_LOGO';
            $constlogosmall = 'SELLYOURSAAS_LOGO_SMALL';

            $constlogoalt = 'SELLYOURSAAS_LOGO_'.str_replace('.', '_', strtoupper($sellyoursaasdomain));
            $constlogosmallalt = 'SELLYOURSAAS_LOGO_SMALL_'.str_replace('.', '_', strtoupper($sellyoursaasdomain));

            //var_dump($sellyoursaasdomain.' '.$constlogoalt.' '.$conf->global->$constlogoalt);exit;
            if (! empty($conf->global->$constlogoalt))
            {
                $constlogo=$constlogoalt;
                $constlogosmall=$constlogosmallalt;
            }

            if (empty($linklogo) && ! empty($conf->global->$constlogosmall))
            {
                if (is_readable($conf->mycompany->dir_output.'/logos/thumbs/'.$conf->global->$constlogosmall))
                {
                    $linklogo=DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode('logos/thumbs/'.$conf->global->$constlogosmall);
                }
            }
            elseif (empty($urllogo) && ! empty($conf->global->$constlogo))
            {
                if (is_readable($conf->mycompany->dir_output.'/logos/'.$conf->global->$constlogo))
                {
                    $linklogo=DOL_URL_ROOT.'/viewimage.php?cache=1&modulepart=mycompany&file='.urlencode('logos/'.$conf->global->$constlogo);
                }
            }
            elseif (empty($urllogo) && is_readable(DOL_DOCUMENT_ROOT.'/theme/'.$conf->theme.'/img/dolibarr_logo.png'))
            {
                $linklogo=DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/dolibarr_logo.png';
            }
            elseif (empty($urllogo) && is_readable(DOL_DOCUMENT_ROOT.'/theme/dolibarr_logo.png'))
            {
                $linklogo=DOL_URL_ROOT.'/theme/dolibarr_logo.png';
            }
            else
            {
                $linklogo=DOL_URL_ROOT.'/theme/login_logo.png';
            }
        }

        if (! GETPOST('noheader','int'))
        {
        ?>
		<div class="page-header-top">
		    <div class="container">
		      <div class="registerheader" style="display:flex; justify-content:space-between;">
				  <div class="valignmiddle" style="padding-right: 25px;">
		          <img class="logoheader"  src="<?php echo $linklogo; ?>" id="logo" /><br>
		          </div>
				  <?php if (empty($mythirdparty->id)) {
				    $langs->load("website");
				      ?>
		          <div class="paddingtop20" style="float: right;">
		              <div class="padding: 4px 10px 5px 10px;">
		              <span class="opacitymedium hideonsmartphone"><?php echo $langs->trans("AlreadyHaveAnAccount"); ?>&nbsp;</span>
		              <a href="/" class="btn blue btn-sm btnalreadyanaccount"><?php echo $langs->trans("LoginAction"); ?></a>
		              </div>
		              <?php if (empty($partner) && empty($partnerkey)) { ?>
		              <div class="padding: 4px 10px 5px 10px;">
		              <span class="opacitymedium"><a class="blue btn-sm" style="padding-left: 0;" href="https://<?php echo $sellyoursaasdomain ?>"><?php echo $langs->trans("BackToHomePage"); ?></a></span>
		              </div>
		              <?php } ?>
		          </div>
		          <?php } ?>
		      </div>

		      <!-- BEGIN TOP NAVIGATION MENU -->
		      <div class="top-menu">
		      </div> <!-- END TOP NAVIGATION MENU -->

		    </div>
		  </div>
		  <?php
          }
          ?>
      <div class="block medium center">
		<?php
        if (! GETPOST('noheader','int'))
        {
        ?>
        <header class="invers">
          <h1><?php echo $langs->trans("Registration") ?> <small><?php echo ($tmpproduct->label?'('.$tmpproduct->label.')':''); ?></small></h1>
        </header>
		<?php
        }
        ?>
		<div class="signup2 centpercent">

			<?php
			if (! empty($tmpproduct->array_options['options_register_text']))
			{
			    print '<div class="register_text">'.$langs->trans($tmpproduct->array_options['options_register_text']).'</div>';
			}
			?>

	      <form action="register_instance.php" method="post" id="formregister">
    	    <div class="form-content">
	    	  <input type="hidden" name="token" value="<?php echo $_SESSION['newtoken']; ?>" />
	          <input type="hidden" name="service" value="<?php echo dol_escape_htmltag($tmpproduct->id); ?>" />
	          <input type="hidden" name="productref" value="<?php echo ($productref == 'none' ? 'none' : dol_escape_htmltag($tmpproduct->ref)); ?>" />
	          <input type="hidden" name="extcss" value="<?php echo dol_escape_htmltag($extcss); ?>" />
	          <input type="hidden" name="package" value="<?php echo dol_escape_htmltag($tmppackage->ref); ?>" />
	          <input type="hidden" name="partner" value="<?php echo dol_escape_htmltag($partner); ?>" />
	          <input type="hidden" name="partnerkey" value="<?php echo dol_escape_htmltag($partnerkey); ?>" />
	          <input type="hidden" name="socid" value="<?php echo dol_escape_htmltag($socid); ?>" />
	          <input type="hidden" name="reusesocid" value="<?php echo dol_escape_htmltag($reusesocid); ?>" />
	          <input type="hidden" name="reusecontractid" value="<?php echo dol_escape_htmltag($reusecontractid); ?>" />
	          <input type="hidden" name="fromsocid" value="<?php echo dol_escape_htmltag($fromsocid); ?>" />
	          <input type="hidden" name="origin" value="<?php echo dol_escape_htmltag($origin); ?>" />
	          <input type="hidden" name="disablecustomeremail" value="<?php echo dol_escape_htmltag($disablecustomeremail); ?>" />

	          <section id="enterUserAccountDetails">


			<?php
			$disabled='';
			if (! empty($conf->global->SELLYOURSAAS_DISABLE_NEW_INSTANCES))
			{
			    $disabled=' disabled';
			    print '<div class="alert alert-warning">';
			    print $langs->trans("RegistrationSuspendedForTheMomentPleaseTryLater");
			    print '</div>';
			}

			if (isset($_SESSION['dol_events']['errors'])) {
				print '<div class="alert alert-error">';
				if (is_array($_SESSION['dol_events']['errors']))
				{
					foreach($_SESSION['dol_events']['errors'] as $key => $val)
					{
						print '<ul><li>'.$val.'</li></ul>';
					}
				}
				else
				{
					print '<ul><li>'.$_SESSION['dol_events']['errors'].'</li></ul>';
				}
				print '</div><br>'."\n";
			}
            ?>

			<?php
			if (empty($mythirdparty->id))
			{
			?>
            <div class="control-group  required">
            	<label class="control-label" for="username" trans="1"><?php echo $langs->trans("Email") ?></label>
            	<div class="controls">
            		<input type="text"<?php echo $disabled; ?> name="username" autofocus value="<?php echo GETPOST('username','alpha'); ?>" required="" id="username" />

            	</div>
            </div>

            <div class="control-group  required">
            	<label class="control-label" for="orgName" trans="1"><?php echo $langs->trans("NameOfCompany") ?></label>
            	<div class="controls">
            		<input type="text"<?php echo $disabled; ?> name="orgName" value="<?php echo GETPOST('orgName','alpha'); ?>" required="" maxlength="250" id="orgName" />
            	</div>
            </div>
			<?php
			}
			if (empty($reusecontractid))
			{
			?>
            <div class="group">
                <div class="horizontal-fld">

                <div class="control-group  required">
                	<label class="control-label" for="password" trans="1"><?php echo $langs->trans("Password") ?></label>
                	<div class="controls">

                        <input<?php echo $disabled; ?> name="password" type="password" required />

                	</div>
                </div>

                </div>
                <div class="horizontal-fld">
                  <div class="control-group required">
                    <label class="control-label" for="password2" trans="1"><?php echo $langs->trans("ConfirmPassword") ?></label>
                    <div class="controls">
                      <input<?php echo $disabled; ?> name="password2" type="password" required />
                    </div>
                  </div>
                </div>
            </div>
			<?php
			}


			if (empty($mythirdparty->id))
			{
			?>

			<div class="control-group  ">
				<label class="control-label" for="address_country"><?php echo $langs->trans("Country") ?></label>
				<div class="controls">
			<?php
			$countryselected=strtoupper(dolGetCountryCodeFromIp($_SERVER["REMOTE_ADDR"]));
			print '<!-- Autodetected IP/Country: '.$_SERVER["REMOTE_ADDR"].'/'.$countryselected.' -->'."\n";
			if (empty($countryselected)) $countryselected='US';
			if (GETPOST('address_country','alpha')) $countryselected=GETPOST('address_country','alpha');
			print $form->select_country($countryselected, 'address_country', 'optionsValue="name"'.$disabled, 0, 'minwidth300', 'code2', 1, 1);
			?>
				</div>
			</div>

			<?php
			}
			?>

          </section>

          <?php
          if ($productref != 'none')
          {
	          if (empty($reusecontractid)) print '<br>';
	          else print '<hr/>';

	          ?>

			  <!-- Selection of domain to create instance -->
	          <section id="selectDomain">
	            <div class="fld select-domain required">
	              <label trans="1"><?php echo $langs->trans("ChooseANameForYourApplication") ?></label>
	              <div class="linked-flds">
	                <span class="opacitymedium">https://</span>
	                <input<?php echo $disabled; ?> class="sldAndSubdomain" type="text" name="sldAndSubdomain" value="<?php echo $sldAndSubdomain; ?>" maxlength="29" />
	                <select<?php echo $disabled; ?> name="tldid" id="tldid" >
	                	<?php
	                	// SERVER_NAME here is myaccount.mydomain.com (we can exploit only the part mydomain.com)
	                	$domainname = getDomainFromURL($_SERVER["SERVER_NAME"], 1);

	                	$listofdomain = explode(',', $conf->global->SELLYOURSAAS_SUB_DOMAIN_NAMES);   // This is list of all domains to show into combo list
	                	foreach($listofdomain as $val)
	                	{
	                	    $newval = $val;
	                	    $reg = array();
	                	    if (preg_match('/:(.*)$/', $newval, $reg)) {      // If this domain must be shown only if domain match
	                	        $newval = preg_replace('/:.*$/', '', $newval);
	                	        if ($reg[1] != $domainname && $reg[1] != GETPOST('forcetoacceptdomain', 'alpha')) continue;
	                	    }

	                	    if (! empty($tmppackage->restrict_domains))   // There is a restriction on some domains for this package
	                	    {
	                	        $restrictfound = false;
	                	        $tmparray=explode(',', $tmppackage->restrict_domains);
	                	        foreach($tmparray as $tmprestrictdomain)
	                	        {
	                	            //var_dump($val.' - '.$tmprestrictdomain);
	                	            if ($newval == $tmprestrictdomain)
                                    {
                                        $restrictfound=true;
                                        break;
                                    }
	                	        }
	                	        if (! $restrictfound) continue;   // The domain in SELLYOURSAAS_SUB_DOMAIN_NAMES is inside restrictlist of package
	                	    }

	                		if (! preg_match('/^\./', $newval)) $newval='.'.$newval;
	                		print '<option value="'.$newval.'"'.(GETPOST('tldid','alpha') == $newval ? ' selected="selected"':'').'>'.$newval.'</option>';
	                	}
	                    ?>
	                </select>
	                <br class="unfloat" />
	              </div>
	            </div>
	          </section>
			<?php
          	}
			?>

			<br>

       </div>

          <section id="formActions">
			<?php
			$urlfortermofuse = '';
			if ($conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME == 'dolicloud.com')
			{
				$urlfortermofuse = 'https://www.'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/en-terms-and-conditions.php';
				if (preg_match('/^fr/i', $langs->defaultlang)) $urlfortermofuse = 'https://www.'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/fr-conditions-utilisations.php';
				if (preg_match('/^es/i', $langs->defaultlang)) $urlfortermofuse = 'https://www.'.$conf->global->SELLYOURSAAS_MAIN_DOMAIN_NAME.'/es-terminos-y-condiciones.php';
			}
			if ($urlfortermofuse)
			{
			?>
	          <p class="termandcondition center" style="color:#444;margin:10px 0;" trans="1"><?php echo $langs->trans("WhenRegisteringYouAccept", $urlfortermofuse) ?></p>
	        <?php
			}
			?>
	          <div class="form-actions center"">
	          	<?php
	          	if ($productref != 'none')
	          	{
	          	?>
	            	<input type="submit"<?php echo $disabled; ?> name="submit" style="margin: 10px;" value="<?php echo $langs->trans("SignMeUp") ?>" class="btn btn-primary" id="submit" />
	            <?php
	          	}
	          	else
	          	{
	          	?>
	            	<input type="submit"<?php echo $disabled; ?> name="submit" style="margin: 10px;" value="<?php echo $langs->trans("CreateMyAccount") ?>" class="btn btn-primary" id="submit" />
	          	<?php
	          	}
	          	?>
	          </div>
          </section>


        <!-- Add fields to send local user information -->
        <input type="hidden" name="tz" id="tz" value="" />
        <input type="hidden" name="tz_string" id="tz_string" value="" />
        <input type="hidden" name="dst_observed" id="dst_observed" value="" />
        <input type="hidden" name="dst_first" id="dst_first" value="" />
        <input type="hidden" name="dst_second" id="dst_second" value="" />
        <input type="hidden" name="screenwidth" id="screenwidth" value="" />
        <input type="hidden" name="screenheight" id="screenheight" value="" />


     </form> <!-- end form-content -->

	</div>

  </div>
</div>




<script type="text/javascript" language="javascript">
    function applyDomainConstraints( domain )
    {
        domain = domain.replace(/ /g,"");
        domain = domain.replace(/\W/g,"");
        domain = domain.replace(/\_/g,"");
        domain = domain.toLowerCase();
        if (!isNaN(domain)) {
          return ""
        }
        while ( domain.length >1 && !isNaN( domain.charAt(0))  ){
          domain=domain.substr(1)
        }
        return domain
    }

    jQuery(document).ready(function() {

        /* Autofill the domain */
        jQuery("[name=orgName]").change(function(){
        	dn = applyDomainConstraints( $(this).val() )
    	    	$("[name=sldAndSubdomain]").val( applyDomainConstraints( $(this).val() ) );
        });


        /* Sow hourglass */
        $('#formregister').submit(function() {
                console.log("We clicked on submit")
                jQuery(document.body).css({ 'cursor': 'wait' });
                jQuery("div#waitMask").show();
                jQuery("#waitMask").css("opacity"); // must read it first
                jQuery("#waitMask").css("opacity", "0.5");
                return true;
        });
	});
</script>


<?php

llxFooter('', 'public', 1);		// We disabled output of messages. Already done into page
$db->close();
