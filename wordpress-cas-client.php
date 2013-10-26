<?php
/*
Plugin Name: WordPress CAS Client
Plugin URI: https://github.com/mfenner/wordpress-cas-client
Description: Integrates WordPress with existing CAS single sign-on architectures. This plugin is a fork of the wordpress-cas-client plugin at https://github.com/BellevueCollege/wordpress-cas-client.
Version: 1.3.0
Author: Martin Fenner
Author URI: http://blog.martinfenner.org
License: GPL2
*/

// include common functions, etc.

include_once(dirname(__FILE__)."/cas-client-constants.php");
include_once(dirname(__FILE__)."/utilities.php");
// automatically include class files when encountered
spl_autoload_register('class_autoloader');
// Must explicitly include class file when referencing static members
include_once(dirname(__FILE__)."/casManager.php");
// This global variable is set to either 'get_option' or 'get_site_option' depending on multisite option value
global $get_options_func ;
//This global variable is defaulted to 'options.php' , but for network setting we want the form to submit to itself, so we will leave it empty
global $form_action;

if (file_exists( dirname(__FILE__).'/config.php' ) )
    /** @noinspection PhpIncludeInspection */
    include_once( dirname(__FILE__).'/config.php' ); // attempt to fetch the optional config file

if (file_exists( dirname(__FILE__).'/cas-server-ui.php' ) )
	include_once( dirname(__FILE__).'/cas-server-ui.php' ); // attempt to fetch the optional config file


if (file_exists( dirname(__FILE__).'/cas-password-encryption.php' ) )
	include_once( dirname(__FILE__).'/cas-password-encryption.php' );

// helps separate debug output
debug_log("================= Executing wordpress-cas-client.php ===================\n");

if (file_exists( dirname(__FILE__).'/cas-client-ui.php' ) )
	include_once( dirname(__FILE__).'/cas-client-ui.php' ); // attempt to fetch the optional config file

$get_options_func = "get_option";
updateSettings();
	if(is_multisite())
	{

		add_action( 'network_admin_menu', 'cas_client_settings' );
		debug_log("multisite true");
		$get_options_func = "get_site_option";
	}
	debug_log("version :". $get_options_func('cas_client_version'));

global $wpcasldap_options;
if($wpcasldap_options)
{
	if (!is_array($wpcasldap_options))
		$wpcasldap_optons = array();
}

$cas_client_use_options = cas_client_getoptions();
debug_log("(wordpress-cas-client) options: ".print_r($cas_client_use_options,true));

global $casManager;
$casManager = new casManager($cas_client_use_options);

// plugin hooks into authentication system
add_action('wp_authenticate', 'cas_client_authenticate', 10, 2);
add_action('wp_logout', 'cas_client_logout');
add_action('lost_password', 'cas_client_lost_password');
add_action('retrieve_password', 'cas_client_retrieve_password');
add_action('password_reset', 'cas_client_password_reset');
add_filter('show_password_fields', 'cas_client_show_password_fields');

if (is_admin() && !is_multisite()) {// Added condition not multisite because if multisite is true thn it should only show the settings in network admin menu.
	add_action( 'admin_init', 'cas_client_register_settings' );
	add_action( 'admin_menu', 'cas_client_options_page_add' );
}

function cas_client_authenticate()
{
  global $casManager;
  $casManager->authenticate();
}

function cas_client_logout()
{
  global $casManager;
  $casManager->logout();
}

function cas_client_lost_password()
{
  global $casManager;
  $casManager->disable_function();
}

function cas_client_retrieve_password()
{
  global $casManager;
  $casManager->disable_function();
}

function cas_client_password_reset()
{
  global $casManager;
  $casManager->disable_function();
}

function cas_client_show_password_fields($show_password_fields)
{
  global $casManager;
  return $casManager->show_password_fields($show_password_fields);
}

function sid2str($sid)
{
$srl = ord($sid[0]);
$number_sub_id = ord($sid[1]);
$x = substr($sid,2,6);
$h = unpack('N',"\x0\x0".substr($x,0,2));
$l = unpack('N',substr($x,2,6));
$iav = bcadd(bcmul($h[1],bcpow(2,32)),$l[1]);
for ($i=0; $i<$number_sub_id; $i++)
{
$sub_id = unpack('V', substr($sid, 8+4*$i, 4));
$sub_ids[] = $sub_id[1];
}
return sprintf('S-%d-%d-%s', $srl, $iav, implode('-',$sub_ids));
}






//----------------------------------------------------------------------------
//		ADMIN OPTION PAGE FUNCTIONS
//----------------------------------------------------------------------------

