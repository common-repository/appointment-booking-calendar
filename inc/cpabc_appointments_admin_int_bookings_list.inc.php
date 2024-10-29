<?php

if ( !is_admin() )
{
    echo 'Direct access not allowed.';
    exit;
}

if (!defined('CP_CALENDAR_ID'))
    define ('CP_CALENDAR_ID', 1);

global $wpdb;

$current_user = wp_get_current_user();

$format = cpabc_appointments_getDateFormat();

$message = "";

$records_per_page = 50;                                                                                  

function cpabc_bklist_verify_nonce() {
    if (isset($_GET['rsave']) && $_GET['rsave'] != '')
        $nonce = sanitize_text_field($_GET['rsave']);
    else
        $nonce = sanitize_text_field($_POST['rsave']);
    $verify_nonce = wp_verify_nonce( $nonce, 'uname_abc_bklist');
    if (!$verify_nonce)
    {
        echo 'Error: Form cannot be authenticated (nonce failed). Please contact our <a href="https://abc.dwbooster.com/contact-us">support service</a> for verification and solution. Thank you.';
        exit;
    } 
}

if (isset($_GET['delmark']) && $_GET['delmark'] != '')
{
    cpabc_bklist_verify_nonce();
    for ($i=0; $i<=$records_per_page; $i++)
    if (isset($_GET['c'.$i]) && $_GET['c'.$i] != '')   
        $wpdb->query( $wpdb->prepare('DELETE FROM `'.CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME.'` WHERE id=%d', $_GET['c'.$i])  );       
    $message = "Marked items deleted";
}
else if (isset($_GET['ld']) && $_GET['ld'] != '')
{
    cpabc_bklist_verify_nonce();
    $wpdb->query( $wpdb->prepare('DELETE FROM `'.CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME.'` WHERE id=%d', $_GET['ld']) );       
    $message = "Item deleted";
} 
else if (isset($_GET['del']) && $_GET['del'] == 'all')
{        
    cpabc_bklist_verify_nonce();
    $wpdb->query( $wpdb->prepare( 'DELETE FROM `'.CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME.'` WHERE appointment_calendar_id=%d', CP_CALENDAR_ID ) );           
    $message = "All items deleted";
}
else if (isset($_GET['paid']) && intval($_GET['paid']))
{
	cpabc_bklist_verify_nonce();
    $item = $wpdb->get_row("SELECT * FROM ".CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME." WHERE id=".intval($_GET['paid']));
    $itemdetails = $wpdb->get_row("SELECT * FROM ".CPABC_APPOINTMENTS_TABLE_NAME." WHERE id=".intval($item->reference));    
    $data = unserialize($itemdetails->buffered_date);
    if (intval($_GET['paidac']))
    {
        $data["payment_type"] = 'Manually updated';
    }
    else
    {
        unset($data["payment_type"]);
        unset($data["txnid"]);
    }
    //print_r($data);
    do_action( 'cpabc_update_paid_status', intval($item->reference), (intval($_GET['paidac'])?true:false) );
    $wpdb->query("UPDATE ".CPABC_APPOINTMENTS_TABLE_NAME." set buffered_date='".esc_sql(serialize($data))."' WHERE id=".intval($item->reference));   
    $message = "Paid status updated.";
}
else if (isset($_GET['cancel']) && $_GET['cancel'] != '')
{    
    cpabc_bklist_verify_nonce();
    $cancelc = intval($_GET['cancel']);
    $wpdb->query("UPDATE `".CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME."` SET who_cancelled='".esc_sql($current_user->ID)."',is_cancelled='1',cancelled_reason='".esc_sql($_GET["reason"])."' WHERE id=".intval($cancelc));
    $message = "Item cancelled";
    // send email to customer         
    $item = $wpdb->get_row("SELECT * FROM ".CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME." WHERE id=".intval($cancelc)); 
    
    $app_source = $wpdb->get_row("SELECT * FROM ".CPABC_APPOINTMENTS_TABLE_NAME." WHERE id=".intval($item->reference)); 
    $params = unserialize($app_source->buffered_date);
    if ('html' == cpabc_get_option('cncustomer_emailformat')) $content_type = "Content-Type: text/html; charset=utf-8\n"; else $content_type = "Content-Type: text/plain; charset=utf-8\n";
    $email_content = str_replace('%INFORMATION%',str_replace('<br />',"\n",$item->description_customer), cpabc_get_option('cemail_notification_to_customer') );   
    $params['ITEMNUMBER'] = $cancelc;
    foreach ($params as $itemr => $value)
    {
        $email_content = str_replace('<%'.$itemr.'%>',(is_array($value)?(implode(", ",$value)):($value)),$email_content);
        $email_content = str_replace('%'.$itemr.'%',(is_array($value)?(implode(", ",$value)):($value)),$email_content);
    }
    $email_content = str_replace("%CALENDAR%", cpabc_get_option('uname'), $email_content);
    $email_content = str_replace("%cancelreason%", sanitize_text_field($_GET["reason"]), $email_content);
    
    //wp_mail($item->title, cpabc_get_option('cemail_subject_notification_to_customer'), $email_content,
    //        "From: \"".cpabc_get_option('notification_from_email')."\" <".cpabc_get_option('notification_from_email').">\r\n".
    //        $content_type.
    //        "X-Mailer: PHP/" . phpversion());    
}
else if (isset($_GET['nocancel']) && $_GET['nocancel'] != '')
{    
    cpabc_bklist_verify_nonce();
    $wpdb->query("UPDATE `".CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME."` SET who_edited='".esc_sql($current_user->ID)."',is_cancelled='0' WHERE id=".intval($_GET['nocancel']));           
    $message = "Item un-cancelled";
}
else if (isset($_GET['resend']) && $_GET['resend'] != '')
{    
    cpabc_bklist_verify_nonce();
    $events = $wpdb->get_results( "SELECT * FROM ".CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME." WHERE id=".intval($_GET['resend']) );
    $events = $wpdb->get_results( "SELECT * FROM ".CPABC_APPOINTMENTS_TABLE_NAME." WHERE id=".intval($events[0]->reference) );    
    define('CPABC_IS_RESEND', true);
    cpabc_process_ready_to_go_appointment($events[0]->id,'',true);
    $message = "Item re-sent";
}



