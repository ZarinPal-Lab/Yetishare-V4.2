<?php

class pluginFaragate extends Plugin
{

    public $config = null;

    public function __construct()
    {
        // get the plugin config
        include(DOC_ROOT.'/plugins/faragate/_plugin_config.inc.php');

        // load config into the object
        $this->config = $pluginConfig;
    }

    public function getPluginDetails()
    {
        return $this->config;
    }
    
    public function install()
    {
        // setup database
        $db = Database::getDatabase();

        // copy over FaraGate details from core if they exist
        $pre_faragate_merchant = $db->getValue('SELECT config_value FROM site_config WHERE config_key="faragate_payments_merchant" LIMIT 1');
        $pre_faragate_sandbox = $db->getValue('SELECT config_value FROM site_config WHERE config_key="faragate_payments_sandbox" LIMIT 1');
        $pre_faragate_currency = $db->getValue('SELECT config_value FROM site_config WHERE config_key="faragate_payments_currency" LIMIT 1');
        if( $pre_faragate_merchant && $pre_faragate_sandbox  && $pre_faragate_currency )
        {
            // get plugin details
            $pluginDetails = $this->getPluginDetails();

            // update settings
            $db = Database::getDatabase();
            $db->query('UPDATE plugin SET plugin_settings = :plugin_settings WHERE folder_name = :folder_name', 
				array('
					plugin_settings'=>'{
						"faragate_merchant":"'.$pre_faragate_merchant.'",
						"faragate_sandbox":"'.$pre_faragate_sandbox.'",
						"faragate_currency":"'.$pre_faragate_currency.'"
					}', 'folder_name' => $pluginDetails['folder_name']
				)
			);
            
            // delete old config value
            $db->query('DELETE FROM site_config WHERE config_key="faragate_payments_merchant" LIMIT 1');
            $db->query('DELETE FROM site_config WHERE config_key="faragate_payments_sandbox" LIMIT 1');
            $db->query('DELETE FROM site_config WHERE config_key="faragate_payments_currency" LIMIT 1');
        }

        return parent::install();
    }
	    
	public function uninstall()
    {
        // setup database
        $db = Database::getDatabase();    
        $db->query('DELETE FROM site_config WHERE config_key="faragate_payments_merchant" LIMIT 1');
        $db->query('DELETE FROM site_config WHERE config_key="faragate_payments_sandbox" LIMIT 1');
        $db->query('DELETE FROM site_config WHERE config_key="faragate_payments_currency" LIMIT 1');
		
		//this section deletes all faragate setting from database after uninstall ... you can remove it if you dont want this action....
		$pluginDetails = $this->getPluginDetails();
        $db->query('DELETE FROM plugin WHERE plugin_name="'.$pluginDetails["plugin_name"].'" LIMIT 1');
		//
		
		
        return parent::uninstall();
    }

}