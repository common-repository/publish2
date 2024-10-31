<?php

require('../../../../wp-blog-header.php');

// Ensure the user is logged in and has suffient permissions
auth_redirect();
get_currentuserinfo();

if ($current_user->user_level > 5) {
	
	global $wpdb;

	if ($_GET['type'] == 'error') {
		
		$table_name = $wpdb->prefix . 'publish2_error_log';
		$query = "SELECT * 
							FROM $table_name
							ORDER BY created DESC";
		$error_records = $wpdb->get_results($query, ARRAY_N);
		$total = count($error_records);
	}

	// required for IE, otherwise Content-disposition is ignored
	if (ini_get('zlib.output_compression')) {
 		ini_set('zlib.output_compression', 'Off'); 
	} 
	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: private", FALSE);
	header("Content-type:application/octect-stream");
	header('Content-Disposition: attachment; filename=' . $table_name . '_' . date('Y-m-d') . '.csv');
	
	echo "record id, action, description, alert sent?, time of record\n";
	// Limiit the report to the last 100 records
	if ($total > 100) {
		$total = 100;
	}
	for ($i = 0; $i < $total; $i++) {
		echo '"' . $error_records[$i][0] . '", ';
		echo '"' . $error_records[$i][1] . '", ';
		echo '"' . $error_records[$i][2] . '", ';
		echo '"' . $error_records[$i][3] . '", ';
		echo '"' . $error_records[$i][4] . '"' . "\n";		
	}

}

exit;


?>