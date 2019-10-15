<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class DMSA_Basic_Table extends DMSA_Spreadsheet_Content {

	public $thead;
	public $tbody;
	public $tfoot;
	public $title;
	public $row_heights = array();
	public $has_footer_row = false;
	public $row_map = array();
	public $columnOrder = array();
	public $columnIndex;
	public $type = 'basic';

	public $styles;
	public $widths;
	public $heights;

	public $topLeft;
	public $bottomRight;

	protected $beforeBuildHead = array();
	protected $afterBuildHead = array();
	protected $beforeBuildBody = array();
	protected $afterBuildBody = array();
	protected $beforeBuildRowStyle = array(); // fn($facade){}
	protected $beforeBuildCellStyle = array(); // fn($cellSchema){}
	protected $afterBuildCellStyle = array(); // fn($cellSchema){}
	protected $beforeRender = array(); // fn( $facade, $rowOffset=0, $colOffset=0 ){}
	protected $afterRender = array(); // fn( $facade, $dataGrid ){}

	const scope_name = 'table';

	public function __construct( $schema, $spreadsheet, $scope=array() ){
		parent::__construct( $schema, $spreadsheet, $scope );

		$this->__spreadsheet = $spreadsheet;

		if( property_exists( $schema->columns, 'accessor' ) ){
			$temp = $this->resolve_generic_getter( $schema->columns, $scope );
			$schema->columns = $temp;
		}

		$this->unpack_postprocessing_specs($schema);

		$this->build_column_order( $schema, $scope );

		if(!empty($this->beforeBuildHead)){
			foreach($this->beforeBuildHead as $callable){
				call_user_func($callable);
			}
		}

		$this->build_table_head( $schema );

		if(!empty($this->afterBuildHead)){
			foreach($this->afterBuildHead as $callable){
				call_user_func($callable);
			}
		}

		$this->build_table_row_data( $schema );

		if(!empty($this->beforeBuildBody)){
			foreach($this->beforeBuildBody as $callable){
				call_user_func($callable);
			}
		}

		$this->build_table_body( $schema );

		if(!empty($this->afterBuildBody)){
			foreach($this->afterBuildBody as $callable){
				call_user_func($callable);
			}
		}

		if( $this->has_footer_row ){
			$this->build_table_footer( $schema );
		}
	}

	protected function unpack_postprocessing_specs( $spec ){
		extract(array_fill_keys(array('styleSets','widthSets','heightSets'),array()));
		if(property_exists($spec,'styles')){
			foreach($spec->styles as $selector => $set){
				if(property_exists($set,'width')){ $widthSets[$selector]=array('width'=>$set->width); }
				if(property_exists($set,'height')){ $heightSets[$selector]=array('height'=>$set->height); }
				$styles = array_diff_key((array)$set,array_flip(array('width','height')));
				if(!empty($styles)){ $styleSets[$selector]=(object)$styles; }
			}
		}

		// exit(print_r([
		// 	'spec'			=>	$spec,
		// 	'styles'		=>	$styles,
		// 	'styleSets'		=>	$styleSets,
		// 	'this->styles'	=>	$this->styles
		// ],true));

		$this->styles = !empty($styleSets) ? $this->unpack_styles((object)$styleSets) :	array();
		$this->widths = !empty($widthSets) ? $this->unpack_styles((object)$widthSets) :	array();
		$this->heights = !empty($heightSets) ? $this->unpack_styles((object)$heightSets) :	array();

		//exit(print_r($this->styles,true));
	}

	public function add_to_collection( $collectionName, $cell ){
		if(!property_exists($this->__cells,$collectionName)){
			$template = array_fill_keys(array('topLeft','bottomRight'),$cell);
			$this->__cells->$collectionName = (object)$template;
		}elseif(is_null($this->__cells->$collectionName->topLeft)){
			$this->__cells->$collectionName->topLeft = $cell;
		}

		return $this->__cells->$collectionName->bottomRight = $cell;
	}

	public function build_column_order( $schema, $scope=array() ){
		// if( property_exists($schema,'column_order') ){
		// 	return $this->columnOrder = $this->resolve_generic_getter($schema->column_order,$scope);
		// }else{
		// 	$columns = $this->resolve_generic_getter( $schema->columns, $scope );
		// 	return array_keys((array)$columns);
		// }
		return $this->columnOrder = property_exists($schema,'column_order')
			?	$this->resolve_generic_getter($schema->column_order,$scope)
			:	array_keys((array)$schema->columns);
	}

	public function build_cell_style( $cellSchema ){
		$styles = (array)$this->styles;
		$style_keys = array_keys($styles);
		$style_vals = array_values($styles);
		$arraySchema = (array)$cellSchema;
		$filteredSchema = array_diff_key($arraySchema,array_flip(array('value','colspan','src_row_idx','grid_row_idx','collection')));
		$cell_keys = explode(' ',trim(implode(' ',array_values(array_filter($filteredSchema)))));
		$applied = array();

		foreach($style_keys as $style_idx => $concatKey){
			$selector_keys = explode(' ',trim($concatKey));
			$matches = array_intersect($selector_keys,$cell_keys);
			if(sizeof($matches)==sizeof($selector_keys)){
				$applied = array_replace_recursive($applied,(array)$style_vals[$style_idx]);
			}
		}

		$cellSchema->styles = $applied;

		if(!empty($this->afterBuildCellStyle)){
			foreach($this->afterBuildCellStyle as $callable){
				call_user_func_array($callable,array($cellSchema));
			}
		}
	}

	public function build_table_style(){
		$styles = (array)$this->styles;
		$style_keys = array_keys($styles);
		$style_vals = array_values($styles);

		$applied = array();

		foreach($style_keys as $style_idx => $concatKey){
			$selector_keys = explode(' ',trim($concatKey));
			$matches = array_intersect($selector_keys,array('table'));
			if(sizeof($matches)==sizeof($selector_keys)){
				$applied = array_replace_recursive($applied,(array)$style_vals[$style_idx]);
			}
		}

		$this->tableStyles = $applied;
	}

	protected function build_collection_styles( $collections ){
		$styles = (array)$this->styles;
		$style_keys = array_keys($styles);
		$style_vals = array_values($styles);

		$output = array_fill_keys($collections,array());

		foreach($style_keys as $style_idx => $concatKey){
			$selector_keys = explode(' ',trim($concatKey));
			foreach($collections as $collectionName){
				$matches = array_intersect($selector_keys,array( $collectionName ));
				if(sizeof($matches)==sizeof($selector_keys)){
					$output[$collectionName] = array_replace_recursive(
						$output[$collectionName],
						(array)$style_vals[$style_idx]
					);
				}
			}
		}

		return $output;
	}

	protected function build_collection_dimensions( $collections ){
		$widths = (array)$this->widths;
		$heights = (array)$this->heights;
		$styles = array_merge_recursive($widths,$heights);
		$style_keys = array_keys($styles);
		$style_vals = array_values($styles);

		$output = array_fill_keys($collections,array());

		foreach($style_keys as $style_idx => $concatKey){
			$selector_keys = explode(' ',trim($concatKey));
			foreach($collections as $collectionName){
				$matches = array_intersect($selector_keys,array( $collectionName ));
				if(sizeof($matches)==sizeof($selector_keys)){
					$output[$collectionName] = array_replace_recursive(
						$output[$collectionName],
						(array)$style_vals[$style_idx]
					);
				}
			}
		}

		return $output;
	}

	public function apply_content_styles( $facade ){
		$collections = array_merge(array('content'),array_keys((array)$this->__cells));
		$collectionStyles = $this->build_collection_styles( $collections );
		$collectionDimensions = $this->build_collection_dimensions( $collections );

		foreach($collectionStyles as $collection => $collectionStyle){
			list($topLeft,$bottomRight) = $collection!=='content'
				?	array($this->__cells->$collection->topLeft,$this->__cells->$collection->bottomRight)
				:	array($this->topLeft,$this->bottomRight);
			$facade->sheet->getStyle("{$topLeft}:{$bottomRight}")->applyFromArray($collectionStyle);
		}

		// foreach($collectionDimensions as $collection => $dimensions){
		// 	list($topLeft,$bottomRight) = $collection!=='content'
		// 		?	array($this->__cells->$collection->topLeft,$this->__cells->$collection->bottomRight)
		// 		:	array($this->topLeft,$this->bottomRight);
		// 	if(property_exists($dimensions,'width')){
		//
		// 	}
		//
		// 	if(property_exists($dimensions,'height')){
		// 		$sheet->getRowDimension( $actualRow )->setRowHeight($this->row_heights[$heightIdx]);
		// 	}
		// }
	}

	public function get_object_type(){ return 'table'; }

	public function get_total_rows(){
		$thead_rows = sizeof($this->thead->rows);
		$tbody_rows = sizeof($this->tbody->rows);
		$tfoot_rows = $this->has_footer_row ? 1 : 0;
		$title_rows = isset($this->title) && strlen($this->title) ? 1 : 0;
		return $thead_rows + $tbody_rows + $tfoot_rows + $title_rows;
	}

	public function get_total_columns(){ return $this->thead->total_columns; }

	public function get_footer_role( $col_id ){
		$columnObj = $this->columnIndex[$col_id];
		return property_exists($columnObj,'footer')
			?	(is_object($columnObj->footer)
				?	(property_exists($columnObj,'getters')
					?	(property_exists($columnObj->getters,'footer')
						?	(property_exists($columnObj->getters->footer,'method')
							?	$columnObj->getters->footer->method
							:	'label')
						:	'label')
					:	'label')
				:	(property_exists($columnObj,'getters')
					?	(property_exists($columnObj->getters,'footer')
						?	(property_exists($columnObj->getters->footer,'method')
							?	$columnObj->getters->footer->method
							:	'label')
						:	'label')
					:	'label'))
			:	(property_exists($columnObj,'getters')
				?	(property_exists($columnObj->getters,'footer')
					?	(property_exists($columnObj->getters->footer,'method')
						?	$columnObj->getters->footer->method
						:	'label')
					:	'label')
				:	'label');
	}

	private function get_column_metaprop( $col_id, $prop, $row_index=null, $default=null ){
		$datasource = $this->get_datasource();
		$columnObj = $this->columnIndex[$col_id];
		$context = is_null($row_index)
			?	compact('datasource')
			:	(array_key_exists($row_index,$datasource)
					?	array('row_index'=>$row_index,'row'=>$datasource[$row_index])
					:	compact('row_index','datasource')
				);
		if(property_exists($columnObj,$prop)){
			$container = $columnObj->$prop;
			return is_array($container)
				?	(array_key_exists($row_index,$container)
					?	$container[$row_index]
					:	$default)
				:	$this->resolve_generic_getter($columnObj->$prop,$context);
		}else{
			return $this->resolve_getterable_prop($columnObj,$prop,$default,$context);
		}
	}

	public function get_column_type( $col_id, $row_index=null ){
		return $this->get_column_metaprop( $col_id, 'type', $row_index, 'text' );
	}

	public function get_column_format( $col_id, $row_index=null ){
		return $this->get_column_metaprop( $col_id, 'format', $row_index, null );
	}

	protected function generate_datagrid($offsetRow=0,$offsetCol=0){
		$gridRowIdx = 0;
		$total_cols = $this->total_columns;
		$total_rows = $this->total_rows;
		$grid = array_fill($offsetRow,$total_rows,array_fill($offsetCol,$total_cols,null));
		$nextOffset = $offsetRow;

		//title
		if(isset($this->title)){
			$temp = (object)array(
				'value' 		=> 	$this->title,
				'colspan'		=>	$total_cols,
				'type'			=>	'text',
				'role'			=>	'title',
				'region'		=>	'title',
				'collection'	=>	array('title')
			);
			$this->build_cell_style($temp);

			$grid[$offsetRow][$offsetCol]=$temp;
			$nextOffset++;
		}
		//thead
		$headRows = sizeof($this->thead->rows);
		foreach($this->thead->rows as $src_row_idx => $src_row){
			$gaps = 0;
			$role = $headRows > 1
				?	($src_row_idx===0
					?	'heading superheading'
					:	($src_row_idx===($headRows-1)
						?	'heading subheading'
						:	'heading superheading subheading'))
				:	'heading';
			$rowAtts = $src_row_idx===0
				? 	($headRows > 1
					?	"row_{$src_row_idx} first_row"
					:	"row_{$src_row_idx} first_row last_row")
				:	($src_row_idx===($headRows-1)
					?	"row_{$src_row_idx} last_row"
					:	"row_{$src_row_idx}");
			$headCols = sizeof($src_row);
			foreach($src_row as $src_col_idx => $src_val){
				$colAtts = $src_col_idx===0
					?	($headCols > 1
						?	"col_{$src_col_idx} first_col"
						:	"col_{$src_col_idx} first_col last_col")
					:	($src_col_idx===($headCols-1)
						?	"col_{$src_col_idx} last_col"
						:	"col_{$src_col_idx}");
				$colspan = $this->thead->colspans[$src_row_idx][$src_col_idx];
				$cellAtts = is_null($src_val)||trim($src_val)===''
					?	"empty"
					:	"not_empty";
				$temp = (object)array(
					'value' 		=> $src_val,
					'colspan'		=> $colspan,
					'type'			=> 'text',
					'region'		=> 'head',
					'role'			=> is_null($src_val)||strlen(trim($src_val))===0 ? "divider {$role}" : $role,
					'collection'	=> array('table','thead'),
					'column_id' 	=> $this->thead->ids[$src_row_idx][$src_col_idx],
					'row_atts'		=> $rowAtts,
					'col_atts' 		=> $colAtts,
					'cell_atts' 	=> $cellAtts,
					'src_row_idx'	=> $src_row_idx,
					'grid_row_idx' 	=> $gridRowIdx
				);
				$this->build_cell_style($temp);
				$grid[$src_row_idx+$nextOffset][$src_col_idx+$offsetCol+$gaps]=$temp;
				$gaps = $gaps + ($colspan - 1);
			}

			$gridRowIdx++;
		}
		//tbody
		$nextOffset = sizeof($this->thead->rows) + $nextOffset;
		$bodyRows = sizeof($this->tbody->rows);
		foreach($this->tbody->rows as $src_row_idx => $src_row){
			$rowAtts = $src_row_idx===0
				? 	($bodyRows > 1
					?	"row_{$src_row_idx} first_row"
					:	"row_{$src_row_idx} first_row last_row")
				:	($src_row_idx===($bodyRows-1)
					?	"row_{$src_row_idx} last_row"
					:	"row_{$src_row_idx}");

			$facade = new stdClass;
			$facade->rowAtts =& $rowAtts;
			$facade->src_row_idx =& $src_row_idx;
			$facade->src_row =& $src_row;

			if(!empty($this->beforeBuildRowStyle)){
				foreach($this->beforeBuildRowStyle as $callable){
					call_user_func($callable,$facade);
				}
			}
			$rowType = property_exists($src_row,'@type') ? $src_row->{'@type'} : 'data';
			switch($rowType)
			{
				case 'data':
					$bodyCols = sizeof($this->row_map);
					foreach($this->row_map as $src_col_idx => $src_col_id){
						$colAtts = $src_col_idx===0
							?	($bodyCols > 1
								?	"col_{$src_col_idx} first_col"
								:	"col_{$src_col_idx} first_col last_col")
							:	($src_col_idx===($bodyCols-1)
								?	"col_{$src_col_idx} last_col"
								:	"col_{$src_col_idx}");
						$type = $this->get_column_type($src_col_id,$src_row_idx);
						$format = $this->get_column_format($src_col_id,$src_row_idx);
						$src_val = property_exists($src_row,$src_col_id)
							?	$src_row->$src_col_id
							:	null;
						$cellAtts = is_null($src_val)||trim($src_val)===''
							?	"empty"
							:	"not_empty [={$src_val}]";

						$temp = (object)array(
							'value'	=> $src_val,
							'colspan' => 1,
							'type' => $type,
							'format' => $format,
							'role' => 'data',
							'region' => 'body',
							'collection' =>	array('table','tbody'),
							'column_id' => $src_col_id,
							'row_atts'	=> $rowAtts,
							'col_atts' 	=> $colAtts,
							'cell_atts' => $cellAtts,
							'src_row_idx'	=> $src_row_idx,
							'grid_row_idx' 	=> $gridRowIdx
						);

						$facade->cellObject = $temp;

						if(!empty($this->beforeBuildCellStyle)){
							foreach($this->beforeBuildCellStyle as $callable){
								call_user_func($callable,$facade);
							}
						}

						$this->build_cell_style($temp);
						$grid[$src_row_idx+$nextOffset][$src_col_idx+$offsetCol]=$temp;
					}
				break;

				case 'headers':
					$gridKeys = array_keys($grid);
					$firstKey = $gridKeys[0];
					$copyFrom = $firstKey + (isset($this->title) ? 1 : 0);

					foreach($this->thead->rows as $header_row_idx => $header_row_data){
						//Each Header Row
						$original_row = $grid[ $firstKey + (isset($this->title) ? 1 : 0) + $header_row_idx ];
						$cloned_row = [];
						$gridRowIdx++;
						$gaps = $gaps + $header_row_idx;
						foreach($original_row as $original_col_idx => $original_cell){
							//Each Column
							$cloned_cell = is_object($original_cell)
								?	clone $original_cell
								:	$original_cell;
							if(is_object($cloned_cell)){
								$cloned_cell->row_atts = "{$rowAtts} body_header";
								$cloned_cell->collection = ['table','tbody'];
								$cloned_cell->src_row_idx = $src_row_idx;
								$cloned_cell->grid_row_idx = $gridRowIdx;
							}
							$cloned_row[$original_col_idx] = $cloned_cell;
						}
						$grid[ $src_row_idx + $nextOffset + $header_row_idx] = $cloned_row;
					}
				break;

				default:
					$label = property_exists($src_row,'@label') ? $src_row->{'@label'} : null;
					$cellAtts = is_null($label)||trim($label)===''
						?	"empty"
						:	"not_empty";
					$temp = (object)array(
						'value'	=> $label,
						'colspan' => $total_cols,
						'type' => 'text',
						'role' => $label ? 'label' : 'divider',
						'format' => null,
						'region' => 'body',
						'row_atts' => $rowAtts,
						'cell_atts' => $cellAtts
					);
					$this->build_cell_style($temp);
					$grid[$src_row_idx+$nextOffset][$offsetCol]=$temp;$label = property_exists($src_row,'@label') ? $src_row->{'@label'} : null;
					$cellAtts = is_null($label)||trim($label)===''
						?	"empty"
						:	"not_empty";
					$temp = (object)array(
						'value'	=> $label,
						'colspan' => $total_cols,
						'type' => 'text',
						'role' => $label ? 'label' : 'divider',
						'format' => null,
						'region' => 'body',
						'collection' =>	array('table','tbody'),
						'row_atts' => $rowAtts,
						'cell_atts' => $cellAtts,
						'grid_row_idx' 	=> $gridRowIdx
					);
					$this->build_cell_style($temp);
					$grid[$src_row_idx+$nextOffset][$offsetCol]=$temp;
				break;
			}

			$gridRowIdx++;
		}
		//tfoot
		$nextOffset = $nextOffset + sizeof($this->tbody->rows);
		if($this->has_footer_row){
			$c = 0;
			$footCols = sizeof($this->tfoot->row_values);
			foreach($this->tfoot->row_values as $col_id => $col_val){
				$colAtts = $c===0
					?	($footCols > 1
						?	"col_{$c} first_col"
						:	"col_{$c} first_col last_col")
					:	($c===($footCols-1)
						?	"col_{$c} last_col"
						:	"col_{$c}");
				$role = is_null($col_val) || trim($col_val)===''
					?	'divider'
					:	$this->get_footer_role($col_id);
				$cellAtts = is_null($col_val)||trim($col_val)===''
					?	"empty"
					:	"not_empty [={$col_val}]";
				$temp = (object)array(
					'value'	=> $col_val,
					'colspan' => 1,
					'type' => $this->get_column_type($col_id),
					'format' => $this->get_column_format($col_id),
					'role' => $role,
					'region' => 'footer',
					'collection' => array('table','tfoot'),
					'col_atts' 	=> $colAtts,
					'cell_atts' => $cellAtts,
					'grid_row_idx' 	=> $gridRowIdx
				);
				$this->build_cell_style($temp);
				$grid[$nextOffset][$c+$offsetCol]=$temp;
				$c++;
			}
		}

		return $grid;
	}

	public function render_to_spreadsheet( $facade, $rowOffset=0, $colOffset=0 ){
		if(!empty($this->beforeRender)){
			foreach($this->beforeRender as $callable){
				call_user_func_array($callable,array($facade,$rowOffset,$colOffset));
			}
		}
		extract((array)$facade,EXTR_REFS);
		$contentIndex = $this->get_index();
		if($contentIndex > 0){
			$rowOffset = $rowOffset + 2;
		}
		$dataGrid = $this->generate_datagrid($rowOffset,$colOffset);
		$this->topLeft = self::CoordinatesToCell($rowOffset,$colOffset);
		$stylesOutput = array();
		$headingRows = sizeof($this->thead->rows);
		$bodyStartsAt = $rowOffset + $headingRows;
		$heights = $this->row_heights;

		//self::CrashBlob($dataGrid);
		foreach($dataGrid as $rowIdx => $rowArray){
			$cells = array_filter($rowArray);

			foreach($cells as $colIdx => $cellObj){
				$cell = self::CoordinatesToCell($rowIdx,$colIdx);
				$sheet->setCellValue($cell,$cellObj->value);

				if(property_exists($cellObj,'collection')){
					$collection = is_array($cellObj->collection)
						?	$cellObj->collection
						:	explode(' ',$cellObj->collection);
					foreach($collection as $collectionName){
						$this->add_to_collection($collectionName,$cell);
					}
				}

				$dataGrid[$rowIdx][$colIdx]->cellAddress = $cell;

				if(property_exists($cellObj,'colspan') && $cellObj->colspan > 1){
					$secondCell = self::CoordinatesToCell(
						$rowIdx,
						$colIdx + ($cellObj->colspan - 1)
					);
					$sheet->mergeCells("{$cell}:{$secondCell}");
					$cell = "{$cell}:{$secondCell}";
				}

				if(property_exists($cellObj,'styles') && !empty($cellObj->styles)){

					if(array_key_exists('borders',$cellObj->styles)){
						//exit(print_r($cellObj,true));
					}
					$sheet->getStyle($cell)->applyFromArray($cellObj->styles);
					// if(array_key_exists('borders',$cellObj->styles)){
					// 	$sheet->getStyle($cell)->applyFromArray(array_diff_key($cellObj->styles,array_flip(['borders'])));
					// 	$this->apply_borders( $sheet->getStyle($cell), $cellObj->styles['borders'] );
					// }else{
					// 	$sheet->getStyle($cell)->applyFromArray($cellObj->styles);
					// }
				}

				// if(property_exists($cellObj,'row_atts') && $cellObj->row_atts=='row_2 body_header'){
				// 	exit(print_r([
				// 		'cellObj'		=>	$cellObj,
				// 		'__cells'		=>	$this->__cells,
				// 		'styles'		=>	$this->styles
				// 	],true));
				// }

				if(property_exists($cellObj,'format') && !empty($cellObj->format)){
					$sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::$FormatCodes[$cellObj->format]);//applyFromArray($cellObj->styles);
				}

				$this->__spreadsheet->register_autowidths( self::CellsToColumns($cell) );
			}

			$actualRow = self::CellToRow($cell);
			$heightIdx = $rowIdx - $bodyStartsAt;
			if(array_key_exists($heightIdx,$this->row_heights)){
				$sheet->getRowDimension( $actualRow )->setRowHeight($this->row_heights[$heightIdx]);
			}
		}

		$this->bottomRight = self::CoordinatesToCell($rowIdx,$colIdx);

		$facade->row = $rowIdx;

		$this->apply_content_styles( $facade );
		//$this->apply_content_dimensions( $facade );

		if(!empty($this->afterRender)){
			foreach($this->afterRender as $callable){
				call_user_func_array($callable,array($facade,$dataGrid));
			}
		}

		return true;
	}

	public function build_table_row_data( $schema ){
		$datasource = $this->get_datasource();
		// if(!is_array($datasource)){
		// 	$file = __FILE__;
		// 	$line = __LINE__;
		// 	self::CrashBlob(compact('datasource','file','line'));
		// }
		foreach($this->row_map as $column_index => $column_id){
			$column_obj = $this->columnIndex[$column_id];
			$row_values = array();
			if( property_exists($column_obj,'type') ) $type = $column_obj->type;
			if( property_exists($column_obj,'format') ) $format = $column_obj->format;
			if( property_exists($column_obj,'value')){
				foreach( $datasource as $row_idx => $row ){
					$row_values[] = $this->resolve_di_string(
						$column_obj->value,
						compact('row','row_idx','datasource','column_id','column_index')
					);
				}
			}else{
				$getter = $column_obj->getters->value;
				if(is_object($getter)){

					if( property_exists($getter,'type')){
						$type = $this->resolve_generic_getter(
							$getter->type,
							compact('row','row_idx','datasource','column_id','column_index')
						);
					}

					if( property_exists($getter,'format')){
						$format = $this->resolve_generic_getter(
							$getter->format,
							compact('row','row_idx','datasource','column_id','column_index')
						);
					}

					switch($getter->accessor){
						case 'row':
							foreach( $datasource as $row_idx => $row ){
								$getterKey = $this->resolve_generic_getter($getter->key,compact('row','row_idx','datasource','column_id','column_index'));
								$temp = array_key_exists($getterKey,$row)
									?	$row[$getterKey]
									:	null;
								$row_values[] = $temp;
							}
						break;

						case 'fn':
							foreach( $datasource as $row_idx => $row ){
								$temp = $this->resolve_fn_string(
									$getter->function,
									compact('row','row_idx','datasource','column_id','column_index')
								);
								$row_values[] = $temp;
							}
						break;
					}
				}elseif(is_string($getter)){
					foreach( $datasource as $row_idx => $row ){
						$row_values[] = $this->resolve_di_string(
							$getter,
							compact('row','row_idx','datasource','column_id','column_index')
						);
					}
				}
			}

			$column_obj->row_values = $row_values;
		}
	}

	public function build_table_body( $schema ){
		$datasource = $this->get_datasource();
		$this->tbody = new stdClass;
		$this->tbody->rows = array();
		$this->tbody->total_rows = sizeof($datasource);
		foreach($datasource as $row_idx => $row_array){
			$row = array();
			foreach($this->row_map as $col_idx => $col_id){
				$vals = $this->columnIndex[$col_id]->row_values;
				$row[$col_id] = array_key_exists($row_idx,$vals)
					?	$vals[$row_idx]
					:	null;
			}
			$this->tbody->rows[] = (object)$row;
		}
	}

	public function build_table_footer( $schema ){
		$this->tfoot = new stdClass;
		$this->tfoot->row_values = array();
		//foreach($blueprint->columnIndex as $column_id => $column){
		foreach($this->row_map as $column_id){
			$column = $this->columnIndex[$column_id];
			if(property_exists($column,'footer')){
				//$value = $column->footer;
				$value = $this->resolve_generic_getter( $column->footer );
			}elseif(property_exists($column,'getters') && property_exists($column->getters,'footer')){
				$getter = $column->getters->footer;
				if($getter->accessor==='column'){
					$row_values = $this->columnIndex[$column_id]->row_values;
					switch($getter->method)
					{
						case 'sum':
							$value = array_sum($row_values);
						break;

						case 'average':
							$sum = array_sum($row_values);
							$value = $sum > 0 ? $sum / sizeof($row_values) : 0;
						break;

						case 'fn':
							$value = $this->resolve_generic_getter( $column->getters->footer );
						break;
					}
				}else{
					// To Do
					$value = $this->resolve_generic_getter( $column->getters->footer );
				}
			}else{
				$value = null;
			}
			$this->columnIndex[$column_id]->footer = $value;
			$this->tfoot->row_values[$column_id] = $value;
		}
	}

	public function build_table_head( $schema ){
		$this->thead = new stdClass;
		$this->columnIndex = array();
		$superHeaders = array();
		$superSpans = array();
		$headers = array();
		$superIds = array();
		$spans = array();
		$ids = array();
		$sc = false;

		foreach($this->columnOrder as $column_id){
			$colSchema = $schema->columns->$column_id;
			$colspec = clone $colSchema;
		//foreach($schema->columns as $column_id => $colSchema){
		//	$colspec = clone $colSchema;
			$colspec->id = $column_id;
			$getters = property_exists($colspec,'getters');
			if(property_exists($colspec,'subcolumns')){
				$sc = true;
				$subs = $colspec->subcolumns;
				$superSpan = sizeof((array)$subs);

				foreach($subs as $subkey => $subcolspec){
					$subId = "{$column_id}.{$subkey}";
					$subGetters = property_exists($subcolspec,'getters');
					if(property_exists($subcolspec,'colspan')){
						//$superspan = ($superspan + ($subcolspec->colspan - 1));
						$superSpan = ($superSpan + ($subcolspec->colspan - 1));
					}else{
						$subcolspec->colspan = 1;
					}
					$spans[] = $subcolspec->colspan;
					$ids[] = $subId;
					$subHeaderContent = property_exists($subcolspec,'header')
						? 	$subcolspec->header
						: 	($subGetters && property_exists($subcolspec->getters,'header')
							?	$this->resolve_generic_getter( $subcolspec->getters->header )
							:	null
					);
					$headers[] = $subHeaderContent;
					$subcolspec->header = $subHeaderContent;

					$subcolspec->id = $subId;
					$this->columnIndex[$subId] = $subcolspec;

					if(  property_exists($subcolspec,'value') || ($subGetters && property_exists($subcolspec->getters,'value'))){
						$this->row_map[] = $subId;
					}

					if($this->has_footer_row===false && (property_exists($subcolspec,'footer') || ($subGetters && property_exists($subcolspec->getters,'footer')))){
						$this->has_footer_row = true;
					}
				}

				$colspec->colspan = $superSpan;
				$superHeaderContent = property_exists($colspec,'header')
					? 	(is_object($colspec->header)
						?	$this->resolve_generic_getter( $colspec->header )
						:	$colspec->header)
					:	($getters && property_exists($colspec->getters,'header')
						?	$this->resolve_generic_getter( $colspec->getters->header )
						:	null);
				$superHeaders[] = $superHeaderContent;
				$colspec->header = $superHeaderContent;
				$superSpans[] = $superSpan;
				$superIds[] = $column_id;

			}else{

				if(!(property_exists($colspec,'colspan'))){
					$colspec->colspan = 1;
				}

				$superHeaders[] = null;
				$superSpans[] = 1;
				$superIds[] = $column_id;

				$headerContent = property_exists($colspec,'header')
					? 	$colspec->header
					: 	($getters && property_exists($colspec->getters,'header')
						?	$this->resolve_generic_getter( $colspec->getters->header )
						:	null);
				$headers[] = $headerContent;
				$colspec->header = $headerContent;
				$spans[] = $colspec->colspan;
				$ids[] = $column_id;

				if(property_exists($colspec,'value') || ($getters && property_exists($colspec->getters,'value'))){
					$this->row_map[] = $column_id;
				}

				if($this->has_footer_row===false && (property_exists($colspec,'footer') || ($getters && property_exists($colspec->getters,'footer')))){
					$this->has_footer_row = true;
				}
			}

			$this->columnIndex[$column_id] = $colspec;
		}

		$this->thead->ids = array($ids);
		$this->thead->total_columns = array_sum( $spans );
		$this->thead->rows = array( $headers );
		$this->thead->colspans = array( $spans );

		if( $sc ){
			array_unshift( $this->thead->ids, $superIds );
			array_unshift( $this->thead->rows, $superHeaders );
			array_unshift( $this->thead->colspans, $superSpans );
		}

		if(property_exists($schema,'title')){
			$this->build_table_head_title( $schema );
		}
	}

	public function build_table_head_title( $schema ){
		$this->title = $this->resolve_generic_getter( $schema->title, $this->scope );
	}

}
