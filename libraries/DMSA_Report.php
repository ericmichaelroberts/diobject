<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once(APPPATH.'libraries/DMSA.php');

class DMSA_Report extends DMSA_DIObject {

	public $id;
	public $title;
	public $description;
	public $workbook;
	public $spreadsheets = array();
	public $endpoints;
	public $ajaxdata;
	public $before_update;
	public $internal = [];

	const scope_name = 'report';

	public function __construct( $schema ){
		parent::__construct( $schema );
		$this->id = $schema->id;
		$this->title = $schema->title;
		$this->description = $schema->description;
	}

	public function init(){
		if(property_exists($this->__schema,'ui')){
			$this->init_ui();
		}
	}

	public function transpose_dataset( $input, $totals=null, $flatten=false, $eliminate=null ){
		$rowIdx = 0;
		$output = [];
		$input_struct = (array)$input;
		$input_is_array = is_array( $input );
		$input_keys = array_keys( $input_struct );
		$input_first_key = $input_keys[0];
		$input_row_struct = $input_struct[ $input_first_key ];
		$input_row_is_array = is_array( $input_row_struct );
		$input_row_keys = array_keys( (array)$input_row_struct );

		$outputColumnKeys = $input_keys;
		$outputRowLabels = $input_row_keys;
		$eliminationFields = is_null( $eliminate )
			?	[]
			:	(is_string( $eliminate )
				?	[ $eliminate ]
				:	$eliminate );

		foreach( $outputRowLabels as $rowLabel ){
			$temp = new stdClass;
			$output[ $rowLabel ] = $temp;

			if( $flatten && is_string( $flatten ) ){
				$temp->$flatten = $rowLabel;
			}

			foreach( $outputColumnKeys as $colKey ){

				if( !in_array( $colKey, $eliminationFields, true ) ){
					$inputRow = $input_is_array
						?	$input[ $colKey ]
						:	$input->$colKey;
					$tempValue = $input_row_is_array
						?	$inputRow[ $rowLabel ]
						:	$inputRow->$rowLabel;

					$temp->$colKey = $tempValue;
				}
			}
		}

		if( !is_null( $totals ) ){
			$totals_struct = (array)$totals;
			foreach( $totals_struct as $metric => $value ){
				if( !in_array( $metric, $eliminationFields, true ) ){
					$output[ $metric ]->total = $value;
				}
			}
		}

		if( $input_row_is_array ){
			array_walk( $output, function( &$val, $key )use( $eliminationFields ){
				$val = (array)$val;
			});
		}

		return $flatten ? array_values( $output ) : $output;
	}

	protected static function ApplySerialization(&$state,$instance){
		$state['id'] = $instance->id;
		$state['title'] = $instance->title;
		$state['description'] = $instance->description;
		$state['endpoints'] = $instance->endpoints;
		$state['ajaxdata'] = $instance->ajaxdata;
		$state['before_update'] = $instance->before_update;
		$state['internal'] = $instance->internal;
		return true;
		//return parent::ApplySerialization($state,$instance);
	}

	protected static function ApplyDeserialization(&$state,$instance){
		$instance->id = $state['id'];
		$instance->title = $state['title'];
		$instance->description = $state['description'];
		$instance->endpoints = $state['endpoints'];
		$instance->ajaxdata = $state['ajaxdata'];
		$instance->before_update = $state['before_update'];
		$instance->internal = $state['internal'];
		return true;
		//return parent::ApplyDeserialization($state,$instance);
	}

	protected function get_all_request_vars(){
		return array_merge($this->__fw->input->get(null,true),$this->__fw->input->post(null,true));
	}

	public function get_request_var($var){
		$request = $this->get_all_request_vars();
		return array_key_exists($var,$request) ? $request[$var] : null;
	}

	public function get_request_vars($vars=array()){
		$request = $this->get_all_request_vars();
		return empty($vars) ? $request : array_intersect_key($request,array_flip($vars));
	}

	public function debug_report_props(){
		$accessors = $this->__accessors;
		$resolved = $this->__resolved;
		$providers = $this->__providers;
		$ajaxdata = $this->ajaxdata;
		//$methods = $this->__methods;
		//$resolved = array_keys((array)$this->__resolved);
		//$ui = $this->__ui;
		self::CrashBlob(compact('resolved','accessors','providers','ui','ajaxdata'));

	}

