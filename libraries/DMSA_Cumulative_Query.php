<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class DMSA_Cumulative_Query {

	protected static $CI;

	protected $td_query;
	protected $td_filters = [];
	protected $td_data = [];
	protected $td_totals = [];
	protected $td_rates = [];

	protected $websites_data;

	protected $specs;
	protected $query;
	protected $where;
	protected $data;

	protected $selects = [];
	protected $filters = [];
	protected $prop_aliases = [
		'fields'			=>	'set_columns',
		'selects'			=>	'set_columns',
		'select'			=>	'set_columns',
		'filter'			=>	'set_ip_filtering',
		'ip_filter'			=>	'set_ip_filtering',
		'filtering'			=>	'set_ip_filtering',
		'ip_filtering'		=>	'set_ip_filtering',
		'filtered'			=>	'set_ip_filtering',
		'ip_filtered'		=>	'set_ip_filtering',
		'year'				=>	'set_yearstamp',
		'month'				=>	'set_monthstamp',
		'week'				=>	'set_weekstamp',
		'date'				=>	'set_datestamp'
	];

	protected $inner_selects = [
		'website_id'					=> "A.website_id",
		'ministry_id'					=> "A.ministry_id",
		'name'							=> "A.name",
		'url'							=> "A.url",
		'country_short'					=> "A.country_short",
		'country_full'					=> "A.country_full",

		'datestamp'						=> "B.datestamp",
		'weekstamp'						=> "B.weekstamp",
		'monthstamp'					=> "B.monthstamp",
		'yearstamp'						=> "B.yearstamp",
		'weekending'					=> "B.weekending",
		'monthending'					=> "B.monthending",

		'visits'						=> "IFNULL(B.visits, 0) AS visits",
		'wtd_visits'					=> "IFNULL(B.visitsWTD, 0) AS wtd_visits",
		'mtd_visits'					=> "IFNULL(B.visitsMTD, 0) AS mtd_visits",
		'ytd_visits'					=> "IFNULL(B.visitsYTD, 0) AS ytd_visits",
		'cum_visits'					=> "IFNULL(B.visitsC, 0) AS cum_visits",

		'prayers'						=> "IFNULL(B.prayers, 0) AS prayers",
		'wtd_prayers'					=> "IFNULL(B.prayersWTD, 0) AS wtd_prayers",
		'mtd_prayers'					=> "IFNULL(B.prayersMTD, 0) AS mtd_prayers",
		'ytd_prayers'					=> "IFNULL(B.prayersYTD, 0) AS ytd_prayers",
		'cum_prayers'					=> "IFNULL(B.prayersC, 0) AS cum_prayers",

		'form_fills'					=> "IFNULL(B.form_fills, 0) AS form_fills",
		'wtd_form_fills'				=> "IFNULL(B.form_fillsWTD, 0) AS wtd_form_fills",
		'mtd_form_fills'				=> "IFNULL(B.form_fillsMTD, 0) AS mtd_form_fills",
		'ytd_form_fills'				=> "IFNULL(B.form_fillsYTD, 0) AS ytd_form_fills",
		'cum_form_fills'				=> "IFNULL(B.form_fillsC, 0) AS cum_form_fills",

		'first_time'					=> "IFNULL(B.first_time, 0) AS first_time",
		'wtd_first_time'				=> "IFNULL(B.first_timeWTD, 0) AS wtd_first_time",
		'mtd_first_time'				=> "IFNULL(B.first_timeMTD, 0) AS mtd_first_time",
		'ytd_first_time'				=> "IFNULL(B.first_timeYTD, 0) AS ytd_first_time",
		'cum_first_time'				=> "IFNULL(B.first_timeC, 0) AS cum_first_time",

		'rededications'					=> "IFNULL(B.rededications, 0) AS rededications",
		'wtd_rededications'				=> "IFNULL(B.rededicationsWTD, 0) AS wtd_rededications",
		'mtd_rededications'				=> "IFNULL(B.rededicationsMTD, 0) AS mtd_rededications",
		'ytd_rededications'				=> "IFNULL(B.rededicationsYTD, 0) AS ytd_rededications",
		'cum_rededications'				=> "IFNULL(B.rededicationsC, 0) AS cum_rededications",

		'questions'						=> "IFNULL(B.questions, 0) AS questions",
		'wtd_questions'					=> "IFNULL(B.questionsWTD, 0) AS wtd_questions",
		'mtd_questions'					=> "IFNULL(B.questionsMTD, 0) AS mtd_questions",
		'ytd_questions'					=> "IFNULL(B.questionsYTD, 0) AS ytd_questions",
		'cum_questions'					=> "IFNULL(B.questionsC, 0) AS cum_questions",

		'response_rate'					=> "IFNULL(B.prayers / B.visits, 0) AS response_rate",
		'wtd_response_rate'				=> "IFNULL(B.prayersWTD / B.visitsWTD, 0) AS wtd_response_rate",
		'mtd_response_rate'				=> "IFNULL(B.prayersMTD / B.visitsMTD, 0) AS mtd_response_rate",
		'ytd_response_rate'				=> "IFNULL(B.prayersYTD / B.visitsYTD, 0) AS ytd_response_rate",
		'cum_response_rate'				=> "IFNULL(B.prayersC / B.visitsC, 0) AS cum_response_rate",

		'first_time_rate'				=> "IFNULL(B.first_time / B.form_fills, 0) AS first_time_rate",
		'wtd_first_time_rate'			=> "IFNULL(B.first_timeWTD / B.form_fillsWTD, 0) AS wtd_first_time_rate",
		'mtd_first_time_rate'			=> "IFNULL(B.first_timeMTD / B.form_fillsMTD, 0) AS mtd_first_time_rate",
		'ytd_first_time_rate'			=> "IFNULL(B.first_timeYTD / B.form_fillsYTD, 0) AS ytd_first_time_rate",
		'cum_first_time_rate'			=> "IFNULL(B.first_timeC / B.form_fillsC, 0) AS cum_first_time_rate",

		'rededications_rate'			=> "IFNULL(B.rededications / B.form_fills, 0) AS rededications_rate",
		'wtd_rededications_rate'		=> "IFNULL(B.rededicationsWTD / B.form_fillsWTD, 0) AS wtd_rededications_rate",
		'mtd_rededications_rate'		=> "IFNULL(B.rededicationsMTD / B.form_fillsMTD, 0) AS mtd_rededications_rate",
		'ytd_rededications_rate'		=> "IFNULL(B.rededicationsYTD / B.form_fillsYTD, 0) AS ytd_rededications_rate",
		'cum_rededications_rate'		=> "IFNULL(B.rededicationsC / B.form_fillsC, 0) AS cum_rededications_rate",

		'questions_rate'				=> "IFNULL(B.questions / B.form_fills, 0) AS questions_rate",
		'wtd_questions_rate'			=> "IFNULL(B.questionsWTD / B.form_fillsWTD, 0) AS wtd_questions_rate",
		'mtd_questions_rate'			=> "IFNULL(B.questionsMTD / B.form_fillsMTD, 0) AS mtd_questions_rate",
		'ytd_questions_rate'			=> "IFNULL(B.questionsYTD / B.form_fillsYTD, 0) AS ytd_questions_rate",
		'cum_questions_rate'			=> "IFNULL(B.questionsC / B.form_fillsC, 0) AS cum_questions_rate"
	];

	protected $calculations = [

	];

	protected $middle_dependencies = [
		'visits_form_fill_rate'			=> ['form_fills','visits'],
		'wtd_visits_form_fill_rate'		=> ['wtd_form_fills','wtd_visits'],
		'mtd_visits_form_fill_rate'		=> ['mtd_form_fills','mtd_visits'],
		'ytd_visits_form_fill_rate'		=> ['ytd_form_fills','ytd_visits'],
		'cum_visits_form_fill_rate'		=> ['cum_form_fills','cum_visits'],

		'prayers_form_fill_rate'		=> ['form_fills','prayers'],
		'wtd_prayers_form_fill_rate'	=> ['wtd_form_fills','wtd_prayers'],
		'mtd_prayers_form_fill_rate'	=> ['mtd_form_fills','mtd_prayers'],
		'ytd_prayers_form_fill_rate'	=> ['ytd_form_fills','ytd_prayers'],
		'cum_prayers_form_fill_rate'	=> ['cum_form_fills','cum_prayers']
	];

	protected $middle_selects = [
		'visits_form_fill_rate'			=> "IFNULL(X.form_fills / X.visits, 0) AS visits_form_fill_rate",
		'wtd_visits_form_fill_rate'		=> "IFNULL(X.wtd_form_fills / X.wtd_visits, 0) AS wtd_visits_form_fill_rate",
		'mtd_visits_form_fill_rate'		=> "IFNULL(X.mtd_form_fills / X.mtd_visits, 0) AS mtd_visits_form_fill_rate",
		'ytd_visits_form_fill_rate'		=> "IFNULL(X.ytd_form_fills / X.ytd_visits, 0) AS ytd_visits_form_fill_rate",
		'cum_visits_form_fill_rate'		=> "IFNULL(X.cum_form_fills / X.cum_visits, 0) AS cum_visits_form_fill_rate",

		'prayers_form_fill_rate'		=> "IFNULL(X.form_fills / X.prayers, 0) AS prayers_form_fill_rate",
		'wtd_prayers_form_fill_rate'	=> "IFNULL(X.wtd_form_fills / X.wtd_prayers, 0) AS wtd_prayers_form_fill_rate",
		'mtd_prayers_form_fill_rate'	=> "IFNULL(X.mtd_form_fills / X.mtd_prayers, 0) AS mtd_prayers_form_fill_rate",
		'ytd_prayers_form_fill_rate'	=> "IFNULL(X.ytd_form_fills / X.ytd_prayers, 0) AS ytd_prayers_form_fill_rate",
		'cum_prayers_form_fill_rate'	=> "IFNULL(X.cum_form_fills / X.cum_prayers, 0) AS cum_prayers_form_fill_rate"
	];

	public function get_query(){
		$this->proc_specs();
		$this->render_td_query();
		return $this->td_query;
	}

	public function get_dataset($outerFmt='array',$innerFmt='object'){
		$this->proc_specs();
		$this->render_td_query();
		$this->get_td_data();
		return $this->create_dataset( $outerFmt, $innerFmt );
	}

	private function get_totals(){
		$this->calc_rates( 'response', 'prayers', 'visits' );
		$this->calc_rates( 'visits_form_fill', 'form_fills', 'visits' );
		$this->calc_rates( 'prayers_form_fill', 'form_fills', 'prayers' );
		$this->calc_rates( 'first_time', 'first_time', 'form_fills' );
		$this->calc_rates( 'rededications', 'first_time', 'form_fills' );
		$this->calc_rates( 'questions', 'first_time', 'form_fills' );

		$temp = array_merge( $this->td_totals, $this->td_rates );

		return (object)$temp;
	}

	private function calc_rates( $rootName, $dividend, $divisor ){
		$prefixes = ['wtd','mtd','ytd','cum'];
		foreach( $prefixes as $prefix ){
			$dividendKey = "{$prefix}_{$dividend}";
			$divisorKey = "{$prefix}_{$divisor}";
			$resultKey = "{$prefix}_{$rootName}_rate";
			if(
				array_key_exists( $dividendKey, $this->td_totals )
				&&
				array_key_exists( $divisorKey, $this->td_totals )
			){
				$this->td_rates[ $resultKey ] = $this->td_totals[ $divisorKey ] > 0
					?	$this->td_totals[ $dividendKey ] / $this->td_totals[ $divisorKey ]
					:	0;
			}
		}
	}

	public function get_totals_array(){
		$totals = $this->get_totals();
		return (array)$totals;
	}

	public function get_totals_object(){
		return $this->get_totals();
	}

	public function __construct( $specs ){
		self::$CI =& get_instance();
		$this->specs = is_null($specs)
			?	new stdClass
			:	(object)$specs;
	}

	public function __call( $fn, $args ){
		return method_exists($this,$fn)
			?	call_user_func_array($this->$fn,$args)
			:	(array_key_exists($fn,$this->prop_aliases)
				?	call_user_func_array($this->prop_aliases[$fn],$args)
				:	new Exception("DMSA_Cumulative_Query::{$fn} is not a valid method name")
		);
	}

	public function __invoke($outerFmt='array',$innerFmt='object'){
		return $this->get_dataset($outerFmt, $innerFmt);
	}

	public function __toString(){
		return $this->get_query();
	}

	public function set_columns( $values=null ){
		return $this->selects = is_null($values)
			?	array_keys($this->inner_selects)
			:	(is_array($values)
				?	$values
				:	(is_string($values)
					?	[$values]
					:	'*'));
	}

	public function set_website_ids( $value=null ){
		$values = is_string($value)
			?	explode(',',trim($value))
			:	(is_array($value)
				?	$value
				:	[$value]);
		return $this->filters['website_id'] = $values;
	}

	public function set_datestamp( $value=null ){
		return $this->filters['datestamp'] = $this->normalize_datetime($value);
	}

	public function set_weekstamp( $value=null ){
		return $this->filters['weekstamp'] = $this->normalize_datetime($value);
	}

	public function set_monthstamp( $value=null ){
		return $this->filters['monthstamp'] = $this->normalize_datetime($value); //A.datestamp
	}

	public function set_yearstamp( $value=null ){
		return $this->filters['yearstamp'] = $value;
	}

	public function set_ip_filtering( $value=true ){
		return $this->filters['filtered'] = !!$value;
	}

	public function set_filters( $filters ){
		foreach($filters as $filter => $value){
			$m = array_key_exists($filter,$this->prop_aliases)
				?	$this->prop_aliases[$filter]
				:	(method_exists($this,"set_{$filter}")
					?	"set_{$filter}"
					:	false);
			$m ? $this->$m( $value ) : null;
		}
	}

	private function is_expression( $input ){
		return !preg_match('/^[a-z|_][a-z|0-9|_]*$/i',$input);
	}

	private function get_expression_dependencies( $input ){
		$output = [];
		$tokens = preg_split('/\b/',$input);
		$possibles = preg_grep('/^[a-z|_][a-z|0-9|_]*$/i',$tokens);
		foreach($possibles as $potential){
			if( array_key_exists($potential,$this->selects) || in_array($potential,$this->selects) ){
				$output[$potential] = false;
			}elseif( array_key_exists($potential,$this->inner_selects) || array_key_exists($potential,$this->middle_selects) ){
				$output[$potential] = true;
			}
		}
		return array_keys(array_filter($output));
	}

	private function build_outer_selects(){
		$map = [];
		$selects = [];
		$calculations = [];

		foreach( $this->selects as $a => $b){
			$value = is_int($a) ? $b : $a;
			if($this->is_expression($value)){
				$calculations[$b] = $value;
			}else{
				$map[$b] = $value;
			}
		}

		foreach($map as $name => $col){
			$selects[$col] = $name==$col ? $col : "{$col} AS {$name}";
		}

		return $selects;
	}

	private function build_inner_query($outer_selects){
		$middle_selects = [];
		$inner_selects = [];
		$deps = array_keys($outer_selects);

		$filter = !empty($this->td_filters)
			?	implode(' AND ',$this->td_filters)
			:	" filtered = 0";

		foreach($deps as $key){
			if(	array_key_exists($key,$this->middle_selects)	){
				$middle_selects[$key] = $this->middle_selects[$key];
				if(array_key_exists($key,$this->middle_dependencies)){
					foreach($this->middle_dependencies[$key] as $inner_key){
						$inner_selects[$inner_key] = $this->inner_selects[$inner_key];
					}
				}
			}elseif(array_key_exists($key,$this->inner_selects)){
				$inner_selects[$key] = $this->inner_selects[$key];
			}
		}

		$inners = implode(',',$inner_selects);

		$middles = empty($middle_selects) ? '' : ', '.implode(',',$middle_selects);

		$inner_query = "SELECT {$inners} FROM websites A LEFT JOIN reports_table_td B ON (A.website_id = B.website_id) WHERE {$filter}";

		$middle_query = "SELECT X.*{$middles} FROM ({$inner_query}) X ORDER BY X.name ASC";

		return $middle_query;
	}

	private function build_td_filtered_filter($value){
		$f = $value ? 1 : 0;
		$this->td_filters['filtered'] = "B.filtered = {$f}";
	}

	private function build_td_website_id_filter($value){
		$this->td_filters['website_id'] = sizeof($value)===1
			?	"A.website_id = {$value[0]}"
			:	"A.website_id IN(".implode(',',$value).")";
	}

	private function build_td_datestamp_filter($datestamp){
		$this->td_filters['datestamp'] = "B.datestamp = {$datestamp}";
	}

	private function build_td_weekstamp_filter($weekstamp){
		$this->td_filters['weekstamp'] = "B.datestamp = (SELECT MAX(datestamp) FROM reports_table_td WHERE weekstamp = {$weekstamp})";
	}

	private function build_td_monthstamp_filter($monthstamp){
		$this->td_filters['monthstamp'] = "B.datestamp = (SELECT MAX(datestamp) FROM reports_table_td WHERE monthstamp = {$monthstamp})";
	}

	private function build_td_yearstamp_filter($year){
		$current_year = date('Y',time());
		$this->td_filters['yearstamp'] = $year == $current_year
			?	"B.datestamp = (SELECT MAX(datestamp) FROM reports_table_td WHERE yearstamp = {$year})"
			:	"B.datestamp = '{$year}-12-31'";
	}

	private function create_dataset_container(){
		$this->websites_data = new stdClass;
		foreach($this->filters['website_id'] as $website_id){
			$this->websites_data->{$website_id} = (object)(array_fill_keys([
				'website_id','name','url','country_short','country_full','visits','wtd_visits','mtd_visits','ytd_visits','cum_visits','prayers','wtd_prayers','mtd_prayers','ytd_prayers','cum_prayers','form_fills','first_time','rededications','questions','wtd_form_fills','wtd_first_time','wtd_rededications','wtd_questions','mtd_form_fills','mtd_first_time','mtd_rededications','mtd_questions','ytd_form_fills','ytd_first_time','ytd_rededications','ytd_questions','cum_form_fills','cum_first_time','cum_rededications','cum_questions','response_rate','wtd_response_rate','mtd_response_rate','ytd_response_rate','cum_response_rate','first_time_rate','rededications_rate','questions_rate','wtd_first_time_rate','wtd_rededications_rate','wtd_questions_rate','mtd_first_time_rate','mtd_rededications_rate','mtd_questions_rate','ytd_first_time_rate','ytd_rededications_rate','ytd_questions_rate','cum_first_time_rate','cum_rededications_rate','cum_questions_rate','visits_form_fill_rate','wtd_visits_form_fill_rate','mtd_visits_form_fill_rate','ytd_visits_form_fill_rate','cum_visits_form_fill_rate','prayers_form_fill_rate','wtd_prayers_form_fill_rate','mtd_prayers_form_fill_rate','ytd_prayers_form_fill_rate','cum_prayers_form_fill_rate'], null));
		}
	}

	private function create_dataset( $outer, $inner ){
		$map = [];
		$dataset = $outer=='array' ? [] : new stdClass;

		foreach( $this->selects as $a => $b){
			$map[$b] = is_int($a) ? $b : $a;
		}

		foreach( $this->websites_data as $website_id => $data ){
			$set = $inner=='array'
				?	$this->create_dataset_array( $map, $data )
				:	$this->create_dataset_object( $map, $data );

			$outer=='array'
				?	$dataset[] = $set
				:	$dataset->{$website_id} = $set;
		}

		return $dataset;
	}

	private function create_dataset_object( $map, $data ){
		$output = new stdClass;
		foreach($map as $name => $col){
			$output->$name = $data->$col;
		}
		return $output;
	}

	private function create_dataset_array( $map, $data ){
		$output = [];
		foreach($map as $name => $col){
			$output[$name] = $data->$col;
		}
		return $output;
	}

	private function render_td_query(){
		foreach($this->filters as $filter => $value){
			$m = "build_td_{$filter}_filter";
			if(method_exists($this,$m)){
				$this->$m( $value );
			}else{
				$this->raw_filters[$filter] = $value;
			}
		}

		$outer_selects = $this->build_outer_selects();

		$inner_query = $this->build_inner_query($outer_selects);

		$outers = implode(',',$outer_selects);

		$this->td_query = "SELECT {$outers} FROM ({$inner_query}) Y";
	}

	private function alt_render_td_query(){

		foreach($this->filters as $filter => $value){
			$m = "build_td_{$filter}_filter";
			if(method_exists($this,$m)){
				$this->$m( $value );
			}else{
				$this->raw_filters[$filter] = $value;
			}
		}

		$filter = !empty($this->td_filters)
			?	implode(' AND ',$this->td_filters)
			:	" filtered = 0";



		$inner = implode(' ',[
			"SELECT",
				"A.website_id,",
				"A.ministry_id,",
				"A.name,",
				"A.url,",
				"A.country_short,",
				"A.country_full,",

				"IFNULL(B.visits, 0) AS visits,",
				"IFNULL(B.visitsWTD, 0) AS wtd_visits,",
				"IFNULL(B.visitsMTD, 0) AS mtd_visits,",
				"IFNULL(B.visitsYTD, 0) AS ytd_visits,",
				"IFNULL(B.visitsC, 0) AS cum_visits,",

				"IFNULL(B.prayers, 0) AS prayers,",
				"IFNULL(B.prayersWTD, 0) AS wtd_prayers,",
				"IFNULL(B.prayersMTD, 0) AS mtd_prayers,",
				"IFNULL(B.prayersYTD, 0) AS ytd_prayers,",
				"IFNULL(B.prayersC, 0) AS cum_prayers,",

				"IFNULL(B.form_fills, 0) AS form_fills,",
				"IFNULL(B.first_time, 0) AS first_time,",
				"IFNULL(B.rededications, 0) AS rededications,",
				"IFNULL(B.questions, 0) AS questions,",

				"IFNULL(B.form_fillsWTD, 0) AS wtd_form_fills,",
				"IFNULL(B.first_timeWTD, 0) AS wtd_first_time,",
				"IFNULL(B.rededicationsWTD, 0) AS wtd_rededications,",
				"IFNULL(B.questionsWTD, 0) AS wtd_questions,",

				"IFNULL(B.form_fillsMTD, 0) AS mtd_form_fills,",
				"IFNULL(B.first_timeMTD, 0) AS mtd_first_time,",
				"IFNULL(B.rededicationsMTD, 0) AS mtd_rededications,",
				"IFNULL(B.questionsMTD, 0) AS mtd_questions,",

				"IFNULL(B.form_fillsYTD, 0) AS ytd_form_fills,",
				"IFNULL(B.first_timeYTD, 0) AS ytd_first_time,",
				"IFNULL(B.rededicationsYTD, 0) AS ytd_rededications,",
				"IFNULL(B.questionsYTD, 0) AS ytd_questions,",

				"IFNULL(B.form_fillsC, 0) AS cum_form_fills,",
				"IFNULL(B.first_timeC, 0) AS cum_first_time,",
				"IFNULL(B.rededicationsC, 0) AS cum_rededications,",
				"IFNULL(B.questionsC, 0) AS cum_questions,",

				"IFNULL(B.prayers / B.visits,0) AS response_rate,",
				"IFNULL(B.prayersWTD / B.visitsWTD,0) AS wtd_response_rate,",
				"IFNULL(B.prayersMTD / B.visitsMTD,0) AS mtd_response_rate,",
				"IFNULL(B.prayersYTD / B.visitsYTD,0) AS ytd_response_rate,",
				"IFNULL(B.prayersC / B.visitsC,0) AS cum_response_rate,",

				"IFNULL(B.first_time / B.form_fills, 0) AS first_time_rate,",
				"IFNULL(B.rededications / B.form_fills, 0) AS rededications_rate,",
				"IFNULL(B.questions / B.form_fills, 0) AS questions_rate,",

				"IFNULL(B.first_timeWTD / B.form_fillsWTD, 0) AS wtd_first_time_rate,",
				"IFNULL(B.rededicationsWTD / B.form_fillsWTD, 0) AS wtd_rededications_rate,",
				"IFNULL(B.questionsWTD / B.form_fillsWTD, 0) AS wtd_questions_rate,",

				"IFNULL(B.first_timeMTD / B.form_fillsMTD, 0) AS mtd_first_time_rate,",
				"IFNULL(B.rededicationsMTD / B.form_fillsMTD, 0) AS mtd_rededications_rate,",
				"IFNULL(B.questionsMTD / B.form_fillsMTD, 0) AS mtd_questions_rate,",

				"IFNULL(B.first_timeYTD / B.form_fillsYTD, 0) AS ytd_first_time_rate,",
				"IFNULL(B.rededicationsYTD / B.form_fillsYTD, 0) AS ytd_rededications_rate,",
				"IFNULL(B.questionsYTD / B.form_fillsYTD, 0) AS ytd_questions_rate,",

				"IFNULL(B.first_timeC / B.form_fillsC, 0) AS cum_first_time_rate,",
				"IFNULL(B.rededicationsC / B.form_fillsC, 0) AS cum_rededications_rate,",
				"IFNULL(B.questionsC / B.form_fillsC, 0) AS cum_questions_rate",

			"FROM websites A",
			"LEFT JOIN reports_table_td B ON (A.website_id = B.website_id)",
			"WHERE {$filter}"
		]);

		$this->td_query = implode(' ',[
			"SELECT X.*,",
				"IFNULL(X.form_fills / X.visits, 0) AS visits_form_fill_rate,",
				"IFNULL(X.wtd_form_fills / X.wtd_visits, 0) AS wtd_visits_form_fill_rate,",
				"IFNULL(X.mtd_form_fills / X.mtd_visits, 0) AS mtd_visits_form_fill_rate,",
				"IFNULL(X.ytd_form_fills / X.ytd_visits, 0) AS ytd_visits_form_fill_rate,",
				"IFNULL(X.cum_form_fills / X.cum_visits, 0) AS cum_visits_form_fill_rate,",

				"IFNULL(X.form_fills / X.prayers, 0) AS prayers_form_fill_rate,",
				"IFNULL(X.wtd_form_fills / X.wtd_prayers, 0) AS wtd_prayers_form_fill_rate,",
				"IFNULL(X.mtd_form_fills / X.mtd_prayers, 0) AS mtd_prayers_form_fill_rate,",
				"IFNULL(X.ytd_form_fills / X.ytd_prayers, 0) AS ytd_prayers_form_fill_rate,",
				"IFNULL(X.cum_form_fills / X.cum_prayers, 0) AS cum_prayers_form_fill_rate",
			"FROM ({$inner}) X",
			"ORDER BY X.name ASC"
		]);
	}


	protected function get_td_data(){
		if(!isset(self::$CI)){ self::$CI =& get_instance(); }
		$this->td_data = self::$CI->db->query($this->td_query)->result_array();
		$this->td_totals = [];
		$this->td_rates = [];

		foreach($this->td_data as $row){
			$website_id = $row['website_id'];
			$container = $this->websites_data->{$website_id};
			foreach($row as $key => $value){
				$container->$key = $value;
				if( preg_match( '/^(wtd|mtd|ytd|cum)_(.+)(?!=_rate)$/', $key ) ){
					!array_key_exists( $key, $this->td_totals )
						?	$this->td_totals[ $key ] = $value
						:	$this->td_totals[ $key ] = $this->td_totals[ $key ] + $value;
				}
			}
		}
	}

	protected function proc_specs(){
		array_splice($this->filters,0);
		foreach($this->specs as $spec => $value){
			$m = array_key_exists($spec,$this->prop_aliases)
				?	$this->prop_aliases[$spec]
				:	(method_exists($this,"set_{$spec}")
					?	"set_{$spec}"
					:	false);

			$m ? $this->$m( $value ) : null;
		}

		$this->create_dataset_container();
	}

	protected function render_where(){
		$this->where = [];

		array_walk(
			$this->filters,
			function($test,$field){
				$this->where[] = "{$field} {$test}";
			}
		);

		return !empty($this->where)
			?	"WHERE ".implode(' AND ',$this->where)
			:	'';
	}

	protected function render_data(){
		$CI =& get_instance();
		$rows = $CI->db->query(implode(' ',$this->query))->result_array();
		$this->data = empty($this->website_ids)
			?	$this->create_dataset_from_returned_websites( $rows )
			:	$this->create_dataset_from_selected_websites( $rows );
	}

	protected function normalize_datetime( $value=null ){
		return is_null($value)
			?	"DATE(CURRENT_TIMESTAMP)"
			:	(preg_match("/^20[0-9]{2}\-[0-9]{2}\-[0-9]{2}$/",$value)
				?	"'{$value}'"
				:	$value);
	}

}
