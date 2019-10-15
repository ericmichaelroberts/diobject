<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class DMSA_Rowgrouped_Table extends DMSA_Basic_Table {

	public $rowGroupings;
	public $rowGroupingChecks = array();
	public $rowGroupingKeys = array();
	public $rowGroupStartOffsets = array();
	public $rowGroupEndOffsets = array();
	public $rowSpans = array();
	public $rowSpanCells = array();
	public $type = 'rowgrouped';

	public function __construct( $schema, $spreadsheet, $scope=array() ){
		if(property_exists($schema,'groups')){
			$this->rowGroupings = $this->resolve_generic_getter($schema->groups,$scope);
			$this->afterBuildHead[] = array($this,'build_row_groupings');
			$this->afterBuildBody[] = array($this,'build_row_spans');
			$this->beforeBuildRowStyle[] = array($this,'apply_row_attributes');
			$this->afterBuildCellStyle[] = array($this,'apply_row_spans');
			$this->afterRender[] = array($this,'apply_row_merging');
		}
		parent::__construct( $schema, $spreadsheet, $scope );
	}

	public function apply_row_attributes( $facade ){
		extract((array)$facade,EXTR_REFS);
		$startToken = null;
		$endToken = null;
		foreach($this->rowGroupingChecks as $level => $column_id){
			$tokenBase = "lvl_{$level}";
			if(array_key_exists($src_row_idx,$this->columnIndex[$column_id]->row_spans)){
				$startToken = "{$tokenBase}_start";
			}
			if(array_key_exists($src_row_idx+1,$this->columnIndex[$column_id]->row_spans)){
				$endToken = "{$tokenBase}_end";
			}
			if($startToken || $endToken){
				$tokens = implode(' ',array($startToken,$endToken));
				$rowAtts = "{$rowAtts} {$tokens}";
				return true;
			}
		}
	}

	// public function apply_row_cell_attributes( &$rowAtts, $cellSchema ){
	// 	if(self::AllPropertiesExist($cellSchema,array('column_id','src_row_idx'))){
	// 		$column_id = $cellSchema->column_id;
	// 		$src_row_idx = $cellSchema->src_row_idx;
	// 		if(array_key_exists($column_id,$this->rowGroupingKeys)){
	// 			$level = $this->rowGroupingKeys[$column_id];
	// 			$tokenBase = "lvl_{$level}";
	// 			if(array_key_exists($src_row_idx,$this->columnIndex[$column_id]->row_spans)){
	// 				$rowAtts = "{$rowAtts} {$tokenBase}_start";
	// 				$cellSchema->row_atts = $cellSchema->row_atts." {$tokenBase}_start";
	// 			}
	// 			if(array_key_exists($src_row_idx+1,$this->columnIndex[$column_id]->row_spans)){
	// 				$rowAtts = "{$rowAtts} {$tokenBase}_end";
	// 				$cellSchema->row_atts = $cellSchema->row_atts." {$tokenBase}_end";
	// 			}
	// 		}
	// 	}
	// }

	public function apply_row_spans( $cellSchema ){
		if(self::AllPropertiesExist($cellSchema,array('region','role','src_row_idx','column_id'))){
			if($cellSchema->region=='body' && $cellSchema->role=='data'){
				if(property_exists($this->columnIndex[$cellSchema->column_id],'row_spans')){
					if(array_key_exists($cellSchema->src_row_idx,$this->columnIndex[$cellSchema->column_id]->row_spans)){
						if($this->columnIndex[$cellSchema->column_id]->row_spans[$cellSchema->src_row_idx] > 1){
							$cellSchema->rowspan = $this->columnIndex[$cellSchema->column_id]->row_spans[$cellSchema->src_row_idx];
							$this->rowSpanCells[]=$cellSchema;
						}
					}
				}
			}
		}
	}

	public function apply_row_merging( $facade, $dataGrid ){
		extract((array)$facade,EXTR_REFS);
		foreach($this->rowSpanCells as $cellObj){
			$numRows = $cellObj->rowspan;
			$startCell = $cellObj->cellAddress;
			list($startCol,$startRow) = self::CellToParts($startCell);
			$endRow = (int)$startRow+($numRows-1);
			$endCell = "{$startCol}{$endRow}";
			$mergeCells = "{$startCell}:{$endCell}";
			$sheet->mergeCells("{$startCell}:{$endCell}");
		}
	}

	public function build_row_spans(){
		$prevLevel = null;
		foreach($this->rowGroupings as $toGroup){
			if(is_array($toGroup)){
				$temp = $this->populate_row_spans(array_shift($toGroup),$prevLevel);
				foreach($toGroup as $subColIdx => $columnId){
					$this->columnIndex[$columnId]->row_spans = $temp;
				}
			}else{
				$temp = $this->populate_row_spans($toGroup,$prevLevel);
			}

			$prevLevel = $temp;
		}
	}

	public function populate_row_spans($columnId,$parentGroups=null){
		$column = $this->columnIndex[$columnId];
		$innerSpans = array();
		$row_values = $column->row_values;
		$leaderRows = array();
		$outerGroups = is_null($parentGroups)
			?	array(sizeof($row_values))
			:	$parentGroups;
		$firstOuterIndex = 0;
		$outerIndex = 0;
		$lastOuterIndex = sizeof($row_values);
		foreach($outerGroups as $outerIndex => $outerSize){
			$currentLeaderRow = $outerIndex;
			for( $innerIndex=0; $innerIndex < $outerSize; $innerIndex++ ){
				$currentValue = $row_values[$outerIndex];
				$innerSpans[] = 0;
				if($outerIndex==0 || $innerIndex==0 || $currentValue!=$previousValue){
					$currentLeaderRow = $outerIndex;
				}
				$outerIndex++;
				$previousValue = $currentValue;
				$innerSpans[$currentLeaderRow]++;
			}
		}

		$result = array_filter($innerSpans);
		$column->row_spans = $result;
		return $result;
	}

	public function build_row_groupings(){
		$cols = array_keys($this->rowGroupings);//array_slice(array_keys($this->rowGroupings),0,-1);
		$this->rowGroupings = array();
		foreach($cols as $columnName){
			if(array_key_exists($columnName,$this->columnIndex)){
				if(property_exists($this->columnIndex[$columnName],'subcolumns')){
					$temp = array();
					foreach($this->columnIndex[$columnName]->subcolumns as $subcolumn){
						$id = $subcolumn->id;
						if(!in_array($id,$this->rowGroupings)){
							$temp[] = $id;
							//$this->rowGroupings[] = $id;
						}
					}
					$this->rowGroupings[] = $temp;
				}elseif(!in_array($this->columnIndex[$columnName]->id,$this->rowGroupings)){
					$this->rowGroupings[] = $this->columnIndex[$columnName]->id;
				}
			}
		}

		foreach($this->rowGroupings as $level => $coldata){
			$is_array = is_array($coldata);
			$this->rowGroupingChecks[]=$is_array ? $coldata[0] : $coldata;
			if($is_array){
				foreach($coldata as $subcol){
					$this->rowGroupingKeys[$subcol]=$level;
				}
			}else{
				$this->rowGroupingKeys[$coldata]=$level;
			}
		}
	}

}
