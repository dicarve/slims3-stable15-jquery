<?php
/**
 * Charts
 * Copyright (C) 2010  Arie Nugraha (dicarve@yahoo.com)
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

/* Chart/Plot Report section */

if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
}

require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
// PHPLOT Library
if (file_exists(LIB_DIR.'phplot'.DIRECTORY_SEPARATOR.'phplot.php')) {
    require LIB_DIR.'phplot'.DIRECTORY_SEPARATOR.'phplot.php';
} else {
    die();
}

// privileges checking
$can_read = utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('reporting', 'w');

if (!$can_read) { die(); }

/**
 * Function to generate random color
 */
function generateRandomColors()
{
    @mt_srand((double)microtime()*1000000);
    $_c = '';
    while(strlen($_c)<6){
        $_c .= sprintf("%02X", mt_rand(0, 255));
    }
    return $_c;
}

// create PHPLot object
$plot = new PHPlot(700);
$plot_data = array();
$data_colors = array();
// default chart
$chart = 'total_title_gmd';
$chart_title = __('Total Titles By Medium/GMD');

if (isset($_GET['chart'])) {
    $chart = trim($_GET['chart']);
}


/**
 * Defines data here
 */
switch ($chart) {
    case 'total_title_colltype':
        $chart_title = __('Total Items By Collection Type');
        $stat_query = $dbs->query('SELECT coll_type_name, COUNT(item_id) AS total_items
            FROM `item` AS i
            INNER JOIN mst_coll_type AS ct ON i.coll_type_id = ct.coll_type_id
            GROUP BY i.coll_type_id
            HAVING total_items >0
            ORDER BY COUNT(item_id) DESC');
        // set plot data and colors
        while ($data = $stat_query->fetch_row()) {
            $plot_data[] = array($data[0], $data[1]);
            $data_colors[] = '#'.generateRandomColors();
        }
        break;
    default:
        $stat_query = $dbs->query('SELECT gmd_name, COUNT(biblio_id) AS total_titles
            FROM `biblio` AS b
            INNER JOIN mst_gmd AS gmd ON b.gmd_id = gmd.gmd_id
            GROUP BY b.gmd_id HAVING total_titles>0 ORDER BY COUNT(biblio_id) DESC');
        // set plot data and colors
        while ($data = $stat_query->fetch_row()) {
            $plot_data[] = array($data[0], $data[1]);
            $data_colors[] = '#'.generateRandomColors();
        }
        break;
}
/**
 * Charts data definition end
 */

// Create plot
if ($plot_data && $chart) {
    // plot titles
    $plot->SetTitle($chart_title);

    // set data
    $plot->SetDataValues($plot_data);

    // plot colors
    $plot->SetDataColors($data_colors);

    // set plot type to pie
    $plot->SetPlotType('pie');
    $plot->SetDataType('text-data-single');

    // set legend
    foreach ($plot_data as $row) {
      $plot->SetLegend(implode(': ', $row));
    }

    //Draw it
    $plot->DrawGraph();
}
exit();
?>
