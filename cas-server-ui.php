<?php
if (file_exists( dirname(__FILE__).'/cas-password-encryption.php' ) )
	include_once( dirname(__FILE__).'/cas-password-encryption.php' );

	function updateSettings()
	{
		//saving CAS network settings
		$update_function = "update_option";
		if(is_multisite())
			$update_function = "update_site_option";
		if(isset($_POST['wpcasldap_include_path']))
		{
			error_log("post================");

			if(isset($_POST['wpcasldap_cas_version']))
				$update_function('wpcasldap_cas_version',$_POST['wpcasldap_cas_version']);

			if(isset($_POST['wpcasldap_include_path']))
				$update_function('wpcasldap_include_path',$_POST['wpcasldap_include_path']);

			if(isset($_POST['wpcasldap_casserver']))
				 $update_function('wpcasldap_casserver',$_POST['wpcasldap_casserver']);

			if(isset($_POST['wpcasldap_useradd']))
				 $update_function('wpcasldap_useradd',$_POST['wpcasldap_useradd']);

			if(isset($_POST['wpcasldap_userrole']))
				 $update_function('wpcasldap_userrole',$_POST['wpcasldap_userrole']);

			if(isset($_POST['wpcasldap_ldapuri']))
				 $update_function('wpcasldap_ldapuri',$_POST['wpcasldap_ldapuri']);

			if(isset($_POST['wpcasldap_useldap']))
				 $update_function('wpcasldap_useldap',$_POST['wpcasldap_useldap']);

			if(isset($_POST['wpcasldap_ldapbasedn']))
				 $update_function('wpcasldap_ldapbasedn',$_POST['wpcasldap_ldapbasedn']);

			if(isset($_POST['wpcasldap_ldapuser']))
				 $update_function('wpcasldap_ldapuser',$_POST['wpcasldap_ldapuser']);

			if(isset($_POST['wpcasldap_email_suffix']))
				 $update_function('wpcasldap_email_suffix',$_POST['wpcasldap_email_suffix']);

			//Encrypt password
			if(isset($_POST['wpcasldap_ldappassword']))
			{
				$ldappassword = $_POST['wpcasldap_ldappassword'];
				$ldappassword = wpcasclient_encrypt($ldappassword,$GLOBALS['ciphers']);
				$update_function('wpcasldap_ldappassword',$ldappassword);
			}
		}
	}
?>