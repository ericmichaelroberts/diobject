<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Reporting_model extends DMSA_Model{

	public static $Version = 4;

	protected static $DBO = array(
		'tables'	=>	array(
			'reporting_module' => array(
				'fields' => array(
					'report_id'	=> 'INT(11) NOT NULL AUTO_INCREMENT',
					'report_title' => 'VARCHAR(128) NOT NULL DEFAULT \'Untitled Report\'',
					'report_schema' => 'TEXT NOT NULL',
					'created_at' => 'DATETIME NOT NULL',
					'modified_at' => 'DATETIME NOT NULL',
					'min_version' => 'INT(11) NOT NULL DEFAULT 1'
				),
				'keys' => array(
					'primary' => 'report_id'
				)
			)
		),
		'views'		=>	array()
	);

	public $active_report_obj = null;

	public function __construct(){
		parent::__construct();
		$this->active_report_obj = array_key_exists('active_report',$_SESSION) && !is_null($_SESSION['active_report'])
			?	unserialize($_SESSION['active_report'])
			:	null;
	}

	public function __destruct(){
		if(!(is_null($this->active_report_obj))){
			$_SESSION['active_report'] = serialize($this->active_report_obj);
		}
	}

	public function call_report_method( $method ){
		$result = $this->active_report_obj->$method();
		return $result;
	}

	public function call_lookahead( $endpoint, $input=null ){
		return $this->active_report_obj->$endpoint( $input );
	}

	public function get_active_report(){
		return $this->active_report_obj;
	}

	public function get_available_reports(){
		$this->dbo_verify( 'reporting_module' );
		$v = self::$Version;
		$q = $this->db->query("SELECT * FROM reporting_module WHERE min_version <={$v} AND enabled=1 ORDER BY report_title ASC");
		$data = $q->result_array();
		$schemas = array();

		if(is_file(__DIR__.'/../wip.json')){
			$contents = trim(file_get_contents(__DIR__.'/../wip.json'));
			if(strlen($contents) && substr($contents,0,1)=='{' && substr($contents,-1,1)=='}'){
				$wip = json_decode($contents);
				$wip->id = 0;
				$wip->title = property_exists($wip,'title') ? "{$wip->title} (WIP)" : 'Unititled (WIP)';
				$schemas[] = $wip;
			}
		}

		foreach($data as $idx => $row){
			$schema = json_decode($row['report_schema']);
			$schema->id = $row['report_id'];
			$schema->title = $row['report_title'];
			$schemas[] = $schema;
		}

		return $schemas;
	}

	public function jumpstart_db_assets(){
		//This was a one-time shortcut, commenting out actual db-insert...
		$reports = json_decode(file_get_contents(__DIR__.'/../reports.json'));
		foreach($reports as $idx => $schema){
			$id = $schema->id;
			$title = $schema->title;
			$now = date('Y-m-d H:i:s');

			unset($schema->id);
		 	unset($schema->title);

			$insert = $this->db->insert_string('reporting_module',array(
				'report_title' => $title,
				'report_schema' => json_encode($schema),
				'created_at' => $now,
				'modified_at' => $now
			));
			//$this->db->query($insert); ...like I said above (EMR)
		}
	}

	public function check_report_requirements( $report_id ){
		$reports = $this->get_available_reports();
		foreach($reports as $schema){
			if($schema->id==$report_id){
				$report = new DMSA_Report( $schema );
				return $report->check_requirements();
			}
		}
	}

	public function unload_report( ){
		$this->active_report_obj = null;
		unset($_SESSION['active_report']);
		unset($_SESSION['active_report_progress']);
	}

	public function load_report( $report_id ){
		$this->unload_report();
		$reports = $this->get_available_reports();
		foreach($reports as $schema){
			if($schema->id==$report_id){
				return $this->activate_report($report_id,$schema);
			}
		}
		return false;
	}

	public function render_workbook(){
		return $this->active_report_obj->render_workbook();
	}

	public function activate_report($report_id,$schema){
		$this->unload_report();
		$this->active_report_obj = new DMSA_Report( $schema );
		$this->active_report_obj->init();
		return $_SESSION['active_report'] = serialize($this->active_report_obj);
	}

	public function get_report_init_data(){
		return $this->active_report_obj->get_client_init_data();
	}

	public function refresh_ui_state(){
		$result = $this->active_report_obj->refresh_ui_state( $this->input->post( null, true) );
		$_SESSION['active_report'] = serialize($this->active_report_obj);
		return $result;
	}

	public function set_report_params($params=array()){
		foreach($params as $key => $value){
			$this->active_report_obj->$key = $value;
		}
		return $this->active_report_obj->get_internal_state();
	}

	public function export_workbook(){
		return $this->active_report_obj->export_workbook();
	}

	public function debug_report_props(){
		return $this->active_report_obj->debug_report_props();
	}
}
