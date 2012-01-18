<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
Copyright (C) 2005 - 2011 EllisLab, Inc.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
ELLISLAB, INC. BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Except as contained in this notice, the name of EllisLab, Inc. shall not be
used in advertising or otherwise to promote the sale, use or other dealings
in this Software without prior written authorization from EllisLab, Inc.
*/

$plugin_info = array(
						'pi_name'			=> 'Retrieve ExpressionEngine.com Blacklist',
						'pi_version'		=> '1.1',
						'pi_author'			=> 'Paul Burdick',
						'pi_author_url'		=> 'http://www.expressionengine.com/',
						'pi_description'	=> 'Cron based blacklist utility',
						'pi_usage'			=> Cron_blacklist::usage()
					);

/**
 * Cron_blacklist Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			ExpressionEngine Dev Team
 * @copyright		Copyright (c) 2005 - 2011, EllisLab, Inc.
 * @link			http://expressionengine.com/downloads/details/cron_retrieve_expressionenginecom_blacklist/
 */


class Cron_blacklist {


    var $return_data	= ''; 
   
    // ---------------------------------
    //  Retrieve Blacklist and Process
    // ---------------------------------

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */    
    function Cron_blacklist()
    {
   		$this->EE =& get_instance();

        if ( ! $this->EE->db->table_exists('blacklisted'))
        {
			return FALSE;
        }
        
        if ( ! $this->EE->db->table_exists('whitelisted'))
        {
			return FALSE;
        }   

        if ( ! $license = $this->EE->config->item('license_number'))
        {
        	return FALSE;
        }

		//  Get Current List from ExpressionEngine.com
		$this->EE->load->library('xmlrpc');
		$this->EE->xmlrpc->server('http://ping.expressionengine.com/index.php', 80);
		$this->EE->xmlrpc->method("ExpressionEngine.blacklist");
		$this->EE->xmlrpc->request(array($license));

		if ($this->EE->xmlrpc->send_request() === FALSE)
		{
			// show the error and stop
			//$message = $this->EE->lang->line("ref_blacklist_irretrievable").BR.$this->EE->xmlrpc->display_error();
			return FALSE;
		}

		// Array of our returned info
		$remote_info = $this->EE->xmlrpc->display_response();

		$new['url'] 	= ( ! isset($remote_info['urls']) OR count($remote_info['urls']) == 0) 	? array() : explode('|',$remote_info['urls']);
		$new['agent'] 	= ( ! isset($remote_info['agents']) OR count($remote_info['agents']) == 0) ? array() : explode('|',$remote_info['agents']);
		$new['ip'] 		= ( ! isset($remote_info['ips']) OR count($remote_info['ips']) == 0) 		? array() : explode('|',$remote_info['ips']);

		//  Add current list
		$query 			= $this->EE->db->get("blacklisted");
		$old['url']		= array();
		$old['agent']	= array();
		$old['ip']		= array();

		if ($query->num_rows() > 0)
		{
			foreach($query->result_array() as $row)
			{
				$old_values = explode('|',$row["blacklisted_value"]);
				for ($i=0; $i < count($old_values); $i++)
				{
					$old[$row["blacklisted_type"]][] = $old_values[$i];
				}
			}
		}

		//  Current listed
		$query 				= $this->EE->db->get('whitelisted');
		$white['url']		= array();
		$white['agent']		= array();
		$white['ip']		= array();

		if ($query->num_rows() > 0)
		{
			foreach($query->result_array() as $row)
			{
				$white_values = explode('|',$row['whitelisted_value']);
				for ($i=0; $i < count($white_values); $i++)
				{
					if (trim($white_values[$i]) != '')
					{
						$white[$row['whitelisted_type']][] = $white_values[$i];
					}
				}
			}
		}

		//  Check for uniqueness and sort
		$new['url'] 	= array_unique(array_merge($old['url'],$new['url']));
		$new['agent']	= array_unique(array_merge($old['agent'],$new['agent']));
		$new['ip']		= array_unique(array_merge($old['ip'],$new['ip']));
		sort($new['url']);
		sort($new['agent']);
		sort($new['ip']);

		//  Put blacklist info back into database
		$this->EE->db->truncate("blacklisted");

		foreach($new as $key => $value)
		{
			$listed_value = implode('|',$value);

			$data = array(
				"blacklisted_type" 	=> $key,
				"blacklisted_value"	=> $listed_value
			);

			$this->EE->db->insert("blacklisted", $data);
		}

		//  Using new blacklist members, clean out spam
		$new['url']		= array_diff($new['url'], $old['url']);
		$new['agent']	= array_diff($new['agent'], $old['agent']);
		$new['ip']		= array_diff($new['ip'], $old['ip']);

		$modified_channels = array();

		foreach($new as $key => $value)
		{
			sort($value);
			$name = ($key == 'url') ? 'from' : $key;

			if (count($value) > 0)
			{
				for($i=0; $i < count($value); $i++)
				{
					if ($value[$i] != '')
					{
						if ($this->EE->db->table_exists('referrers'))
						{
							$this->EE->db->like('ref_'.$name, $value[$i]);

							foreach ($white[$key] as $w_value)
							{
								$this->EE->db->not_like('ref_'.$name, $w_value);
							}

							$this->EE->db->delete('referrerss');
						}

						if ($key == 'url' OR $key == 'ip' AND $this->EE->table_exists('trackbacks'))
						{
							$this->EE->db->select('entry_id, channel_id');
							$this->EE->db->like('trackback_'.$key, $value[$i]);

							foreach ($white[$key] as $w_value)
							{
								$this->EE->db->not_like('trackback_'.$key, $w_value);
							}

							$query = $this->EE->db->get('trackbacks');

							if ($query->num_rows() > 0)
							{
								$this->EE->db->like('trackback_'.$key, $value[$i]);

								foreach ($white[$key] as $w_value)
								{
									$this->EE->db->not_like('trackback_'.$key, $w_value);
								}

								$this->EE->db->delete('trackbacks');

								foreach($query->result_array() as $row)
								{
									$modified_channels[] = $row['channel_id'];

									$this->EE->db->where('entry_id', $row['entry_id']);
									$results = $this->EE->db->count_all_results('trackbacks');

									$this->EE->db->where('entry_id', $row['entry_id']);
									$this->EE->db->select_max('trackback_date', 'max_date');
									$results_2 = $this->EE->db->get('trackbacks');
									$max_date = $results_2->row('max_date');

									$date = ($results_2->num_rows() == 0 OR ! is_numeric($max_date)) ? 0 : $max_date;

									$this->EE->db->where('entry_id', $entry_id);

									$data = array(
										'recent_trackback_date' => $date,
										'trackback_total' => $results
									);

									$this->EE->db->update('channel_titles', $data);
								}
							}
						}
					}
				}
			}
		}

		if (isset($modified_channels) && count($modified_channels) > 0)
		{
			$modified_channels = array_unique($modified_channels);

			foreach($modified_channels as $channel_id)
			{
				$this->EE->stats->update_trackback_stats($channel_id);
			}
		}

        
        return TRUE;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Usage
	 *
	 * Plugin Usage
	 *
	 * @access	public
	 * @return	string
	 */
	function usage()
	{

		ob_start(); 
		?>

		This plugin downloads the ExpressionEngine.com Blacklist and appends it to the site's
		current Blacklist.  You must have the Blacklist module installed *and* have your
		license number filled out in the Admin section of the ExpressionEngine Control 
		Panel under General Configuration for this to work. Basically, think of it as 
		automating the link provided in the Blacklist module for this purpose.

		If you post a question in the forum wondering why this plugin does not work
		and you do not have your license number filled out, I will scold you mightily.

		Find your license number:

		http://expressionengine.com/knowledge_base/article/my_expressionengine_license_number/


		============================
		 EXAMPLES
		============================

		Updates site blacklist with ExpressionEngine.com Blacklist at 5am every morning

		{exp:cron minute="0" hour="5" plugin="cron_blacklist"}{/exp:cron}

		Version 1.1
		******************
		- Updated plugin to be 2.0 compatible

		<?php
		
		$buffer = ob_get_contents();
	
		ob_end_clean(); 

		return $buffer;
	}

	// --------------------------------------------------------------------
	
}
// END CLASS

/* End of file pi.cron_blacklist.php */
/* Location: ./system/expressionengine/third_party/cron_blacklist/pi.cron_blacklist.php */