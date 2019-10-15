<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class DMSA_Spreadsheet extends DMSA_DIObject {

	private $__report;
	private $__workbook;
	private $__contents = array();

	public $column_widths = array();
	public $type='static';
	public $title;
	public $id;

	const scope_name = 'spreadsheet';

	private static function ConvertBase($numberInput,$fromBaseInput,$toBaseInput){
		if($fromBaseInput==$toBaseInput){
			return $numberInput;
		}else{
			$fromBase = str_split($fromBaseInput,1);
			$toBase = str_split($toBaseInput,1);
			$number = str_split($numberInput,1);
			$fromLen=strlen($fromBaseInput);
			$toLen=strlen($toBaseInput);
			$numberLen=strlen($numberInput);
			$retval='';

			if ($toBaseInput == '0123456789'){
				$retval=0;
				for($i=1;$i<= $numberLen;$i++){
					$retval = bcadd($retval, bcmul(array_search($number[$i-1], $fromBase),bcpow($fromLen,$numberLen-$i)));
				}
				return $retval;
			}

			if($fromBaseInput != '0123456789'){
				$base10=convBase($numberInput, $fromBaseInput, '0123456789');
			}else{
				$base10 = $numberInput;
			}

			if($base10<strlen($toBaseInput)){
				return $toBase[$base10];
			}

			while($base10 != '0'){
				$retval = $toBase[bcmod($base10,$toLen)].$retval;
				$base10 = bcdiv($base10,$toLen,0);
			}
			return $retval;
		}
	}

	public static function CoordinatesToCell($x,$y){
		$alpha = self::ConvertBase($y,'0123456789','ABCDEFGHIJKLMNOPQRSTUVWXYZ');
		$x++;
		return "{$alpha}{$x}";
	}

	public static function CellToCoordinates($cell){
		list($alpha,$y) = preg_split('/(?<=[A-Z])(?=[0-9])/i',$cell,2);
		$x = self::ConvertBase($alpha,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','0123456789');
		$y--;
		return compact('x','y');
	}

	public static function CellToColumn($cell){
		list($alpha,$y) = preg_split('/(?<=[A-Z])(?=[0-9])/i',$cell,2);
		return $alpha;
	}

	public static function CellsToColumns($cells){
		$inputs = explode(':',$cells);
		$outputs = array();
		foreach($inputs as $input){
			$outputs[] = self::CellToColumn($input);
		}
		return $outputs;
	}


	protected function populate_workbook( $facade ){
		$facade->sheetIndex++;
		if($facade->sheetIndex > 0)
			$facade->excelWorkbook->createSheet();

		$facade->spreadsheet =& $this;
		$facade->excelWorkbook->setActiveSheetIndex($facade->sheetIndex);
		$facade->sheet = $facade->excelWorkbook->getActiveSheet();
		$facade->row = 1;

		foreach($this->__contents as $contentIndex => $contentItem){
			$facade->col = 1;
			$contentItem->render_to_spreadsheet( $facade, $facade->row, $facade->col );
			$facade->row++;
		}

		foreach($this->column_widths as $col){
			$facade->sheet->getColumnDimension($col)->setAutoSize(true);
		}

		$facade->sheet->setTitle($this->title);
	}

	public function render_to_workbook( $facade ){
		$this->populate_workbook( $facade );
		$facade->sheet->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
		$facade->sheet->getPageSetup()->setPaperSize(PHPExcel_Worksheet_PageSetup::PAPERSIZE_A4);
		$facade->sheet->getPageSetup()->setFitToPage(true);
		$facade->sheet->getSheetView()->setZoomScale(125);
	}

	public function __construct( $workbook, $schema, $scope=array() ){
		parent::__construct( $schema, $workbook, $scope );
		$report = $workbook->report;
		$this->__report = $report;
		$this->title = property_exists($schema,'title')
			? 	$this->resolve_generic_getter($schema->title,$this->__scope)
			: 	null;
		$this->id = property_exists($schema,'id')
			? 	$this->resolve_generic_getter($schema->id,$this->__scope)
			: 	"sheet_".sizeof($report->spreadsheets);
		$this->initialize_contents();
	}


	public function get_schema(){ return $this->__schema; }

	public function get_report(){ return $this->__report; }

	public function get_contents(){ return $this->__contents; }

	public function get_content_at_index($index){
		return $this->__contents[$index];
	}

	public function get_object_index( $object ){
		if(!in_array($object,$this->__contents)){
			$contents = $this->__contents;
			self::CrashBlob(compact('contents'));
		}
		return in_array($object,$this->__contents)
			?	array_search($object,$this->__contents)
			:	false;
	}

	public function get_object_datasource( $object ){
		$index = $this->get_object_index( $object );
		$schema = $this->__schema->contents[$index];
		$dataprop = $schema->datasource;
		return is_array($schema->datasource)
			?	$schema->datasource
			:	$this->$dataprop;
	}

	protected function generate_iterative_contents($iterator){
		$iteratorProp = $iterator->each;
		$indexProp = property_exists($iterator,'index') ? $iterator->index : 'current_index';
		$valueProp = property_exists($iterator,'value') ? $iterator->value : 'current_row';
		foreach($this->$iteratorProp as $iterator_index => $iterator_value){
			$subSchema = clone $iterator->schema;
			if(!property_exists($subSchema,'properties')){
				$subSchema->properties = new stdClass;
			}

			$subSchema->properties->$indexProp = $iterator_index;
			$subSchema->properties->$valueProp = $iterator_value;
			$subScope = array(
				'content_index' => sizeof($this->__contents),
				$indexProp => $iterator_index,
				$valueProp => $iterator_value
			);

			$this->initialize_content_object(
				$subSchema->object,
				$subSchema,
				$subScope
			);
		}
	}

	protected function initialize_content_object( $obj, $schema, $downScope=array() ){
		$generator_method = "generate_{$obj}_object";
		return $this->$generator_method( $schema, $downScope );
	}

	protected function initialize_contents( ){
		foreach($this->schema->contents as $index => $schema){
			property_exists($schema,'object')
				?	$this->initialize_content_object($schema->object,$schema)
				:	(property_exists($schema,'iterator')
					?	$this->generate_iterative_contents($schema->iterator)
					:	null
				);
		}
	}

	protected function generate_table_object( $schema, $scope=array() ){
		$tableType = property_exists($schema,'type')
			?	$schema->type
			:	'basic';
		$upScope = $this->get_scope($scope);
		switch( $tableType )
		{
			case 'breakdown': return $this->__contents[] = new DMSA_Breakdown_Table( $schema, $this, $upScope );
			case 'rowgrouped': return $this->__contents[] = new DMSA_Rowgrouped_Table( $schema, $this, $upScope );
			default: return $this->__contents[] = new DMSA_Basic_Table( $schema, $this, $upScope );
		}
	}

	protected function generate_text_object( $schema, $scope=array() ){
		$upScope = $this->get_scope($scope);
		return $this->__contents[] = new DMSA_Spreadsheet_Text( $schema, $this, $upScope );
	}

	public function get_outer_scope(){
		return array_merge($this->__scope,array('spreadsheet' => $this,'report'=>$this->__report));
	}

	public function render_contents(){
		$output = array();
		foreach(array_filter($this->__contents) as $index => $obj){
			$tmp = new stdClass;
			$tmp->type = is_object($obj)
				? 	$obj->object_type
				: 	(is_string($obj)
					?	'text'
					:	null);
			$tmp->schema = $obj;
			$output[] = $tmp;
		}
		return $output;
	}

	public function render(){
		$output = array(
			'id' 	=> $this->id,
			'title' => $this->title,
			'contents' => $this->render_contents(),
			'properties' => $this->get_properties()
		);

		if(property_exists($this->__schema,'buttons')){
			$output['buttons'] = $this->get_buttons();
		}

		return $output;
	}

	protected function get_buttons(){
		$output = array();

		foreach($this->__schema->buttons as $btn_name => $spec){
			$output[$btn_name] = $this->build_button( $spec );
		}
		return $output;
	}

	protected function build_button( $button_spec ){
		return is_object( $button_spec )
			?	$this->build_di_object( $button_spec )
			:	(is_array( $button_spec )
				?	$this->build_di_array( $button_spec )
				:	$this->resolve_di_string( $button_spec, $this->__scope ));
	}

	private function build_di_object( $spec ){
		//self::CrashBlob(array_keys($this->get_scope()));
		$output = new stdClass;
		foreach($spec as $key => $value){
			//$newKey = $this->resolve_di_string( $key, $this->__scope  );
			$newKey = $this->resolve_di_string( $key, $this->get_scope()  );
			$contents = is_object( $value )
				?	$this->build_di_object( $value )
				:	(is_array($value)
					?	$this->build_di_array($value)
					//:	$this->resolve_di_string($value, $this->__scope ));
					:	$this->resolve_di_string($value, $this->get_scope() ));
			$output->{$newKey} = $contents;
		}
		return $output;
	}

	private function build_di_array( $spec ){
		$output = array();
		foreach($spec as $idx => $value){
			$output[] = is_object( $value )
				?	$this->build_di_object( $value )
				:	(is_array( $value )
					?	$this->build_di_array( $value )
					//:	$this->resolve_di_string( $value, $this->__scope  ));
					:	$this->resolve_di_string( $value, $this->get_scope()  ));
		}
		return $output;
	}

	public function register_autowidths( $columns=array() ){
		$this->column_widths = array_unique(array_merge($this->column_widths,$columns));
	}
}
