<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* Location Management section */

// main system configuration
require '../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('master_file', 'r');
$can_write = utility::havePrivilege('master_file', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
}

/* RECORD OPERATION */
if (isset($_POST['saveData']) AND $can_read AND $can_write) {
    $locationName = trim(strip_tags($_POST['locationName']));
    $locationID = trim(strip_tags($_POST['locationID']));
    // check form validity
    if (empty($locationName) OR empty($locationID)) {
        utility::jsAlert(__('Location ID and Name can\'t be empty')); //mfc
        exit();
    } else {
        $data['location_id'] = $dbs->escape_string($locationID);
        $data['location_name'] = $dbs->escape_string($locationName);
        $data['input_date'] = date('Y-m-d');
        $data['last_update'] = date('Y-m-d');

        // create sql op object
        $sql_op = new simbio_dbop($dbs);
        if (isset($_POST['updateRecordID'])) {
            /* UPDATE RECORD MODE */
            // remove input date
            unset($data['input_date']);
            // filter update record ID
            $updateRecordID = $dbs->escape_string(trim($_POST['updateRecordID']));
            // update the data
            $update = $sql_op->update('mst_location', $data, 'location_id=\''.$updateRecordID.'\'');
            if ($update) {
                utility::jsAlert(__('Location Data Successfully Updated'));
                // update location ID in item table to keep data integrity
                $sql_op->update('item', array('location_id' => $data['location_id']), 'location_id=\''.$updateRecordID.'\'');
                echo '<script type="text/javascript">parent.jQuery(\'#mainContent\').simbioAJAX(parent.jQuery.ajaxHistory[1].url);</script>';
            } else { utility::jsAlert(__('Location Data FAILED to Updated. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); }
            exit();
        } else {
            /* INSERT RECORD MODE */
            // insert the data
            $insert = $sql_op->insert('mst_location', $data);
            if ($insert) {
                utility::jsAlert(__('New Location Data Successfully Saved'));
                echo '<script type="text/javascript">parent.jQuery(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'\');</script>';
            } else { utility::jsAlert(__('Location Data FAILED to Save. Please Contact System Administrator')."\n".$sql_op->error); }
            exit();
        }
    }
    exit();
} else if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) {
    if (!($can_read AND $can_write)) {
        die();
    }
    /* DATA DELETION PROCESS */
    $sql_op = new simbio_dbop($dbs);
    $failed_array = array();
    $error_num = 0;
    if (!is_array($_POST['itemID'])) {
        // make an array
        $_POST['itemID'] = array($dbs->escape_string(trim($_POST['itemID'])));
    }
    // loop array
    foreach ($_POST['itemID'] as $itemID) {
        $itemID = $dbs->escape_string(trim($itemID));
        // check if this item data still have an item
        $item_q = $dbs->query('SELECT loc.location_name, COUNT(item_id) FROM item AS i
            LEFT JOIN mst_location AS loc ON i.location_id=loc.location_id
            WHERE i.location_id=\''.$itemID.'\' GROUP BY i.location_id');
        $item_d = $item_q->fetch_row();
        if ($item_d[1] < 1) {
            if (!$sql_op->delete('mst_location', "location_id='$itemID'")) {
                $error_num++;
            }
        } else {
            $msg = str_replace('{item_name}', $item_d[0], __('Location ({item_name}) still used by {number_items} item(s)')); //mfc
            $msg = str_replace('{number_items}', $item_d[1], $msg);
            $still_have_item[] = $msg;
            $error_num++;
        }
    }

    if ($still_have_item) {
        $undeleted_locations = '';
        foreach ($still_have_item as $location) {
            $undeleted_locations .= $location."\n";
        }
        utility::jsAlert(__('Below data can not be deleted:')." : \n".$undeleted_locations);
        exit();
    }
    // error alerting
    if ($error_num == 0) {
        utility::jsAlert(__('All Data Successfully Deleted'));
        echo '<script type="text/javascript">parent.jQuery(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\');</script>';
    } else {
        utility::jsAlert(__('Some or All Data NOT deleted successfully!\nPlease contact system administrator'));
        echo '<script type="text/javascript">parent.jQuery(\'#mainContent\').simbioAJAX(\''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\');</script>';
    }
    exit();
}
/* RECORD OPERATION END */

/* search form */
?>
<fieldset class="menuBox">
<div class="menuBoxInner masterFileIcon">
    <?php echo strtoupper(__('Location')); ?> - <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/location.php?action=detail" class="headerText2"><?php echo __('Add New Location'); ?></a>
    &nbsp; <a href="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/location.php" class="headerText2"><?php echo __('Location List'); ?></a>
    <hr />
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>master_file/location.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
    <input type="text" name="keywords" size="30" />
    <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="button" />
    </form>
</div>
</fieldset>
<?php
/* search form end */
/* main content */
if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail')) {
    if (!($can_read AND $can_write)) {
        die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
    }
    /* RECORD FORM */
    $itemID = $dbs->escape_string(isset($_POST['itemID'])?$_POST['itemID']:'');
    $rec_q = $dbs->query("SELECT * FROM mst_location WHERE location_id='$itemID'");
    $rec_d = $rec_q->fetch_assoc();

    // create new instance
    $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="button"';

    // form table attributes
    $form->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
    $form->table_content_attr = 'class="alterCell2"';

    // edit mode flag set
    if ($rec_q->num_rows > 0) {
        $form->edit_mode = true;
        // record ID for delete process
        $form->record_id = $itemID;
        // form record title
        $form->record_title = $rec_d['location_name'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="button"';
    }

    /* Form Element(s) */
    // location code
    $form->addTextField('text', 'locationID', __('Location Code').'*', $rec_d['location_id'], 'style="width: 20%;" maxlength="3"');
    // location name
    $form->addTextField('text', 'locationName', __('Location Name').'*', $rec_d['location_name'], 'style="width: 60%;"');

    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox">'.__('You are going to edit location data').' : <b>'.$rec_d['location_name'].'</b> <br />'.__('Last Update').$rec_d['last_update'].'</div>'; //mfc
    }
    // print out the form object
    echo $form->printOut();
} else {
    /* LOCATION LIST */
    // table spec
    $table_spec = 'mst_location AS l';

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_read AND $can_write) {
        $datagrid->setSQLColumn('l.location_id',
            'l.location_id AS \''.__('Location Code').'\'',
            'l.location_name AS \''.__('Location Name').'\'',
            'l.last_update AS \''.__('Last Update').'\'');
    } else {
        $datagrid->setSQLColumn('l.location_id AS \''.__('Location Code').'\'',
            'l.location_name AS \''.__('Location Name').'\'',
            'l.last_update AS \''.__('Last Update').'\'');
    }
    $datagrid->setSQLorder('location_name ASC');

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
       $keywords = $dbs->escape_string($_GET['keywords']);
       $datagrid->setSQLCriteria("l.location_name LIKE '%$keywords%'");
    }

    // set table and table header attributes
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];

    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : "'.$_GET['keywords'].'"</div>';
    }

    echo $datagrid_result;
}
/* main content end */
?>
