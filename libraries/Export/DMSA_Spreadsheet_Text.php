<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class DMSA_Spreadsheet_Text extends DMSA_Spreadsheet_Content {

	protected $__spreadsheet;
	protected $__schema;

	public $content;

	public function __construct($schema,$spreadsheet){
		$this->__spreadsheet = $spreadsheet;
		$this->__schema = $schema;
		$this->content = $schema->content;
	}

	public function get_object_type(){ return 'text'; }

	public function render_to_spreadsheet( $facade, $rowOffset=0, $colOffset=0 ){
		extract((array)$facade,EXTR_REFS);
		$contentIndex = $this->get_index();
		$rowIdx = $contentIndex > 0 ? $rowOffset + 2 : $row;
		$reference_object = $this->__spreadsheet->get_content_at_index( $contentIndex > 0 ? $contentIndex-1 :$contentIndex+1 );
		$total_columns = $reference_object->get_total_columns();

		$cell = self::CoordinatesToCell($rowIdx,$col);
		$sheet->setCellValue($cell,$this->content);

		if($total_columns > 1){
			$secondCell = self::CoordinatesToCell(
				$rowIdx,
				$col + ($total_columns - 1)
			);
			$sheet->mergeCells("{$cell}:{$secondCell}");
		}
	}
}
