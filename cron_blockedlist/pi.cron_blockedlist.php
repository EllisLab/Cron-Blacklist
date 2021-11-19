<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
Copyright (C) 2005 - 2021 Packet Tide, LLC

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
PACKET TIDE, LLC BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Except as contained in this notice, the name of Packet Tide, LLC shall not be
used in advertising or otherwise to promote the sale, use or other dealings
in this Software without prior written authorization from Packet Tide, LLC.
*/


/**
 * Cron_blockedlist Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			Packet Tide
 * @copyright		Copyright (C) 2005 - 2021 Packet Tide, LLC
 * @link			https://github.com/EllisLab/Cron-Blacklist
 */


class Cron_blockedlist {


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
    function __construct()
    {
		if ( APP_VER < 6 )
		{
			$blockedlistname  = "blacklist";
			$blockedtablename = "blacklisted";
			$allowedtablename = "whitelisted";
		}
		else
		{
			$blockedlistname  = "blockedlist";
			$blockedtablename = "blockedlist";
			$allowedtablename = "allowedlist";
		}
   

        if ( ! ee()->db->table_exists($blockedtablename))
        {
			return FALSE;
        }

        if ( ! ee()->db->table_exists($allowedtablename))
        {
			return FALSE;
        }

		//  Get Current List from ExpressionEngine.com
        ee()->load->library('xmlrpc');
        //ee()->xmlrpc->debug = true;
        ee()->xmlrpc->server('http://ping.expressionengine.com/index.php', 80);
        $method = $blockedlistname;
        ee()->xmlrpc->method("ExpressionEngine." . $method);

        if (ee()->xmlrpc->send_request() === false) {
            ee()->logger->developer("Cron Blocked List: ref_{$blockedlistname}_irretrievable. ".ee()->xmlrpc->display_error());
			return FALSE;
        }

        // Array of our returned info
        $remote_info = ee()->xmlrpc->display_response();

        $new['url'] = (! isset($remote_info['urls']) || strlen($remote_info['urls']) == 0) ? array() : explode('|', $remote_info['urls']);
        $new['agent'] = (! isset($remote_info['agents']) || strlen($remote_info['agents']) == 0) ? array() : explode('|', $remote_info['agents']);
        $new['ip'] = (! isset($remote_info['ips']) || strlen($remote_info['ips']) == 0) ? array() : explode('|', $remote_info['ips']);

        //  Add current list
        $query = ee()->db->get($blockedtablename);
        $old['url'] = array();
        $old['agent'] = array();
        $old['ip'] = array();

        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $old_values = explode('|', $row["{$blockedtablename}list_value"]);
                for ($i = 0; $i < count($old_values); $i++) {
                    $old[$row["{$blockedtablename}list_type"]][] = $old_values[$i];
                }
            }
        }

        //  Current listed
        $query = ee()->db->get("{$allowedtablename}");
        $white['url'] = array();
        $white['agent'] = array();
        $white['ip'] = array();

        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                $white_values = explode('|', $row["{$allowedtablename}_value"]);
                for ($i = 0; $i < count($white_values); $i++) {
                    if (trim($white_values[$i]) != '') {
                        $white[$row["{$allowedtablename}_type"]][] = $white_values[$i];
                    }
                }
            }
        }

        //  Check for uniqueness and sort
        $new['url'] = array_unique(array_merge($old['url'], $new['url']));
        $new['agent'] = array_unique(array_merge($old['agent'], $new['agent']));
        $new['ip'] = array_unique(array_merge($old['ip'], $new['ip']));
        sort($new['url']);
        sort($new['agent']);
        sort($new['ip']);

        //  Put list info back into database
        ee()->db->truncate($blockedtablename);

        foreach ($new as $key => $value) {
            $listed_value = implode('|', $value);

            $data = array(
                "{$blockedtablename}_type" => $key,
                "{$blockedtablename}_value" => $listed_value
            );

            ee()->db->insert("{$blockedtablename}", $data);
        }


        return TRUE;
	}

	// --------------------------------------------------------------------

}
// END CLASS

/* End of file pi.cron_blockedlist.php */
/* Location: ./system/user/addons/cron_blockedlist/pi.cron_blockedlist.php */