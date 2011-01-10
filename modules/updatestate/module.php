<?php
$Module = array('name' => 'Update State');
$ViewList = array();
$ViewList['list'] = array(	'script' => 'list.php',
							'functions' => array( 'list' ),
						    'default_navigation_part' => 'ezmynavigationpart',
							'params' => array('State'),
							'unordered_params' => array( 'offset' => 'Offset' ) );


$FunctionList = array();
$FunctionList['list'] = array();
?>