$mycalendarrows = $wpdb->get_results( 'SELECT * FROM '.CPABC_APPOINTMENTS_CONFIG_TABLE_NAME .' WHERE `'.CPABC_TDEAPP_CONFIG_ID.'`='.CP_CALENDAR_ID);

if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset( $_POST['cpabc_appointments_post_options'] ) )
    echo "<div id='setting-error-settings_updated' class='updated settings-error'> <p><strong>Settings saved.</strong></p></div>";

$current_user = wp_get_current_user();

if (cpabc_appointment_is_administrator() || $mycalendarrows[0]->conwer == $current_user->ID) {

$current_page = intval(cpabc_get_get_param("p"));
if (!$current_page) $current_page = 1;

$cond = '';
if (cpabc_get_get_param("search") != '') 
{
    $search_text = sanitize_text_field($_GET["search"]);
    $cond .= " AND (title like '%".esc_sql($search_text)."%' OR description LIKE '%".esc_sql($search_text)."%')";
}
if (cpabc_get_get_param("dfrom") != '') $cond .= " AND (datatime >= '".esc_sql(sanitize_text_field($_GET["dfrom"]))."')";
if (cpabc_get_get_param("dto") != '') $cond .= " AND (datatime <= '".esc_sql(sanitize_text_field($_GET["dto"]))." 23:59:59')";


if (!empty($_GET["added_by"]) && $_GET["added_by"] != '') $cond .= " AND (who_added >= '".esc_sql(sanitize_text_field($_GET["added_by"]))."')";
if (!empty($_GET["edited_by"]) && $_GET["edited_by"] != '') $cond .= " AND (who_edited >= '".esc_sql(sanitize_text_field($_GET["edited_by"]))."')";
if (!empty($_GET["cancelled_by"]) && $_GET["cancelled_by"] != '') $cond .= " AND (is_cancelled='1' AND who_cancelled >= '".esc_sql(sanitize_text_field($_GET["cancelled_by"]))."')";
if (!empty($_GET["cstatus"]) && $_GET["cstatus"] == 'cancelled') $cond .= " AND (is_cancelled='1')";
if (!empty($_GET["cstatus"]) && $_GET["cstatus"] == 'approved') $cond .= " AND (is_cancelled<>'1')";


$orderby = empty($_GET["orderby"]) ? 'datatime DESC' : sanitize_text_field($_GET["orderby"]);
if ($orderby != 'id DESC' && $orderby != 'id ASC' && $orderby != 'datatime DESC' && $orderby != 'datatime ASC')
	$orderby = 'datatime DESC';

$events = $wpdb->get_results( "SELECT ".CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME.".*,".CPABC_APPOINTMENTS_TABLE_NAME.".buffered_date,".CPABC_APPOINTMENTS_TABLE_NAME.".time FROM ".CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME." LEFT JOIN  ".CPABC_APPOINTMENTS_TABLE_NAME." ON  ".CPABC_APPOINTMENTS_TABLE_NAME.".id=".CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME.".reference WHERE appointment_calendar_id=".intval(CP_CALENDAR_ID).$cond." ORDER BY ".$orderby." " );    
$total_pages = ceil(count($events) / $records_per_page);

$users_arr = array();
$users_arr['id-1'] = 'CUSTOMER';
$users = $wpdb->get_results( "SELECT user_login,ID FROM ".$wpdb->users." ORDER BY ID DESC" );                                                                     
foreach ($users as $user)
    $users_arr["id".$user->ID] = $user;
	
if ($message) echo "<div id='setting-error-settings_updated' class='updated settings-error'><p><strong>".esc_html($message)."</strong></p></div>";

$nonce_un = wp_create_nonce( 'uname_abc_bklist' );

?>
<script type="text/javascript">
 function cp_deleteMessageItem(id)
 {
    if (confirm('Are you sure that you want to delete this item?'))
    {        
        document.location = 'admin.php?page=cpabc_appointments.php&rsave=<?php echo esc_attr($nonce_un); ?>&cal=<?php echo intval($_GET["cal"]); ?>&list=1&ld='+id+'&r='+Math.random();
    }
 }
 function do_dexapp_deleteall()
 {
    if (confirm('Are you sure that you want to delete ALL bookings for this calendar? Note: This action cannot be undone.'))
    {        
        document.location = 'admin.php?page=cpabc_appointments.php&rsave=<?php echo esc_attr($nonce_un); ?>&cal=<?php echo intval($_GET["cal"]); ?>&list=1&del=all&r='+Math.random();
    }    
 }
 function cp_paidMessageItem(id,ac)
 {
    if (confirm('Are you sure that you want to change this paid status?'))
    {        
        document.location = 'admin.php?page=cpabc_appointments.php&rsave=<?php echo esc_attr($nonce_un); ?>&cal=<?php echo intval($_GET["cal"]); ?>&list=1&paid='+id+'&paidac='+ac+'&r='+Math.random();
    }
 }
 function cp_editItem(id, cal)
 {
     document.location = 'admin.php?page=cpabc_appointments.php&rsave=<?php echo esc_attr($nonce_un); ?>&cal='+cal+'&edit='+id+'&r='+Math.random();
 }
 function cp_uncancelItem(id)
 {
    if (confirm('Are you sure that you want to un-cancel this item?'))
    {        
        document.location = 'admin.php?page=cpabc_appointments.php&rsave=<?php echo esc_attr($nonce_un); ?>&cal=<?php echo intval($_GET["cal"]); ?>&list=1&nocancel='+id+'&r='+Math.random();
    }
 }
 function cp_resendItem(id)
 {
    if (confirm('Are you sure that you want to resend this email?'))
    {        
        document.location = 'admin.php?page=cpabc_appointments.php&rsave=<?php echo esc_attr($nonce_un); ?>&cal=<?php echo intval($_GET["cal"]); ?>&list=1&resend='+id+'&r='+Math.random();
    }
 }
 function cp_cancelItem(id)
 {
        var reason;
        if (reason = prompt('Please enter cancellation reason:'))
        {
                document.location = 'admin.php?page=cpabc_appointments.php&rsave=<?php echo esc_attr($nonce_un); ?>&cal=<?php echo intval($_GET["cal"]); ?>&list=1&cancel='+id+'&reason='+reason;
        }
 } 
</script>
<div class="wrap">
<h1>Appointment Booking Calendar - Bookings List</h1>

<input type="button" name="backbtn" value="Back to items list..." onclick="document.location='admin.php?page=cpabc_appointments.php';">


<div id="normal-sortables" class="meta-box-sortables">
 <hr />
 <h3>This booking list applies only to: <?php echo esc_html($mycalendarrows[0]->uname); ?></h3>
</div>


<form action="admin.php" method="get">
 <input type="hidden" name="rsave" value="<?php echo esc_attr($nonce_un); ?>" />
 <input type="hidden" name="page" value="cpabc_appointments.php" />
 <input type="hidden" name="cal" value="<?php echo intval(CP_CALENDAR_ID); ?>" />
 <input type="hidden" name="list" value="1" />
<table>
  <tr>
   <td align="right">Search for:</td>
   <td><input type="text" name="search" value="<?php echo esc_attr(cpabc_get_get_param("search")); ?>" /></td>
   <td align="right">From:</td>
   <td><input autocomplete="off" type="text" id="dfrom" name="dfrom" value="<?php echo esc_attr(cpabc_get_get_param("dfrom")); ?>" /></td> 
   <td align="right">To:</td>
   <td><input autocomplete="off" type="text" id="dto" name="dto" value="<?php echo esc_attr(cpabc_get_get_param("dto")); ?>" /></td>
  </tr> 
<?php if (cpabc_appointment_is_administrator()) { ?>     
  <tr>
   <td align="right">Added by:</td>
   <td><select name="added_by"><option value="">--- all users ---</option><?php foreach ($users as $user) echo '<option value="'.$user->ID.'"'.($user->ID==cpabc_get_get_param("added_by")?' selected':'').'>'.$user->user_login.'</option>'; ?></select></td>
   <td align="right">Edited by:</td>
   <td><select name="edited_by"><option value="">--- all users ---</option><?php foreach ($users as $user) echo '<option value="'.$user->ID.'"'.($user->ID==cpabc_get_get_param("edited_by")?' selected':'').'>'.$user->user_login.'</option>'; ?></select></td>
   <td align="right">Cancelled by:</td>
   <td><select name="cancelled_by"><option value="">--- all users ---</option><?php foreach ($users as $user) echo '<option value="'.$user->ID.'"'.($user->ID==cpabc_get_get_param("cancelled_by")?' selected':'').'>'.$user->user_login.'</option>'; ?></select></td>
  </tr>
  <tr>
   <td align="right">Status:</td>  
   <td><select name="cstatus">
        <option value="">--- all ---</option>
        <option value="approved" <?php if (!empty($_GET["cstatus"]) && $_GET["cstatus"] == 'approved') echo ' selected '; ?>>active</option>
        <option value="cancelled" <?php if (!empty($_GET["cstatus"]) && $_GET["cstatus"] == 'cancelled') echo ' selected '; ?>>cancelled</option>
       </select></td>    
<?php } ?>
   <td align="right" nowrap>Order By</td>
   <td colspan="2">     
     <select name="orderby">
      <option value="id DESC" <?php if ($orderby == 'id DESC') echo ' selected'; ?>>Submission time - desc</option>
      <option value="id ASC" <?php if ($orderby == 'id ASC') echo ' selected'; ?>>Submission time - asc</option>
      <option value="datatime DESC" <?php if ($orderby == 'datatime DESC') echo ' selected'; ?>>Appointment time - desc</option>
      <option value="datatime ASC" <?php if ($orderby == 'datatime ASC') echo ' selected'; ?>>Appointment time - asc</option>
     </select>   
</td> 
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td colspan="5">  
     <span class="submit"><input type="submit" name="ds" value="Filter" /></span> &nbsp; &nbsp; &nbsp; 
     <nobr>
     <span class="submit"><input type="submit" name="cpabc_appointments_csv" value="Export to CSV" /></span>&nbsp; &nbsp; &nbsp; 
     </nobr>
   </td>
  </tr>
 </table>  
  
</form>

<br />
                             
<?php


echo paginate_links(  array(
    'base'         => 'admin.php?page=cpabc_appointments.php&cal='.CP_CALENDAR_ID.'&list=1%_%&dfrom='.urlencode(sanitize_text_field(cpabc_get_get_param("dfrom"))).'&dto='.urlencode(sanitize_text_field(cpabc_get_get_param("dto"))).'&search='.urlencode(sanitize_text_field(cpabc_get_get_param("search"))),
    'format'       => '&p=%#%',
    'total'        => $total_pages,
    'current'      => $current_page,
    'show_all'     => False,
    'end_size'     => 1,
    'mid_size'     => 2,
    'prev_next'    => True,
    'prev_text'    => '&laquo; '.__('Previous','appointment-booking-calendar'),
    'next_text'    => __('Next','appointment-booking-calendar').' &raquo;',
    'type'         => 'plain',
    'add_args'     => False
    ) );

?>

<div id="cpabc_printable_contents">
<form name="dex_table_form" id="dex_table_form" action="admin.php" method="get">
 <input type="hidden" name="page" value="cpabc_appointments.php" />
 <input type="hidden" name="cal" value="<?php echo intval($_GET["cal"]); ?>" />
 <input type="hidden" name="list" value="1" />
 <input type="hidden" name="rsave" value="<?php echo esc_attr($nonce_un); ?>" />
 <input type="hidden" name="delmark" value="1" />
<table class="wp-list-table widefat fixed pages" cellspacing="0" width="100%"> 
	<thead>
	<tr>
	  <th width="30"  class="cpnopr"></th>
	  <th style="padding-left:7px;font-weight:bold;">Date</th>
	  <th style="padding-left:7px;font-weight:bold;">Title</th>
	  <th style="padding-left:7px;font-weight:bold;">Description</th>
	  <th style="padding-left:7px;font-weight:bold;">Quantity</th>
	  <th class="cpnopr" style="padding-left:7px;font-weight:bold;">Options</th>
	</tr>
	</thead>
	<tbody id="the-list">
	 <?php for ($i=($current_page-1)*$records_per_page; $i<$current_page*$records_per_page; $i++) if (isset($events[$i])) { ?>
	  <tr class='<?php if (!($i%2)) { ?>alternate <?php } ?>author-self status-draft format-default iedit' valign="top">
	    <td width="1%"  class="cpnopr"><input type="checkbox" name="c<?php echo intval($i-($current_page-1)*$records_per_page); ?>" value="<?php echo intval($events[$i]->id); ?>" /></td>
		<td><?php echo esc_html(substr($events[$i]->datatime,0,16)); ?></td>
		<td><?php echo esc_html($events[$i]->title); ?></td>
		<td><?php echo str_replace('--br />','<br />',str_replace('<','&lt;',str_replace('<br />','--br />',$events[$i]->description))); ?></td>
		<td><?php echo intval($events[$i]->quantity); ?></td>
        <td <?php if ($events[$i]->is_cancelled == '1') { ?>style="color:#faabbb;"<?php } ?> class="cpnopr">
		  <input type="button" name="caledit_<?php echo $events[$i]->id; ?>" value="Edit" onclick="cp_editItem(<?php echo intval($events[$i]->id); ?>,<?php echo intval($events[$i]->appointment_calendar_id); ?>);" />
		  <?php if ($events[$i]->is_cancelled == '1') { ?>
		  <input type="button" name="calcancel_<?php echo intval($events[$i]->id); ?>" value="Un-Cancel" onclick="cp_uncancelItem(<?php echo intval($events[$i]->id); ?>);" />
		  <?php } else { ?>
		  <input type="button" name="calcancel_<?php echo intval($events[$i]->id); ?>" value="Cancel" onclick="cp_cancelItem(<?php echo intval($events[$i]->id); ?>);" />
		  <?php } ?>
		  <input type="button" name="resend_<?php echo intval($events[$i]->id); ?>" value="Resend Email" onclick="cp_resendItem(<?php echo intval($events[$i]->id); ?>);" />          
		  <?php if (cpabc_appointment_is_administrator()) { ?><input type="button" name="caldelete_<?php echo intval($events[$i]->id); ?>" value="Delete" onclick="cp_deleteMessageItem(<?php echo intval($events[$i]->id); ?>);" /><?php } ?>
		  <span style="font-style:italic;font-size:11px;">
		  <?php
		    if (isset($users_arr["id".$events[$i]->who_added]))
		        echo '<br />Added by: <strong>'. esc_html($users_arr["id".$events[$i]->who_added]->user_login).'</strong>';
		    if (isset($users_arr["id".$events[$i]->who_edited]))
		        echo '<br />Edited by: <strong>'.esc_html($users_arr["id".$events[$i]->who_edited]->user_login).'</strong>';
		    if (isset($users_arr["id".$events[$i]->who_cancelled]) && $events[$i]->is_cancelled == '1')
		        if ($events[$i]->who_cancelled == '-1')
		            echo '<br />Cancelled by: <strong><b>'.esc_html($users_arr["id".$events[$i]->who_cancelled]).'</b></strong>';
		        else
		            echo '<br />Cancelled by: <strong>'.esc_html($users_arr["id".$events[$i]->who_cancelled]->user_login).'</strong>';    
		    
		    if ($events[$i]->cancelled_reason != '' && $events[$i]->is_cancelled == '1')
		        echo '<br />Cancelled reason: <strong>'.esc_html($events[$i]->cancelled_reason).'</strong>';
        
             $params = unserialize($events[$i]->buffered_date);      
             
             if (is_array($params) && (array_key_exists("txnid",$params) || (isset($params["payment_type"]) && ($params["payment_type"] != 'PayPal' || isset($params["txnid"])))))
             {
                 echo '<hr /><span class="abcpaid">'.__('Paid.','cpabc');
                 if (isset($params["payment_type"]) && ($params["payment_type"] != 'PayPal' || isset($params["txnid"]))) echo esc_html($params["payment_type"]).".";
                 if (isset($params["txnid"])) echo __('Payment ID','cpabc').": ".esc_html($params["txn_id"]?$params["txn_id"]:' --- ');
                 echo '</span>';
                 ?><br /><input type="button" name="calpaide_<?php echo intval($events[$i]->id); ?>" value="Mark as unpaid" onclick="cp_paidMessageItem(<?php echo intval($events[$i]->id); ?>,0);" /><?php
             }
             else   
             {
                 echo '<hr /><span class="abcunpaid">'.__('Payment: not confirmed so far','cpabc').'</span>';
                 ?><br /><input type="button" name="calpaide_<?php echo intval($events[$i]->id); ?>" value="Mark as paid" onclick="cp_paidMessageItem(<?php echo intval($events[$i]->id); ?>,1);" /><?php
             }
              echo '<hr /><span class="abcsubdate">'.__('Submission date:','cpabc').'</span><br /><strong>'. esc_html(date($format,strtotime($events[$i]->time))).'</strong>';
		  ?>
		  </SPAN>
		</td>		
      </tr>
     <?php } ?>
	</tbody>
</table>
</form>
</div>

<br /><input type="button" name="pbutton" value="Print" onclick="do_dexapp_print();" />
<div style="clear:both"></div>
<p class="submit" style="float:left;"><input type="button" name="pbutton" value="Delete marked items" onclick="do_dexapp_deletemarked();" /> &nbsp; &nbsp; &nbsp; </p>

<p class="submit" style="float:left;"><input type="button" name="pbutton" value="Delete All Bookings" onclick="do_dexapp_deleteall();" /></p>


</div>


<script type="text/javascript">
 function do_dexapp_print()
 {
      w=window.open();
      w.document.write("<style>.cpnopr{display:none;};table{border:2px solid black;width:100%;}th{border-bottom:2px solid black;text-align:left}td{padding-left:10px;border-bottom:1px solid black;}</style>"+document.getElementById('cpabc_printable_contents').innerHTML);
      w.print();     
 }
 function do_dexapp_deletemarked()
 {
    document.dex_table_form.submit();
 }  
 var $j = jQuery.noConflict();
 $j(function() {
 	$j("#dfrom").datepicker({     	                
                    dateFormat: 'yy-mm-dd'
                 });
 	$j("#dto").datepicker({     	                
                    dateFormat: 'yy-mm-dd'
                 });
 });
 
</script>
<style>
.abcpaid { font-weight: bold; color: green; font-size: 110%; }
.abcunpaid {  }
</style>



<?php } else { ?>
  <br />
  The current user logged in doesn't have enough permissions to edit this calendar. This user can edit only his/her own calendars. Please log in as administrator to get access to all calendars.

<?php } ?>