	public function call_ajax($endpoint,$get=array(),$post=array()){
		$spec = $this->endpoints->$endpoint;
		$params = array_merge($get,$post);
		return $this->resolve_generic_getter($spec,array_merge((array)$this->ajaxdata,$params));
	}

	protected function check_requirement( $name, $schema ){
		$context = property_exists($schema,'dependencies')
		 	?	$this->resolve_dependencies($schema->dependencies)
			:	array();
		return $this->resolve_generic_getter($schema,$context)
			?	true
			:	(property_exists($schema,'failure')
				?	$schema->failure
				:	"You do not have access requirements for this report.");
	}

	public function check_requirements(){
		$result = array();
		if(property_exists($this->__schema,'requires')){
			foreach($this->__schema->requires as $requireName => $requireSchema){
				$result[$requireName] = $this->check_requirement( $requireName, $requireSchema );
			}
		}
		return $result;
	}

	public function refresh_ui_state( $inputs=array() ){

		$options_key = json_encode($inputs);

		// $breakit = true;
		//
		// $ui = $this->__ui;
		// $resolved = $this->__resolved;
		// $accessors = $this->__accessors;
		// $providers = $this->__providers;

		foreach($this->__accessors['ui'] as $prop){
			$current = $this->$prop;
			if(array_key_exists($prop,$inputs)){
				if(serialize($current)!=serialize($inputs[$prop])){
					$this->$prop = $inputs[$prop];
				}
			}
		}

		if(isset($breakit)){
			exit(print_r(compact('ui','resolved','accessors','providers'),true));
		}

		if(!is_null($this->before_update)){
			if(!array_key_exists('confirmed_options',$this->internal)){
				$this->internal['confirmed_options'] = array();
			}

			if(!array_key_exists($options_key,$this->internal['confirmed_options'])){
				$method = $this->before_update;
				$before_result = $this->$method( $inputs );
				if($before_result===true){
					$this->internal['confirmed_options'][$options_key] = true;
				}else{
					$this->internal['confirmed_options'][$options_key] = false;
					return array(
						'confirmation' => $before_result,
						'options' => $inputs
					);
				}
			}
		}

		return array(
			'ui' => $this->__ui,
			'accessors' => $this->__accessors,
			'resolved' => $this->__resolved,
			'providers' => $this->__providers,
			'props' => $this->get_properties()
		);
	}

	public function export_workbook(){
		$this->build_workbook();
		$this->workbook->render_to_file();
	}

	private function build_workbook(){
		$spec = $this->__schema->workbook;
		$scope = property_exists($spec,'dependencies')
		 	?	$this->resolve_dependencies($spec->dependencies)
			:	array('report'=>$this);

		$this->workbook = new DMSA_Workbook( $spec, $this, $scope );
		$this->spreadsheets = array();

		foreach( $spec->spreadsheets as $sheet_index => $subSchema ){
			if($subSchema->type=='static'){
				$spreadsheet = $this->workbook->add_spreadsheet( $subSchema, array('sheet_index'=>$sheet_index,'report'=>$this) );
				$this->spreadsheets[$spreadsheet->id] = $spreadsheet;
			}else{
				$this->build_dynamic_spreadsheets( clone $subSchema );
			}
		}
	}

	private function build_dynamic_spreadsheets( $buildSchema ){
		$schemas = array();
		$iterator = $buildSchema->iterator;
		$iteratorProp = $iterator->each;
		$indexProp = property_exists($iterator,'index') ? $iterator->index : 'current_index';
		$valueProp = property_exists($iterator,'value') ? $iterator->value : 'current_row';
		foreach($this->$iteratorProp as $iterator_index => $iterator_value){

			$spreadsheet = $this->workbook->add_spreadsheet( $iterator->schema, array(
				'report' => $this,
				'sheet_index' => $iterator_index + sizeof($this->spreadsheets),
				$indexProp => $iterator_index,
				$valueProp => $iterator_value
			));
			$this->spreadsheets[$spreadsheet->id] = $spreadsheet;
		}
	}