function cas_client_register_settings() {
	global $cas_client_options;

	$options = array('email_suffix', 'casserver', 'cas_version', 'include_path', 'server_hostname', 'server_port', 'server_path', 'useradd', 'userrole', 'casatt_name', 'casatt_operator', 'casatt_user_value_to_compare', 'casatt_wp_role', 'casatt_wp_site');

	foreach ($options as $o) {
		if (!isset($cas_client_options[$o])) {
			switch($o) {
				case 'cas_verion':
					$cleaner = 'cas_client_oneortwo';
					break;
				case 'useradd':
				case 'userrole':
					$cleaner = 'cas_client_fix_userrole';
					break;
				case 'server_port':
					$cleaner = 'intval';
					break;
				default:
					$cleaner = 'wpcasldap_dummy';
			}
			register_setting( 'wpcasldap', 'wpcasldap_'.$o,$cleaner );
		}
	}
}

// TODO: The following 5 functions look like perhaps they should be moved into utilities.php

function wpcasldap_strip_at($in) {
	return str_replace('@','',$in);
}
function wpcasldap_yesorno ($in) {
	return (strtolower($in) == 'yes')?'yes':'no';
}

function wpcasldap_oneortwo($in) {
	return ($in == '1.0')?'1.0':'2.0';
}
function wpcasldap_fix_userrole($in) {
	$roles = array('subscriber','contributor','author','editor','administrator');
	if (in_array($in,$roles))
		return $in;
	else
		return 'subscriber';
}
function wpcasldap_dummy($in) {
	return $in;
}

function cas_client_settings()
{
	add_submenu_page("settings.php","CAS Client","CAS Client","manage_network","casclient",'wpcasldap_options_page');
}

function wpcasldap_options_page_add() {

	if (function_exists('add_management_page'))
	{
		error_log("options general ----------------------------");
		add_submenu_page('options-general.php', 'CAS Client', 'CAS Client', CAPABILITY, 'casclient', 'wpcasldap_options_page');
	}
		//add_submenu_page('options-general.php', 'wpCAS with LDAP', 'wpCAS with LDAP', CAPABILITY, 'wpcasldap', 'wpcasldap_options_page');
	else
	{
		error_log("CAS Client for single site ----------------------------");
		add_options_page( 'CAS Client','CAS Client',CAPABILITY, basename(__FILE__), 'wpcasldap_options_page');
	}
		//add_options_page( __( 'wpCAS with LDAP', 'wpcasldap' ), __( 'wpCAS with LDAP', 'wpcasldap' ),CAPABILITY, basename(__FILE__), 'wpcasldap_options_page');

}



function wpcasldap_getoptions() {
	global $wpcasldap_options;
	global $get_options_func;
	//Parse the url to retrieve server_name, server_port and path
	$cas_server = $get_options_func('wpcasldap_casserver');
	$componentsOfUrl = parse_cas_url($cas_server);
	error_log("url componenets :".print_r($componentsOfUrl,true));
	$host = "";
	$port = "";
	$path = "";
	if($componentsOfUrl)
	{
		if(isset($componentsOfUrl['host']))
		{
			$host = $componentsOfUrl['host'];
		}

		if(isset($componentsOfUrl['port']))
			$port = $componentsOfUrl['port'];
		else
			$port = CAS_DEFAULT_PORT;

		if(isset($componentsOfUrl['path']))
			$path = $componentsOfUrl['path'];
		else
			$path = CAS_DEFAULT_PATH;
	}

//error_log("hostname :".$host);
//error_log("port :".$port);
//error_log("path :".$path);

	$out = array (
			'cas_version' => $get_options_func('wpcasldap_cas_version'),
			'include_path' => $get_options_func('wpcasldap_include_path'),
			'casserver' => $cas_server, //$get_options_func('wpcasldap_casserver'),
			'server_hostname' => $host,//$get_options_func('wpcasldap_server_hostname'),
			'server_port' => $port,//$get_options_func('wpcasldap_server_port'),
			'server_path' => $path,//$get_options_func('wpcasldap_server_path'),
			'useradd' => $get_options_func('wpcasldap_useradd'),
			'userrole' => $get_options_func('wpcasldap_userrole'),
			'casatt_name' => $get_options_func('wpcasldap_casatt_name'),
			'casatt_operator' => $get_options_func('wpcasldap_casatt_operator'),
			'casatt_user_value_to_compare' => $get_options_func('wpcasldap_casatt_user_value_to_compare'),
			'casatt_wp_role' => $get_options_func('wpcasldap_casatt_wp_role'),
			'casatt_wp_site' => $get_options_func('wpcasldap_casatt_wp_site')
		);

	if (is_array($wpcasldap_options) && count($wpcasldap_options) > 0)
    {
		foreach ($wpcasldap_options as $key => $val) {
			$out[$key] = $val;
		}
    }

    //error_log("OUT :".print_r($out,true));
	return $out;
}

function parse_cas_url(&$cas_server_url)
{
  $components =  parse_url($cas_server_url);
  if($components)
  {
    if(empty($components['host']) && !empty($components['path']))
    {
      error_log("path :".$components['path']);
      $cas_server_url = SCHEME.$cas_server_url;
      error_log("cas url :".$cas_server_url);
      $components =  parse_url($cas_server_url);
      error_log("componenets after editing url :".print_r($components,true));
    }
  }
  return $components;
}

function get_option_wrapper($opt)
{
  global $get_options_func;
  return $get_options_func($opt);
}

?>
