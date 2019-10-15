<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

include_once('DMSA_Workbook_Object.php');

class DMSA_Workbook extends DMSA_DIObject {

	protected $__report;
	protected $excelWorkbook;
	//protected $filename = 'workbook';

	const scope_name = 'workbook';

	// DMSA Objects
	protected $contents;

	// Workbook Pointers
	protected $sheet;
	protected $sheetIndex = -1;
	protected $row = 0;
	protected $col = 0;

	private function build_accessor(){
		$accessor = new stdClass;
		$accessor->report =& $this->__report;
		$accessor->workbook =& $this;
		$accessor->sheet =& $this->sheet;
		$accessor->sheetIndex =& $this->sheetIndex;
		$accessor->row =& $this->row;
		$accessor->col =& $this->col;
		$accessor->spreadsheets =& $this->contents;
		$accessor->excelWorkbook =& $this->excelWorkbook;

		//$accessor->grid = new GridProxy($accessor);

		return $accessor;
	}

	protected function populate_workbook(){
		//self::CrashBlob(array_keys((array)$this->contents));
		$facade = $this->build_accessor();
		foreach($this->contents as $sheetId => $sheetObj){
			$sheetObj->render_to_workbook( $facade );
		}
	}

	public function get_report(){ return $this->__report; }

	public function get_filename(){ return $this->__report->file_name; }

	public function __construct( $schema, $report, $scope=array() ){
		parent::__construct( $schema, $report, $scope );
		$this->__report = $report;
		$this->contents = new stdClass;
		$this->excelWorkbook = new PHPExcel();
		$this->sheet = $this->excelWorkbook->getActiveSheet();
	}

	public function add_spreadsheet( $sheetSchema, $subScope ){
		$sheet = new DMSA_Spreadsheet( $this, $sheetSchema, $subScope );
		$sheetId = $sheet->id;
		$this->contents->$sheetId = $sheet;
		return $sheet;
	}

	public function render_to_file(){
		$this->populate_workbook();
		$this->excelWorkbook->getProperties()->setCreator("BGEA")
			->setLastModifiedBy("BGEA")
			->setTitle("Dashboard Reporting Export")
			->setSubject("Dashboard Reporting Export")
			->setDescription("Dashboard Reporting Export.")
			->setKeywords("dashboard dmsa reporting export")
			->setCategory("Reporting Data");

		$this->excelWorkbook->setActiveSheetIndex(0);
		$filename = preg_replace('/\.xlsx?$/','',$this->filename);

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		//header("Content-Disposition: attachment;filename=\"{$filename}.xlsx\"");
		header("Content-Disposition: attachment;filename=\"{$filename}.xls\"");
		header('Cache-Control: max-age=0');
		header('Cache-Control: max-age=1');
		//$objWriter = PHPExcel_IOFactory::createWriter($this->excelWorkbook, 'Excel2007');
		$objWriter = PHPExcel_IOFactory::createWriter($this->excelWorkbook, 'Excel5');
		$objWriter->save('php://output');
		exit;
	}

	public function render_to_data(){
		$spreadsheets = array();
		foreach($this->contents as $id => $obj){
			$spreadsheets[$id] = $obj->render();
		}
		return $spreadsheets;
	}
}