	public function render_workbook(){
		$this->build_workbook();
		$spreadsheets = $this->workbook->render_to_data();
		return compact('spreadsheets');
	}

	protected function init_ajax(){
		$this->endpoints = $this->__schema->ajax;
		$this->ajaxdata = new stdClass;

		foreach($this->endpoints as $endpoint => $spec){
			if(!is_object($spec)){
				$endpoints = $this->endpoints;
				self::CrashBlob(compact('spec','endpoint','endpoints'));
			}
			if(property_exists($spec,'dependencies')){
				foreach($spec->dependencies as $dependency){
					if(!property_exists($this->ajaxdata,$dependency)){
						$this->ajaxdata->$dependency = $this->$dependency;
					}
				}
			}
		}
	}

	protected function init_ui(){
		$this->before_update = property_exists($this->__schema->ui,'before_update')
			?	$this->__schema->ui->before_update
			:	null;

		if(property_exists($this->__schema->ui,'flavor')){
			$this->__ui->flavor = $this->__schema->ui->flavor;
		}

		if(property_exists($this->__schema->ui,'dependencies')){
			$data = array();
			foreach( $this->__schema->ui->dependencies as $item ){
				$data[$item] = $this->$item;
			}
			$this->__ui->data = (object)$data;
		}

		if(property_exists($this->__schema->ui,'components')){
			$components = new stdClass;
			foreach($this->__schema->ui->components as $name => $spec){
				$method = "init_{$spec->type}_component";
				$this->$method( $name, $spec, $components );
			}
			$this->__ui->components = $components;
		}

		if(property_exists($this->__schema->ui,'layout')){
			$this->__ui->layout = $this->__schema->ui->layout;
		}

		if(property_exists($this->__schema->ui,'functions')){
			$this->__ui->functions = $this->__schema->ui->functions;
		}
	}

	protected function init_lookahead_component( $component, $spec, $ui ){
		$schema = new stdClass;
		$schema->label = property_exists($spec,'label') ? $spec->label : null;
		$schema->type = 'dropdown';
		$schema->parameter = $spec->parameter;
		$schema->placeholder = property_exists($spec,'placeholder') ? $spec->placeholder : true;
		$schema->validate = property_exists($spec,'validate') ? $spec->validate : true;
		$schema->multiple = property_exists($spec,'multiple') ? $spec->multiple : false;
		$schema->select2 = property_exists($spec,'select2') ? $spec->select2 : null;
		$schema->value = property_exists($spec,'value')
			? 	$this->resolve_generic_getter( $spec->value, (array)$this->__ui->data )
			: 	(property_exists($spec,'default')
				?	$this->resolve_generic_getter( $spec->default, (array)$this->__ui->data )
				:	(!!$spec->multiple ? [] : null));

		if(property_exists($spec,'classes')){
			$schema->classes = $spec->classes;
		}

		if(property_exists($spec,'onChange')){
			$schema->onChange = $spec->onChange;
		}

		$schema->options = $this->resolve_recursive_struct(
			$spec->options,
			property_exists($spec,'dependencies')
				? $spec->dependencies
				: array());

		return $ui->$component = $schema;
	}

	protected function init_dropdown_component( $component, $spec, $ui ){
		$schema = new stdClass;
		$schema->label = property_exists($spec,'label') ? $spec->label : null;
		$schema->type = 'dropdown';
		$schema->parameter = $spec->parameter;
		$schema->placeholder = property_exists($spec,'placeholder') ? $spec->placeholder : true;
		$schema->validate = property_exists($spec,'validate') ? $spec->validate : true;
		$schema->multiple = property_exists($spec,'multiple') ? $spec->multiple : false;
		$schema->select2 = property_exists($spec,'select2') ? $spec->select2 : null;
		$schema->value = $this->{$spec->parameter};

		$options = $spec->options;

		$vkey = property_exists($options,'value_key') ? $options->value_key : null;
		$lkey = property_exists($options,'label_key') ? $options->label_key : null;
		$chex = property_exists($options,'preselect') ? $options->preselect : null;

		$source = is_string($options->source)
			?	$this->{$options->source}
			:	$options->source;

		$schema->options = $this->resolve_options($source,$vkey,$lkey,$chex);

		foreach(["classes","onBlur","onFocus","onChange"] as $prop){
			if( property_exists($spec,$prop) ){
				$schema->$prop = $spec->$prop;
			}
		}

		return $ui->$component = $schema;
	}

