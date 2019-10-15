<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/* @author Eric Roberts
* Description: Reporting Controller
**/
ini_set('memory_limit','512M');
set_time_limit(300);

require_once(APPPATH.'libraries/DMSA_Export.php');
require_once(APPPATH.'libraries/DMSA_Color.php');
require_once(APPPATH.'libraries/DMSA_Query.php');

require_once(__DIR__.'/../inc/DMSA_Report.php');
require_once(__DIR__.'/../inc/DMSA_Cumulative_Query.php');

class Reporting_controller extends DMSA_Controller{

	public function __construct(){
		parent::__construct();
		$this->assure_session_exists();
		$this->load->model('privileges_model');
		$this->load->model('reporting_model');
	}

	public function test_newclass(){
		$specs = [
			'website_ids'	=>	[90,74,43,100,101,215],
			'filtered'		=>	true,
			'datestamp'		=>	"LAST_DAY('2017-05-01')",
			'columns'		=>	[
				'monthstamp',
				'website_id',
				'ministry_id',
				'name',
				'url',
				'country_short',
				'country_full',
				'mtd_visits' => 'visits',
				'ytd_visits',
				'cum_visits',
				'mtd_prayers' => 'prayers',
				'ytd_prayers',
				'cum_prayers',
				'mtd_form_fills' => 'form_fills',
				'ytd_form_fills',
				'cum_form_fills',
				'mtd_first_time' => 'first_time',
				'ytd_first_time',
				'cum_first_time',
				'mtd_rededications' => 'rededications',
				'ytd_rededications',
				'cum_rededications',
				'mtd_questions' => 'questions',
				'ytd_questions',
				'cum_questions',
				'mtd_response_rate' => 'response_rate',
				'ytd_response_rate',
				'cum_response_rate',
				'mtd_visits_form_fill_rate' => 'visits_form_fill_rate',
				'ytd_visits_form_fill_rate',
				'cum_visits_form_fill_rate',
				'mtd_prayers_form_fill_rate' => 'prayers_form_fill_rate',
				'ytd_prayers_form_fill_rate',
				'cum_prayers_form_fill_rate',
				'cum_first_time_rate',
				'cum_rededications_rate',
				'cum_questions_rate'
			]
		];

		$object = new DMSA_Cumulative_Query($specs);

		$output = $object('object','object');

		$query = $object->get_query();

		exit(print_r(compact('output','specs','object','query'),1));
	}

	public function export(){
		$data['module'] = 'export';
		$data['active_report'] = $this->reporting_model->active_report_obj;
		$this->load->view('reporting.html',array( 'viewdata'=>$data ));
	}

	public function create(){
		$data['module'] = 'create';
		$this->reporting_model->unload_report();
		$this->load->view('reporting.html',array( 'viewdata'=>$data ));
	}

	// Ajax Methods

	public function check_progress(){
		$result = array_key_exists('progress',$_SESSION)
			?	$_SESSION['progress']
			:	null;
		exit(json_encode(compact('result')));
	}

	public function jumpstart_db_assets(){
		$this->reporting_model->jumpstart_db_assets();
	}

	public function get_available_reports(){
		$reports = $this->reporting_model->get_available_reports();
		exit(json_encode($reports));
	}

	public function lookahead( $endpoint, $input=null ){
		$result = $this->reporting_model->call_lookahead( $endpoint, $input );
		exit(json_encode($result));
	}

	public function load_report( $report_id ){
		$loaded = $this->reporting_model->load_report( $report_id );

		$options = $loaded
			?	$this->reporting_model->get_report_init_data()
			:	array('problem'=>'Session Data Was Not Set?!?!');
		exit(json_encode($options));
	}

	public function clear_active(){
		$this->reporting_model->unload_report();
		exit(json_encode($_SESSION));
	}

	public function get_active_report(){
		$active_report = $this->reporting_model->get_active_report();
		exit(json_encode($active_report));
	}

	public function debug_method($method){
		$result = $this->reporting_model->call_report_method($method);
		exit(json_encode($result));
	}

	public function debug_session(){
		exit($_SESSION['active_report']);
	}

	public function update_report_params(){
		$state = $this->reporting_model->refresh_ui_state();
		$result = array_key_exists('confirmation',$state)
			?	$state
			:	$this->reporting_model->render_workbook();
		exit(json_encode($result));
	}

	public function export_workbook(){
		$result = $this->reporting_model->export_workbook();
	}

	public function debug_report_props(){
		$this->reporting_model->debug_report_props();
	}

	public function debug_query(){
		$obj = new ChurchLocator_Query();

		$query = $obj	->select(['clicks','unique_clicks','visits','unique_visits','seekers','searches','requests'])
						->filter( 'datestamp', "BETWEEN '2018-01-01' AND '2018-01-25'" )
						->order(  'name', 'DESC' )
						->group( 'country' )
						->rollup();

		exit( $query );
	}

	public function debug_state(){
		$have_active_report_obj = array_key_exists('active_report',$_SESSION);
		//$serialization = $_SESSION['active_report'];
		$have_active_report_data = array_key_exists('active_report_data',$_SESSION);
		$data = $have_active_report_data ? $_SESSION['active_report_data'] : array();
		exit(print_r(compact('have_active_report_obj','have_active_report_data','data'),true));
	}

	public function check_requirements( $report_id ){
		$result = $this->reporting_model->check_report_requirements( $report_id );
		exit(json_encode($result));
	}

}
