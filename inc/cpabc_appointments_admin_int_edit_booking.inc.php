<?php

if ( !is_admin() )
{
    echo 'Direct access not allowed.';
    exit;
}

if (!defined('CP_CALENDAR_ID'))
    define ('CP_CALENDAR_ID',intval($_GET["cal"]));

global $wpdb;

$current_user = wp_get_current_user();

function cpabcedit_verify_nonce() {
    if (isset($_GET['rsaveedit']) && $_GET['rsaveedit'] != '')
        $nonce = sanitize_text_field($_GET['rsaveedit']);
    else
        $nonce = sanitize_text_field($_POST['rsaveedit']);
    $verify_nonce = wp_verify_nonce( $nonce, 'uname_abc_editlist');
    if (!$verify_nonce)
    {
        echo 'Error: Form cannot be authenticated (nonce failed). Please contact our <a href="https://abc.dwbooster.com/contact-us">support service</a> for verification and solution. Thank you.';
        exit;
    } 
}

if (cpabc_appointment_is_administrator() || $mycalendarrows[0]->conwer == $current_user->ID) {
    
    $event = $wpdb->get_results( "SELECT * FROM ".CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME." WHERE id=".esc_sql($_GET["edit"]) );
    $event = $event[0];
       
    if ($event->reference != '')
    {
        $form_data = json_decode(cpabc_appointment_cleanJSON(cpabc_get_option('form_structure', CPABC_APPOINTMENTS_DEFAULT_form_structure))); 
        
        $org_booking = $wpdb->get_results( "SELECT buffered_date FROM ".CPABC_APPOINTMENTS_TABLE_NAME." WHERE id=".intval($event->reference) );
        $params = unserialize($org_booking[0]->buffered_date);
        unset($params["QUANTITY"]);
        unset($params["DATE"]);
        unset($params["TIME"]);       
    }
    else
        $params["description"] = $event->description;
        
    if (count($_POST) > 0) 
    {
	   cpabcedit_verify_nonce();
       $datatime = sanitize_text_field($_POST["datatime"])." ".sanitize_text_field($_POST["datatime_hour"]).":".sanitize_text_field($_POST["datatime_minutes"]).":00";
       if (cpabc_get_option('calendar_militarytime', CPABC_APPOINTMENTS_DEFAULT_CALENDAR_MILITARYTIME) == '0') $format = "g:i A"; else $format = "H:i";
       $dfoption = cpabc_get_option('calendar_dateformat', CPABC_APPOINTMENTS_DEFAULT_CALENDAR_DATEFORMAT);
       if ($dfoption == '0') 
          $format = "m/d/Y ".$format; 
       else if ($dfoption == '2')
          $format = "d.m.Y ".$format;
       else
          $format = "d/m/Y ".$format;
  
       
       // save quantity
       // save title
       // save buffered_date en original table
       // save description in destination table
       // track who editied the item
       
       $military_time = cpabc_get_option('calendar_militarytime', CPABC_APPOINTMENTS_DEFAULT_CALENDAR_MILITARYTIME);
       if (cpabc_get_option('calendar_militarytime', CPABC_APPOINTMENTS_DEFAULT_CALENDAR_MILITARYTIME) == '0') $format_t = "g:i A"; else $format_t = "H:i";
       if (cpabc_get_option('calendar_dateformat', CPABC_APPOINTMENTS_DEFAULT_CALENDAR_DATEFORMAT) == '0') $format_d = "m/d/Y "; else $format_d = "d/m/Y ";
    
       $params_new = $params ;
       $params_new['DATE'] = date($format_d,strtotime( sanitize_text_field($_POST["datatime"]) ));
       $params_new['TIME'] = date($format_t,strtotime( sanitize_text_field($_POST["datatime_hour"]) .":". sanitize_text_field($_POST["datatime_minutes"]) ));
       $params_new['QUANTITY'] = intval($_POST['quantity']);
       
       foreach ($params as $item => $value)
           if (isset($_POST[$item]))
               $params_new[$item] = sanitize_text_field($_POST[$item]);
          
       $description = cpabc_get_option('uname','').'<br />'.date($format, strtotime($datatime)).'<br />';
       $description_customer = cpabc_get_option('uname','').'<br />'.date($format, strtotime($datatime)).'<br />';
       foreach ($params_new as $item => $value)
           if ($value != '' && $item != 'DATE' && $item != 'TIME' && $item != 'QUANTITY' && $item != 'UTIMEZONE'
    		   && $item != 'PRICE' && $item != 'request_timestamp' && $item != 'MAINDATE')
           {
               $name = cpabc_appointments_get_field_name($item,$form_data[0]); 
               //if ($name == 'ADULTS')
               //    $name = cpabc_get_option('quantity_field_label',CPABC_APPOINTMENTS_ENABLE_QUANTITY_FIELD_LABEL);
               //else if ($name == 'JUNIORS')
               //    $name = cpabc_get_option('quantity_field_two_label',CPABC_APPOINTMENTS_ENABLE_QUANTITY_FIELD_TWO_LABEL);         
               $description .= $name.': '.$value.'<br />';
               
               if ($name != 'Juniors' && $name != 'IP' && $name != 'PRICE' && $name != 'DATE_CUSTOMER' && $name != 'TIME_CUSTOMER' )
                   $description_customer .= $name.': '.$value.'<br />';
           }
       
       if ($event->reference == '')  $description = $_POST["description"];
       
       $data1 = array(
                        'datatime' => $datatime,
                        'quantity' => intval($_POST['quantity']),
                        'title' => sanitize_text_field($_POST["title"]),
                        'description' => $description,
                        'description_customer' => $description_customer,
                        'who_edited' => $current_user->ID
                     );
       
       $data2 = array(
                        'booked_time_unformatted' => $datatime,
                        'booked_time' => date($format, strtotime($datatime)),
                        'booked_time_customer' => date($format, strtotime($datatime)),
                        'quantity' => intval($_POST['quantity']),
                        'buffered_date' => serialize($params_new)
                     );
       
       
       $wpdb->update ( CPABC_APPOINTMENTS_CALENDARS_TABLE_NAME, $data1, array( 'id' => intval($_GET["edit"]) ));
       if ($event->reference != '') $wpdb->update ( CPABC_APPOINTMENTS_TABLE_NAME, $data2, array( 'id' => intval($event->reference) ));
       
       echo '<script type="text/javascript">  document.location = "admin.php?page=cpabc_appointments&cal='.intval($_GET["cal"]).'&list=1&message=Item updated&r="+Math.random();</script>';
       exit;
    }    
    
    $date = date("Y-m-d", strtotime($event->datatime));
    $hour = intval (date("G", strtotime($event->datatime)));
    $minute = intval(date("i", strtotime($event->datatime)));
    if (strlen($minute)==2 && $minute[0] == '0') $minute = $minute[1];
  
    $nonce_un = wp_create_nonce( 'uname_abc_editlist' );  
    
?>

<div class="wrap">
<h1>Edit Booking</h1>

<form method="post" name="dexeditfrm" action=""> 
 <input type="hidden" name="rsaveedit" value="<?php echo esc_attr($nonce_un); ?>" />
 <div id="metabox_basic_settings" class="postbox" >
  <h3 class='hndle' style="padding:5px;"><span>Appointment Data</span></h3>
  <div class="inside">  
     <table class="form-table">    
        <tr valign="top">
        <th scope="row">Date</th>
        <td><input type="text" name="datatime" id="datatime" size="40" value="<?php echo esc_attr($date); ?>" /></td>
        </tr>
        <tr valign="top">
        <th scope="row">Time</th>
        <td>
          <select name="datatime_hour">
           <?php for ($i=0;$i<24;$i++) echo '<option'.($i==$hour?' selected':'').'>'.esc_html(($i<10?'0':'').$i).'</option>'; ?>
          </select> :
          <select name="datatime_minutes">
           <?php for ($i=0;$i<60;$i+=5) echo '<option'.($i==$minute?' selected':'').'>'.esc_html(($i<10?"0":"").$i).'</option>'; ?>
          </select>
        </td>
        </tr>
        <tr valign="top">
        <th scope="row">Appointment Title</th>
        <td><input type="text" name="title" size="40" value="<?php echo esc_attr($event->title); ?>" /></td>
        </tr>
        <tr valign="top">
        <th scope="row">Quantity</th>
        <td>
          <select name="quantity">
           <?php for ($i=1;$i<$event->quantity+20;$i++) { ?>
             <option <?php if (intval($event->quantity)==$i) echo ' selected'; ?>><?php echo intval($i); ?></option>
           <?php } ?>
          </select>
        </td>
        </tr>
        <?php 
         $excluded = explode(",",get_option('CPABC_EXCLUDED_COLUMNS',""));
         for ($i=0; $i<count($excluded); $i++)
             $excluded[$i] = strtolower(trim($excluded[$i]));
		 $excluded[] = 'utimezone';
		 $excluded[] = 'couponcode';
		 $excluded[] = 'request_timestamp';
         foreach ($params as $item => $value) 
         { 
             if (!is_array($value) && !in_array(strtolower($item), $excluded))
             {
        ?>
        <tr valign="top">
        <th scope="row"><?php 
                           $name = cpabc_appointments_get_field_name($item,$form_data[0]); 
                           //if ($name == 'ADULTS')
                           //    echo cpabc_get_option('quantity_field_label',CPABC_APPOINTMENTS_ENABLE_QUANTITY_FIELD_LABEL);
                           //else if ($name == 'JUNIORS')
                           //    echo cpabc_get_option('quantity_field_two_label',CPABC_APPOINTMENTS_ENABLE_QUANTITY_FIELD_TWO_LABEL);
                           //else
                               echo esc_html($name);
        ?></th>
        <td>
          <?php if (!is_array($value) && (strpos($value,"\n") > 0 || strlen($value) > 80)) { ?>
          <textarea cols="85" name="<?php echo $item; ?>"><?php echo esc_textarea($value); ?></textarea>
          <?php } else { ?>
          <input type="text" name="<?php echo $item; ?>" value="<?php echo esc_attr(is_array($value) ? implode(",", $value) : esc_attr($value)); ?>" />
          <?php } ?>
        </td>
        </tr>
        <?php 
               } 
               else
               {  
                  if (false) {
                      ?><input type="hidden" name="<?php echo $item; ?>" value="<?php echo esc_attr(serialize($value)); ?>" /><?php
                  }
               }
            }
        ?>
     </table>         
  </div>
 </div>       



<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="Save"  />  &nbsp; <input type="button" value="Cancel" onclick="javascript:gobackapp();"></p>



</form>

</div>

<script type="text/javascript">
 var $j = jQuery.noConflict();
 $j(function() {
 	$j("#datatime").datepicker({     	                
                    dateFormat: 'yy-mm-dd'
                 }); 	
 });
 function gobackapp()
 {
     document.location = 'admin.php?page=cpabc_appointments&cal=<?php echo intval($_GET["cal"]); ?>&list=1&r='+Math.random();
 }
</script>


<?php } else { ?>
  <br />
  The current user logged in doesn't have enough permissions to edit this item. Please log in as administrator to get full access.

<?php } ?>