	protected function init_text_component( $component, $spec, $ui ){
		$schema = new stdClass;
		$schema->label = property_exists($spec,'label')
			? 	$spec->label
				: null;
		$schema->value = property_exists($spec,'value')
			? 	$this->resolve_generic_getter( $spec->value, (array)$this->__ui->data )
			: 	null;
		$schema->type = 'text';
		$schema->parameter = $spec->parameter;
		$schema->validate = property_exists($spec,'validate') ? $spec->validate : true;

		// if(property_exists($spec,'onChange')){
		// 	$schema->onChange = $spec->onChange;
		// }
		foreach(["classes","onBlur","onFocus","onChange"] as $prop){
			if( property_exists($spec,$prop) ){
				$schema->$prop = $spec->$prop;
			}
		}

		if(property_exists($spec,'options')){
			$schema->options = $spec->options;
		}

		return $ui->$component = $schema;
	}

	protected function init_datepicker_component( $component, $spec, $ui ){
		$schema = new stdClass;
		$schema->label = property_exists($spec,'label')
			? 	$spec->label
				: null;
		$schema->value = property_exists($spec,'value')
			?	$this->resolve_generic_getter( $spec->value, (array)$this->__ui->data)
			:	null;
		$schema->type = 'datepicker';
		$schema->parameter = $spec->parameter;

		foreach(["classes","onBlur","onFocus","onChange"] as $prop){
			if( property_exists($spec,$prop) ){
				$schema->$prop = $spec->$prop;
			}
		}

		if(property_exists($spec,'options')){
			$schema->options = $this->resolve_recursive_struct($spec->options,(array)$this->__ui->data);
		}

		return $ui->$component = $schema;
	}

	protected function init_daterange_component( $component, $spec, $ui ){
		$schema = new stdClass;
		$schema->label = property_exists($spec,'label')
			?	$spec->label
			:	null;
		$schema->type = 'daterange';

		if(property_exists($spec,'options')){
			$schema->options = $this->resolve_recursive_struct($spec->options,(array)$this->__ui->data);
		}

		foreach(["classes","onBlur","onFocus","onChange"] as $prop){
			if( property_exists($spec,$prop) ){
				$schema->$prop = $spec->$prop;
			}
		}

		$schema->start_date = $this->resolve_recursive_struct($spec->start_date,(array)$this->__ui->data);
		$schema->end_date = $this->resolve_recursive_struct($spec->end_date,(array)$this->__ui->data);

		return $ui->$component = $schema;
	}

	protected function init_toggle_component( $component, $spec, $ui ){
		$schema = new stdClass;
		$schema->label = property_exists($spec,'label')
			? 	$spec->label
				: null;
		$schema->value = property_exists($spec,'value')
			? 	$this->resolve_generic_getter( $spec->value, $this->__scope )
			: 	null;
		$schema->type = 'toggle';
		$schema->parameter = $spec->parameter;
		//$schema->validate = property_exists($spec,'validate') ? $spec->validate : true;

		// if(property_exists($spec,'options')){
		// 	$schema->options = $spec->options;
		// }
		foreach(["classes","onBlur","onFocus","onChange"] as $prop){
			if( property_exists($spec,$prop) ){
				$schema->$prop = $spec->$prop;
			}
		}

		return $ui->$component = $schema;
	}

	protected function init_checkboxes_component( $component, $spec, $ui ){
		$schema = new stdClass;
		$schema->label = property_exists($spec,'label') ? $spec->label : null;
		$schema->type = 'checkboxes';
		$schema->parameter = $spec->parameter;
		$schema->validate = property_exists($spec,'validate') ? $spec->validate : true;

		if( property_exists($spec,'style') ){ $schema->style = $spec->style; }

		$options = $spec->options;

		$vkey = property_exists($options,'value_key') ? $options->value_key : null;
		$lkey = property_exists($options,'label_key') ? $options->label_key : null;
		$chex = property_exists($options,'preselect') ? $options->preselect : null;

		$source = is_string($options->source)
			?	$this->{$options->source}
			:	$options->source;

		$schema->options = $this->resolve_options($source,$vkey,$lkey,$chex);

		foreach(["toolbar","classes","onBlur","onFocus","onChange"] as $prop){
			if( property_exists($spec,$prop) ){
				$schema->$prop = $spec->$prop;
			}
		}

		return $ui->$component = $schema;
	}

	protected function init_radio_component( $component, $spec, $ui ){
		$schema = new stdClass;
		$schema->label = property_exists($spec,'label') ? $spec->label : null;
		$schema->type = 'radio';
		$schema->parameter = $spec->parameter;
		$schema->validate = property_exists($spec,'validate') ? $spec->validate : true;

		if( property_exists($spec,'style') ){ $schema->style = $spec->style; }

		$options = $spec->options;

		$vkey = property_exists($options,'value_key') ? $options->value_key : null;
		$lkey = property_exists($options,'label_key') ? $options->label_key : null;
		$chex = property_exists($options,'preselect') ? $options->preselect : null;

		//

		$source = is_string($options->source)
			?	$this->{$options->source}
			:	$options->source;

		//exit( print_r( compact('component','spec','ui','vkey','lkey','chex','options','source'), 1 ) );

		$schema->options = $this->resolve_options($source,$vkey,$lkey,null);

		foreach(["toolbar","classes","onBlur","onFocus","onChange"] as $prop){
			if( property_exists($spec,$prop) ){
				$schema->$prop = $spec->$prop;
			}
		}

		return $ui->$component = $schema;
	}

	public function get_client_init_data(){
		return $this->__ui;
	}

	private function resolve_iterator_datasource( $rows, $context=array()){
		$output = array();
		foreach( $rows as $row_idx => $row_data ){
			$row = array();
			foreach($row_data as $col_idx => $col){
				$row[$col_idx] = $this->resolve_generic_getter( $col, $context );
			}
			$output[] = $row;
		}
		return $output;
	}

	private function resolve_date_value($input){
		if(is_object($input) && property_exists($input,'source')){
			$source = $input->source;
			return $this->$source;
		}else{
			return $input;
		}
	}

	//private function resolve_options($input,$value_key=null,$label_key=null,$selected=array()){
	private function resolve_options($input,$value_key=null,$label_key=null,$selected=null){
		$output = array();
		if(is_string($input)){
			return $this->resolve_options($this->$input,$value_key,$label_key,$selected);
		}else{
			$inputIsArray = is_array($input);

			foreach($input as $i => $e){
				$flat_option = is_scalar($e)
					?	array($e,$e)
					:	(array)$e;
				if(!isset($v)){

					$keys = array_keys($flat_option);

					$v = is_null($value_key) || !in_array($value_key,$keys)
						?	$keys[0]
						:	$value_key;

					$k = is_null($label_key) || !in_array($label_key,$keys)
						?	$keys[0]
						:	$label_key;
				}
				$temp = array('value'=>$flat_option[$v],'label'=>$flat_option[$k]);
				$temp['selected'] = is_bool($selected)
					?	$selected
					:	(is_null( $selected )
						?	$e->selected
						:	(is_array($selected)
							?	in_array($temp['value'],$selected)
							:	$temp['value']==$selected
						)
					);
				// $temp['selected'] = is_bool($selected)
				// 	?	$selected
				// 	:	(is_array($selected)
				// 		?	in_array($temp['value'],$selected)
				// 		:	$temp['value']==$selected);

				$output[] = (object)$temp;
			}

			return $output;
		}
	}

	public function create_temporary_table($name,$query,$primaryKey=null){
		$dropStatement = "DROP TEMPORARY TABLE IF EXISTS {$name}";
		$createStatement[] = "CREATE TEMPORARY TABLE IF NOT EXISTS {$name}";
		if(!is_null($primaryKey)){
			$createStatement[] = "(PRIMARY KEY({$primaryKey}))";
		}
		$createStatement[] = "AS ({$query})";
		$this->__fw->db->query( $dropStatement );
		$this->__fw->db->query( implode(' ',$createStatement) );
	}

}
